<?php
/**
 * ARCHIVO: api/payload.php
 * Se encarga de listar, eliminar y enviar archivos .bin al puerto del BinLoader en streaming.
 */
header('Content-Type: application/json');
@set_time_limit(0);

// --- LEER CARPETA DE PAYLOADS LOCALES ---
if (isset($_GET['action']) && $_GET['action'] == 'get_payloads') {
    $payloads = [];
    $directorio = '../payloads';
    if (file_exists($directorio) && is_dir($directorio)) {
        $archivos = glob($directorio . '/*.bin');
        if(is_array($archivos)) { 
            foreach($archivos as $archivo) { 
                $payloads[] = ['nombre' => basename($archivo), 'url' => 'payloads/' . basename($archivo)]; 
            } 
        }
    }
    echo json_encode(['status'=>'success', 'data'=>$payloads]);
    exit;
}

// --- ELIMINAR PAYLOAD DEL SERVIDOR ---
if (isset($_POST['action']) && $_POST['action'] == 'delete_payload') {
    $filename = basename($_POST['file_name'] ?? '');
    $path = '../payloads/' . $filename;
    
    if ($filename && file_exists($path) && @unlink($path)) {
        echo json_encode(['status' => 'success', 'message' => 'Payload eliminado correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar el payload. Verifica los permisos de KSWEB.']);
    }
    exit;
}

// --- INYECTAR PAYLOAD (BINLOADER) ---
if (isset($_POST['action']) && $_POST['action'] == 'send_payload') {
    $host_ip = $_POST['host_ip'] ?? '';
    $port = intval($_POST['port'] ?? 9020); 
    $source_type = $_POST['source_type'] ?? 'local';
    
    $ruta_archivo_origen = '';

    if (empty($host_ip)) {
        echo json_encode(['status' => 'error', 'message' => 'Falta la IP de la PS4.']);
        exit;
    }

    try {
        if ($source_type === 'gallery') {
            $nombre_archivo = basename($_POST['payload_path']);
            $real_path = "../payloads/" . $nombre_archivo;
            
            if (file_exists($real_path) && pathinfo($real_path, PATHINFO_EXTENSION) === 'bin') {
                $ruta_archivo_origen = $real_path;
            } else {
                throw new Exception("El payload seleccionado no existe en el servidor.");
            }
        } else {
            if (!isset($_FILES['payload_file']) || $_FILES['payload_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Selecciona un archivo .bin válido desde tu dispositivo.");
            }
            $ruta_archivo_origen = $_FILES['payload_file']['tmp_name'];
        }

        // Abrimos conexión TCP directa a la PS4
        $fp_out = @fsockopen($host_ip, $port, $errno, $errstr, 5);
        if (!$fp_out) {
            throw new Exception("No se pudo conectar al puerto $port.<br><br>Asegúrate de tener habilitada la opción <b>BinLoader Server</b> en la configuración de GoldHEN en tu PS4.");
        }

        // Leemos el archivo en trozos pequeños (Streaming) para no saturar la RAM del celular
        $fp_in = @fopen($ruta_archivo_origen, 'rb');
        if (!$fp_in) {
            fclose($fp_out);
            throw new Exception("No se pudo leer el archivo payload local.");
        }

        $enviado_ok = true;
        while (!feof($fp_in)) {
            $chunk = fread($fp_in, 8192); // Leemos de a 8KB
            if (fwrite($fp_out, $chunk) === false) {
                $enviado_ok = false;
                break;
            }
        }

        fclose($fp_in);
        fclose($fp_out);

        if ($enviado_ok) {
            echo json_encode(['status' => 'success', 'message' => 'Payload inyectado correctamente en la memoria.']);
        } else {
            throw new Exception("Error al transferir los datos del payload. Conexión interrumpida.");
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>
