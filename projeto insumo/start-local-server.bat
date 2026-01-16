@echo off
REM ===================================================
REM Script para iniciar o servidor PHP local
REM ===================================================

setlocal enabledelayedexpansion

echo.
echo ====================================================
echo   Sistema de Controle de Insumos JNJ
echo   Servidor PHP Local (Sem Docker)
echo ====================================================
echo.

REM Detectar PHP instalado
set PHP_EXE=php.exe

REM Procurar em caminhos comuns
if not exist "%PHP_EXE%" (
    for %%P in (
        "C:\xampp\php\php.exe"
        "C:\wamp\bin\php\php.exe"
        "C:\wamp64\bin\php\php.exe"
        "C:\laragon\bin\php\php.exe"
    ) do (
        if exist %%P (
            set "PHP_EXE=%%P"
            goto found_php
        )
    )
    
    REM Tentar command
    for /f "delims=" %%i in ('where php.exe 2^>nul') do (
        set "PHP_EXE=%%i"
        goto found_php
    )
)

:found_php

REM Verificar se PHP foi encontrado
php -v >nul 2>&1
if errorlevel 1 (
    echo.
    echo [ERRO] PHP nao encontrado!
    echo.
    echo Opcoes:
    echo  1. Instale XAMPP: https://www.apachefriends.org/pt_BR/index.html
    echo  2. Instale WAMP: https://www.wampserver.com/
    echo  3. Instale Laragon: https://laragon.org/
    echo.
    echo Depois reabra este arquivo (start-local-server.bat)
    echo.
    pause
    exit /b 1
)

echo [OK] PHP encontrado
php -v

echo.
cd /d "%~dp0"

echo.
echo ====================================================
echo   SERVIDOR INICIADO
echo ====================================================
echo.
echo Acesse: http://localhost:8080
echo.
echo Pressione Ctrl+C para parar
echo.

php -S localhost:8080

pause
