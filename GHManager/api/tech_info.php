<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$ip = $_POST['host_ip'] ?? '';
$cusa = $_POST['cusa_id'] ?? '';

if (empty($ip) || empty($cusa)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
    exit;
}

// Función moderna con cURL
function folder_exists_curl($ip, $path) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:2121$path");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "NLST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $res = curl_exec($ch);
    curl_close($ch);
    return ($res !== false && trim($res) !== '');
}

$loc = 'No encontrado';
if (folder_exists_curl($ip, "/user/app/$cusa/")) {
    $loc = 'Almacenamiento Interno';
} elseif (folder_exists_curl($ip, "/mnt/ext0/user/app/$cusa/")) {
    $loc = 'Almacenamiento Extendido';
} elseif (folder_exists_curl($ip, "/mnt/usb0/user/app/$cusa/")) {
    $loc = 'Disco USB Externo';
}

echo json_encode([
    'status' => 'success', 
    'size' => '-- GB', // Mostrar peso exacto por FTP relantiza la app, lo dejamos visual
    'location' => $loc
]);
?>
