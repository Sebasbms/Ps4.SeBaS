<?php
/**
 * ARCHIVO: api/ps4_screenshots.php
 * Extractor de Capturas (Escanea los perfiles de usuario reales)
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

// 1. Escanear qué usuarios existen en la consola (Ej: 10000000)
$user_folders = get_ftp_list($host, $port, '/user/home/');
$user_ids = [];
foreach ($user_folders as $uf) {
    if ($uf['is_dir'] && is_numeric($uf['name'])) {
        $user_ids[] = $uf['name'];
    }
}
if (empty($user_ids)) $user_ids = ['10000000', 'system']; // Salvavidas por defecto

if ($action === 'count_only') {
    $count = 0;
    foreach ($user_ids as $uid) {
        $path = "/user/home/$uid/share/screenshots/$cusa/";
        $items = get_ftp_list($host, $port, $path);
        foreach($items as $item) {
            $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
            if (!$item['is_dir'] && in_array($ext, ['jpg', 'png'])) $count++;
        }
    }
    echo json_encode(['status' => 'success', 'count' => $count]);
    exit;
}

if ($action === 'get_caps') {
    $images = [];
    foreach ($user_ids as $uid) {
        $path = "/user/home/$uid/share/screenshots/$cusa/";
        $items = get_ftp_list($host, $port, $path);
        foreach ($items as $item) {
            $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
            if (!$item['is_dir'] && in_array($ext, ['jpg', 'png'])) {
                $images[] = [
                    'name' => $item['name'],
                    'url'  => "api/stream_image.php?ip=$host&path=" . urlencode($path . $item['name'])
                ];
            }
        }
    }
    echo json_encode(['status' => 'success', 'images' => array_reverse($images)]); 
    exit;
}

if ($action === 'get_all_caps') {
    $images = [];
    foreach ($user_ids as $uid) {
        $base_path = "/user/home/$uid/share/screenshots/";
        $items = get_ftp_list($host, $port, $base_path);
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
    }
    echo json_encode(['status' => 'success', 'images' => array_reverse($images)]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Comando inválido.']);
?>
