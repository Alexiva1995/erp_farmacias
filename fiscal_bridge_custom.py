import ctypes
import time
import requests
import urllib.parse
import urllib3
import os
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# ==============================================================================
# PUENTE FISCAL PNP - VERSIÓN PERSONALIZADA (LOGO, CAJERO, 40 COLUMNAS, IGTF)
# ==============================================================================

# --- CONFIGURACIÓN ---
BRIDGE_MODE = "REAL"  # "REAL" o "WEBSIM" 
SERIAL_PORT_NUM = "1" # Puerto serial de la impresora fiscal (ej: 1, 96, 97)
API_BASE_URL = "https://farmaciabs.com/api" 
POLLING_INTERVAL = 5 

DLL_PATH = r"C:\fiscal_farmacia\pnp\pnpdll\pnpdll64.dll"
WEBSIM_URL = "https://desarrollospnp.com/sim/pf.php"

# --- CARGA DE DLL ---
pnp = None
if BRIDGE_MODE == "REAL":
    try:
        if os.path.exists(DLL_PATH):
            pnp = ctypes.WinDLL(DLL_PATH)
            
            # Funciones estándar obligatorias
            pnp.PFabrepuerto.argtypes = [ctypes.c_char_p]
            pnp.PFabrepuerto.restype = ctypes.c_void_p
            
            pnp.PFabrefiscal.argtypes = [ctypes.c_char_p, ctypes.c_char_p]
            pnp.PFabrefiscal.restype = ctypes.c_void_p
            
            pnp.PFrenglon.argtypes = [ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p]
            pnp.PFrenglon.restype = ctypes.c_void_p
            
            pnp.PFtotal.restype = ctypes.c_void_p
            
            pnp.PFComando.argtypes = [ctypes.c_char_p]
            pnp.PFComando.restype = ctypes.c_void_p
            
            pnp.PFrepx.restype = ctypes.c_void_p
            pnp.PFrepz.restype = ctypes.c_void_p
            
            pnp.PFrepMemNF.argtypes = [ctypes.c_char_p, ctypes.c_char_p]
            pnp.PFrepMemNF.restype = ctypes.c_void_p
            
            pnp.PFultimo.restype = ctypes.c_void_p
            
            # Funciones opcionales según compilación de la DLL
            if hasattr(pnp, 'PFTfiscal'):
                pnp.PFTfiscal.argtypes = [ctypes.c_char_p]
                pnp.PFTfiscal.restype = ctypes.c_void_p
                
            if hasattr(pnp, 'PFLogoClick'):
                pnp.PFLogoClick.restype = ctypes.c_void_p
            
            print(f"[DLL] Librería cargada satisfactoriamente desde {DLL_PATH}")
        else:
            print(f"[DLL ERROR] No se encontró la DLL en {DLL_PATH}")
            BRIDGE_MODE = "WEBSIM"
    except Exception as e:
        print(f"[DLL ERROR] Fallo al cargar DLL: {e}")
        BRIDGE_MODE = "WEBSIM"

def get_pnp_res(ptr):
    if ptr:
        return ctypes.string_at(ptr).decode('ansi', errors='ignore')
    return ""

def decode_pnp_status(status_str):
    parts = status_str.split(',')
    if len(parts) < 2: 
        return status_str
    
    st_p, st_f = parts[0], parts[1]
    msgs = []
    try:
        val_p = int(st_p, 16)
        if val_p & (1 << 14): msgs.append("SIN PAPEL")
        if val_p & (1 << 3): msgs.append("FUERA DE LÍNEA")
        if val_p & (1 << 2): msgs.append("FALLA IMPRESORA")
        
        val_f = int(st_f, 16)
        if val_f & (1 << 11): msgs.append("REQUIERE REPORTE Z")
        if val_f & (1 << 7): msgs.append("MEMORIA FISCAL LLENA")
        if val_f & (1 << 5): msgs.append("COMANDO INVÁLIDO (Estado)")
        if val_f & (1 << 4): msgs.append("CAMPO DATOS INVÁLIDO")
        if val_f & (1 << 3): msgs.append("COMANDO NO RECONOCIDO")
        if val_f & (1 << 12): msgs.append("FACTURA ABIERTA")
    except: 
        pass
    
    return f"{status_str} -> [{' | '.join(msgs)}]" if msgs else status_str

def call_pnp(func, *args):
    b_args = [str(arg).encode('ansi') for arg in args]
    ptr = func(*b_args)
    res = get_pnp_res(ptr)
    
    if res == "ER":
        err_ptr = pnp.PFultimo()
        err_msg = get_pnp_res(err_ptr)
        decoded = decode_pnp_status(err_msg)
        print(f"[DLL ERROR] Falla en llamada. PFultimo devolvió: '{err_msg}' -> {decoded}")
        return "ERROR|" + decoded
    return res

def format_short_name(full_name):
    """Extrae únicamente el Primer Nombre y Primer Apellido."""
    if not full_name:
        return "CLIENTE GENERICO"
    parts = full_name.strip().split()
    if len(parts) == 1:
        return parts[0][:30].upper()
    elif len(parts) >= 2:
        return f"{parts[0]} {parts[1]}"[:30].upper()
    return full_name[:30].upper()

def extract_cashier_name(data):
    """Obtiene el nombre del cajero/vendedor."""
    user = data.get('user')
    if user:
        employee = user.get('employee')
        if employee and employee.get('name'):
            emp_name = f"{employee.get('name', '')} {employee.get('last_name', '')}".strip()
            return format_short_name(emp_name)
        if user.get('username'):
            return str(user.get('username')).upper()
    return None

# --- PROTOCOLO WEBSIM ---
class WebSimPrinter:
    def __init__(self, url):
        self.url = url

    def print_invoice(self, data):
        commands = []
        raw_name = data.get('business_name', 'CLIENTE GENERICO')
        name = format_short_name(raw_name)
        rif = data.get('identification', 'V000000000')
        rif_clean = "".join(filter(str.isalnum, rif))
        commands.append(f"@:{name[:39]}:{rif_clean[:12]}")
        
        cashier = extract_cashier_name(data)
        if cashier:
            commands.append(f"A:CAJERO: {cashier[:30]}")
        
        for detail in data.get('details', []):
            qty_int = int(float(detail['quantity']) * 1000)
            is_taxable = detail.get('vat_status') == 1 or detail.get('vat_status') is True
            price_unit = float(detail['total_amount']) / (1.16 if is_taxable else 1.0) / float(detail['quantity'])
            price_int = int(price_unit * 100)
            tax_val = 1600 if is_taxable else 0
            name_clean = detail['product_name'].replace("|", "").replace(":", "")
            commands.append(f"B:{name_clean[:30]}:{qty_int}:{price_int}:{tax_val}:M")
        
        total_int = int(float(data['total_amount']) * 100)
        apply_spe = bool(data.get('spe', 0) == 1 or float(data.get('spe_surcharge_amount', 0) or 0) > 0)
        if apply_spe:
            commands.append(f"E:U:{total_int}")
        else:
            commands.append(f"E:T")
            
        return self._send_to_sim(commands)

    def print_report(self, type_char):
        commands = [f"{type_char}"]
        return self._send_to_sim(commands)

    def _send_to_sim(self, commands):
        full_query = "|".join(commands)
        safe_query = urllib.parse.quote(full_query, safe="|:?=@")
        full_url = f"{self.url}?{safe_query}"
        print(f"[WEBSIM] Enviando: {full_url}")
        try:
            resp = requests.get(full_url, timeout=15, verify=False)
            return resp.text
        except Exception as e:
            return f"ERROR: {e}"

# --- WORKER LÓGICA PERSONALIZADA ---
def process_pending_invoices(sim):
    try:
        print(f"[DEBUG] Ping facturas -> {API_BASE_URL}/fiscal/pending")
        resp = requests.get(f"{API_BASE_URL}/fiscal/pending", timeout=10, verify=False)
        print(f"[DEBUG] Resp facturas: {resp.status_code} - {resp.text[:50]}")
        if resp.status_code == 200:
            data = resp.json()
            if data and 'id' in data:
                invoice_id = data['id']
                print(f"\n[INVOICE] Procesando Factura ID: {invoice_id}")
                
                res_text = ""
                if BRIDGE_MODE == "WEBSIM":
                    res_text = sim.print_invoice(data)
                else:
                    # 1. Configurar Fuente Compacta si está disponible en DLL
                    if hasattr(pnp, 'PFTIPOIMP'):
                        try:
                            call_pnp(pnp.PFTIPOIMP, "300")
                        except:
                            pass

                    # 2. Estampar Logo Fiscal en Cabecera
                    if hasattr(pnp, 'PFLogoClick'):
                        try:
                            print("[DLL] Estampando Logo Fiscal de cabecera...")
                            call_pnp(pnp.PFLogoClick)
                        except Exception as logo_err:
                            print(f"[DLL LOGO NOTE] {logo_err}")

                    # 3. Abrir Factura Fiscal con Primer Nombre y Primer Apellido
                    raw_name = data.get('business_name', 'CLIENTE GENERICO')
                    name = format_short_name(raw_name)
                    rif = "".join(filter(str.isalnum, data.get('identification', 'V000000000')))[:12]
                    
                    print(f"[DLL] @ {name} | {rif}")
                    call_pnp(pnp.PFabrefiscal, name, rif)
                    
                    # 4. Imprimir línea informativa de Cajero (Texto Fiscal inicial)
                    cashier_name = extract_cashier_name(data)
                    if cashier_name:
                        cashier_line = f"CAJERO: {cashier_name}"[:40]
                        print(f"[DLL] Texto Fiscal -> {cashier_line}")
                        call_pnp(pnp.PFTfiscal, cashier_line)

                    # 5. Imprimir Renglones (Hasta 40 caracteres en descripción compacta)
                    for detail in data.get('details', []):
                        d_name = detail['product_name'][:40]
                        qty = "{:.3f}".format(float(detail['quantity']))
                        is_taxable = detail.get('vat_status') == 1 or detail.get('vat_status') is True
                        price_u = float(detail['total_amount']) / (1.16 if is_taxable else 1.0) / float(detail['quantity'])
                        price = "{:.2f}".format(price_u)
                        tax = "1600" if is_taxable else "0"
                        
                        print(f"[DLL] B {d_name} | Q:{qty} | P:{price} | T:{tax}")
                        call_pnp(pnp.PFrenglon, d_name, str(qty), str(price), tax)
                    
                    # 6. Determinar si aplica IGTF (3%) o Cierre Estándar
                    spe_flag = data.get('spe', 0)
                    spe_surcharge = float(data.get('spe_surcharge_amount', 0.0) or 0.0)
                    total_amount = float(data.get('total_amount', 0.0) or 0.0)
                    
                    apply_igtf = bool(spe_flag == 1 or spe_surcharge > 0)
                    
                    if apply_igtf:
                        igtf_base_cents = int(round(total_amount * 100))
                        igtf_cmd = f"E|U|{igtf_base_cents}"
                        print(f"[DLL IGTF] Cierre con IGTF 3% -> PFComando('{igtf_cmd}') sobre Base Bs {total_amount:.2f}")
                        res_text = call_pnp(pnp.PFComando, igtf_cmd)
                    else:
                        print("[DLL] Cerrando factura estándar (PFtotal)...")
                        ptr = pnp.PFtotal()
                        res_text = get_pnp_res(ptr)
                
                inv_num = res_text.split('|')[-1] if (res_text and '|' in res_text) else "FAC" + str(invoice_id)
                requests.patch(f"{API_BASE_URL}/fiscal/confirm/{invoice_id}", json={
                    "invoice_number": inv_num[:20],
                    "fiscal_id": None
                }, timeout=10, verify=False)
                print(f"[OK] Factura {invoice_id} confirmada: {inv_num}")
    except Exception as e:
        print(f"[INV ERR] {e}")

def process_general_commands(sim):
    try:
        print(f"[DEBUG] Ping comandos -> {API_BASE_URL}/fiscal/commands/pending")
        resp = requests.get(f"{API_BASE_URL}/fiscal/commands/pending", timeout=10, verify=False)
        print(f"[DEBUG] Resp comandos: {resp.status_code} - {resp.text[:50]}")
        if resp.status_code == 200:
            full_data = resp.json()
            cmd_data = full_data.get('data') if full_data and 'data' in full_data else full_data
            
            if cmd_data and 'id' in cmd_data:
                cmd_id = cmd_data['id']
                cmd_type = cmd_data['command']
                payload = cmd_data.get('payload', {})
                print(f"\n[COMMAND] Ejecutando: {cmd_type} (ID: {cmd_id})")
                
                res_output = "OK"
                try:
                    if cmd_type == "REPORT_Z":
                        if BRIDGE_MODE == "WEBSIM": res_output = sim.print_report("I")
                        else: res_output = call_pnp(pnp.PFrepz)
                    elif cmd_type == "REPORT_X":
                        if BRIDGE_MODE == "WEBSIM": res_output = sim.print_report("H")
                        else: res_output = call_pnp(pnp.PFrepx)
                    elif cmd_type == "ANNUL_INVOICE":
                        inv_to_annul = payload.get('invoice_number', '')
                        if BRIDGE_MODE == "WEBSIM": res_output = f"G:{inv_to_annul}"
                        else: res_output = call_pnp(pnp.PFComando, f"G|{inv_to_annul}")
                    elif cmd_type == "REPRINT_REPORT_Z":
                        z_num = str(payload.get('z_number', '1'))
                        if BRIDGE_MODE == "WEBSIM": res_output = f"U:{z_num}:{z_num}"
                        else: res_output = call_pnp(pnp.PFrepMemNF, z_num, z_num)
                    
                    status = "success"
                except Exception as ex:
                    import traceback
                    traceback.print_exc()
                    res_output = str(ex)
                    status = "error"

                requests.patch(f"{API_BASE_URL}/fiscal/commands/{cmd_id}/confirm", json={
                    "status": status,
                    "response": res_output
                }, timeout=10, verify=False)
                print(f"[OK] Comando {cmd_id} ({status})")
    except Exception as e:
        print(f"[CMD ERR] {e}")

if __name__ == "__main__":
    websim = WebSimPrinter(WEBSIM_URL)
    print(f"--- Worker Fiscal DLL Personalizado Activo ({BRIDGE_MODE}) ---")
    
    if BRIDGE_MODE == "REAL":
        print(f"[DLL] Probando apertura inicial de puerto {SERIAL_PORT_NUM}...")
        res = call_pnp(pnp.PFabrepuerto, SERIAL_PORT_NUM)
        if "ERROR" in res:
            print(f"[DLL ERROR] No se pudo abrir el puerto {SERIAL_PORT_NUM}. Verifica la conexión.")
        else:
            print(f"[DLL OK] Puerto {SERIAL_PORT_NUM} abierto correctamente.")

    while True:
        process_pending_invoices(websim)
        process_general_commands(websim)
        time.sleep(POLLING_INTERVAL)
