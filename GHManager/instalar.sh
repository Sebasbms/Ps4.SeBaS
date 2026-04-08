#!/bin/bash

# 1. Esperar permisos de almacenamiento (Parche Inteligente)
if [ ! -d "$HOME/storage" ]; then
    termux-setup-storage
    while ! ls ~/storage/shared >/dev/null 2>&1; do sleep 2; done
fi

# 2. Instalar paquetes esenciales
pkg update -y
pkg upgrade -y -o Dpkg::Options::="--force-confold"
pkg install php git busybox curl -y -o Dpkg::Options::="--force-confold"

# 3. Limpiar y descargar la app
rm -rf ~/PS4Manager
git clone https://github.com/Sebasbms/Ps4.SeBaS.git ~/PS4Manager

# 4. Crear carpetas públicas en el celular
mkdir -p ~/storage/shared/GoldHenManager/iconos
mkdir -p ~/storage/shared/GoldHenManager/payloads
mkdir -p ~/storage/shared/GoldHenManager/servidor_rpi
mkdir -p ~/storage/shared/GoldHenManager/backup_icons
mkdir -p ~/storage/shared/GoldHenManager/cache_biblioteca

# 5. Borrar carpetas locales y crear los túneles seguros
cd ~/PS4Manager/GHManager
rm -rf iconos payloads servidor_rpi backup_icons cache_biblioteca

ln -s ~/storage/shared/GoldHenManager/iconos .
ln -s ~/storage/shared/GoldHenManager/payloads .
ln -s ~/storage/shared/GoldHenManager/servidor_rpi .
ln -s ~/storage/shared/GoldHenManager/backup_icons .
ln -s ~/storage/shared/GoldHenManager/cache_biblioteca .
mkdir -p rpi_cache

# 6. Configurar el Auto-Arranque a prueba de fallos
touch ~/.hushlogin
cat << 'EOF' > ~/.bashrc
clear
echo -e "\e[1;36m"
echo " ██████╗  ██████  ██╗     ██████╗ ██╗  ██╗███████╗███╗   ██╗"
echo "██╔════╝ ██╔═══██╗██║     ██╔══██╗██║  ██║██╔════╝████╗  ██║"
echo "██║  ███╗██║   ██║██║     ██║  ██║███████║█████╗  ██╔██╗ ██║"
echo "██║   ██║██║   ██║██║     ██║  ██║██╔══██║██╔══╝  ██║╚██╗██║"
echo "╚██████╔╝╚██████╔╝███████╗██████╔╝██║  ██║███████╗██║ ╚████║"
echo " ╚═════╝  ╚═════╝ ╚══════╝╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═══╝"
echo -e "\e[1;32m         ▶ MANAGER V2.1 | DEVELOPED BY SEBAS ◀\e[0m"
echo ""

cd ~/PS4Manager/GHManager
git reset --hard >/dev/null 2>&1
git pull >/dev/null 2>&1

echo -e "\e[1;33m[*] \e[1;37mIniciando Servidores..."
# Asesinamos cualquier proceso fantasma con -9 (Forzado)
killall -9 php busybox >/dev/null 2>&1
sleep 0.5

# Encendemos Busybox
busybox httpd -p 8081 -h ~/PS4Manager/GHManager

# Orden oculta: Esperar 2 segundos y abrir navegador
(sleep 2 && am start -a android.intent.action.VIEW -d "http://localhost:8080/index.php" >/dev/null 2>&1) &

# CANDADO DE SEGURIDAD: Obligamos a PHP a arrancar en la carpeta correcta
PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:8080 -t ~/PS4Manager/GHManager
EOF

# 7. Finalización
clear
echo -e "\e[1;32m¡INSTALACIÓN COMPLETADA CON ÉXITO!\e[0m"
echo -e "La aplicación se abrirá en tu navegador en unos segundos..."
source ~/.bashrc