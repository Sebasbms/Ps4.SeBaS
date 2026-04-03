<?php
/**
 * ARCHIVO: api/stream_image.php
 * Transmisión y CACHÉ LOCAL de imágenes
 */
error_reporting(0);
$ip = $_GET['ip'] ?? '';
$path = $_GET['path'] ?? '';
$port = 2121;

if (!$ip || !$path) {
    http_response_code(400); exit;
}

// 1. Crear carpeta de caché en la memoria interna
$cache_dir = '../cache_biblioteca/img_cache';
if (!file_exists($cache_dir)) { @mkdir($cache_dir, 0777, true); }

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$cache_filename = md5($path) . '.' . $ext;
$cache_filepath = $cache_dir . '/' . $cache_filename;

header('Content-Type: ' . ($ext === 'png' ? 'image/png' : 'image/jpeg'));
header('Cache-Control: max-age=31536000'); // Guardar en el navegador por 1 año

// 2. Si la foto ya fue descargada antes, la mostramos AL INSTANTE desde el celular
if (file_exists($cache_filepath) && filesize($cache_filepath) > 0) {
    readfile($cache_filepath);
    exit;
}

// 3. Si no existe, la descargamos de la PS4 (de forma segura con URL encodeada)
$ch = curl_init();
$ftp_url = "ftp://$ip:$port" . implode('/', array_map('rawurlencode', explode('/', $path)));

curl_setopt($ch, CURLOPT_URL, $ftp_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Mucha paciencia para fotos pesadas
$data = curl_exec($ch);
curl_close($ch);

// 4. Guardamos la foto en el celular para el futuro y la mostramos
if ($data !== false && strlen($data) > 0) {
    file_put_contents($cache_filepath, $data);
    echo $data;
}
?>
