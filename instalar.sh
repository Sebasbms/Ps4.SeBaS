 
#!/bin/bash

# 1. Esperar a que el usuario conceda los permisos de almacenamiento
while ! ls ~/storage/shared >/dev/null 2>&1; do sleep 2; done

# 2. Reparar repositorios e instalar PHP y Git
echo "deb https://packages.termux.dev/apt/termux-main stable main" > $PREFIX/etc/apt/sources.list
pkg update -y
pkg install php git -y

# 3. Limpiar rastros viejos y descargar la última versión de tu app
rm -rf ~/PS4Manager
git clone https://github.com/Sebasbms/Ps4.SeBaS.git ~/PS4Manager

# 4. Crear las carpetas públicas en la memoria interna del celular
mkdir -p ~/storage/shared/GoldHenManager/{iconos,payloads,servidor_rpi,backup_icons,cache_biblioteca}

# 5. Borrar carpetas locales del repo y crear los túneles (Symlinks)
rm -rf ~/PS4Manager/GHManager/{iconos,payloads,servidor_rpi,backup_icons,cache_biblioteca}
ln -s ~/storage/shared/GoldHenManager/iconos ~/PS4Manager/GHManager/iconos
ln -s ~/storage/shared/GoldHenManager/payloads ~/PS4Manager/GHManager/payloads
ln -s ~/storage/shared/GoldHenManager/servidor_rpi ~/PS4Manager/GHManager/servidor_rpi
ln -s ~/storage/shared/GoldHenManager/backup_icons ~/PS4Manager/GHManager/backup_icons
ln -s ~/storage/shared/GoldHenManager/cache_biblioteca ~/PS4Manager/GHManager/cache_biblioteca
mkdir -p ~/PS4Manager/GHManager/rpi_cache

# 6. Configurar el Auto-Arranque para el futuro (El cartel ASCII)
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
echo -e "\e[1;34m============================================================\e[0m"
echo -e "\e[1;33m[*] \e[1;37mIniciando Servidor PHP..."
echo -e "\e[1;33m[*] \e[1;37mAbriendo GoldHen Manager..."
echo -e "\e[1;34m============================================================\e[0m"
echo ""
cd ~/PS4Manager/GHManager
am start -a android.intent.action.VIEW -d "http://localhost:8080/index.php" >/dev/null 2>&1
php -S 0.0.0.0:8080
EOF

# 7. Ejecutar la app por primera vez tras la instalación
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
echo -e "\e[1;34m============================================================\e[0m"
echo -e "\e[1;33m[*] \e[1;37mInstalación Completada con Éxito."
echo -e "\e[1;33m[*] \e[1;37mAbriendo GoldHen Manager..."
echo -e "\e[1;34m============================================================\e[0m"
echo ""
cd ~/PS4Manager/GHManager
am start -a android.intent.action.VIEW -d "http://localhost:8080/index.php" >/dev/null 2>&1
php -S 0.0.0.0:8080
