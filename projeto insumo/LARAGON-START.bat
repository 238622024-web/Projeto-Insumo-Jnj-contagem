@echo off
setlocal

echo ========================================
echo  Projeto Insumo JNJ - Start Rapido
echo ========================================
echo.
echo 1) Inicie o Laragon e clique em Start All.
echo 2) Este script abre as URLs de setup no navegador.
echo.

start "" "http://localhost/projeto-insumo/database/init_db.php"
start "" "http://localhost/projeto-insumo/"

echo URLs abertas. Se nao funcionar, use a pasta com espaco:
echo http://localhost/projeto%%20insumo/database/init_db.php
echo http://localhost/projeto%%20insumo/
echo.
pause
