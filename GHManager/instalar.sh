#!/bin/bash

# 1. Esperar a que el usuario conceda los permisos de almacenamiento
termux-setup-storage
while ! ls ~/storage/shared >/dev/null 2>&1; do sleep 2; done

# 2. Actualizar sistema e instalar paquetes en modo SILENCIOSO
pkg update -y
pkg upgrade -y -o Dpkg::Options::="--force-confold"
pkg install php git busybox curl -y -o Dpkg::Options::="--force-confold"

# 3. Limpiar rastros viejos y descargar el proyecto fresco
rm -rf ~/PS4Manager
git clone https://github.com/Sebasbms/Ps4.SeBaS.git ~/PS4Manager

# 4. Crear las carpetas públicas en la memoria interna del celular
mkdir -p ~/storage/shared/GoldHenManager/{iconos,payloads,servidor_rpi,backup_icons,cache_biblioteca}

# 5. Borrar carpetas de caché de GitHub y conectarlas con tu memoria interna
rm -rf ~/PS4Manager/{iconos,payloads,servidor_rpi,backup_icons,cache_biblioteca}
ln -s ~/storage/shared/GoldHenManager/iconos ~/PS4Manager/iconos
ln -s ~/storage/shared/GoldHenManager/payloads ~/PS4Manager/payloads
ln -s ~/storage/shared/GoldHenManager/servidor_rpi ~/PS4Manager/servidor_rpi
ln -s ~/storage/shared/GoldHenManager/backup_icons ~/PS4Manager/backup_icons
ln -s ~/storage/shared/GoldHenManager/cache_biblioteca ~/PS4Manager/cache_biblioteca

# 6. CONFIGURAR EL AUTO-ARRANQUE
cat << 'EOF' > ~/.bashrc
clear
# Logo gigante Hacker
echo -e "\e[1;36m"
echo " ██████╗  ██████  ██╗     ██████╗ ██╗  ██╗███████╗███╗   ██╗"
echo "██╔════╝ ██╔═══██╗██║     ██╔══██╗██║  ██║██╔════╝████╗  ██║"
echo "██║  ███╗██║   ██║██║     ██║  ██║███████║█████╗  ██╔██╗ ██║"
echo "██║   ██║██║   ██║██║     ██║  ██║██╔══██║██╔══╝  ██║╚██╗██║"
echo "╚██████╔╝╚██████╔╝███████╗██████╔╝██║  ██║███████╗██║ ╚████║"
echo " ╚═════╝  ╚═════╝ ╚══════╝╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═══╝"
echo -e "\e[1;33m                                              By SeBaS\e[0m"
echo ""

echo -e "\e[1;32m[+] Inicializando terminal segura...\e[0m"
sleep 0.3

echo -e "\e[1;33m[*] Purgando conexiones fantasmas...\e[0m"
killall php >/dev/null 2>&1
killall busybox >/dev/null 2>&1
sleep 0.4

# ENTRAMOS DIRECTAMENTE A LA RAÍZ DEL PROYECTO
cd ~/PS4Manager

echo -e "\e[1;32m[+] Levantando motor RPI Sender (Puerto 8081)...\e[0m"
busybox httpd -p 8081 -h .
sleep 0.3

echo -e "\e[1;32m[+] Arrancando servidor interno PHP (Puerto 8080)...\e[0m"
sleep 0.4

echo -e "\e[1;36m[√] SISTEMA EN LÍNEA. Inyectando interfaz gráfica...\e[0m"

echo -e "\e[0;32m==========================================================\e[0m"
echo -e "\e[0;32m[!] Servidor activo. No cierres esta ventana de Termux.\e[0m"
echo -e "\e[0;32m==========================================================\e[0m"

# Orden oculta para abrir el navegador 2 SEGUNDOS DESPUÉS de encender PHP
(sleep 2 && am start -a android.intent.action.VIEW -d "http://localhost:8080/index.php" >/dev/null 2>&1) &

# Lanzamos PHP de fondo bloqueando la terminal
PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:8080
EOF

# 7. Finalización
clear
echo -e "\e[1;32m¡INSTALACIÓN COMPLETADA CON ÉXITO!\e[0m"
echo -e "La aplicación se abrirá en tu navegador en unos segundos..."
source ~/.bashrc
