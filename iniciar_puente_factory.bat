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

:: 2. Detectar comando de Python (py o python)
set PYTHON_CMD=python
py --version >nul 2>&1
if "%ERRORLEVEL%"=="0" (
    set PYTHON_CMD=py
)

:: 3. Ruta del Listener DemoTCPIP de The Factory HKA
set LISTENER_EXE=%PROJECT_DIR%FACTORY\TFHKA Venezuela - PHP SDK\TFHKA Venezuela - WEB PHP con TCP SDK\Socket TCPIP\DemoTCPIP-PHP\bin\Debug\DemoTCPIP-PHP.exe

:: 4. Verificar si el listener de HKA existe y abrirlo si no esta en ejecucion
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

:: 5. Verificar e instalar dependencias con py -m pip o python -m pip
%PYTHON_CMD% -c "import requests, urllib3" 2>nul
if "%ERRORLEVEL%" NEQ "0" (
    echo [INFO] Instalando librerias necesarias (requests, urllib3)...
    %PYTHON_CMD% -m pip install requests urllib3
    echo.
)

:: 6. Determinar script de Python a ejecutar (factory_fiscal_bridge.py o iniciar_puente_factory.py)
set PY_SCRIPT=factory_fiscal_bridge.py
if not exist "%PY_SCRIPT%" (
    if exist "iniciar_puente_factory.py" (
        set PY_SCRIPT=iniciar_puente_factory.py
    )
)

:: 7. Ejecutar el puente fiscal en bucle continuo
:LOOP
echo.
echo [PUENTE FISCAL] Conectando con https://ensalud.tovaerp.com...
echo [PUENTE FISCAL] Presione Ctrl + C para detener el servicio.
echo.

%PYTHON_CMD% %PY_SCRIPT%

echo.
echo [AVISO] El puente fiscal se detuvo o perdio conexion. Reiniciando en 5 segundos...
timeout /t 5
goto LOOP
