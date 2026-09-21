import ctypes
import os
import sys

DLL_PATH = r"c:\laragon\www\erp_farmacias\pnp\pnpdll\pnpdll64.dll"
if not os.path.exists(DLL_PATH):
    DLL_PATH = r"C:\fiscal_farmacia\pnp\pnpdll\pnpdll64.dll"
SERIAL_PORT_NUM = "1"

print("==================================================")
print(" HERRAMIENTA DE BORRADO DE LOGO - IMPRESORA FISCAL")
print("==================================================")

if not os.path.exists(DLL_PATH):
    print(f"[ERROR] No se encontro la DLL en: {DLL_PATH}")
    sys.exit(1)

try:
    pnp = ctypes.WinDLL(DLL_PATH)
    pnp.PFabrepuerto.argtypes = [ctypes.c_char_p]
    pnp.PFabrepuerto.restype = ctypes.c_void_p

    pnp.PFComando.argtypes = [ctypes.c_char_p]
    pnp.PFComando.restype = ctypes.c_void_p

    pnp.PFcierrapuerto.restype = ctypes.c_void_p

    def get_res(ptr):
        return ctypes.string_at(ptr).decode('ansi', errors='ignore') if ptr else ""

    print(f"[1] Abriendo puerto COM{SERIAL_PORT_NUM}...")
    ptr_open = pnp.PFabrepuerto(SERIAL_PORT_NUM.encode('ansi'))
    res_open = get_res(ptr_open)
    print(f"    Respuesta abrir puerto: {res_open}")

    # Intentar métodos de borrado de logo
    # Metodo A: Comando de protocolo 'L|0' o 'L' (Limpia logo en buffer)
    print("[2] Enviando comando de borrado de logo (L|0)...")
    ptr_cmd1 = pnp.PFComando(b"L|0")
    print(f"    Respuesta comando L|0: {get_res(ptr_cmd1)}")

    # Metodo B: Comando '8|0' / 'G|0'
    print("[3] Enviando comando auxiliar de limpieza...")
    ptr_cmd2 = pnp.PFComando(b"L")
    print(f"    Respuesta comando L: {get_res(ptr_cmd2)}")

    # Metodo C: Si la DLL tiene PFEliminaLogo o similar
    for func_name in ['PFEliminaLogo', 'PFBorraLogo', 'PFCancelaLogo', 'PFClearLogo']:
        if hasattr(pnp, func_name):
            try:
                func = getattr(pnp, func_name)
                func.restype = ctypes.c_void_p
                print(f"[4] Ejecutando {func_name}()...")
                print(f"    Respuesta: {get_res(func())}")
            except Exception as e:
                print(f"    Error en {func_name}: {e}")

    print("[5] Cerrando puerto...")
    if hasattr(pnp, 'PFcierrapuerto'):
        pnp.PFcierrapuerto()

    print("\n[LISTO] Proceso de limpieza de logo completado.")

except Exception as e:
    print(f"[ERROR EXCEPCION] {e}")
