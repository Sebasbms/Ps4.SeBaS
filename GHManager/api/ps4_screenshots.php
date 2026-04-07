<?php
/**
 * ARCHIVO: api/ps4_screenshots.php
 * Extractor de Capturas Definitivo (Ruta Original + Motor cURL + Streaming)
 * ¡CON MINIATURAS (THUMBNAILS) INTEGRADAS!
 */
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$host_ip = $_POST['host_ip'] ?? '';
$cusa = strtoupper(trim($_POST['cusa_id'] ?? ''));
$port = 2121;

if (!$host_ip) {
    echo json_encode(['status' => 'error', 'message' => 'Falta IP de PS4']);
    exit;
}

// Función súper estable para listar carpetas en Termux
function get_ftp_list($ip, $port, $dir) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port" . rtrim($dir, '/') . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $files = [];
    if ($res) {
        $lines = explode("\n", trim($res));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $parts = preg_split('/\s+/', trim($line), 9);
            if (count($parts) >= 9) {
                $name = trim($parts[8]);
                if ($name === '.' || $name === '..') continue;
                $files[] = ['name' => $name, 'is_dir' => (substr($parts[0], 0, 1) === 'd')];
            }
        }
    }
    return $files;
}

// El "Taladro" que busca fotos dentro de carpetas
function excavar_fotos($ip, $port, $dir, &$capturas, $profundidad = 0) {
    if ($profundidad > 4) return; // Límite de seguridad
    $items = get_ftp_list($ip, $port, $dir);
    foreach ($items as $item) {
        $ruta_completa = rtrim($dir, '/') . '/' . $item['name'];
        if ($item['is_dir']) {
            excavar_fotos($ip, $port, $ruta_completa, $capturas, $profundidad + 1);
        } else {
            $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $capturas[] = $ruta_completa;
            }
        }
    }
}

// La ruta de oro que descubriste
$base_photo = "/user/av_contents/photo";

// ==========================================
// MODO 1: BÓVEDA GLOBAL
// ==========================================
if ($action === 'get_all_caps') {
    $capturas = [];
    $items_nivel1 = get_ftp_list($host_ip, $port, $base_photo);
    
    foreach ($items_nivel1 as $item1) {
        if ($item1['is_dir']) {
            excavar_fotos($host_ip, $port, "$base_photo/{$item1['name']}", $capturas);
        }
    }
    
    if (empty($capturas)) {
        echo json_encode(['status' => 'error', 'message' => "No se encontraron fotos en la consola."]);
    } else {
        sort($capturas);
        $capturas = array_reverse($capturas); // Las más nuevas primero
        
        $images_format = [];
        foreach ($capturas as $cap) {
            // Extraer el nombre de la carpeta para identificar el juego
            $parts = explode('/', $cap);
            $name = (count($parts) > 2) ? "[" . $parts[count($parts)-2] . "] " . end($parts) : end($parts);
            
            $images_format[] = [
                'name' => $name,
                // URL Original Gigante
                'url' => "api/stream_image.php?ip=$host_ip&path=" . urlencode($cap),
                // URL Miniatura Ligera
                'thumb' => "api/stream_image.php?ip=$host_ip&path=" . urlencode($cap) . "&thumb=1"
            ];
        }
        echo json_encode(['status' => 'success', 'images' => $images_format]);
    }
    exit;
}

// ==========================================
// MODO 2: ESPECÍFICO (Galería o Contador)
// ==========================================
if ($action === 'get_caps' || $action === 'count_only') {
    if (!$cusa) {
        echo json_encode(['status' => 'error', 'message' => 'Falta el ID del juego.']);
        exit;
    }

    $rutas_cusa = [];
    $items_nivel1 = get_ftp_list($host_ip, $port, $base_photo);
    
    foreach ($items_nivel1 as $item1) {
        if ($item1['is_dir']) {
            $ruta1 = "$base_photo/{$item1['name']}";
            if (stripos($item1['name'], $cusa) !== false) {
                $rutas_cusa[] = $ruta1;
            } else {
                $items_nivel2 = get_ftp_list($host_ip, $port, $ruta1);
                foreach ($items_nivel2 as $item2) {
                    if ($item2['is_dir'] && stripos($item2['name'], $cusa) !== false) {
                        $rutas_cusa[] = "$ruta1/{$item2['name']}";
                    }
                }
            }
        }
    }

    $capturas = [];
    foreach ($rutas_cusa as $ruta) {
        excavar_fotos($host_ip, $port, $ruta, $capturas);
    }

    if ($action === 'count_only') {
        echo json_encode(['status' => 'success', 'count' => count($capturas)]);
        exit;
    }

    if (empty($capturas)) {
        echo json_encode(['status' => 'error', 'message' => "No se encontraron fotos del juego.<br><br><span class='text-[10px]'>Intenta abrir el juego y sacar una captura nueva.</span>"]);
    } else {
        sort($capturas);
        $capturas = array_reverse($capturas);
        
        $images_format = [];
        foreach ($capturas as $cap) {
            $images_format[] = [
                'name' => basename($cap),
                // URL Original Gigante
                'url' => "api/stream_image.php?ip=$host_ip&path=" . urlencode($cap),
                // URL Miniatura Ligera
                'thumb' => "api/stream_image.php?ip=$host_ip&path=" . urlencode($cap) . "&thumb=1"
            ];
        }
        echo json_encode(['status' => 'success', 'images' => $images_format]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Comando inválido.']);
?>
