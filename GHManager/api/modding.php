<?php
/**
 * ARCHIVO: api/modding.php
 * Motor de Modding (Actualizado para leer Discos Externos)
 */
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$host_ip = $_POST['host_ip'] ?? '';
$cusa_id = strtoupper(trim($_POST['cusa_id'] ?? ''));
$port = 2121;

if (!$host_ip) { echo json_encode(['status' => 'error', 'message' => 'Faltan datos de IP']); exit; }

function curl_download($ip, $port, $remote_path, $local_path) {
    $ch = curl_init("ftp://$ip:$port$remote_path");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $data = curl_exec($ch);
    curl_close($ch);
    if ($data !== false && strlen($data) > 0) {
        file_put_contents($local_path, $data);
        return true;
    }
    return false;
}

function curl_upload($ip, $port, $remote_path, $local_path) {
    $fp = fopen($local_path, 'r');
    $ch = curl_init("ftp://$ip:$port$remote_path");
    curl_setopt($ch, CURLOPT_UPLOAD, 1);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($local_path));
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $res = curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    return $res;
}

function check_folder_exists($ip, $port, $path) {
    $ch = curl_init("ftp://$ip:$port$path/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
    return ($res !== false && strlen(trim($res)) > 0);
}

// AQUÍ ESTÁN LAS 4 RUTAS INTELIGENTES (Internas y USBs Externos)
$rutas_appmeta = [
    "/user/appmeta/", 
    "/user/appmeta/external/", 
    "/system_data/priv/appmeta/", 
    "/system_data/priv/appmeta/external/"
];

if ($action === 'get_ps4_profile') {
    $ch = curl_init("ftp://$host_ip:$port/user/home/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $avatar_path = null;
    if ($res) {
        $lines = explode("\n", trim($res));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $parts = preg_split('/\s+/', trim($line), 9);
            if (count($parts) >= 9 && is_numeric(trim($parts[8]))) {
                $avatar_path = "/user/home/" . trim($parts[8]) . "/avatar.png";
                break; 
            }
        }
    }
    
    if ($avatar_path) {
        $ch = curl_init("ftp://$host_ip:$port$avatar_path");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data !== false && strlen($data) > 0) {
            $base64 = 'data:image/png;base64,' . base64_encode($data);
            echo json_encode(['status' => 'success', 'avatar' => $base64]);
            exit;
        }
    }
    echo json_encode(['status' => 'error']);
    exit;
}

if (!$cusa_id) { echo json_encode(['status' => 'error', 'message' => 'Falta Title ID']); exit; }

if ($action === 'backup_original') {
    $backup_dir = '../backup_icons';
    if (!file_exists($backup_dir)) mkdir($backup_dir, 0777, true);
    
    $local_path = $backup_dir . '/' . $cusa_id . '_' . time() . '.png';
    $encontrado = false;

    foreach ($rutas_appmeta as $base) {
        if (curl_download($host_ip, $port, $base . $cusa_id . "/icon0.png", $local_path)) {
            $encontrado = true;
            break;
        }
    }

    if ($encontrado) echo json_encode(['status' => 'success']);
    else echo json_encode(['status' => 'error', 'message' => "No se encontró el icono original de $cusa_id en la PS4."]);
    exit;
}

if ($action === 'upload_icon') {
    $source_type = $_POST['source_type'] ?? '';
    $local_file = '';
    
    if ($source_type === 'local' && isset($_FILES['local_icon'])) {
        $local_file = $_FILES['local_icon']['tmp_name'];
    } elseif ($source_type === 'local_gallery' && isset($_POST['icon_path'])) {
        $local_file = '../' . $_POST['icon_path'];
    }

    if (!$local_file || !file_exists($local_file)) {
        echo json_encode(['status' => 'error', 'message' => 'Imagen no recibida.']); exit;
    }

    // Como el JS ya validó que es 512x512, enviamos el archivo directo por FTP sin usar la librería GD
    $exitos = 0;
    foreach ($rutas_appmeta as $base) {
        $carpeta_juego = $base . $cusa_id;
        if (check_folder_exists($host_ip, $port, $carpeta_juego)) {
            if (curl_upload($host_ip, $port, $carpeta_juego . "/icon0.png", $local_file)) {
                $exitos++;
            }
        }
    }
    
    if ($exitos > 0) {
        @unlink("../cache_biblioteca/$cusa_id.png"); // Borrar caché del cel para que actualice
        echo json_encode(['status' => 'success', 'message' => 'Portada aplicada en la PS4.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'La portada no existe. ¿Está el juego instalado? (Ruta no hallada)']);
    }
    exit;
}

if ($action === 'get_all_cusa') {
    $cusa_list = [];
    $patron = '/^[A-Z]{4}\d{5}$/i';
    
    foreach ($rutas_appmeta as $base) {
        $ch = curl_init("ftp://$host_ip:$port" . rtrim($base, '/') . '/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $res = curl_exec($ch);
        curl_close($ch);
        
        if ($res) {
            $lines = explode("\n", trim($res));
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $parts = preg_split('/\s+/', trim($line), 9);
                if (count($parts) >= 9) {
                    $name = strtoupper(trim($parts[8]));
                    if (preg_match($patron, $name)) $cusa_list[] = $name;
                }
            }
        }
    }
    echo json_encode(['status' => 'success', 'juegos' => array_values(array_unique($cusa_list))]);
    exit;
}
?>
