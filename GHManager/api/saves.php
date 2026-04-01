<?php
/**
 * ARCHIVO: api/saves.php
 * Motor de Backup de Partidas Guardadas (RAM Optimizada con almacenamiento temporal en disco)
 */
header('Content-Type: application/json');
set_time_limit(300); // 5 minutos de tiempo límite

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$host_ip = $_POST['host_ip'] ?? $_GET['host_ip'] ?? '';
$cusa = $_POST['cusa_id'] ?? $_GET['cusa_id'] ?? '';

// 1. Descarga el ZIP creado
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

$conn = @ftp_connect($host_ip, 2121, 5);
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'No conecta a PS4.']);
    exit;
}
@ftp_login($conn, "anonymous", "");
ftp_pasv($conn, true);

// Función para calcular peso sin colgar la consola (¡Tu regla de -1 es genial aquí!)
function getDirStats($conn, $remote_dir, &$fileCount) {
    $size = 0;
    $files = @ftp_nlist($conn, $remote_dir);
    if (is_array($files)) {
        foreach ($files as $file) {
            $base = basename($file);
            if ($base == '.' || $base == '..') continue;
            
            $remote_path = rtrim($remote_dir, '/') . '/' . $base;
            $s = @ftp_size($conn, $remote_path);
            
            if ($s == -1) {
                $size += getDirStats($conn, $remote_path, $fileCount);
            } else {
                $size += $s;
                $fileCount++;
            }
        }
    }
    return $size;
}

// NUEVA VERSIÓN: Descarga a disco (casi 0% consumo de RAM)
function addFolderToZip($conn, $remote_dir, $zip, $zip_dir, $temp_base_dir) {
    $files = @ftp_nlist($conn, $remote_dir);
    if (is_array($files)) {
        $zip->addEmptyDir($zip_dir); 
        foreach ($files as $file) {
            $base = basename($file);
            if ($base == '.' || $base == '..') continue;
            
            $remote_path = rtrim($remote_dir, '/') . '/' . $base;
            $local_zip_path = $zip_dir . '/' . $base;
            
            $size = @ftp_size($conn, $remote_path);
            
            if ($size == -1) {
                addFolderToZip($conn, $remote_path, $zip, $local_zip_path, $temp_base_dir); 
            } else {
                // Descargamos a un archivo físico temporal
                $temp_file = $temp_base_dir . '/' . uniqid('sv_') . '.tmp';
                if (@ftp_get($conn, $temp_file, $remote_path, FTP_BINARY)) {
                    // addFile no colapsa la RAM, solo encola la ruta para cuando hagas $zip->close()
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

// 2. Acción: Revisar y calcular peso ANTES de descargar
if ($action === 'check_saves') {
    $users = @ftp_nlist($conn, "/user/home");
    $total_size = 0;
    $total_files = 0;
    $found_users = [];

    if (is_array($users)) {
        foreach ($users as $u) {
            $user_id = basename($u);
            if ($user_id === '.' || $user_id === '..') continue;
            
            $target = "/user/home/$user_id/savedata/$cusa";
            
            $check = @ftp_nlist($conn, $target);
            if (is_array($check) && count($check) > 0) {
                $count = 0;
                $size = getDirStats($conn, $target, $count);
                if ($count > 0) {
                    $total_size += $size;
                    $total_files += $count;
                    $found_users[] = $user_id;
                }
            }
        }
    }
    @ftp_close($conn);

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

// 3. Acción: Crear el ZIP real
if ($action === 'backup') {
    $users = @ftp_nlist($conn, "/user/home");
    $save_paths = [];
    
    if (is_array($users)) {
        foreach ($users as $u) {
            $user_id = basename($u);
            if ($user_id === '.' || $user_id === '..') continue;
            
            $target = "/user/home/$user_id/savedata/$cusa";
            $check = @ftp_nlist($conn, $target);
            if (is_array($check) && count($check) > 0) {
                $save_paths[] = $target; 
            }
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
            addFolderToZip($conn, $path, $zip, "Saves_Usuario_$user_folder", $temp_work_dir);
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
    @ftp_close($conn);
    exit;
}
?>
