<?php
/**
 * ARCHIVO: api/stream_image.php
 * Transmisión y CACHÉ LOCAL de imágenes (Con Motor de Miniaturas)
 */
error_reporting(0);
$ip = $_GET['ip'] ?? '';
$path = $_GET['path'] ?? '';
$is_thumb = isset($_GET['thumb']) && $_GET['thumb'] == '1';
$port = 2121;

if (!$ip || !$path) {
    http_response_code(400); exit;
}

// 1. Crear carpeta de caché en la memoria interna
$cache_dir = '../cache_biblioteca/img_cache';
if (!file_exists($cache_dir)) { @mkdir($cache_dir, 0777, true); }

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

// Nombres de archivos caché (uno para la original 4K, otro para la miniatura)
$original_cache_filename = md5($path) . '.' . $ext;
$original_cache_filepath = $cache_dir . '/' . $original_cache_filename;

$thumb_cache_filename = 'thumb_' . md5($path) . '.jpg';
$thumb_cache_filepath = $cache_dir . '/' . $thumb_cache_filename;

// =========================================================
// MODO MINIATURA (Para la grilla de capturas de la UI)
// =========================================================
if ($is_thumb) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: max-age=31536000');
    
    // Si ya procesamos la miniatura antes, la mostramos al instante
    if (file_exists($thumb_cache_filepath) && filesize($thumb_cache_filepath) > 0) {
        readfile($thumb_cache_filepath);
        exit;
    }
    
    // Si no existe, tenemos que crearla.
    // Primero, verificamos si ya habíamos descargado la foto original gigante:
    $img_data = false;
    if (file_exists($original_cache_filepath) && filesize($original_cache_filepath) > 0) {
        $img_data = file_get_contents($original_cache_filepath);
    } else {
        // Si no tenemos ni la gigante, descargamos de FTP
        $ch = curl_init();
        $ftp_url = "ftp://$ip:$port" . implode('/', array_map('rawurlencode', explode('/', $path)));
        curl_setopt($ch, CURLOPT_URL, $ftp_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $img_data = curl_exec($ch);
        curl_close($ch);
    }
    
    // Motor de compresión GD de PHP
    if ($img_data) {
        $img = @imagecreatefromstring($img_data);
        if ($img !== false) {
            $width = imagesx($img);
            $height = imagesy($img);
            
            // Reducimos a 400px de ancho (Ideal para celulares, ultra ligero)
            $new_width = 400;
            $new_height = floor($height * ($new_width / $width));
            
            $thumb = imagecreatetruecolor($new_width, $new_height);
            
            // Llenar el fondo de negro (por si era un PNG transparente)
            $bg = imagecolorallocate($thumb, 0, 0, 0);
            imagefill($thumb, 0, 0, $bg);
            
            imagecopyresampled($thumb, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            
            // Guardar la miniatura en calidad 60% (Pasa de pesar 3MB a 25KB)
            imagejpeg($thumb, $thumb_cache_filepath, 60);
            
            // Enviar al navegador
            imagejpeg($thumb, null, 60);
            
            imagedestroy($img);
            imagedestroy($thumb);
            exit;
        }
    }
    
    // Si algo falló
    http_response_code(404); exit;
}

// =========================================================
// MODO ORIGINAL GIGANTE (Para el botón "Descargar" o "Zoom")
// =========================================================
header('Content-Type: ' . ($ext === 'png' ? 'image/png' : 'image/jpeg'));
header('Cache-Control: max-age=31536000');

// Si la foto original ya fue descargada antes
if (file_exists($original_cache_filepath) && filesize($original_cache_filepath) > 0) {
    readfile($original_cache_filepath);
    exit;
}

// Si no existe, la descargamos de la PS4
$ch = curl_init();
$ftp_url = "ftp://$ip:$port" . implode('/', array_map('rawurlencode', explode('/', $path)));
curl_setopt($ch, CURLOPT_URL, $ftp_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Damos más tiempo para fotos pesadas
$image_data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($image_data && ($http_code == 200 || $http_code == 0)) {
    // Guardamos la foto original en el celular
    file_put_contents($original_cache_filepath, $image_data);
    echo $image_data;
} else {
    http_response_code(404);
}
