<?php
/**
 * ARCHIVO: api/saves.php
 * Motor de Backup de Partidas (RAM Optimizada con almacenamiento temporal en disco)
 * VERSIÓN ACTUALIZADA A cURL (Compatible con el nuevo Termux)
 */
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
set_time_limit(300); // 5 minutos de tiempo límite

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$host_ip = $_POST['host_ip'] ?? $_GET['host_ip'] ?? '';
$cusa = $_POST['cusa_id'] ?? $_GET['cusa_id'] ?? '';
$port = 2121;

// 1. Descarga el ZIP creado (NO USA RED, SOLO ARCHIVOS LOCALES)
if ($action === 'download') {
    $file = $_GET['file'] ?? '';
    $path = "../cache_biblioteca/" . basename($file);
    if (file_exists($path) && strpos($file, '.zip') !== false) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.basename($path).'"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    exit;
}

if (!$host_ip || !$cusa) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos de conexión.']);
    exit;
}

// ==========================================
// FUNCIONES NÚCLEO cURL (Reemplazo de ftp_*)
// ==========================================

// Lista detallada de archivos (Equivalente a ftp_rawlist para sacar tamaño y tipo de una sola vez)
function curl_ftp_list_details($ip, $port, $path) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port" . rtrim($path, '/') . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $items = [];
    if ($res) {
        $lines = explode("\n", trim($res));
        foreach ($lines as $line) {
            if(empty(trim($line))) continue;
            $parts = preg_split('/\s+/', trim($line), 9);
            if(count($parts) >= 9) {
                $name = $parts[8];
                if ($name === '.' || $name === '..') continue;
                $is_dir = (substr($parts[0], 0, 1) === 'd');
                $size = (int)$parts[4];
                $items[] = ['name' => $name, 'is_dir' => $is_dir, 'size' => $size];
            }
        }
    }
    return $items;
}

// Descarga un archivo por FTP directo a un archivo temporal (Bajo consumo de RAM)
function curl_ftp_download($ip, $port, $remote_path, $local_path) {
    $ch = curl_init();
    $fp = fopen($local_path, 'w');
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port$remote_path");
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $success = curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    return $success;
}

// Función recursiva para calcular peso usando cURL
function getDirStatsCurl($ip, $port, $remote_dir, &$fileCount) {
    $size = 0;
    $items = curl_ftp_list_details($ip, $port, $remote_dir);
    foreach ($items as $item) {
        $remote_path = rtrim($remote_dir, '/') . '/' . $item['name'];
        if ($item['is_dir']) {
            $size += getDirStatsCurl($ip, $port, $remote_path, $fileCount);
        } else {
            $size += $item['size'];
            $fileCount++;
        }
    }
    return $size;
}

// Descarga a disco y encola al ZIP
function addFolderToZipCurl($ip, $port, $remote_dir, $zip, $zip_dir, $temp_base_dir) {
    $items = curl_ftp_list_details($ip, $port, $remote_dir);
    if (count($items) > 0) {
        $zip->addEmptyDir($zip_dir); 
        foreach ($items as $item) {
            $remote_path = rtrim($remote_dir, '/') . '/' . $item['name'];
            $local_zip_path = $zip_dir . '/' . $item['name'];
            
            if ($item['is_dir']) {
                addFolderToZipCurl($ip, $port, $remote_path, $zip, $local_zip_path, $temp_base_dir); 
            } else {
                // Descargamos a un archivo físico temporal (Sin saturar RAM)
                $temp_file = $temp_base_dir . '/' . uniqid('sv_') . '.tmp';
                if (curl_ftp_download($ip, $port, $remote_path, $temp_file)) {
                    $zip->addFile($temp_file, $local_zip_path); 
                }
            }
        }
    }
}

// Utilidad para limpiar los archivos temporales de disco
function limpiarDirectorioTemporal($dir) {
    if (!file_exists($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? limpiarDirectorioTemporal("$dir/$file") : @unlink("$dir/$file");
    }
    @rmdir($dir);
}


// ==========================================
// 2. Acción: Revisar y calcular peso ANTES de descargar
// ==========================================
if ($action === 'check_saves') {
    $users = curl_ftp_list_details($host_ip, $port, "/user/home");
    $total_size = 0;
    $total_files = 0;
    $found_users = [];

    foreach ($users as $u) {
        if (!$u['is_dir']) continue;
        $user_id = $u['name'];
        
        $target = "/user/home/$user_id/savedata/$cusa";
        $check = curl_ftp_list_details($host_ip, $port, $target);
        
        if (count($check) > 0) {
            $count = 0;
            $size = getDirStatsCurl($host_ip, $port, $target, $count);
            if ($count > 0) {
                $total_size += $size;
                $total_files += $count;
                $found_users[] = $user_id;
            }
        }
    }

    if (empty($found_users)) {
        echo json_encode(['status' => 'error', 'message' => 'No se encontraron partidas (Saves) en la consola para este juego.']);
    } else {
        $mb = number_format($total_size / (1024 * 1024), 2);
        echo json_encode([
            'status' => 'success', 
            'files' => $total_files,
            'size_mb' => $mb,
            'users' => implode(", ", $found_users)
        ]);
    }
    exit;
}

// ==========================================
// 3. Acción: Crear el ZIP real
// ==========================================
if ($action === 'backup') {
    $users = curl_ftp_list_details($host_ip, $port, "/user/home");
    $save_paths = [];
    
    foreach ($users as $u) {
        if (!$u['is_dir']) continue;
        $user_id = $u['name'];
        
        $target = "/user/home/$user_id/savedata/$cusa";
        $check = curl_ftp_list_details($host_ip, $port, $target);
        if (count($check) > 0) {
            $save_paths[] = $target; 
        }
    }

    $cache_dir = '../cache_biblioteca';
    if (!file_exists($cache_dir)) { @mkdir($cache_dir, 0777, true); }
    
    // Creamos la carpeta para los archivos temporales físicos
    $temp_work_dir = $cache_dir . '/tmp_saves_' . uniqid();
    @mkdir($temp_work_dir, 0777, true);
    
    $zip_filename = "{$cusa}_SAVES.zip";
    $zip_path = "$cache_dir/$zip_filename";
    
    if (file_exists($zip_path)) { @unlink($zip_path); }

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach ($save_paths as $path) {
            $user_folder = basename(dirname(dirname($path))); 
            addFolderToZipCurl($host_ip, $port, $path, $zip, "Saves_Usuario_$user_folder", $temp_work_dir);
        }
        
        // ¡Magia aquí! Cierra y compila el ZIP leyendo desde disco, no desde RAM.
        $zip->close();
        
        // Limpiamos los archivos temporales que usamos de puente
        limpiarDirectorioTemporal($temp_work_dir);
        
        echo json_encode(['status' => 'success', 'download_url' => "api/saves.php?action=download&file=$zip_filename"]);
    } else {
        // Limpiamos la basura por si falló la creación
        limpiarDirectorioTemporal($temp_work_dir);
        echo json_encode(['status' => 'error', 'message' => 'Error al comprimir el archivo ZIP en el servidor local.']);
    }
    exit;
}
?>
