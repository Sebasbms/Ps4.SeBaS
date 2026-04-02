<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$ip = $_POST['host_ip'] ?? '';
$cusa = $_POST['cusa_id'] ?? '';
$port = 2121;

if (empty($ip) || empty($cusa)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
    exit;
}

// Función cURL para listar y sacar tamaños
function curl_ftp_list_details($ip, $port, $path) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port" . rtrim($path, '/') . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $items = [];
    if ($res) {
        $lines = explode("\n", trim($res));
        foreach ($lines as $line) {
            if(empty(trim($line))) continue;
            $parts = preg_split('/\s+/', trim($line), 9);
            if(count($parts) >= 9) {
                $name = $parts[8];
                if ($name === '.' || $name === '..') continue;
                $is_dir = (substr($parts[0], 0, 1) === 'd');
                $size = (int)$parts[4];
                $items[] = ['name' => $name, 'is_dir' => $is_dir, 'size' => $size];
            }
        }
    }
    return $items;
}

// Función recursiva para sumar todo el peso de la carpeta del juego
function calculate_folder_size_curl($ip, $port, $remote_dir) {
    $size = 0;
    $items = curl_ftp_list_details($ip, $port, $remote_dir);
    foreach ($items as $item) {
        $remote_path = rtrim($remote_dir, '/') . '/' . $item['name'];
        if ($item['is_dir']) {
            $size += calculate_folder_size_curl($ip, $port, $remote_path);
        } else {
            $size += $item['size'];
        }
    }
    return $size;
}

// 1. Buscar dónde está instalado el juego
$loc = 'No encontrado';
$game_path = '';

$check_internal = curl_ftp_list_details($ip, $port, "/user/app/$cusa/");
if (count($check_internal) > 0) {
    $loc = 'Almacenamiento Interno';
    $game_path = "/user/app/$cusa/";
} else {
    $check_ext = curl_ftp_list_details($ip, $port, "/mnt/ext0/user/app/$cusa/");
    if (count($check_ext) > 0) {
        $loc = 'Almacenamiento Extendido';
        $game_path = "/mnt/ext0/user/app/$cusa/";
    } else {
        $check_usb = curl_ftp_list_details($ip, $port, "/mnt/usb0/user/app/$cusa/");
        if (count($check_usb) > 0) {
            $loc = 'Disco USB Externo';
            $game_path = "/mnt/usb0/user/app/$cusa/";
        }
    }
}

// 2. Calcular el peso exacto
$size_formatted = '-- GB';
if ($game_path !== '') {
    $total_bytes = calculate_folder_size_curl($ip, $port, $game_path);
    if ($total_bytes > 0) {
        $size_gb = $total_bytes / (1024 * 1024 * 1024);
        $size_formatted = number_format($size_gb, 2) . ' GB';
    }
}

echo json_encode([
    'status' => 'success', 
    'size' => $size_formatted, 
    'location' => $loc
]);
?>
