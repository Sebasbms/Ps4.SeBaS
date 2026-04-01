<?php
/**
 * ARCHIVO: api/explorer.php
 * Motor de Exploración FTP (Actualizado con ftp_rawlist para PS4)
 */
header('Content-Type: application/json');
set_time_limit(0);

// 1. DESCARGA DIRECTA (Streaming al celular)
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $host_ip = $_GET['host_ip'] ?? '';
    $path = $_GET['path'] ?? '';
    if ($host_ip && $path) {
        $conn = @ftp_connect($host_ip, 2121, 10);
        if ($conn) {
            @ftp_login($conn, "anonymous", "");
            ftp_pasv($conn, true);
            $filename = basename($path);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            @ftp_get($conn, "php://output", $path, FTP_BINARY);
            @ftp_close($conn);
        }
    }
    exit;
}

$action = $_POST['action'] ?? '';
$host_ip = $_POST['host_ip'] ?? '';

if (!$host_ip) { echo json_encode(['status' => 'error', 'message' => 'Falta IP de PS4']); exit; }

$conn = @ftp_connect($host_ip, 2121, 10);
if (!$conn) { echo json_encode(['status' => 'error', 'message' => 'No conecta a FTP']); exit; }
@ftp_login($conn, "anonymous", ""); ftp_pasv($conn, true);

if ($action === 'list_dir') {
    $path = $_POST['path'] ?? '/';
    // Usamos rawlist para leer los permisos reales (drwxr-xr-x)
    $raw_list = @ftp_rawlist($conn, $path);
    $items = [];
    
    if (is_array($raw_list)) {
        foreach ($raw_list as $line) {
            // Dividir la línea del listado UNIX
            $parts = preg_split('/\s+/', $line, 9);
            if (count($parts) >= 9) {
                $name = $parts[8];
                if ($name === '.' || $name === '..') continue;
                
                // Si los permisos empiezan con 'd', es un directorio/carpeta
                $is_dir = (substr($parts[0], 0, 1) === 'd');
                
                $items[] = ['name' => $name, 'is_dir' => $is_dir];
            }
        }
    } else {
        // Plan B estricto por si el servidor restringe rawlist
        $folders_and_files = @ftp_nlist($conn, $path);
        if (is_array($folders_and_files)) {
            foreach ($folders_and_files as $item) {
                $name = basename($item);
                if ($name === '.' || $name === '..') continue;
                $abs_path = rtrim($path, '/') . '/' . $name;
                
                $is_dir = false;
                $current_dir = @ftp_pwd($conn);
                if (@ftp_chdir($conn, $abs_path)) {
                    $is_dir = true;
                    @ftp_chdir($conn, $current_dir);
                }
                $items[] = ['name' => $name, 'is_dir' => $is_dir];
            }
        }
    }
    
    // Ordenar: Primero carpetas, luego archivos (alfabéticamente)
    usort($items, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    
    echo json_encode(['status' => 'success', 'data' => $items]);
}
elseif ($action === 'delete_item') {
    $path = $_POST['path'];
    
    // Validar Path Traversal por seguridad en red local
    if (strpos($path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'Ruta no permitida.']);
        exit;
    }

    $is_dir = $_POST['is_dir'] === 'true' || $_POST['is_dir'] === '1';
    
    // Borrado en cadena corregido para usar atributos reales y no fallar
    function deleteDir($conn, $dir) {
        $raw_list = @ftp_rawlist($conn, $dir);
        if (is_array($raw_list)) {
            foreach ($raw_list as $line) {
                $parts = preg_split('/\s+/', $line, 9);
                if (count($parts) >= 9) {
                    $name = $parts[8];
                    if ($name === '.' || $name === '..') continue;
                    
                    $abs_path = rtrim($dir, '/') . '/' . $name;
                    $is_dir = (substr($parts[0], 0, 1) === 'd');
                    
                    if ($is_dir) {
                        deleteDir($conn, $abs_path); // Es subcarpeta
                    } else {
                        @ftp_delete($conn, $abs_path); // Es archivo
                    }
                }
            }
        }
        return @ftp_rmdir($conn, $dir);
    }

    if ($is_dir) { $res = deleteDir($conn, $path); } 
    else { $res = @ftp_delete($conn, $path); }
    echo json_encode(['status' => $res ? 'success' : 'error']);
}
elseif ($action === 'rename') {
    $old = $_POST['old_path'];
    $new = $_POST['new_path'];
    
    if (strpos($old, '..') !== false || strpos($new, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'Ruta no permitida.']);
        exit;
    }

    $res = @ftp_rename($conn, $old, $new);
    echo json_encode(['status' => $res ? 'success' : 'error']);
}
elseif ($action === 'mkdir') {
    $path = $_POST['path'];
    
    if (strpos($path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'Ruta no permitida.']);
        exit;
    }

    $res = @ftp_mkdir($conn, $path);
    echo json_encode(['status' => $res ? 'success' : 'error']);
}
elseif ($action === 'read_file') {
    $path = $_POST['path'];
    
    if (strpos($path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'Ruta no permitida.']);
        exit;
    }

    $tempHandle = fopen('php://temp', 'r+');
    if (@ftp_fget($conn, $tempHandle, $path, FTP_BINARY, 0)) {
        rewind($tempHandle);
        $content = stream_get_contents($tempHandle);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
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
    fclose($tempHandle);
}

@ftp_close($conn);
exit;
?>
