<style>
    /* Estilos base de las capas */
    .bg-layer { position: absolute; inset: 0; display: none; z-index: -2; overflow: hidden; } 
    .bg-layer.active { display: block; animation: fadeInBg 0.8s ease-in-out; }
    .bg-layer canvas { display: block; width: 100%; height: 100%; }
    @keyframes fadeInBg { from { opacity: 0; } to { opacity: 1; } }

    /* ====== PEGA AQUÍ LOS ESTILOS CSS DE TUS FONDOS ====== */
    /* (Copia desde tu wallpaper.html los estilos de .ps4-symbols, .ps2-bg, .fiber, etc.) */
    
</style>

<div id="dynamic-wallpapers-container" class="fixed inset-0 z-[-2] pointer-events-none">
    
    </div>

<script>
    let currentAnimFrame = null;

    function stopAllCanvas() {
        if (currentAnimFrame) { 
            cancelAnimationFrame(currentAnimFrame); 
            currentAnimFrame = null; 
        }
    }

    /* ====== PEGA AQUÍ TODAS TUS FUNCIONES JS DE CANVAS ====== */
    /* (Copia startMatrix(), initPS5Dust(), startStarfield(), etc. desde tu wallpaper.html) */


    /* =========================================
       CONTROLADOR PARA INDEX.PHP
       ========================================= */
    function changeDynamicWallpaper(bgId) {
        stopAllCanvas();
        document.querySelectorAll('.bg-layer').forEach(layer => layer.classList.remove('active'));

        // Guardar la elección
        localStorage.setItem('ps4_dynamic_bg', bgId);

        if (bgId === 'none') {
            // Si elige "none", mostramos el fondo de imagen normal del index
            document.getElementById('dynamic-bg').style.display = 'block';
            return;
        }

        // Ocultar el fondo de imagen normal para mostrar la animación
        const normalBg = document.getElementById('dynamic-bg');
        if(normalBg) normalBg.style.display = 'none';

        const layer = document.getElementById(bgId);
        if (layer) layer.classList.add('active');

        // Disparadores (Añade todos los que tenías en tu setBG)
        if(bgId === 'bg-ps5') initPS5Dust('ps5-dust-container', '#22d3ee');
        if(bgId === 'bg-ps5-gold') initPS5Dust('ps5-gold-container', '#ffb400');
        if(bgId === 'bg-ps4') initPS4Shapes();
        if(bgId === 'bg-ps2') initPS2Cubes();
        if(bgId === 'bg-fiber') initFiber();
        if(bgId === 'bg-matrix') startMatrix();
        if(bgId === 'bg-binary') startBinary();
        if(bgId === 'bg-starfield') startStarfield(); 
        if(bgId === 'bg-warp') startWarp(); 
        if(bgId === 'bg-network') startNetwork();
        // ... (agrega los demás if que tenías)
    }

    // Auto-Cargar el fondo guardado al iniciar
    window.addEventListener('load', () => {
        const savedBg = localStorage.getItem('ps4_dynamic_bg') || 'none';
        
        // Sincronizar el selector de Ajustes si existe
        const selector = document.getElementById('select-wallpaper');
        if(selector) selector.value = savedBg;

        changeDynamicWallpaper(savedBg);
    });
</script>
