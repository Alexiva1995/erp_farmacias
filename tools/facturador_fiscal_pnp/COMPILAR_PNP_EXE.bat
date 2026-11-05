@echo off
title COMPILAR FACTURADOR FISCAL PNP 88A (.EXE DARK PREMIUM)
color 0b

echo ======================================================================
echo       COMPILANDO FACTURADOR FISCAL PNP 88A - TOVA ERP (.EXE)
echo ======================================================================
echo.

REM Cerrar procesos existentes
taskkill /F /IM TovaFiscalPNP.exe /T 2>nul

python -m PyInstaller --onefile --windowed --icon="%~dp0tova.ico" --name="TovaFiscalPNP" --add-data "%~dp0tova.ico;." --add-data "%~dp0icon.png;." --add-data "%~dp0pnpdll64.dll;." --collect-all customtkinter --clean "%~dp0pnp_bridge_app.py"

echo.
if exist "%~dp0dist\TovaFiscalPNP.exe" (
    echo [EXITO] Ejecutable generado correctamente en: %~dp0dist\TovaFiscalPNP.exe
    copy /y "%~dp0dist\TovaFiscalPNP.exe" "%~dp0TovaFiscalPNP.exe"
    copy /y "%~dp0dist\TovaFiscalPNP.exe" "%~dp0..\..\TovaFiscalPNP.exe"
    echo [OK] Copiado a la raiz del proyecto y a la carpeta facturador_fiscal_pnp.
) else (
    echo [ERROR] Ocurrio un fallo durante la compilacion.
)

pause
