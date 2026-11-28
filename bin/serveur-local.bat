@echo off
rem Serveur de développement local du site Taysir (WAMP, PHP 8.4).
rem Double-cliquer sur ce fichier, puis ouvrir http://127.0.0.1:8080 dans le navigateur.
rem Laisser cette fenêtre ouverte tant que vous naviguez ; la fermer (ou Ctrl+C) arrête le serveur.
rem MySQL doit tourner : lancer WAMP avant (icône verte).

cd /d "%~dp0.."
echo Site Taysir : http://127.0.0.1:8080
echo Fermez cette fenetre pour arreter le serveur.
"C:\wamp64\bin\php\php8.4.15\php.exe" -n ^
  -d extension_dir=C:/wamp64/bin/php/php8.4.15/ext ^
  -d extension=pdo_mysql -d extension=mbstring -d extension=openssl ^
  -d extension=fileinfo -d extension=curl -d extension=zip ^
  -d upload_max_filesize=6M -d post_max_size=20M -d output_buffering=0 ^
  -S 127.0.0.1:8080 -t public
pause
