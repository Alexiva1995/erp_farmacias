@echo off
title COMPILAR FACTURADOR FISCAL THE FACTORY HKA (.EXE DARK PREMIUM)
color 0b

echo ======================================================================
echo    COMPILANDO FACTURADOR FISCAL THE FACTORY HKA - TOVA ERP (.EXE)
echo ======================================================================
echo.

REM Cerrar procesos existentes
taskkill /F /IM TovaFiscalFactory.exe /T 2>nul

python -m PyInstaller --onefile --windowed --icon="%~dp0tova.ico" --name="TovaFiscalFactory" --add-data "%~dp0tova.ico;." --add-data "%~dp0icon.png;." --collect-all customtkinter --clean "%~dp0factory_bridge_app.py"

echo.
if exist "%~dp0dist\TovaFiscalFactory.exe" (
    echo [EXITO] Ejecutable generado correctamente en: %~dp0dist\TovaFiscalFactory.exe
    copy /y "%~dp0dist\TovaFiscalFactory.exe" "%~dp0TovaFiscalFactory.exe"
    copy /y "%~dp0dist\TovaFiscalFactory.exe" "%~dp0..\..\TovaFiscalFactory.exe"
    echo [OK] Copiado a la raiz del proyecto y a la carpeta facturador_fiscal_thefactory.
) else (
    echo [ERROR] Ocurrio un fallo durante la compilacion.
)

pause
