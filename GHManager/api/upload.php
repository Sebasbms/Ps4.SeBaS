<?php
/**
 * ARCHIVO: api/upload.php
 * Se encarga de la subida por fragmentos (Soporte Real +200GB sin colapsar la PS4)
 */
header('Content-Type: application/json');
@set_time_limit(0); 
@ini_set('memory_limit', '-1');

if (isset($_POST['action'])) {
    
    if ($_POST['action'] == 'check_file') {
        $host_ip = $_POST['host_ip']; 
        $ruta_destino = rtrim($_POST['path'], '/') . '/';
        $file_name = $_POST['file_name'];
        $conn_id = @ftp_connect($host_ip, 2121, 10);
        $size = 0;
        
        if ($conn_id && @ftp_login($conn_id, "anonymous", "")) {
            ftp_pasv($conn_id, true);
            
            // MÉTODO SEGURO: Evita que el celular cuelgue la suma si el PKG pesa más de 2GB
            $raw_size = @ftp_raw($conn_id, "SIZE " . $ruta_destino . $file_name);
            if (is_array($raw_size) && preg_match('/^213\s+(\d+)$/', $raw_size[0], $matches)) {
                $size = (float) $matches[1];
            } else {
                $size = @ftp_size($conn_id, $ruta_destino . $file_name);
            }
        }
        
        @ftp_close($conn_id);
        echo json_encode(['status' => 'success', 'size' => $size > 0 ? $size : 0]);
        exit;
    }
    
    if ($_POST['action'] == 'upload_chunk') {
        $host_ip = $_POST['host_ip']; 
        $ruta_destino = rtrim($_POST['selected_path'], '/') . '/';
        $file_name = $_POST['file_name']; 
        $is_first_chunk = (isset($_POST['is_first_chunk']) && $_POST['is_first_chunk'] === 'true');
        $puerto_ftp = 2121; 
        $ruta_remota = $ruta_destino . $file_name;
        
        if (isset($_FILES['archivo_subida']) && $_FILES['archivo_subida']['error'] === UPLOAD_ERR_OK) {
            $archivo_temporal = $_FILES['archivo_subida']['tmp_name'];
            
            if ($is_first_chunk) { 
                // PRIMER FRAGMENTO: Usamos FTP nativo para sobreescribir limpiamente
                $conn_id = @ftp_connect($host_ip, $puerto_ftp, 15);
                if ($conn_id && @ftp_login($conn_id, "anonymous", "")) {
                    ftp_pasv($conn_id, true);
                    @ftp_delete($conn_id, $ruta_remota); 
                    $result = @ftp_put($conn_id, $ruta_remota, $archivo_temporal, FTP_BINARY);
                    @ftp_close($conn_id);
                    
                    if ($result) { echo json_encode(['status' => 'success']); } 
                    else { echo json_encode(['status' => 'error', 'message' => 'Fallo al iniciar archivo en PS4']); }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Desconexión de red FTP']);
                }
            } else {
                // SIGUIENTES FRAGMENTOS: Usamos cURL de forma exclusiva.
                // ¡Nunca abrimos ftp_connect() aquí para no ahogar a la PS4 con múltiples conexiones!
                $ch = curl_init(); 
                $fp = fopen($archivo_temporal, 'r');
                
                curl_setopt($ch, CURLOPT_URL, "ftp://" . $host_ip . ":" . $puerto_ftp . $ruta_remota); 
                curl_setopt($ch, CURLOPT_USERPWD, "anonymous:"); // Credencial clave para GoldHen
                curl_setopt($ch, CURLOPT_UPLOAD, 1); 
                curl_setopt($ch, CURLOPT_INFILE, $fp); 
                curl_setopt($ch, CURLOPT_INFILESIZE, filesize($archivo_temporal)); 
                curl_setopt($ch, CURLOPT_FTPAPPEND, true); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
                
                $result = curl_exec($ch); 
                
                if(curl_errno($ch)){
                    $err = curl_error($ch);
                    curl_close($ch); 
                    fclose($fp);
                    echo json_encode(['status' => 'error', 'message' => 'Error enviando fragmento: ' . $err]);
                    exit;
                }
                
                curl_close($ch); 
                fclose($fp);
                
                echo json_encode(['status' => 'success']);
            }
        } else { 
            echo json_encode(['status' => 'error', 'message' => 'Fragmento vacío, dañado o cancelado por el usuario']); 
        }
        exit;
    }
}
?>
