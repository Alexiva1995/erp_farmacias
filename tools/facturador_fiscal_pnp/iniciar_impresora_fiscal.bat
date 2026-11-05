@echo off
title PUENTE IMPRESORA FISCAL - FARMACIA BARRIO SUCRE
color 0A
mode con: cols=80 lines=22

if exist "C:\fiscal_farmacia\fiscal_bridge_con_logo.py" (
    cd /d C:\fiscal_farmacia
) else (
    cd /d "%~dp0"
)

:START
cls
echo =======================================================================
echo          SISTEMA DE IMPRESION FISCAL - FARMACIA BARRIO SUCRE
echo =======================================================================
echo.
echo   [OK] Iniciando servicio de impresion fiscal con logo automaticamente...
echo   (No cierres esta ventana mientras la caja este operando)
echo.
echo =======================================================================
echo.

python fiscal_bridge_con_logo.py

echo.
echo =======================================================================
echo [AVISO] El puente fiscal se ha detenido. Reiniciando en 5 segundos...
echo =======================================================================
timeout /t 5 >nul
goto START
