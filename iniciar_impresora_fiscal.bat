@echo off
title PUENTE IMPRESORA FISCAL - FARMACIA BARRIO SUCRE
color 0A
mode con: cols=75 lines=20

if exist "C:\fiscal_farmacia\fiscal_bridge_con_logo.py" (
    cd /d C:\fiscal_farmacia
) else (
    cd /d "%~dp0"
)

:MENU
cls
echo =======================================================================
echo          SISTEMA DE IMPRESION FISCAL - FARMACIA BARRIO SUCRE
echo =======================================================================
echo.
echo   Selecciona el modo de impresion:
echo.
echo     [1] Iniciar CON LOGO de cabecera
echo     [2] Iniciar SIN LOGO (Modo Clasico)
echo     [3] Salir
echo.
echo =======================================================================
set /p opt="  Digita tu opcion (1, 2 o 3) y presiona ENTER: "

if "%opt%"=="1" goto CON_LOGO
if "%opt%"=="2" goto SIN_LOGO
if "%opt%"=="3" exit
goto MENU

:CON_LOGO
cls
echo =======================================================================
echo   CONECTANDO IMPRESORA FISCAL (MODO CON LOGO)...
echo   (No cierres esta ventana mientras la caja este cobrando)
echo =======================================================================
echo.
python fiscal_bridge_con_logo.py
echo.
echo [AVISO] El puente fiscal se ha detenido. Reiniciando en 5 segundos...
timeout /t 5
goto CON_LOGO

:SIN_LOGO
cls
echo =======================================================================
echo   CONECTANDO IMPRESORA FISCAL (MODO SIN LOGO)...
echo   (No cierres esta ventana mientras la caja este cobrando)
echo =======================================================================
echo.
python fiscal_bridge_sin_logo.py
echo.
echo [AVISO] El puente fiscal se ha detenido. Reiniciando en 5 segundos...
timeout /t 5
goto SIN_LOGO
