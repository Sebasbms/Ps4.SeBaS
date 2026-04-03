<?php
/**
 * ARCHIVO: api/modding.php
 * Sistema de inyección y extracción de portadas (CUSA) - Versión Definitiva
 */
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$host = $_POST['host_ip'] ?? '';
$cusa = strtoupper(trim($_POST['cusa_id'] ?? ''));
$port = 2121;

if (!$host) {
    echo json_encode(['status' => 'error', 'message' => 'Falta IP de PS4']);
    exit;
}

// ==========================================
// 1. INYECTAR PORTADA (UPLOAD) - Modo "Caballo de Troya"
// ==========================================
if ($action === 'upload_icon') {
    $source = $_POST['source_type'] ?? '';
    $icon_data = '';

    if ($source === 'local_gallery') {
        $path = __DIR__ . '/../' . ($_POST['icon_path'] ?? '');
        if (file_exists($path)) {
            $icon_data = file_get_contents($path);
        }
    } else {
        if (isset($_FILES['local_icon']['tmp_name'])) {
            $icon_data = file_get_contents($_FILES['local_icon']['tmp_name']);
        }
    }

    if (empty($icon_data)) {
        echo json_encode(['status' => 'error', 'message' => 'No se encontró la imagen en el celular.']);
        exit;
    }

    // Ruta de destino. Usamos doble barra al inicio para asegurar la raíz en GoldHEN
    $dest_path = "//user/app/meta/$cusa/icon0.png";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port$dest_path");
    curl_setopt($ch, CURLOPT_UPLOAD, 1);
    
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $icon_data);
    rewind($stream);
    
    curl_setopt($ch, CURLOPT_INFILE, $stream);
    curl_setopt($ch, CURLOPT_INFILESIZE, strlen($icon_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FTP_USE_EPSV, 0); 
    
    // MAGIA: El "Caballo de Troya".
    // Obligamos a cURL a navegar por las carpetas una por una antes de soltar el archivo.
    // Esto desarma el escudo de GoldHEN que causa el Error 550.
    curl_setopt($ch, CURLOPT_FTP_FILEMETHOD, CURLFTPMETHOD_MULTICWD); 
    
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($stream);

    if ($res) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Error al inyectar: $err"]);
    }
    exit;
}

// ==========================================
// 2. EXTRAER PORTADA ORIGINAL (BACKUP)
// ==========================================
if ($action === 'backup_original') {
    if (!$cusa) {
        echo json_encode(['status' => 'error', 'message' => 'Falta el ID del juego (CUSA)']);
        exit;
    }

    // Doble barra de seguridad para la raíz
    $remote = "//user/app/meta/$cusa/icon0.png";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port$remote");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FTP_USE_EPSV, 0); 
    
    // Para leer, el modo directo sigue siendo el más seguro
    curl_setopt($ch, CURLOPT_FTP_FILEMETHOD, CURLFTPMETHOD_NOCWD); 
    
    $data = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($data && empty($err)) {
        $backup_dir = __DIR__ . '/../backup_icons';
        if (!is_dir($backup_dir)) @mkdir($backup_dir, 0777, true);
        
        file_put_contents("$backup_dir/{$cusa}_original.png", $data);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => "La portada no existe. ¿Está el juego instalado? (Error: $err)"]);
    }
    exit;
}

// ==========================================
// 3. ESCANEAR TODOS LOS JUEGOS (Para el botón "Respaldar Todos")
// ==========================================
if ($action === 'get_all_cusa') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port/user/app/meta/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FTP_USE_EPSV, 0);
    curl_setopt($ch, CURLOPT_FTP_FILEMETHOD, CURLFTPMETHOD_MULTICWD); // Usamos multi-navegación aquí también por precaución
    $res = curl_exec($ch);
    curl_close($ch);
    
    $juegos = [];
    if ($res) {
        $lines = explode("\n", trim($res));
        foreach ($lines as $line) {
            if (preg_match('/(CUSA\d{5})/i', $line, $matches)) {
                $juegos[] = strtoupper($matches[1]);
            }
        }
    }
    
    if (!empty($juegos)) {
        echo json_encode(['status' => 'success', 'juegos' => array_unique($juegos)]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se encontraron juegos instalados.']);
    }
    exit;
}

// ==========================================
// 4. OBTENER AVATAR DEL PERFIL PS4
// ==========================================
if ($action === 'get_ps4_profile') {
    $remote = "//user/home/10000000/avatar.png"; 
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:$port$remote");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_FTP_USE_EPSV, 0);
    curl_setopt($ch, CURLOPT_FTP_FILEMETHOD, CURLFTPMETHOD_NOCWD);
    $data = curl_exec($ch);
    curl_close($ch);

    if ($data) {
        $base64 = 'data:image/png;base64,' . base64_encode($data);
        echo json_encode(['status' => 'success', 'avatar' => $base64]);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// Acción no encontrada
echo json_encode(['status' => 'error', 'message' => 'Comando no válido.']);
?>
