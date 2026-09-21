@echo off
title PUENTE FISCAL - PNP 88A (80mm)
color 0B
mode con: cols=85 lines=25

if exist "C:\fiscal_farmacia\pnp88_fiscal_bridge.py" (
    cd /d C:\fiscal_farmacia
) else (
    cd /d "%~dp0"
)

:START
cls
echo ==================================================================================
echo                  SISTEMA DE IMPRESION FISCAL - PNP 88A (80mm)
echo ==================================================================================
echo.
echo   [OK] Iniciando servicio de facturacion optimizado para 40 Columnas y SENIAT...
echo   (No cierres esta ventana mientras la caja este operando)
echo.
echo ==================================================================================
echo.

python pnp88_fiscal_bridge.py

echo.
echo ==================================================================================
echo [AVISO] El puente fiscal se ha detenido o ha caido. Reiniciando en 5 segundos...
echo ==================================================================================
timeout /t 5 >nul
goto START
