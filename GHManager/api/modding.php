<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$host = $_POST['host_ip'] ?? '';
$cusa = $_POST['cusa_id'] ?? '';
$port = 2121;

if ($action === 'upload_icon') {
    $source = $_POST['source_type'] ?? '';
    $icon_data = '';

    if ($source === 'local_gallery') {
        $path = '../' . ($_POST['icon_path'] ?? '');
        $icon_data = file_get_contents($path);
    } else {
        $icon_data = file_get_contents($_FILES['local_icon']['tmp_name']);
    }

    $dest_path = "/user/app/meta/$cusa/icon0.png";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port$dest_path");
    curl_setopt($ch, CURLOPT_UPLOAD, 1);
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $icon_data);
    rewind($stream);
    curl_setopt($ch, CURLOPT_INFILE, $stream);
    curl_setopt($ch, CURLOPT_INFILESIZE, strlen($icon_data));
    $res = curl_exec($ch);
    curl_close($ch);
    fclose($stream);

    echo json_encode(['status' => $res ? 'success' : 'error']);
    exit;
}

if ($action === 'backup_original') {
    $remote = "/user/app/meta/$cusa/icon0.png";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port$remote");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    curl_close($ch);

    if ($data) {
        if (!is_dir('../backup_icons')) mkdir('../backup_icons', 0777, true);
        file_put_contents("../backup_icons/{$cusa}_original.png", $data);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo extraer']);
    }
    exit;
}
?>
