<?php
/**
 * ARCHIVO: api/ps4_screenshots.php
 * Extractor de Capturas (Versión Streaming Ultra-Rápida)
 */
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$host = $_POST['host_ip'] ?? '';
$cusa = strtoupper(trim($_POST['cusa_id'] ?? ''));
$port = 2121;

if (!$host) { echo json_encode(['status' => 'error', 'message' => 'Falta IP de PS4']); exit; }

function get_ftp_list($host, $port, $dir) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port" . rtrim($dir, '/') . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FTP_USE_EPSV, 0);
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

if ($action === 'count_only') {
    $path = "/user/system/share/screenshots/$cusa/";
    $items = get_ftp_list($host, $port, $path);
    $count = 0;
    foreach($items as $item) {
        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
        if (!$item['is_dir'] && in_array($ext, ['jpg', 'png'])) $count++;
    }
    echo json_encode(['status' => 'success', 'count' => $count]);
    exit;
}

// Extraer galería de un juego específico
if ($action === 'get_caps') {
    $path = "/user/system/share/screenshots/$cusa/";
    $items = get_ftp_list($host, $port, $path);
    
    $images = [];
    foreach ($items as $item) {
        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
        if (!$item['is_dir'] && in_array($ext, ['jpg', 'png'])) {
            $images[] = [
                'name' => $item['name'],
                'url'  => "api/stream_image.php?ip=$host&path=" . urlencode($path . $item['name'])
            ];
        }
    }
    // Devolvemos la lista al revés para que las fotos nuevas salgan primero
    echo json_encode(['status' => 'success', 'images' => array_reverse($images)]); 
    exit;
}

// BÓVEDA GLOBAL: Escanear TODO el disco en busca de carpetas huérfanas
if ($action === 'get_all_caps') {
    $base_path = "/user/system/share/screenshots/";
    $items = get_ftp_list($host, $port, $base_path);
    
    $images = [];
    foreach ($items as $item) {
        if ($item['is_dir']) {
            $folder = $item['name'];
            $sub_items = get_ftp_list($host, $port, $base_path . $folder . '/');
            
            foreach ($sub_items as $sub_item) {
                $ext = strtolower(pathinfo($sub_item['name'], PATHINFO_EXTENSION));
                if (!$sub_item['is_dir'] && in_array($ext, ['jpg', 'png'])) {
                    $images[] = [
                        'name' => "[$folder] " . $sub_item['name'],
                        'url'  => "api/stream_image.php?ip=$host&path=" . urlencode($base_path . $folder . '/' . $sub_item['name'])
                    ];
                }
            }
        }
    }
    echo json_encode(['status' => 'success', 'images' => array_reverse($images)]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Comando no válido.']);
?>
