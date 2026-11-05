@echo off
title COMPILAR CONECTOR a2 SOFTWAY (.EXE DARK PREMIUM)
color 0b

echo ======================================================================
echo           COMPILANDO CONECTOR a2 SOFTWAY - TOVA ERP (.EXE)
echo ======================================================================
echo.

python -m PyInstaller --onefile --windowed --icon="%~dp0tova.ico" --name="TovaSyncA2" --add-data "%~dp0icon.png;." --collect-all customtkinter --clean "%~dp0a2_bridge_app.py"

echo.
if exist "%~dp0dist\TovaSyncA2.exe" (
    echo [EXITO] Ejecutable generado correctamente en: %~dp0dist\TovaSyncA2.exe
    copy /y "%~dp0dist\TovaSyncA2.exe" "%~dp0TovaSyncA2.exe"
    copy /y "%~dp0dist\TovaSyncA2.exe" "%~dp0..\..\TovaSyncA2.exe"
) else (
    echo [ERROR] Ocurrio un fallo durante la compilacion.
)

pause
