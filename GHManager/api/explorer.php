<?php
/**
 * ARCHIVO: api/explorer.php
 * Motor de Exploración FTP (Fusión: Lógica original SeBaS + Motor cURL Moderno)
 */
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);

$host_ip = $_REQUEST['host_ip'] ?? '';
$port = 2121;

// 1. DESCARGA DIRECTA (Streaming al celular - Convertido a cURL)
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $path = $_GET['path'] ?? '';
    if ($host_ip && $path) {
        $filename = basename($path);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "ftp://$host_ip:$port$path");
        // CURLOPT_RETURNTRANSFER en false hace que el archivo fluya directo al navegador
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0); // Sin límite para archivos pesados
        curl_exec($ch);
        curl_close($ch);
    }
    exit;
}

$action = $_POST['action'] ?? '';
if (!$host_ip) { echo json_encode(['status' => 'error', 'message' => 'Falta IP de PS4']); exit; }

// ==========================================
// FUNCIÓN NÚCLEO: LISTADO UNIX (Reemplazo de ftp_rawlist)
// ==========================================
function curl_ftp_list_details($ip, $port, $path) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port" . rtrim($path, '/') . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $items = [];
    if ($res) {
        $lines = explode("\n", trim($res));
        foreach ($lines as $line) {
            if(empty(trim($line))) continue;
            // Dividir la línea del listado UNIX (Tu lógica original)
            $parts = preg_split('/\s+/', trim($line), 9);
            if(count($parts) >= 9) {
                $name = $parts[8];
                if ($name === '.' || $name === '..') continue;
                $is_dir = (substr($parts[0], 0, 1) === 'd');
                $items[] = ['name' => $name, 'is_dir' => $is_dir];
            }
        }
    }
    return $items;
}

// ==========================================
// RUTAS DE ACCIÓN
// ==========================================

if ($action === 'list_dir') {
    $path = $_POST['path'] ?? '/';
    $items = curl_ftp_list_details($host_ip, $port, $path);
    
    // Ordenar: Primero carpetas, luego archivos (alfabéticamente - Tu lógica original)
    usort($items, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    
    echo json_encode(['status' => 'success', 'data' => $items]);
    exit;
}

elseif ($action === 'delete_item') {
    $path = $_POST['path'];
    
    // Validar Path Traversal por seguridad
    if (strpos($path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'Ruta no permitida.']);
        exit;
    }

    $is_dir = $_POST['is_dir'] === 'true' || $_POST['is_dir'] === '1';
    
    // Borrado en cadena (Reescrito para cURL)
    function deleteDirCurl($ip, $port, $dir) {
        $items = curl_ftp_list_details($ip, $port, $dir);
        foreach($items as $item) {
            $abs_path = rtrim($dir, '/') . '/' . $item['name'];
            if ($item['is_dir']) {
                deleteDirCurl($ip, $port, $abs_path);
            } else {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port/");
                curl_setopt($ch, CURLOPT_QUOTE, ["DELE " . $abs_path]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch); curl_close($ch);
            }
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port/");
        curl_setopt($ch, CURLOPT_QUOTE, ["RMD " . $dir]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch); curl_close($ch);
        return $res !== false;
    }

    if ($is_dir) { 
        $res = deleteDirCurl($host_ip, $port, $path); 
    } else { 
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "ftp://$host_ip:$port/");
        curl_setopt($ch, CURLOPT_QUOTE, ["DELE " . $path]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch); 
        $err = curl_error($ch);
        curl_close($ch);
        $res = empty($err);
    }
    echo json_encode(['status' => $res ? 'success' : 'error']);
    exit;
}

elseif ($action === 'rename') {
    $old = $_POST['old_path'];
    $new = $_POST['new_path'];
    
    if (strpos($old, '..') !== false || strpos($new, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'Ruta no permitida.']);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host_ip:$port/");
    curl_setopt($ch, CURLOPT_QUOTE, ["RNFR $old", "RNTO $new"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    echo json_encode(['status' => empty($err) ? 'success' : 'error']);
    exit;
}

elseif ($action === 'mkdir') {
    $path = $_POST['path'];
    
    if (strpos($path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'Ruta no permitida.']);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host_ip:$port/");
    curl_setopt($ch, CURLOPT_QUOTE, ["MKD $path"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    echo json_encode(['status' => empty($err) ? 'success' : 'error']);
    exit;
}

elseif ($action === 'read_file') {
    $path = $_POST['path'];
    
    if (strpos($path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'Ruta no permitida.']);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host_ip:$port$path");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $content = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($content !== false && empty($err)) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        // Tu lógica original de procesar imágenes y texto
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'bmp'])) {
            $base64 = base64_encode($content);
            $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            echo json_encode(['status' => 'success', 'type' => 'image', 'data' => "data:$mime;base64,$base64"]);
        } else {
            $text = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            echo json_encode(['status' => 'success', 'type' => 'text', 'data' => $text]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo leer el archivo.']);
    }
    exit;
}
?>
