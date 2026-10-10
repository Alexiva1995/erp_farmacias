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
set PYTHON_CMD=
py --version >nul 2>&1
if "%ERRORLEVEL%"=="0" (
    set PYTHON_CMD=py
) else (
    python --version >nul 2>&1
    if "%ERRORLEVEL%"=="0" (
        set PYTHON_CMD=python
    )
)

if "%PYTHON_CMD%"=="" (
    echo [ERROR] No se detecto Python ni el lanzador 'py' en este equipo.
    echo Por favor descargue e instale Python desde https://www.python.org
    echo Asegurese de marcar la casilla "Add Python to PATH" durante la instalacion.
    echo.
    pause
    exit /b 1
)

echo [INFO] Utilizando ejecutor: %PYTHON_CMD%
%PYTHON_CMD% --version
echo.

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

:: 5. Verificar e instalar dependencias con requests, urllib3
%PYTHON_CMD% -c "import requests, urllib3" 2>nul
if "%ERRORLEVEL%" NEQ "0" (
    echo [INFO] Instalando librerias necesarias (requests, urllib3)...
    %PYTHON_CMD% -m pip install requests urllib3
    echo.
)

:: 6. Determinar script de Python a ejecutar
set PY_SCRIPT=
if exist "iniciar_puente_factory.py" (
    set PY_SCRIPT=iniciar_puente_factory.py
) else (
    if exist "factory_fiscal_bridge.py" (
        set PY_SCRIPT=factory_fiscal_bridge.py
    )
)

if "%PY_SCRIPT%"=="" (
    echo [ERROR] No se encontro el archivo iniciar_puente_factory.py ni factory_fiscal_bridge.py en esta carpeta.
    echo Asegurese de haber copiado el archivo .py junto al archivo .bat
    echo.
    pause
    exit /b 1
)

echo [INFO] Ejecutando script: %PY_SCRIPT%
echo.

:: 7. Bucle de ejecucion con proteccion de cierre
:LOOP
echo ======================================================================
echo [PUENTE FISCAL] Conectando con https://ensalud.tovaerp.com...
echo [PUENTE FISCAL] Presione Ctrl + C para detener el servicio.
echo ======================================================================
echo.

%PYTHON_CMD% "%PY_SCRIPT%"

echo.
echo [AVISO] El script termino con codigo: %ERRORLEVEL%
echo Si ocurrio un error, revise el mensaje anterior.
echo Reiniciando en 5 segundos (presione Ctrl+C para salir o cualquier tecla para pausar)...
pause
goto LOOP
