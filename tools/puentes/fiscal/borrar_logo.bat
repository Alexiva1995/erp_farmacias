@echo off
title Borrar Logo Impresora Fiscal
cls
echo ======================================================
echo  EJECUTANDO BORRADO DE LOGO - IMPRESORA FISCAL
echo ======================================================
echo.

where python >nul 2>nul
if %errorlevel% equ 0 (
    python borrar_logo_fiscal.py
    goto end
)

where py >nul 2>nul
if %errorlevel% equ 0 (
    py borrar_logo_fiscal.py
    goto end
)

if exist "C:\Users\%USERNAME%\AppData\Local\Programs\Python\Python312\python.exe" (
    "C:\Users\%USERNAME%\AppData\Local\Programs\Python\Python312\python.exe" borrar_logo_fiscal.py
    goto end
)

if exist "C:\Users\%USERNAME%\AppData\Local\Programs\Python\Python311\python.exe" (
    "C:\Users\%USERNAME%\AppData\Local\Programs\Python\Python311\python.exe" borrar_logo_fiscal.py
    goto end
)

if exist "C:\Users\%USERNAME%\AppData\Local\Programs\Python\Python310\python.exe" (
    "C:\Users\%USERNAME%\AppData\Local\Programs\Python\Python310\python.exe" borrar_logo_fiscal.py
    goto end
)

if exist "C:\Python312\python.exe" (
    "C:\Python312\python.exe" borrar_logo_fiscal.py
    goto end
)

if exist "C:\Python311\python.exe" (
    "C:\Python311\python.exe" borrar_logo_fiscal.py
    goto end
)

echo [ERROR] No se encontro Python en el sistema.
echo Por favor asegurese de tener Python instalado.

:end
echo.
echo Presione cualquier tecla para salir...
pause >nul
