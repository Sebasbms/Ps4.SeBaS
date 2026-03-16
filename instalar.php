<?php
/**
 * ====================================================================
 * AUTO-INSTALADOR GOLDHEN MANAGER V2.0
 * DEVELOPED BY SEBAS
 * ====================================================================
 */

// --- LÓGICA DE INSTALACIÓN (Se ejecuta en segundo plano) ---
if (isset($_GET['action']) && $_GET['action'] == 'install') {
    header('Content-Type: application/json');
    
    // URL Corregida al formato RAW para que descargue el archivo directo y no la página web
    $zipUrl = "https://raw.githubusercontent.com/Sebasbms/Ps4.SeBaS/main/instalar.zip"; 
    $zipFile = "temp_instalar.zip";

    try {
        // 1. Descargar el archivo
        $ch = curl_init($zipUrl);
        $fp = fopen($zipFile, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpCode != 200) {
            throw new Exception("Error al descargar los archivos desde GitHub. Código HTTP: " . $httpCode);
        }

        // 2. Descomprimir
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo('./'); // Extrae en la misma carpeta donde está este instalador
            $zip->close();
            
            // 3. Limpieza (Borra el zip descargado)
            @unlink($zipFile);
            
            // (Opcional) Borra este mismo instalador para no dejar rastros
            // @unlink(__FILE__); 

            echo json_encode(['status' => 'success']);
        } else {
            throw new Exception("El archivo descargado está corrupto o no es un ZIP.");
        }
    } catch (Exception $e) {
        @unlink($zipFile);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Instalar GoldHen Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0b0c10; color: white; font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        /* Se agregó transform: translateY(-10vh) para subir la tarjeta */
        .glass-panel { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); transform: translateY(-10vh); }
    </style>
</head>
<body>

    <div class="glass-panel rounded-[2rem] p-8 w-full max-w-sm text-center mx-5 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/20 rounded-full blur-[40px] pointer-events-none"></div>
        
        <i class="fa-brands fa-playstation text-5xl mb-4 text-white drop-shadow-[0_0_15px_rgba(255,255,255,0.4)]"></i>
        <h1 class="text-xl font-black tracking-widest mb-1">GOLDHEN MANAGER</h1>
        <p class="text-[10px] text-white/50 tracking-widest mb-8">V2.0 BY SEBAS</p>

        <div id="ui-initial">
            <p class="text-xs text-white/70 mb-6 px-2 leading-relaxed">Presiona el botón para descargar los archivos necesarios e instalar la aplicación en tu servidor local.</p>
            <button onclick="iniciarInstalacion()" class="w-full bg-white text-black font-black text-xs tracking-widest py-4 rounded-xl shadow-[0_0_20px_rgba(255,255,255,0.3)] active:scale-95 transition-all">
                INSTALAR AHORA
            </button>
        </div>

        <div id="ui-loading" class="hidden">
            <i class="fa-solid fa-circle-notch fa-spin text-3xl text-blue-500 mb-4 block"></i>
            <p class="text-xs font-bold text-white tracking-widest animate-pulse" id="loading-text">DESCARGANDO ARCHIVOS...</p>
            <p class="text-[9px] text-white/40 mt-2">Por favor, no cierres esta pestaña.</p>
        </div>

        <div id="ui-success" class="hidden">
            <i class="fa-solid fa-check-circle text-4xl text-green-400 mb-4 block drop-shadow-[0_0_15px_rgba(74,222,128,0.4)]"></i>
            <p class="text-sm font-bold text-white tracking-widest mb-2">¡INSTALACIÓN EXITOSA!</p>
            <p class="text-[10px] text-white/50 mb-6">Redirigiendo a la aplicación...</p>
        </div>

        <div id="ui-error" class="hidden">
            <i class="fa-solid fa-triangle-exclamation text-4xl text-red-500 mb-4 block drop-shadow-[0_0_15px_rgba(239,68,68,0.4)]"></i>
            <p class="text-xs font-bold text-white tracking-widest mb-2">ERROR DE INSTALACIÓN</p>
            <p class="text-[10px] text-red-400/80 mb-6" id="error-text">No se pudo conectar con el servidor.</p>
            <button onclick="location.reload()" class="w-full bg-white/10 border border-white/20 text-white font-bold text-[10px] tracking-widest py-3 rounded-xl hover:bg-white/20 transition-all">REINTENTAR</button>
        </div>
    </div>

    <script>
        async function iniciarInstalacion() {
            document.getElementById('ui-initial').classList.add('hidden');
            document.getElementById('ui-loading').classList.remove('hidden');

            try {
                let res = await fetch('?action=install');
                let data = await res.json();

                if (data.status === 'success') {
                    document.getElementById('ui-loading').classList.add('hidden');
                    document.getElementById('ui-success').classList.remove('hidden');
                    
                    // Esperar 2 segundos para que el usuario lea "Éxito" y redirigir
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                } else {
                    mostrarError(data.message);
                }
            } catch (error) {
                mostrarError("Fallo de red. Comprueba tu conexión a internet.");
            }
        }

        function mostrarError(msg) {
            document.getElementById('ui-loading').classList.add('hidden');
            document.getElementById('ui-error').classList.remove('hidden');
            document.getElementById('error-text').innerText = msg;
        }
    </script>
</body>
</html>
