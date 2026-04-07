<?php
/**
 * ARCHIVO: api/ps4_screenshots.php
 * Extractor de Capturas Definitivo (Ruta Original + Motor cURL + Streaming con Thumbnails)
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
                $is_dir = strpos(strtoupper($parts[0]), 'D') === 0;
                $files[] = ['name' => $name, 'is_dir' => $is_dir];
            }
        }
    }
    return $files;
}

function excavar_fotos($ip, $port, $ruta_cusa, &$capturas) {
    $items = get_ftp_list($ip, $port, $ruta_cusa);
    foreach ($items as $item) {
        if (!$item['is_dir']) {
            $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                $capturas[] = "$ruta_cusa/{$item['name']}";
            }
        }
    }
}

$base_photo = "/user/home/10000000/photo";
$items_base = get_ftp_list($host_ip, $port, $base_photo);

if (empty($items_base)) {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo leer la carpeta de capturas de la PS4. Verifica la conexión.']);
    exit;
}

$rutas_cusa = [];

if (empty($cusa)) {
    echo json_encode(['status' => 'error', 'message' => 'CUSA no válido.']);
    exit;
}

if ($cusa === 'TODOS LOS JUEGOS') {
    foreach ($items_base as $item1) {
        if ($item1['is_dir']) {
            $items_nivel2 = get_ftp_list($host_ip, $port, "$base_photo/{$item1['name']}");
            foreach ($items_nivel2 as $item2) {
                if ($item2['is_dir']) {
                    $rutas_cusa[] = "$base_photo/{$item1['name']}/{$item2['name']}";
                }
            }
        }
    }
} else {
    foreach ($items_base as $item1) {
        if (!$item1['is_dir']) continue;
        
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
            // URL original: Tamaño gigante para el Lightbox y Descarga
            'url' => "api/stream_image.php?ip={$host_ip}&path=" . rawurlencode($cap),
            // THUMB: Miniatura súper liviana para que la cuadrícula cargue al instante
            'thumb' => "api/stream_image.php?ip={$host_ip}&path=" . rawurlencode($cap) . "&thumb=1"
        ];
    }
    echo json_encode(['status' => 'success', 'images' => $images_format]);
}
