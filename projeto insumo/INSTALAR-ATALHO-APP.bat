@echo off
setlocal EnableDelayedExpansion

if /I "%~1"=="--launch" goto :launchMode
goto :installMode

:installMode
set "SELF=%~f0"
set "SCRIPT_DIR=%~dp0"
set "ICON_ICO=%SCRIPT_DIR%logo_manserv.ico"
set "ICON_PNG=%SCRIPT_DIR%logo_manserv.png"
set "ICON_FALLBACK=C:\xampp\xampp-control.exe"
powershell -NoProfile -ExecutionPolicy Bypass -Command "$WshShell = New-Object -ComObject WScript.Shell; $Desktop = [Environment]::GetFolderPath('Desktop'); $old = Join-Path $Desktop 'Projeto Insumo JNJ.lnk'; if (Test-Path $old) { Remove-Item $old -Force -ErrorAction SilentlyContinue }; $Shortcut = $WshShell.CreateShortcut($Desktop + '\MANSERV.lnk'); $Shortcut.TargetPath = '%SELF%'; $Shortcut.Arguments = '--launch'; $Shortcut.WorkingDirectory = '%SCRIPT_DIR%'; if (Test-Path '%ICON_ICO%') { $Shortcut.IconLocation = '%ICON_ICO%' } elseif (Test-Path '%ICON_PNG%') { $Shortcut.IconLocation = '%ICON_PNG%' } elseif (Test-Path '%ICON_FALLBACK%') { $Shortcut.IconLocation = '%ICON_FALLBACK%,0' }; $Shortcut.Save()"

if %ERRORLEVEL% NEQ 0 (
  echo Falha ao criar atalho na Area de Trabalho.
  pause
  exit /b 1
)

echo ========================================
echo Atalho criado com sucesso:
echo Area de Trabalho\MANSERV
echo ========================================
echo.
echo Deseja abrir agora? (S/N)
set /p OPEN_NOW=
if /I "%OPEN_NOW%"=="S" start "" "%SELF%" --launch
exit /b 0

:launchMode
set "XAMPP_DIR=C:\xampp"
set "XAMPP_START=%XAMPP_DIR%\xampp_start.exe"
set "APP_URL="

if exist "%XAMPP_START%" (
  start "" "%XAMPP_START%"
)

timeout /t 4 /nobreak >nul

for %%U in (
  "http://localhost/Projeto-Insumo-Jnj-contagem/projeto%%20insumo/login.php"
  "http://localhost/Projeto-Insumo-Jnj-contagem/projeto%%20insumo/"
  "http://localhost/Projeto-Insumo-Jnj-contagem/projeto-insumo/login.php"
  "http://localhost/Projeto-Insumo-Jnj-contagem/projeto-insumo/"
  "http://localhost/projeto%%20insumo/login.php"
  "http://localhost/projeto%%20insumo/"
  "http://localhost/projeto-insumo/login.php"
  "http://localhost/projeto-insumo/"
  "http://localhost/"
) do (
  powershell -NoProfile -ExecutionPolicy Bypass -Command "try { $r = Invoke-WebRequest -Uri '%%~U' -Method Head -UseBasicParsing -TimeoutSec 4; if ($r.StatusCode -ge 200 -and $r.StatusCode -lt 500) { exit 0 } else { exit 1 } } catch { exit 1 }"
  if !ERRORLEVEL! EQU 0 (
    set "APP_URL=%%~U"
    goto :openBrowser
  )
)

set "APP_URL=http://localhost/"

:openBrowser

if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" (
  start "" "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" --app="%APP_URL%"
  exit /b 0
)

if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" (
  start "" "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" --app="%APP_URL%"
  exit /b 0
)

if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" (
  start "" "%ProgramFiles%\Google\Chrome\Application\chrome.exe" --app="%APP_URL%"
  exit /b 0
)

if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" (
  start "" "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" --app="%APP_URL%"
  exit /b 0
)

start "" "%APP_URL%"
exit /b 0
