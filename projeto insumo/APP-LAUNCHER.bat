@echo off
setlocal EnableDelayedExpansion

set "XAMPP_DIR=C:\xampp"
set "XAMPP_START=%XAMPP_DIR%\xampp_start.exe"

if exist "%XAMPP_START%" (
  start "" "%XAMPP_START%"
)

set "SCRIPT_DIR=%~dp0"
set "SCRIPT_DIR=!SCRIPT_DIR:~0,-1!"
set "ROOT=\xampp\htdocs\"
set "REL_PATH=!SCRIPT_DIR:*%ROOT%=!"

if /I "!REL_PATH!"=="!SCRIPT_DIR!" (
  set "REL_PATH=projeto insumo"
)

set "REL_PATH=!REL_PATH:\=/!"
set "URL_PATH=!REL_PATH: =%%20!"
set "APP_URL=http://localhost/!URL_PATH!/"

timeout /t 4 /nobreak >nul

if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" (
  start "" "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" --app="!APP_URL!"
  exit /b 0
)

if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" (
  start "" "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" --app="!APP_URL!"
  exit /b 0
)

if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" (
  start "" "%ProgramFiles%\Google\Chrome\Application\chrome.exe" --app="!APP_URL!"
  exit /b 0
)

if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" (
  start "" "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" --app="!APP_URL!"
  exit /b 0
)

start "" "!APP_URL!"
