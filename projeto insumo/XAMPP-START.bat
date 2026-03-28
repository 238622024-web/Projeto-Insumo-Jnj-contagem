@echo off
setlocal EnableDelayedExpansion

echo ========================================
echo  Projeto Insumo JNJ - Start com XAMPP
echo ========================================
echo.

set "XAMPP_DIR=C:\xampp"
set "XAMPP_START=%XAMPP_DIR%\xampp_start.exe"
set "XAMPP_CONTROL=%XAMPP_DIR%\xampp-control.exe"

if exist "%XAMPP_START%" (
  echo Iniciando Apache e MySQL pelo XAMPP...
  start "" "%XAMPP_START%"
) else (
  echo Aviso: nao encontrei xampp_start.exe em %XAMPP_DIR%.
)

if exist "%XAMPP_CONTROL%" (
  echo Abrindo painel do XAMPP...
  start "" "%XAMPP_CONTROL%"
)

echo Aguarde alguns segundos para os servicos subirem...
timeout /t 5 /nobreak >nul

set "SCRIPT_DIR=%~dp0"
set "SCRIPT_DIR=!SCRIPT_DIR:~0,-1!"
set "ROOT=\xampp\htdocs\"
set "REL_PATH=!SCRIPT_DIR:*%ROOT%=!"

if /I "!REL_PATH!"=="!SCRIPT_DIR!" (
  set "REL_PATH=projeto insumo"
)

set "REL_PATH=!REL_PATH:\=/!"
set "URL_PATH=!REL_PATH: =%%20!"

set "BASE_URL=http://localhost/!URL_PATH!"

echo Abrindo inicializacao do banco...
start "" "!BASE_URL!/database/init_db.php"

echo Abrindo sistema...
start "" "!BASE_URL!/"

echo.
echo URLs abertas:
echo !BASE_URL!/database/init_db.php
echo !BASE_URL!/
echo.
echo Se Apache/MySQL nao iniciarem, inicie manualmente no painel XAMPP.
pause
