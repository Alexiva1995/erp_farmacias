@echo off
title PUENTE a2 SOFTWAY - TOVA ERP (POLLING)
color 0b

echo ======================================================================
echo           INICIANDO PUENTE AUTONOMO a2 SOFTWAY - TOVA ERP
echo ======================================================================
echo.

if not exist "%~dp0a2_bridge_poller.py" (
    echo [ERROR] No se encontro el archivo a2_bridge_poller.py en este directorio.
    pause
    exit /b
)

python "%~dp0a2_bridge_poller.py"

echo.
echo [AVISO] El servicio se ha detenido. Reiniciando en 5 segundos...
timeout /t 5
goto :eof
