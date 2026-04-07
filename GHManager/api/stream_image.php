<?php
/**
 * ARCHIVO: api/stream_image.php
 * Motor de Miniaturas - RUTA CORREGIDA: cache_biblioteca/img_cache
 */
error_reporting(0);
$ip = $_GET['ip'] ?? '';
$path = $_GET['path'] ?? '';
$is_thumb = isset($_GET['thumb']) && $_GET['thumb'] == '1';
$port = 2121;

if (!$ip || !$path) { http_response_code(400); exit; }

// RUTA EXACTA SEGÚN TU ZARCHIVER
$cache_dir = __DIR__ . '/../cache_biblioteca/img_cache';
if (!file_exists($cache_dir)) { @mkdir($cache_dir, 0777, true); }

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$hash = md5($path);
$original_file = $cache_dir . '/' . $hash . '.' . $ext;
$thumb_file = $cache_dir . '/thumb_' . $hash . '.jpg';

// --- MODO MINIATURA ---
if ($is_thumb) {
    header('Content-Type: image/jpeg');
    if (file_exists($thumb_file) && filesize($thumb_file) > 0) { readfile($thumb_file); exit; }

    // Descargar de PS4 para procesar miniatura
    $ch = curl_init();
    $ftp_url = "ftp://$ip:$port" . implode('/', array_map('rawurlencode', explode('/', $path)));
    curl_setopt($ch, CURLOPT_URL, $ftp_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $data = curl_exec($ch);
    curl_close($ch);

    if ($data) {
        $img = @imagecreatefromstring($data);
        if ($img) {
            $w = imagesx($img); $h = imagesy($img);
            $nw = 400; $nh = floor($h * ($nw / $w));
            $tmp = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagejpeg($tmp, $thumb_file, 60); // Guardamos la miniatura liviana
            imagejpeg($tmp, null, 60);
            imagedestroy($img); imagedestroy($tmp);
            exit;
        }
    }
    http_response_code(404); exit;
}

// --- MODO ORIGINAL (ZOOM/DESCARGAR) ---
header('Content-Type: image/' . ($ext == 'png' ? 'png' : 'jpeg'));
if (file_exists($original_file) && filesize($original_file) > 0) { readfile($original_file); exit; }

$ch = curl_init();
$ftp_url = "ftp://$ip:$port" . implode('/', array_map('rawurlencode', explode('/', $path)));
curl_setopt($ch, CURLOPT_URL, $ftp_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$data = curl_exec($ch);
curl_close($ch);

if ($data) {
    file_put_contents($original_file, $data); // Guardamos la pesada
    echo $data;
} else {
    http_response_code(404);
}
