<?php
/**
 * ARCHIVO: api/stream_image.php
 * Motor de Miniaturas y Caché Ultra Optimizado (Sin doble descarga)
 */
error_reporting(0);
@ini_set('memory_limit', '1024M'); // Fundamental para procesar imágenes 4K sin crashear
@set_time_limit(120); // Tiempo suficiente para que la PS4 mande todo sin cortar la conexión

$ip = $_GET['ip'] ?? '';
$path = $_GET['path'] ?? '';
$is_thumb = isset($_GET['thumb']) && $_GET['thumb'] == '1';
$port = 2121;

if (!$ip || !$path) { http_response_code(400); exit; }

// Ruta de tu caché
$cache_dir = __DIR__ . '/../cache_biblioteca/img_cache';
if (!file_exists($cache_dir)) { @mkdir($cache_dir, 0777, true); }

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$hash = md5($path);
$original_file = $cache_dir . '/' . $hash . '.' . $ext;
$thumb_file = $cache_dir . '/thumb_' . $hash . '.jpg';

// Función para descargar la imagen de la PS4 y guardarla localmente
function descargar_original_ftp($ip, $port, $path, $destino) {
    $ch = curl_init();
    $ftp_url = "ftp://$ip:$port" . implode('/', array_map('rawurlencode', explode('/', $path)));
    curl_setopt($ch, CURLOPT_URL, $ftp_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 segundos por foto para evitar colapsos
    $data = curl_exec($ch);
    curl_close($ch);

    if ($data) {
        file_put_contents($destino, $data);
        return true;
    }
    return false;
}

// =========================================================
// MODO MINIATURA (Grilla principal)
// =========================================================
if ($is_thumb) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: max-age=31536000');
    
    // 1. Si la miniatura ya existe, se envía al instante
    if (file_exists($thumb_file) && filesize($thumb_file) > 0) {
        readfile($thumb_file);
        exit;
    }

    // 2. Si NO existe la miniatura, revisamos si tenemos la foto original guardada
    if (!file_exists($original_file) || filesize($original_file) == 0) {
        // Si tampoco tenemos la original, la descargamos de la PS4 (UNA SOLA VEZ)
        if (!descargar_original_ftp($ip, $port, $path, $original_file)) {
            http_response_code(404); exit;
        }
    }

    // 3. Crear la miniatura leyendo el archivo original que ya está en el celular (Cero lag de red)
    $img_data = file_get_contents($original_file);
    $img = @imagecreatefromstring($img_data);
    
    if ($img) {
        $w = imagesx($img);
        $h = imagesy($img);
        $nw = 400; // Tamaño perfecto para celular
        $nh = floor($h * ($nw / $w));
        
        $tmp = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($tmp, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        
        // Guardamos la miniatura liviana para la próxima vez
        imagejpeg($tmp, $thumb_file, 60);
        
        // La mostramos en pantalla
        imagejpeg($tmp, null, 60);
        
        imagedestroy($img);
        imagedestroy($tmp);
        exit;
    }
    
    http_response_code(404); exit;
}

// =========================================================
// MODO ORIGINAL GIGANTE (Botón Descargar o Zoom)
// =========================================================
header('Content-Type: image/' . ($ext == 'png' ? 'png' : 'jpeg'));
header('Cache-Control: max-age=31536000');

// Si la queremos ver/descargar grande, revisamos si ya la bajó el creador de miniaturas
if (!file_exists($original_file) || filesize($original_file) == 0) {
    if (!descargar_original_ftp($ip, $port, $path, $original_file)) {
        http_response_code(404); exit;
    }
}

// Se la enviamos al usuario súper rápido porque ya está en el almacenamiento local
readfile($original_file);
exit;
