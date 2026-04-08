<?php
/**
 * COMPILADOR AUTOMÁTICO DE MÓDULOS GOLDHEN
 * Extrae el código intacto de los HTML del usuario y los convierte en módulos PHP.
 */
@mkdir('modulos', 0777, true);

function limpiar_html($html) {
    $html = preg_replace('/<!DOCTYPE.*?>/si', '', $html);
    $html = preg_replace('/<\/?html.*?>/si', '', $html);
    $html = preg_replace('/<\/?head.*?>/si', '', $html);
    $html = preg_replace('/<meta.*?>/si', '', $html);
    $html = preg_replace('/<title>.*?<\/title>/si', '', $html);
    $html = preg_replace('/<\/?body.*?>/si', '', $html);
    $html = preg_replace('/body\s*{.*?}/is', '', $html); // Quita el fondo negro base
    return $html;
}

// =========================================
// 1. COMPILAR INTROS (12)
// =========================================
if (file_exists('Intros.html')) {
    $html = limpiar_html(file_get_contents('Intros.html'));
    $html .= "
    <style> #menu-intros, .grid-menu { display: none !important; } </style>
    <script>
        // AUTO-DETECCIÓN DE INTROS PARA EL MENÚ
        window.addEventListener('DOMContentLoaded', () => {
            const selectIntro = document.getElementById('select-intro');
            if(selectIntro) {
                selectIntro.innerHTML = '<option value=\"none\">Sin Intro (Arranque Rápido)</option>';
                document.querySelectorAll('.intro-container, [id^=\"intro-\"]').forEach(el => {
                    if(el.id && el.id !== 'intro-wrapper' && el.id !== 'intro-screen') {
                        let name = el.id.replace('intro-', '').replace(/-/g, ' ').toUpperCase();
                        let opt = document.createElement('option');
                        opt.value = el.id; opt.textContent = name;
                        selectIntro.appendChild(opt);
                    }
                });
                selectIntro.value = localStorage.getItem('ps4_selected_intro') || 'none';
            }
        });

        function bootSelectedIntro() {
            const savedIntro = localStorage.getItem('ps4_selected_intro') || 'none';
            const wrap = document.getElementById('intro-wrapper');
            if (savedIntro === 'none') { if (wrap) wrap.style.display = 'none'; return; }
            
            if(typeof playIntro === 'function') {
                try {
                    playIntro(savedIntro);
                    setTimeout(() => {
                        if (wrap) {
                            wrap.style.opacity = '0';
                            wrap.style.transition = 'opacity 0.5s';
                            setTimeout(() => wrap.style.display = 'none', 500);
                        }
                    }, 6000);
                } catch(e) { if(wrap) wrap.style.display = 'none'; }
            } else { if(wrap) wrap.style.display = 'none'; }
        }
    </script>";
    file_put_contents('modulos/intros.php', $html);
    echo "✅ modulos/intros.php creado (12 intros intactos).\n";
} else { echo "❌ ERROR: No se encontró Intros.html\n"; }

// =========================================
// 2. COMPILAR WALLPAPERS
// =========================================
if (file_exists('wallpaper.html')) {
    $html = limpiar_html(file_get_contents('wallpaper.html'));
    $html .= "
    <style> .btn-bg, #menu-wallpapers, .glass-panel, #ui-container { display: none !important; } </style>
    <script>
        // AUTO-DETECCIÓN DE FONDOS PARA EL MENÚ
        window.addEventListener('DOMContentLoaded', () => {
            const selectWp = document.getElementById('select-wallpaper');
            if(selectWp) {
                selectWp.innerHTML = '<option value=\"none\">Fondo Estático de Imagen</option>';
                document.querySelectorAll('.bg-layer').forEach(el => {
                    if(el.id) {
                        let name = el.id.replace('bg-', '').replace(/-/g, ' ').toUpperCase();
                        let opt = document.createElement('option');
                        opt.value = el.id; opt.textContent = name;
                        selectWp.appendChild(opt);
                    }
                });
                selectWp.value = localStorage.getItem('ps4_dynamic_bg') || 'none';
            }
        });

        function changeDynamicWallpaper(bgId) {
            if (bgId === 'none') {
                if(typeof stopAllCanvas === 'function') stopAllCanvas();
                document.querySelectorAll('.bg-layer').forEach(layer => layer.classList.remove('active'));
                const normalBg = document.getElementById('dynamic-bg');
                if(normalBg) normalBg.style.display = 'block';
                return;
            }
            
            const normalBg = document.getElementById('dynamic-bg');
            if(normalBg) normalBg.style.display = 'none';

            if(typeof setBG === 'function') {
                try {
                    const fakeBtn = document.createElement('button');
                    setBG(bgId, fakeBtn);
                } catch(e) { console.error('Error wallpaper:', e); }
            }
        }
    </script>";
    file_put_contents('modulos/wallpapers.php', $html);
    echo "✅ modulos/wallpapers.php creado (Todos los fondos intactos).\n";
} else { echo "❌ ERROR: No se encontró wallpaper.html\n"; }
?>
