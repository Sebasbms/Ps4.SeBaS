<?php
/**
 * ARCHIVO: api/stream_image.php
 * Transmisión directa de imágenes para no ahogar la RAM de Termux
 */
error_reporting(0);
$ip = $_GET['ip'] ?? '';
$path = $_GET['path'] ?? '';
$port = 2121;

if (!$ip || !$path) {
    http_response_code(400);
    exit;
}

// Le decimos al navegador qué tipo de imagen va a recibir
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if ($ext === 'png') {
    header('Content-Type: image/png');
} else {
    header('Content-Type: image/jpeg');
}
header('Cache-Control: max-age=86400'); // Permite que tu celular guarde la foto en caché

// Magia: Extraemos y enviamos la foto directamente sin guardarla en memoria
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port$path");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); 
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_FTP_USE_EPSV, 0);
curl_setopt($ch, CURLOPT_FTP_FILEMETHOD, CURLFTPMETHOD_NOCWD);
curl_exec($ch);
curl_close($ch);
?>
