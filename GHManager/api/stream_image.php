<?php
/**
 * ARCHIVO: api/stream_image.php
 * Transmisión, CACHÉ LOCAL y SISTEMA ANTI-COLAPSO FTP
 */
error_reporting(0);
// Aumentamos la memoria para que el celular no se ahogue comprimiendo fotos
@ini_set('memory_limit', '512M'); 
@set_time_limit(60);

$ip = $_GET['ip'] ?? '';
$path = $_GET['path'] ?? '';
$is_thumb = isset($_GET['thumb']) && $_GET['thumb'] == '1';
$port = 2121;

if (!$ip || !$path) { http_response_code(400); exit; }

$cache_dir = __DIR__ . '/../cache_biblioteca/img_cache';
if (!file_exists($cache_dir)) { @mkdir($cache_dir, 0777, true); }

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$hash = md5($path);
$original_file = $cache_dir . '/' . $hash . '.' . $ext;
$thumb_file = $cache_dir . '/thumb_' . $hash . '.jpg';

// =======================================================
// 1. SI LA FOTO YA ESTÁ EN CACHÉ, ENVIARLA AL INSTANTE
// =======================================================
if ($is_thumb && file_exists($thumb_file) && filesize($thumb_file) > 0) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: max-age=31536000');
    readfile($thumb_file);
    exit;
}
if (!$is_thumb && file_exists($original_file) && filesize($original_file) > 0) {
    header('Content-Type: image/' . ($ext == 'png' ? 'png' : 'jpeg'));
    header('Cache-Control: max-age=31536000');
    readfile($original_file);
    exit;
}

// =======================================================
// 2. COLA DE DESCARGA ANTI-COLAPSO
// =======================================================
// Evita que el navegador sature el FTP de la PS4 pidiendo 20 fotos a la vez.
$lock_file = sys_get_temp_dir() . '/goldhen_ftp.lock';
$lock_fp = fopen($lock_file, 'w');
flock($lock_fp, LOCK_EX); // El script "hace fila" y espera su turno

$ch = curl_init();
$ftp_url = "ftp://$ip:$port" . implode('/', array_map('rawurlencode', explode('/', $path)));
curl_setopt($ch, CURLOPT_URL, $ftp_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Si la PS4 no responde rápido, aborta
curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Tiempo máximo descargando una foto
$data = curl_exec($ch);
curl_close($ch);

flock($lock_fp, LOCK_UN); // Termina la descarga y avisa al siguiente que puede pasar
fclose($lock_fp);

// =======================================================
// 3. PROCESAR, COMPRIMIR Y ENVIAR
// =======================================================
if ($data) {
    if ($is_thumb) {
        $img = @imagecreatefromstring($data);
        if ($img) {
            $w = imagesx($img); $h = imagesy($img);
            $nw = 400; $nh = floor($h * ($nw / $w));
            $tmp = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            
            imagejpeg($tmp, $thumb_file, 60); // Guardamos la miniatura en caché
            
            header('Content-Type: image/jpeg');
            header('Cache-Control: max-age=31536000');
            imagejpeg($tmp, null, 60); // Enviamos al celular
            
            imagedestroy($img); imagedestroy($tmp);
            exit;
        }
    } else {
        // Guardar original pesado
        file_put_contents($original_file, $data);
        header('Content-Type: image/' . ($ext == 'png' ? 'png' : 'jpeg'));
        header('Cache-Control: max-age=31536000');
        echo $data;
        exit;
    }
}

// Si la descarga falló por red lenta, enviamos un pixel transparente en vez de error 404.
// Así el recuadro se queda en negro esperando a que recargues la página, y no se ve "roto".
header('Content-Type: image/png');
echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
exit;
