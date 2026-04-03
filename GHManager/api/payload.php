<?php
/**
 * ARCHIVO: api/payload.php
 * Inyector BinLoader (Adaptado para los carriles de Termux)
 */
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(0);

if (isset($_GET['action']) && $_GET['action'] == 'get_payloads') {
    $payloads = [];
    $directorio = '../payloads';
    if (!file_exists($directorio)) { @mkdir($directorio, 0777, true); }
    
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

if (isset($_POST['action']) && $_POST['action'] == 'delete_payload') {
    $filename = basename($_POST['file_name'] ?? '');
    $path = '../payloads/' . $filename;
    
    if ($filename && file_exists($path) && @unlink($path)) {
        echo json_encode(['status' => 'success', 'message' => 'Payload eliminado.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar. Verifica permisos de Termux.']);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'send_payload') {
    $host_ip = $_POST['host_ip'] ?? '';
    $port = intval($_POST['port'] ?? 9020); 
    $source_type = $_POST['source_type'] ?? 'local';
    $ruta_archivo_origen = '';

    if (empty($host_ip)) { echo json_encode(['status' => 'error', 'message' => 'Falta IP de PS4.']); exit; }

    try {
        if ($source_type === 'gallery') {
            $nombre_archivo = basename($_POST['payload_path']);
            $real_path = "../payloads/" . $nombre_archivo;
            if (file_exists($real_path) && pathinfo($real_path, PATHINFO_EXTENSION) === 'bin') {
                $ruta_archivo_origen = $real_path;
            } else { throw new Exception("El payload no existe en Termux."); }
        } else {
            if (!isset($_FILES['payload_file']) || $_FILES['payload_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Archivo inválido.");
            }
            $ruta_archivo_origen = $_FILES['payload_file']['tmp_name'];
        }

        // Conexión TCP con timeout estricto de 3 segundos para no trancar Termux
        $fp_out = @fsockopen($host_ip, $port, $errno, $errstr, 3);
        if (!$fp_out) {
            throw new Exception("No se pudo conectar al puerto $port. Activa el BinLoader Server en GoldHEN.");
        }

        $fp_in = @fopen($ruta_archivo_origen, 'rb');
        if (!$fp_in) { fclose($fp_out); throw new Exception("No se pudo leer el payload local."); }

        $enviado_ok = true;
        stream_set_timeout($fp_out, 5); // Evita bloqueos a mitad de transmisión
        
        while (!feof($fp_in)) {
            $chunk = fread($fp_in, 8192);
            if (fwrite($fp_out, $chunk) === false) { $enviado_ok = false; break; }
        }

        fclose($fp_in);
        fclose($fp_out);

        if ($enviado_ok) {
            echo json_encode(['status' => 'success', 'message' => 'Payload inyectado en memoria.']);
        } else {
            throw new Exception("Conexión interrumpida durante la inyección.");
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>
