<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$host = $_POST['host_ip'] ?? $_GET['host_ip'] ?? '';
$port = 2121; // Puerto FTP por defecto de GoldHen

if (empty($host)) {
    echo json_encode(['status' => 'error', 'message' => 'Falta la IP de la PS4']);
    exit;
}

function curl_ftp_command($ip, $port, $commands) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_QUOTE, $commands);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return $err === '';
}

if ($action === 'list_dir') {
    $path = $_POST['path'] ?? '/';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port" . rtrim($path, '/') . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $result = curl_exec($ch);
    curl_close($ch);
    
    $items = [];
    if ($result !== false) {
        $lines = explode("\n", trim($result));
        foreach($lines as $line) {
            if(empty(trim($line))) continue;
            // Parsea el texto del servidor FTP de la PS4
            $parts = preg_split('/\s+/', trim($line), 9);
            if(count($parts) >= 9) {
                $name = $parts[8];
                if($name === '.' || $name === '..') continue;
                $is_dir = (substr($parts[0], 0, 1) === 'd');
                $items[] = ['name' => $name, 'is_dir' => $is_dir];
            }
        }
        echo json_encode(['status' => 'success', 'data' => $items]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo leer la ruta.']);
    }
    exit;
}

if ($action === 'mkdir') {
    $path = $_POST['path'] ?? '';
    if (curl_ftp_command($host, $port, ["MKD $path"])) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

if ($action === 'delete_item') {
    $path = $_POST['path'] ?? '';
    $is_dir = $_POST['is_dir'] ?? 'false';
    $cmd = ($is_dir === 'true') ? "RMD $path" : "DELE $path";
    if (curl_ftp_command($host, $port, [$cmd])) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

if ($action === 'rename') {
    $old = $_POST['old_path'] ?? '';
    $new = $_POST['new_path'] ?? '';
    if (curl_ftp_command($host, $port, ["RNFR $old", "RNTO $new"])) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

if ($action === 'check_file') {
    $path = $_POST['path'] ?? '';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port$path");
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code == 200 || $code == 213 || $size >= 0) {
        echo json_encode(['status' => 'success', 'exists' => true, 'size' => (float)$size]);
    } else {
        echo json_encode(['status' => 'success', 'exists' => false]);
    }
    exit;
}

if ($action === 'upload_chunk') {
    $path = $_POST['path'] ?? '';
    $file_name = $_POST['file_name'] ?? '';
    $chunk_index = (int)($_POST['chunk_index'] ?? 0);
    $tmp_file = $_FILES['chunk']['tmp_name'] ?? '';
    
    if(empty($tmp_file)) {
        echo json_encode(['status' => 'error', 'message' => 'Chunk no recibido']);
        exit;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port$path$file_name");
    curl_setopt($ch, CURLOPT_UPLOAD, 1);
    $fp = fopen($tmp_file, 'r');
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($tmp_file));
    
    // Si no es el primer pedazo, le decimos a cURL que lo "pegue" al final del archivo (Append)
    if ($chunk_index > 0) {
        curl_setopt($ch, CURLOPT_APPEND, true);
    }
    
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);
    
    if ($result) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $err]);
    }
    exit;
}

if ($action === 'read_file') {
    $path = $_POST['path'] ?? '';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port$path");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    curl_close($ch);
    
    if ($data !== false) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','gif','bmp'])) {
            $b64 = base64_encode($data);
            echo json_encode(['status'=>'success', 'type'=>'image', 'data'=>"data:image/$ext;base64,$b64"]);
        } else {
            echo json_encode(['status'=>'success', 'type'=>'text', 'data'=> mb_convert_encoding($data, 'UTF-8', 'auto')]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo leer']);
    }
    exit;
}

if ($action === 'download') {
    $path = $_GET['path'] ?? '';
    $name = basename($path);
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$name\"");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port$path");
    curl_exec($ch);
    curl_close($ch);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
?>
