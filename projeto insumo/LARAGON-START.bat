@echo off
setlocal EnableDelayedExpansion

echo ========================================
echo  Projeto Insumo JNJ - Start Rapido
echo ========================================
echo.
echo 1) Inicie o Laragon e clique em Start All.
echo 2) Este script tenta abrir o site no Apache.
echo 3) Se o Apache nao responder, ele sobe um servidor PHP local.
echo.

set "PROJECT_DIR=%~dp0"
set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"

set "CANDIDATE_URL_1=http://localhost/Projeto-Insumo-Jnj-contagem/projeto%%20insumo/database/init_db.php"
set "CANDIDATE_URL_2=http://localhost/Projeto-Insumo-Jnj-contagem/projeto%%20insumo/"
set "CANDIDATE_URL_3=http://localhost/projeto%%20insumo/database/init_db.php"
set "CANDIDATE_URL_4=http://localhost/projeto%%20insumo/"

set "APP_URL="

for %%U in (
	"%CANDIDATE_URL_1%"
	"%CANDIDATE_URL_2%"
	"%CANDIDATE_URL_3%"
	"%CANDIDATE_URL_4%"
) do (
	powershell -NoProfile -ExecutionPolicy Bypass -Command "try { $r = Invoke-WebRequest -Uri '%%~U' -Method Head -UseBasicParsing -TimeoutSec 4; if ($r.StatusCode -ge 200 -and $r.StatusCode -lt 400) { exit 0 } else { exit 1 } } catch { exit 1 }"
	if !ERRORLEVEL! EQU 0 (
		set "APP_URL=%%~U"
		goto :openSite
	)
)

set "PHP_EXE="
for %%P in (
	"%ProgramFiles%\Laragon\bin\php\php.exe"
	"%ProgramFiles(x86)%\Laragon\bin\php\php.exe"
	"C:\laragon\bin\php\php.exe"
	"C:\xampp\php\php.exe"
) do (
	if exist "%%~P" (
		set "PHP_EXE=%%~P"
		goto :startLocalServer
	)
)

for /f "delims=" %%P in ('dir /b /s "C:\laragon\bin\php\*\php.exe" 2^>nul') do (
	set "PHP_EXE=%%P"
	goto :startLocalServer
)

for /f "delims=" %%P in ('dir /b /s "C:\xampp\php\php.exe" 2^>nul') do (
	set "PHP_EXE=%%P"
	goto :startLocalServer
)

for /f "delims=" %%P in ('where php 2^>nul') do (
	set "PHP_EXE=%%P"
	goto :startLocalServer
)

echo Nenhuma URL respondeu e nao encontrei php.exe para fallback local.
echo Abra manualmente a pasta do projeto ou instale o PHP/Laragon corretamente.
pause
exit /b 1

:startLocalServer
echo Apache nao respondeu. Iniciando servidor PHP local em http://localhost:8000/ ...
start "" "%PHP_EXE%" -S localhost:8000 -t "%PROJECT_DIR%"
timeout /t 2 /nobreak >nul
set "APP_URL=http://localhost:8000/solicitacoes.php"

:openSite
start "" "%APP_URL%"

echo URLs abertas. Se nao funcionar, use a pasta com espaco:
echo %CANDIDATE_URL_1%
echo %CANDIDATE_URL_2%
echo Servidor local: http://localhost:8000/solicitacoes.php
echo.
pause
