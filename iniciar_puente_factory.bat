@echo off
title PUENTE FISCAL THE FACTORY HKA - TOVA ERP
color 0A

echo ======================================================================
echo           INICIANDO PUENTE FISCAL THE FACTORY HKA (BIXOLON)
echo ======================================================================
echo.

:: 1. Ruta base del proyecto
set PROJECT_DIR=%~dp0
cd /d "%PROJECT_DIR%"

:: 2. Ruta del Listener DemoTCPIP de The Factory HKA
set LISTENER_EXE=%PROJECT_DIR%FACTORY\TFHKA Venezuela - PHP SDK\TFHKA Venezuela - WEB PHP con TCP SDK\Socket TCPIP\DemoTCPIP-PHP\bin\Debug\DemoTCPIP-PHP.exe

:: 3. Verificar si el listener de HKA existe y abrirlo si no esta en ejecucion
tasklist /FI "IMAGENAME eq DemoTCPIP-PHP.exe" 2>NUL | find /I /N "DemoTCPIP-PHP.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [INFO] El listener DemoTCPIP-PHP ya se encuentra en ejecucion.
) else (
    if exist "%LISTENER_EXE%" (
        echo [INFO] Iniciando DemoTCPIP-PHP en segundo plano...
        start "" "%LISTENER_EXE%"
        timeout /t 2 /nobreak >nul
    ) else (
        echo [AVISO] No se encontro DemoTCPIP-PHP.exe en la ruta local.
    )
)

:: 4. Verificar dependencias de Python (requests, urllib3)
python -c "import requests, urllib3" 2>nul
if "%ERRORLEVEL%" NEQ "0" (
    echo [INFO] Instalando librerias necesarias (requests, urllib3)...
    pip install requests urllib3
    echo.
)

:: 5. Ejecutar el puente fiscal en bucle continuo
:LOOP
echo.
echo [PUENTE FISCAL] Conectando con https://ensalud.tovaerp.com...
echo [PUENTE FISCAL] Presione Ctrl + C para detener el servicio.
echo.

python factory_fiscal_bridge.py

echo.
echo [AVISO] El puente fiscal se detuvo o perdio conexion. Reiniciando en 5 segundos...
timeout /t 5
goto LOOP
