<?php
/**
 * ARCHIVO: api/modding.php
 * Motor de Modding Definitivo (Peticiones atómicas para Saqueo Frontal y Evitar Reemplazos)
 */
header('Content-Type: application/json');

if (isset($_POST['action'])) {
    $host_ip = $_POST['host_ip'] ?? '';
    $puerto_ftp = 2121;
    
    // =========================================================
    // 0. SAQUEO: OBTENER LISTA DE CARPETAS (JUEGOS Y APPS)
    // =========================================================
    if ($_POST['action'] == 'get_all_cusa') {
        $juegos = [];
        $conn_id = @ftp_connect($host_ip, $puerto_ftp, 10);
        if ($conn_id && @ftp_login($conn_id, "anonymous", "")) {
            ftp_pasv($conn_id, true);
            
            $patron_id = '/^[A-Z]{4}\d{5}$/i';

            $lista_int = @ftp_nlist($conn_id, "/user/appmeta");
            if (is_array($lista_int)) {
                foreach($lista_int as $ruta) { 
                    $b = basename($ruta); 
                    if(preg_match($patron_id, $b)) $juegos[] = strtoupper($b); 
                }
            }
            
            $lista_push = @ftp_nlist($conn_id, "/user/appmeta/push_resource");
            if (is_array($lista_push)) {
                foreach($lista_push as $ruta) { 
                    $b = basename($ruta); 
                    if(preg_match($patron_id, $b)) $juegos[] = strtoupper($b); 
                }
            }
            
            $lista_ext = @ftp_nlist($conn_id, "/user/appmeta/external");
            if (is_array($lista_ext)) {
                foreach($lista_ext as $ruta) { 
                    $b = basename($ruta); 
                    if(preg_match($patron_id, $b)) $juegos[] = strtoupper($b); 
                }
            }

            $lista_sys = @ftp_nlist($conn_id, "/system_data/priv/appmeta");
            if (is_array($lista_sys)) {
                foreach($lista_sys as $ruta) { 
                    $b = basename($ruta); 
                    if(preg_match($patron_id, $b)) $juegos[] = strtoupper($b); 
                }
            }
            
            @ftp_close($conn_id);
            $juegos = array_values(array_unique($juegos));
            echo json_encode(['status' => 'success', 'juegos' => $juegos]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo conectar a la PS4.']);
        }
        exit;
    }

    // =========================================================
    // 1. DESCARGA UNITARIA (ACELERADA)
    // =========================================================
    if ($_POST['action'] == 'backup_original') {
        $cusa_id = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_POST['cusa_id'] ?? '')));
        if (preg_match('/^[A-Z]{4}(\d{4})$/', $cusa_id, $matches)) {
            $prefijo = substr($cusa_id, 0, 4);
            $cusa_id = $prefijo . '0' . $matches[1];
        }

        $backup_dir = '../backup_icons';
        if (!file_exists($backup_dir)) @mkdir($backup_dir, 0777, true);
        
        $archivo_destino = $backup_dir . '/' . $cusa_id . '_original.png';

        $conn_id = @ftp_connect($host_ip, $puerto_ftp, 10);
        if ($conn_id && @ftp_login($conn_id, "anonymous", "")) {
            ftp_pasv($conn_id, true);
            ftp_set_option($conn_id, FTP_TIMEOUT_SEC, 15); 

            $rutas = [
                "/user/appmeta/external/$cusa_id/icon0.png",
                "/user/appmeta/push_resource/$cusa_id/icon0.png",
                "/user/appmeta/$cusa_id/icon0.png",
                "/system_data/priv/appmeta/$cusa_id/icon0.png"
            ];
            
            $descargado = false;
            foreach($rutas as $ruta) {
                // ACELERADOR: Preguntamos el tamaño primero (milisegundos)
                $size = @ftp_size($conn_id, $ruta);
                if ($size > 100) {
                    // Si existe, recién ahí abrimos el canal de datos para descargarlo
                    if (@ftp_get($conn_id, $archivo_destino, $ruta, FTP_BINARY)) {
                        $descargado = true;
                        break; // ¡Encontrado y descargado! Salimos del bucle.
                    }
                }
            }
            @ftp_close($conn_id); 

            if ($descargado) {
                echo json_encode(['status' => 'success']);
            } else { 
                @unlink($archivo_destino); 
                echo json_encode(['status' => 'error', 'message' => "No hallado en discos."]); 
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => "Error de conexión."]);
        }
        exit;
    }

    // =========================================================
    // 2. INYECTAR PORTADA A LA PS4
    // =========================================================
    if ($_POST['action'] == 'upload_icon') {
        $cusa_id = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_POST['cusa_id'] ?? '')));
        if (preg_match('/^[A-Z]{4}(\d{4})$/', $cusa_id, $matches)) {
            $prefijo = substr($cusa_id, 0, 4);
            $cusa_id = $prefijo . '0' . $matches[1];
        }
        $source_type = $_POST['source_type'] ?? ''; 
        $archivo_temporal = "";
        
        try {
            if (empty($cusa_id)) throw new Exception("ID no válido.");

            if ($source_type == 'local_gallery') {
                $nombre_archivo = basename($_POST['icon_path']);
                $directorio_origen = (strpos($_POST['icon_path'], 'backup_icons') !== false) ? '../backup_icons/' : '../iconos/';
                $real_path = $directorio_origen . $nombre_archivo;
                if (file_exists($real_path)) { $archivo_temporal = $real_path; } 
                else { throw new Exception("Archivo no encontrado en tu dispositivo."); }
            } else {
                if (!isset($_FILES['local_icon']) || $_FILES['local_icon']['error'] !== UPLOAD_ERR_OK) { 
                    throw new Exception("Error al procesar la imagen."); 
                }
                $archivo_temporal = $_FILES['local_icon']['tmp_name'];
            }
            
            $conn_id = @ftp_connect($host_ip, $puerto_ftp, 10);
            if (!$conn_id || !@ftp_login($conn_id, "anonymous", "")) throw new Exception("Error conectando al FTP.");
            ftp_pasv($conn_id, true);
            
            $exito = false;
            @ftp_mkdir($conn_id, "/user/appmeta/" . $cusa_id);
            if (@ftp_put($conn_id, "/user/appmeta/" . $cusa_id . "/icon0.png", $archivo_temporal, FTP_BINARY)) {
                $exito = true;
            }
            
            @ftp_mkdir($conn_id, "/user/appmeta/push_resource/" . $cusa_id);
            if (@ftp_put($conn_id, "/user/appmeta/push_resource/" . $cusa_id . "/icon0.png", $archivo_temporal, FTP_BINARY)) {
                $exito = true;
            }

            @ftp_mkdir($conn_id, "/user/appmeta/external/" . $cusa_id);
            if (@ftp_put($conn_id, "/user/appmeta/external/" . $cusa_id . "/icon0.png", $archivo_temporal, FTP_BINARY)) {
                $exito = true;
            }
            
            @ftp_close($conn_id);
            
            if ($exito) echo json_encode(['status' => 'success', 'message' => "Portada inyectada en $cusa_id."]); 
            else throw new Exception("Error al escribir el icono en la PS4."); 
        } catch (Exception $e) { 
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); 
        }
        exit;
    }

    // =========================================================
    // 3. EXTRAER AVATAR DE LA PS4
    // =========================================================
    if ($_POST['action'] == 'get_ps4_profile') {
        $avatar_b64 = null;
        $conn_id = @ftp_connect($host_ip, $puerto_ftp, 10); 
        
        if ($conn_id && @ftp_login($conn_id, "anonymous", "")) {
            ftp_pasv($conn_id, true);
            ftp_set_option($conn_id, FTP_TIMEOUT_SEC, 5); 
            
            $usuarios = ['10000000', '10000001', '10000002', '10000003']; 
            $temp_file = '../backup_icons/avatar_tmp.png';
            
            $encontrado = false;
            foreach ($usuarios as $u) {
                $rutas = [
                    "/system_data/priv/mms/tenka/profile/$u/avatar.png",
                    "/system_data/priv/mms/tenka/profile/$u/avatar64.png",
                    "/user/home/$u/avatar.png"
                ];
                
                foreach ($rutas as $ruta) {
                    if (@ftp_size($conn_id, $ruta) > 100) { // Acelerador aplicado aquí también
                        if (@ftp_get($conn_id, $temp_file, $ruta, FTP_BINARY)) {
                            $encontrado = true;
                            break 2;
                        }
                    }
                }
            }
            @ftp_close($conn_id);

            if ($encontrado) {
                $img_data = file_get_contents($temp_file);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = @$finfo->buffer($img_data) ?: 'image/png';
                $avatar_b64 = 'data:' . $mime . ';base64,' . base64_encode($img_data);
                @unlink($temp_file);
            }
        }
        
        if ($avatar_b64) {
            echo json_encode(['status' => 'success', 'avatar' => $avatar_b64]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se encontró el avatar.']);
        }
        exit;
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida.']);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Petición incorrecta.']);
    exit;
}
?>