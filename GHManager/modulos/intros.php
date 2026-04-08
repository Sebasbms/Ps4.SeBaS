<style>
    /* Estilos base de las intros */
    .intro-container { position: fixed; inset: 0; background: #05050a; z-index: 9999; display: none; align-items: center; justify-content: center; flex-direction: column; overflow: hidden; opacity: 0; transition: opacity 0.5s ease; }
    .intro-container.active { display: flex; opacity: 1; }
    
    /* Intro 1: PS5 Style */
    .ps5-logo-container { position: relative; z-index: 10; animation: ps5-float 6s ease-in-out infinite; }
    @keyframes ps5-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }
    .ps5-bg-glow { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(0, 112, 204, 0.2) 0%, transparent 70%); top: 50%; left: 50%; transform: translate(-50%, -50%); animation: ps5-pulse 4s ease-in-out infinite; }
    @keyframes ps5-pulse { 0%, 100% { opacity: 0.5; transform: translate(-50%, -50%) scale(1); } 50% { opacity: 1; transform: translate(-50%, -50%) scale(1.2); } }
    .particle { position: absolute; background: white; border-radius: 50%; opacity: 0; }

    /* Intro 2: PS2 Nostalgia */
    .ps2-bg { position: absolute; inset: 0; background: radial-gradient(circle at center, #000033 0%, #000 100%); }
    .ps2-cube { position: absolute; border: 1px solid rgba(0, 255, 255, 0.3); background: rgba(0, 50, 255, 0.1); transform-style: preserve-3d; animation: ps2-spin linear infinite; }
    @keyframes ps2-spin { 0% { transform: rotateX(0deg) rotateY(0deg); } 100% { transform: rotateX(360deg) rotateY(360deg); } }
    .ps2-text { font-family: 'Times New Roman', serif; color: #fff; font-size: 3rem; letter-spacing: 5px; text-shadow: 0 0 10px rgba(255,255,255,0.8); z-index: 10; opacity: 0; animation: ps2-fadein 2s forwards 1s; }
    @keyframes ps2-fadein { to { opacity: 1; } }

    /* Intro 3: Cyber Glitch */
    .glitch-wrapper { position: relative; font-family: 'Press Start 2P', monospace; font-size: 2rem; color: white; text-transform: uppercase; }
    .glitch-text::before, .glitch-text::after { content: attr(data-text); position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0.8; }
    .glitch-text::before { left: 2px; text-shadow: -2px 0 red; clip: rect(44px, 450px, 56px, 0); animation: glitch-anim 5s infinite linear alternate-reverse; }
    .glitch-text::after { left: -2px; text-shadow: -2px 0 blue; clip: rect(44px, 450px, 56px, 0); animation: glitch-anim2 5s infinite linear alternate-reverse; }
    @keyframes glitch-anim { 0% { clip: rect(10px, 9999px, 31px, 0); } 20% { clip: rect(62px, 9999px, 12px, 0); } 40% { clip: rect(34px, 9999px, 81px, 0); } 60% { clip: rect(81px, 9999px, 2px, 0); } 80% { clip: rect(21px, 9999px, 51px, 0); } 100% { clip: rect(5px, 9999px, 91px, 0); } }
    @keyframes glitch-anim2 { 0% { clip: rect(65px, 9999px, 100px, 0); } 20% { clip: rect(3px, 9999px, 20px, 0); } 40% { clip: rect(85px, 9999px, 40px, 0); } 60% { clip: rect(20px, 9999px, 80px, 0); } 80% { clip: rect(40px, 9999px, 60px, 0); } 100% { clip: rect(90px, 9999px, 10px, 0); } }

    /* Intro 4: Terminal Boot */
    .terminal-text { font-family: 'Share Tech Mono', monospace; color: #0f0; text-align: left; width: 80%; max-width: 600px; font-size: 0.9rem; line-height: 1.5; text-shadow: 0 0 5px #0f0; }
    .cursor-blink { animation: blink 1s step-end infinite; }
    @keyframes blink { 50% { opacity: 0; } }

    /* Intro 5: System Decrypt */
    .decrypt-text { font-family: 'Share Tech Mono', monospace; color: #0ff; font-size: 2.5rem; letter-spacing: 8px; text-shadow: 0 0 10px #0ff; }

    /* Intro 6: Matrix Rain */
    #matrix-canvas { position: absolute; inset: 0; z-index: 1; }
    .matrix-logo { position: relative; z-index: 10; font-family: 'Share Tech Mono', monospace; color: #fff; font-size: 3rem; text-shadow: 0 0 20px #0f0; background: rgba(0,0,0,0.7); padding: 20px 40px; border-radius: 10px; border: 1px solid #0f0; }

    /* Intro 7: CRT Retro */
    .crt-screen { position: absolute; inset: 0; background: #111; z-index: 1; overflow: hidden; display: flex; align-items: center; justify-content: center; flex-direction: column; }
    .crt-scanline { position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,0) 50%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0.2)); background-size: 100% 4px; z-index: 10; pointer-events: none; }
    .crt-flicker { animation: crt-flicker 0.15s infinite; }
    @keyframes crt-flicker { 0% { opacity: 0.95; } 100% { opacity: 1; } }
    .crt-text { color: #3f3; font-family: 'Press Start 2P', monospace; font-size: 1.5rem; text-shadow: 0 0 10px #3f3; }

    /* Intro 8: Security Breach */
    .breach-container { text-align: center; color: red; font-family: 'Share Tech Mono', monospace; z-index: 10; }
    .breach-icon { font-size: 5rem; text-shadow: 0 0 20px red; margin-bottom: 20px; }
</style>

<div id="intro-ps5" class="intro-container">
    <div class="ps5-bg-glow"></div>
    <div id="ps5-particles" class="absolute inset-0 z-0"></div>
    <div class="ps5-logo-container flex flex-col items-center">
        <i class="fa-brands fa-playstation text-7xl text-white drop-shadow-[0_0_15px_rgba(255,255,255,0.8)] mb-4"></i>
        <h1 class="text-3xl font-black tracking-[0.3em] text-white">GOLDHEN</h1>
        <p class="text-[10px] tracking-[0.5em] text-blue-400 mt-2">SYSTEM INITIALIZED</p>
    </div>
</div>

<div id="intro-ps2" class="intro-container">
    <div class="ps2-bg"></div>
    <div id="ps2-cubes" class="absolute inset-0 z-0"></div>
    <div class="ps2-text">Sony Computer Entertainment</div>
</div>

<div id="intro-glitch" class="intro-container">
    <div class="glitch-wrapper">
        <div class="glitch-text font-black" data-text="GOLDHEN MANAGER">GOLDHEN MANAGER</div>
    </div>
    <p class="text-white mt-4 font-mono tracking-widest text-xs opacity-70">EXPLOIT ACTIVE // v2.1</p>
</div>

<div id="intro-terminal" class="intro-container">
    <div id="terminal-content" class="terminal-text"></div>
</div>

<div id="intro-decrypt" class="intro-container">
    <i class="fa-solid fa-shield-halved text-[#0ff] text-6xl mb-6 drop-shadow-[0_0_15px_#0ff]"></i>
    <div id="decrypt-target" class="decrypt-text" data-value="GOLDHEN MANAGER">XXXXXXXXXXXXXXX</div>
    <p class="text-[#0ff] font-mono mt-4 text-sm tracking-widest opacity-80">DECRYPTING SYSTEM...</p>
</div>

<div id="intro-matrix-rain" class="intro-container">
    <canvas id="matrix-canvas"></canvas>
    <div class="matrix-logo">GOLDHEN <span class="text-xs block mt-2 tracking-[0.5em] opacity-80">SYSTEM WAKEUP</span></div>
</div>

<div id="intro-crt" class="intro-container">
    <div class="crt-screen crt-flicker">
        <div class="crt-scanline"></div>
        <i class="fa-solid fa-microchip crt-text text-6xl mb-6 block"></i>
        <div id="crt-type-text" class="crt-text"></div>
    </div>
</div>

<div id="intro-breach" class="intro-container">
    <div class="breach-container">
        <i id="breach-icon" class="fa-solid fa-lock breach-icon"></i>
        <h1 id="breach-text" class="text-4xl font-black tracking-widest">SYSTEM LOCKED</h1>
        <div class="w-64 h-2 bg-red-900/50 mt-6 rounded overflow-hidden mx-auto">
            <div id="breach-bar" class="h-full bg-red-500 w-0 transition-all duration-[2000ms] ease-linear"></div>
        </div>
    </div>
</div>

<script>
    /* =========================================
       LÓGICA DE EJECUCIÓN DE INTROS
       ========================================= */
    let currentIntroInterval = null;

    function generatePS5Particles() {
        const container = document.getElementById('ps5-particles');
        container.innerHTML = '';
        for(let i=0; i<50; i++) {
            let p = document.createElement('div');
            p.className = 'particle';
            let size = Math.random() * 4 + 1;
            p.style.width = size + 'px'; p.style.height = size + 'px';
            p.style.left = Math.random() * 100 + '%'; p.style.top = Math.random() * 100 + '%';
            p.style.animation = `ps5-pulse ${Math.random()*3+2}s infinite alternate`;
            p.style.animationDelay = Math.random() * 2 + 's';
            container.appendChild(p);
        }
    }

    function generatePS2Cubes() {
        const container = document.getElementById('ps2-cubes');
        container.innerHTML = '';
        for(let i=0; i<15; i++) {
            let cube = document.createElement('div');
            cube.className = 'ps2-cube';
            let size = Math.random() * 100 + 50;
            cube.style.width = size + 'px'; cube.style.height = size + 'px';
            cube.style.left = Math.random() * 100 + '%'; cube.style.top = Math.random() * 100 + '%';
            cube.style.animationDuration = (Math.random() * 10 + 5) + 's';
            container.appendChild(cube);
        }
    }

    function typeTerminal() {
        const lines = [ "root@ps4:~# ./goldhen_loader.sh", "[*] Injecting payload...", "[*] Bypassing kernel protection...", "[*] Escalating privileges...", "[+] Success! Payload injected.", "[*] Starting GoldHen Manager v2.1...", "DONE." ];
        const container = document.getElementById('terminal-content');
        container.innerHTML = '';
        let lineIdx = 0;
        
        currentIntroInterval = setInterval(() => {
            if(lineIdx < lines.length) {
                container.innerHTML += lines[lineIdx] + "<br>";
                lineIdx++;
            } else {
                container.innerHTML += '<span class="cursor-blink">_</span>';
                clearInterval(currentIntroInterval);
            }
        }, 500);
    }

    function runDecryptEffect() {
        const target = document.getElementById('decrypt-target');
        const finalWord = target.getAttribute('data-value');
        const letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$%&*";
        let iterations = 0;
        
        currentIntroInterval = setInterval(() => {
            target.innerText = finalWord.split("").map((letter, index) => {
                if(index < iterations) return finalWord[index];
                return letters[Math.floor(Math.random() * letters.length)];
            }).join("");
            if(iterations >= finalWord.length) clearInterval(currentIntroInterval);
            iterations += 1 / 3;
        }, 30);
    }

    function initMatrix() {
        const canvas = document.getElementById('matrix-canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth; canvas.height = window.innerHeight;
        const letters = '01ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const fontSize = 16; const columns = canvas.width / fontSize;
        const drops = [];
        for(let x = 0; x < columns; x++) drops[x] = 1;
        
        currentIntroInterval = setInterval(() => {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#0f0'; ctx.font = fontSize + 'px monospace';
            for(let i = 0; i < drops.length; i++) {
                const text = letters.charAt(Math.floor(Math.random() * letters.length));
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                if(drops[i] * fontSize > canvas.height && Math.random() > 0.975) drops[i] = 0;
                drops[i]++;
            }
        }, 33);
    }

    function runCRTScript() {
        const text = "LOADING ROM...\\nOS VER: 11.00\\nMEMORY: OK\\nBOOTING GOLDHEN...";
        const el = document.getElementById('crt-type-text');
        el.innerHTML = '';
        let i = 0;
        currentIntroInterval = setInterval(() => {
            if(i < text.length) {
                if(text.charAt(i) === '\\' && text.charAt(i+1) === 'n') { el.innerHTML += '<br>'; i+=2; } 
                else { el.innerHTML += text.charAt(i); i++; }
            } else {
                clearInterval(currentIntroInterval);
            }
        }, 50);
    }

    function runBreach() {
        setTimeout(() => document.getElementById('breach-bar').style.width = '100%', 100);
        setTimeout(() => {
            document.getElementById('breach-icon').className = "fa-solid fa-unlock text-7xl mb-5 block text-green-500 drop-shadow-[0_0_20px_#0f0]";
            let textEl = document.getElementById('breach-text');
            textEl.innerText = "ACCESS GRANTED"; textEl.classList.replace('text-red-600', 'text-green-500');
        }, 2100);
    }

    // EL MOTOR QUE ARRANCA LA INTRO ELEGIDA
    function bootSelectedIntro() {
        const savedIntro = localStorage.getItem('ps4_selected_intro') || 'intro-ps5'; // PS5 por defecto
        
        if (savedIntro === 'none') {
            // Si eligió "Sin Intro", mostramos la app directo
            document.getElementById('intro-wrapper').style.display = 'none';
            return;
        }

        const introEl = document.getElementById(savedIntro);
        if(introEl) {
            introEl.classList.add('active');
            
            // Disparar las animaciones correspondientes
            if (savedIntro === 'intro-ps5') generatePS5Particles();
            if (savedIntro === 'intro-ps2') generatePS2Cubes();
            if (savedIntro === 'intro-glitch') { /* CSS based */ }
            if (savedIntro === 'intro-terminal') typeTerminal();
            if (savedIntro === 'intro-decrypt') runDecryptEffect();
            if (savedIntro === 'intro-matrix-rain') initMatrix();
            if (savedIntro === 'intro-crt') runCRTScript();
            if (savedIntro === 'intro-breach') runBreach();

            // Desaparecer la intro después de 5 segundos
            setTimeout(() => {
                introEl.style.opacity = '0';
                setTimeout(() => {
                    document.getElementById('intro-wrapper').style.display = 'none';
                    if(currentIntroInterval) clearInterval(currentIntroInterval);
                }, 500);
            }, 5000);
        } else {
            document.getElementById('intro-wrapper').style.display = 'none';
        }
    }

    // Iniciar automáticamente
    window.addEventListener('load', bootSelectedIntro);
</script>