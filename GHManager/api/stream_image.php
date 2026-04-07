<?php
/**
 * ARCHIVO: api/stream_image.php
 * Transmisión y Caché Directo (Sin compresión innecesaria)
 */
error_reporting(0);
@set_time_limit(120);

$ip = $_GET['ip'] ?? '';
$path = $_GET['path'] ?? '';
$port = 2121;

if (!$ip || !$path) { http_response_code(400); exit; }

// Ruta de tu caché confirmada
$cache_dir = __DIR__ . '/../cache_biblioteca/img_cache';
if (!file_exists($cache_dir)) { @mkdir($cache_dir, 0777, true); }

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$hash = md5($path);
$cache_file = $cache_dir . '/' . $hash . '.' . $ext;

header('Content-Type: image/' . ($ext === 'png' ? 'png' : 'jpeg'));
header('Cache-Control: max-age=31536000');

// 1. Si ya está descargada en el caché, la mostramos instantáneamente
if (file_exists($cache_file) && filesize($cache_file) > 0) {
    readfile($cache_file);
    exit;
}

// 2. Si no está en caché, la descargamos de la PS4 y la guardamos
$ch = curl_init();
$ftp_url = "ftp://$ip:$port" . implode('/', array_map('rawurlencode', explode('/', $path)));
curl_setopt($ch, CURLOPT_URL, $ftp_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$data = curl_exec($ch);
curl_close($ch);

if ($data && strlen($data) > 0) {
    file_put_contents($cache_file, $data);
    echo $data;
} else {
    http_response_code(404);
}
