@echo off
setlocal

cd /d "%~dp0"

if not exist node_modules (
  echo Instalando dependencias...
  call npm install
)

echo Iniciando API Node em http://localhost:3000
call npm run dev
