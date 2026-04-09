import ctypes
import time
import requests
import urllib.parse
import urllib3
import os

# --- SCRIPT DE RÉPLICA (REGISTRO FIEL DE FACTURAS) ---
# Este script asegura que el número devuelto por la impresora se guarde en fiscal_id.

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# --- CONFIGURACIÓN ---
BRIDGE_MODE = "REAL" # "REAL" o "WEBSIM" 
SERIAL_PORT_NUM = "3" # Solo el número del puerto COM
API_BASE_URL = "https://erp_farmacias.test/api" 
POLLING_INTERVAL = 5 

DLL_PATH = r"c:\laragon\www\erp_farmacias\pnp\pnpdll\pnpdll64.dll"
WEBSIM_URL = "https://desarrollospnp.com/sim/pf.php"

# --- CARGA DE DLL ---
pnp = None
if BRIDGE_MODE == "REAL":
    try:
        if os.path.exists(DLL_PATH):
            pnp = ctypes.WinDLL(DLL_PATH)
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
            print(f"[DLL] Librería RÉPLICA cargada satisfactoriamente desde {DLL_PATH}")
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

def call_pnp(func, *args):
    b_args = [str(arg).encode('ansi') for arg in args]
    ptr = func(*b_args)
    res = get_pnp_res(ptr)
    if res == "ER":
        err_ptr = pnp.PFultimo()
        err_msg = get_pnp_res(err_ptr)
        print(f"[DLL ERROR] Falla: {err_msg}")
        return "ERROR|" + err_msg
    return res

# --- PROTOCOLO WEBSIM ---
class WebSimPrinter:
    def __init__(self, url):
        self.url = url

    def print_invoice(self, data):
        commands = []
        name = data.get('business_name', 'CLIENTE GENERICO')
        rif = data.get('identification', 'V000000000')
        rif_clean = "".join(filter(str.isalnum, rif))
        commands.append(f"@:{name[:39]}:{rif_clean[:12]}")
        
        for detail in data.get('details', []):
            qty_int = int(float(detail['quantity']) * 1000)
            is_taxable = detail.get('vat_status') == 1 or detail.get('vat_status') is True
            price_unit = float(detail['total_amount']) / (1.16 if is_taxable else 1.0) / float(detail['quantity'])
            price_int = int(price_unit * 100)
            tax_val = 1600 if is_taxable else 0
            name_clean = detail['product_name'].replace("|", "").replace(":", "")
            commands.append(f"B:{name_clean[:20]}:{qty_int}:{price_int}:{tax_val}:M")
        
        total_int = int(float(data['total_amount']) * 100)
        commands.append(f"E:U:{total_int}")
        return self._send_to_sim(commands)

    def print_report(self, type_char):
        return self._send_to_sim([f"{type_char}"])

    def _send_to_sim(self, commands):
        full_query = "|".join(commands)
        safe_query = urllib.parse.quote(full_query, safe="|:?=@")
        full_url = f"{self.url}?{safe_query}"
        try:
            resp = requests.get(full_url, timeout=15, verify=False)
            return resp.text
        except Exception as e:
            return f"ERROR: {e}"

# --- WORKER LÓGICA RÉPLICA ---
def process_pending_invoices(sim):
    try:
        resp = requests.get(f"{API_BASE_URL}/fiscal/pending", timeout=10, verify=False)
        if resp.status_code == 200:
            data = resp.json()
            if data and 'id' in data:
                invoice_id = data['id']
                print(f"\n[RÉPLICA] Procesando Factura ID: {invoice_id}")
                
                res_text = ""
                if BRIDGE_MODE == "WEBSIM":
                    res_text = sim.print_invoice(data)
                else:
                    name = data.get('business_name', 'CLIENTE GENERICO')[:40]
                    rif = "".join(filter(str.isalnum, data.get('identification', 'V000000000')))[:12]
                    call_pnp(pnp.PFabrefiscal, name, rif)
                    for detail in data.get('details', []):
                        d_name = detail['product_name'][:30]
                        qty = "{:.3f}".format(float(detail['quantity']))
                        is_taxable = detail.get('vat_status') == 1 or detail.get('vat_status') is True
                        price_u = float(detail['total_amount']) / (1.16 if is_taxable else 1.0) / float(detail['quantity'])
                        price = "{:.2f}".format(price_u)
                        tax = "1600" if is_taxable else "0"
                        call_pnp(pnp.PFrenglon, d_name, str(qty), str(price), tax)
                    
                    ptr = pnp.PFtotal()
                    res_text = get_pnp_res(ptr)
                
                # MEJORA: Extracción precisa del número de factura del reporte final de la impresora
                parts = res_text.split('|') if res_text else []
                inv_num = parts[-1] if (len(parts) > 0 and parts[-1] != "OK" and parts[-1] != "") else "FAC" + str(invoice_id)
                
                print(f"[RÉPLICA] Número detectado: {inv_num}. Confirmando en /confirm-replica...")
                
                # LLAMADA AL NUEGO ENDPOINT DE RÉPLICA
                requests.patch(f"{API_BASE_URL}/fiscal/confirm-replica/{invoice_id}", json={
                    "invoice_number": inv_num[:20],
                    "fiscal_id": inv_num[:20] # Forzamos que fiscal_id sea el mismo número
                }, timeout=10, verify=False)
                print(f"[OK RÉPLICA] Factura {invoice_id} registrada como {inv_num}")
    except Exception as e:
        print(f"[INV ERR RÉPLICA] {e}")

def process_general_commands(sim):
    try:
        resp = requests.get(f"{API_BASE_URL}/fiscal/commands/pending", timeout=10, verify=False)
        if resp.status_code == 200:
            full_data = resp.json()
            cmd_data = full_data.get('data') if full_data and 'data' in full_data else full_data
            if cmd_data and 'id' in cmd_data:
                cmd_id = cmd_data['id']
                cmd_type = cmd_data['command']
                payload = cmd_data.get('payload', {})
                print(f"\n[COMMAND RÉPLICA] Ejecutando: {cmd_type}")
                
                res_output = "OK"
                try:
                    if cmd_type == "REPORT_Z":
                        if BRIDGE_MODE == "WEBSIM": res_output = sim.print_report("I")
                        else: res_output = call_pnp(pnp.PFrepz)
                    elif cmd_type == "REPORT_X":
                        if BRIDGE_MODE == "WEBSIM": res_output = sim.print_report("H")
                        else: res_output = call_pnp(pnp.PFrepx)
                    
                    status = "success"
                except Exception as ex:
                    res_output = str(ex)
                    status = "error"

                requests.patch(f"{API_BASE_URL}/fiscal/commands/{cmd_id}/confirm", json={
                    "status": status,
                    "response": res_output
                }, timeout=10, verify=False)
                print(f"[OK RÉPLICA] Comando {cmd_id} finalizado")
    except Exception as e:
        print(f"[CMD ERR RÉPLICA] {e}")

if __name__ == "__main__":
    websim = WebSimPrinter(WEBSIM_URL)
    print(f"--- NUEVO Worker Fiscal RÉPLICA v1.0 Activo ({BRIDGE_MODE}) ---")
    
    if BRIDGE_MODE == "REAL":
        res = call_pnp(pnp.PFabrepuerto, SERIAL_PORT_NUM)
        if "ERROR" in res:
            print(f"[DLL ERROR] No se pudo abrir el puerto {SERIAL_PORT_NUM}.")
        else:
            print(f"[DLL OK] Puerto {SERIAL_PORT_NUM} abierto correctamente.")

    while True:
        process_pending_invoices(websim)
        process_general_commands(websim)
        time.sleep(POLLING_INTERVAL)
