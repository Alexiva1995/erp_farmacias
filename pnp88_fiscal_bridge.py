import ctypes
import time
import requests
import urllib.parse
import urllib3
import os
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# ==============================================================================
# PUENTE FISCAL PNP - PFT88A / 80mm (LOGO, IGTF 3% Y SENIAT PROVIDENCIA 0071)
# ==============================================================================

# --- CONFIGURACION ---
BRIDGE_MODE = "REAL"  # "REAL" o "WEBSIM"
SERIAL_PORT_NUM = "1" # Puerto serial de la impresora fiscal (COM1)
API_BASE_URL = "https://farmaciabs.com/api"
POLLING_INTERVAL = 5

# Serial fisico de la PFT88A impreso en la etiqueta (Ej: EOM0000310)
MACHINE_SERIAL = "EOM0000310" 

DLL_PATH = r"C:\laragon\www\erp_farmacias\pnp\pnpdll\pnpdll64.dll"
if not os.path.exists(DLL_PATH):
    DLL_PATH = r"C:\fiscal_farmacia\pnp\pnpdll\pnpdll64.dll"

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
            
            pnp.PFultimo.restype = ctypes.c_void_p
            
            if hasattr(pnp, 'PFCortar'):
                pnp.PFCortar.restype = ctypes.c_void_p
                
            if hasattr(pnp, 'PFestatus'):
                pnp.PFestatus.argtypes = [ctypes.c_char_p]
                pnp.PFestatus.restype = ctypes.c_void_p

            if hasattr(pnp, 'PFTipoImp'):
                pnp.PFTipoImp.argtypes = [ctypes.c_char_p]
                pnp.PFTipoImp.restype = ctypes.c_void_p

            if hasattr(pnp, 'PFLogoClick'):
                pnp.PFLogoClick.restype = ctypes.c_void_p

            if hasattr(pnp, 'PFDevolucion'):
                pnp.PFDevolucion.argtypes = [ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p]
                pnp.PFDevolucion.restype = ctypes.c_void_p
                
            if hasattr(pnp, 'PFRepMemoriaNumero'):
                pnp.PFRepMemoriaNumero.argtypes = [ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p]
                pnp.PFRepMemoriaNumero.restype = ctypes.c_void_p
            
            print(f"[DLL] Libreria PNP cargada desde {DLL_PATH}")
        else:
            print(f"[DLL ERROR] No se encontro la DLL en {DLL_PATH}")
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
        print(f"[DLL ERROR] PFultimo devolvio: '{err_msg}'")
        return "ERROR|" + err_msg
    return res

def clean_text(text, max_len=40):
    if not text: return ""
    text = str(text).replace("|", "").replace(":", "").replace("@", "").strip()
    text = " ".join(text.split())
    return text[:max_len].upper()

def extract_client_name(data):
    # Providencia 0071: Razon Social completa. DLL procesa hasta 80 caracteres.
    order = data.get('order')
    if order and order.get('client'):
        c_name = str(order['client'].get('name', '')).strip()
        c_last = str(order['client'].get('last_name', '')).strip()
        full = f"{c_name} {c_last}".strip()
        if full:
            return clean_text(full, 80)
    
    raw_name = data.get('business_name', 'CLIENTE GENERICO')
    return clean_text(raw_name, 80)

def extract_client_rif(data):
    # Providencia 0071: RIF estructurado (V, J, G, E, P). Alfanumerico max 12.
    rif = data.get('identification', 'V000000000')
    return "".join(filter(str.isalnum, str(rif)))[:12].upper()

# --- WEBSIM PRINTER ---
class WebSimPrinter:
    def __init__(self, url):
        self.url = url
    def print_invoice(self, data):
        commands = []
        name = extract_client_name(data)
        rif = extract_client_rif(data)
        commands.append(f"@:{name[:39]}:{rif}")
        for detail in data.get('details', []):
            qty_int = int(float(detail['quantity']) * 1000)
            is_taxable = detail.get('vat_status') == 1 or detail.get('vat_status') is True
            price_unit = float(detail['total_amount']) / (1.16 if is_taxable else 1.0) / float(detail['quantity'])
            price_int = int(price_unit * 100)
            tax_val = 1600 if is_taxable else 0
            name_clean = clean_text(detail['product_name'], 30)
            commands.append(f"B:{name_clean}:{qty_int}:{price_int}:{tax_val}:M")
        total_int = int(float(data['total_amount']) * 100)
        apply_spe = bool(data.get('spe', 0) == 1 or float(data.get('spe_surcharge_amount', 0) or 0) > 0)
        if apply_spe:
            commands.append(f"E:U:{total_int}")
        else:
            commands.append(f"E:T")
        return self._send_to_sim(commands)
    def print_report(self, type_char):
        return self._send_to_sim([f"{type_char}"])
    def _send_to_sim(self, commands):
        safe_query = urllib.parse.quote("|".join(commands), safe="|:?=@")
        try:
            resp = requests.get(f"{self.url}?{safe_query}", timeout=15, verify=False)
            return resp.text
        except Exception as e:
            return f"ERROR: {e}"

PROCESSED_INVOICE_IDS = set()

# --- LOGICA DE FACTURACION ---
def process_pending_invoices(sim):
    try:
        resp = requests.get(f"{API_BASE_URL}/fiscal/pending", timeout=10, verify=False)
        if resp.status_code == 200:
            data = resp.json()
            if data and 'id' in data:
                invoice_id = data['id']
                if invoice_id in PROCESSED_INVOICE_IDS:
                    return

                details = data.get('details', [])
                total_amount = float(data.get('total_amount', 0.0) or 0.0)
                
                if not details or len(details) == 0 or total_amount <= 0:
                    PROCESSED_INVOICE_IDS.add(invoice_id)
                    requests.patch(f"{API_BASE_URL}/fiscal/confirm/{invoice_id}", json={"invoice_number": f"ERR_VAC_{invoice_id}"}, verify=False)
                    return

                print(f"\n[FACTURA] Procesando ID: {invoice_id} | Total: {total_amount:.2f}")
                
                res_text = ""
                if BRIDGE_MODE == "WEBSIM":
                    res_text = sim.print_invoice(data)
                else:
                    # 1. Logo ANTES de abrir documento (Protocolo Pág. 50)
                    # if hasattr(pnp, 'PFLogoClick'):
                    #     call_pnp(pnp.PFLogoClick)

                    # 2. Abrir Factura (Razón Social completa y RIF)
                    name = extract_client_name(data)
                    rif = extract_client_rif(data)
                    call_pnp(pnp.PFabrefiscal, name, rif)

                    # 3. Renglones (Hasta 40 columnas en PFT88A)
                    items_printed = 0
                    for detail in details:
                        qty_val = float(detail.get('quantity', 0) or 0)
                        amt_val = float(detail.get('total_amount', 0) or 0)
                        if qty_val <= 0: continue
                        # Máximo 20 caracteres permitidos por la controladora fiscal para evitar ERROR 1
                        d_name = clean_text(detail.get('product_name', 'PRODUCTO'), 20)
                        qty = "{:.3f}".format(qty_val)
                        
                        is_taxable = detail.get('vat_status') == 1 or detail.get('vat_status') is True
                        price_u = amt_val / (1.16 if is_taxable else 1.0) / qty_val
                        price = "{:.2f}".format(price_u)
                        
                        # Alicuota: DEBE SER de 4 digitos siempre (Protocolo DLL pág 37)
                        tax = "1600" if is_taxable else "0000" 
                        
                        print(f"[ITEM] {d_name} | Q:{qty} | P:{price} | IVA:{tax}")
                        r_res = call_pnp(pnp.PFrenglon, d_name, str(qty), str(price), tax)
                        if "ERROR" not in str(r_res): items_printed += 1

                    if items_printed == 0:
                        call_pnp(pnp.PFComando, "G") # Cancelar
                        if hasattr(pnp, 'PFCortar'):
                            call_pnp(pnp.PFCortar)
                        requests.patch(f"{API_BASE_URL}/fiscal/confirm/{invoice_id}", json={"invoice_number": f"ERR_RNG_{invoice_id}"}, verify=False)
                        return

                    # 4. IGTF 3% o Cierre Estandar
                    spe_flag = data.get('spe', 0)
                    spe_surcharge = float(data.get('spe_surcharge_amount', 0.0) or 0.0)
                    if bool(spe_flag == 1 or spe_surcharge > 0):
                        exempt_amt = float(data.get('exempt_amount', 0.0) or 0.0)
                        taxable_amt = float(data.get('taxable_amount', 0.0) or 0.0)
                        iva_amt = float(data.get('iva_amount', 0.0) or 0.0)
                        base_divisa = (exempt_amt + taxable_amt + iva_amt) if (exempt_amt or taxable_amt or iva_amt) else (total_amount - spe_surcharge)
                        if base_divisa <= 0: base_divisa = total_amount
                        
                        igtf_base_cents = int(round(base_divisa * 100))
                        igtf_cmd = f"E|U|{igtf_base_cents}"
                        print(f"[IGTF] Cierre IGTF 3% -> Base Bs {base_divisa:.2f}")
                        res_text = call_pnp(pnp.PFComando, igtf_cmd)
                    else:
                        print("[CIERRE] Finalizando factura estandar...")
                        res_text = get_pnp_res(pnp.PFtotal())

                    # Forzar avance y corte de papel para expulsar el ticket
                    if hasattr(pnp, 'PFCortar'):
                        call_pnp(pnp.PFCortar)
                
                # 5. Obtener Número de Factura Fiscal Real desde la memoria de la máquina
                inv_num = ""
                if hasattr(pnp, 'PFestatus'):
                    try:
                        pnp.PFestatus(b'N')
                        res_st = get_pnp_res(pnp.PFultimo())
                        print(f"\n[DEBUG CONTADORES] Trama cruda de la impresora: '{res_st}'")
                        if res_st and "ERROR" not in res_st:
                            parts = [p.strip() for p in res_st.replace(',', '|').split('|') if p.strip()]
                            for i, part in enumerate(parts):
                                print(f"  -> Campo {i+1}: {part}")
                            
                            # Campo 10 (indice 9) = # Factura fiscal acumulado (ej: 00012300)
                            # Campo 8 (indice 7) = # Factura fiscal del periodo
                            if len(parts) >= 10 and parts[9].isdigit() and int(parts[9]) > 0:
                                inv_num = str(int(parts[9])).zfill(8)
                                print(f"[DLL PARSER] Factura fiscal acumulada seleccionada (Campo 10): '{inv_num}'")
                            elif len(parts) >= 8 and parts[7].isdigit() and int(parts[7]) > 0:
                                inv_num = str(int(parts[7])).zfill(8)
                                print(f"[DLL PARSER] Factura fiscal del periodo seleccionada (Campo 8): '{inv_num}'")
                    except Exception as st_err:
                        print(f"[STATUS ERR] {st_err}")

                # Si no se pudo obtener de status, consultar último cierre
                if not inv_num:
                    try:
                        res_close = get_pnp_res(pnp.PFultimo())
                        print(f"[DEBUG CIERRE] Trama del último comando: '{res_close}'")
                        if res_close and "ERROR" not in res_close:
                            parts = [p.strip() for p in res_close.replace(',', '|').split('|') if p.strip()]
                            if len(parts) >= 4 and parts[3].isdigit() and int(parts[3]) > 0:
                                inv_num = str(int(parts[3])).zfill(8)
                                print(f"[DLL PARSER] Factura fiscal extraida de cierre: '{inv_num}'")
                    except:
                        pass
                
                if not inv_num:
                    inv_num = f"FAC{invoice_id}"

                PROCESSED_INVOICE_IDS.add(invoice_id)
                requests.patch(f"{API_BASE_URL}/fiscal/confirm/{invoice_id}", json={"invoice_number": inv_num[:20]}, verify=False)
                print(f"[OK] Factura confirmada en sistema con Número Fiscal: {inv_num}")
    except Exception as e:
        import traceback
        print(f"\n[ERROR CRITICO EN FACTURACION] {e}")
        traceback.print_exc()

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
                print(f"\n[COMANDO] {cmd_type} (ID: {cmd_id})")
                
                res_output = "OK"
                try:
                    if cmd_type == "REPORT_Z":
                        if BRIDGE_MODE == "WEBSIM": res_output = sim.print_report("I")
                        else: res_output = call_pnp(pnp.PFrepz)
                    elif cmd_type == "REPORT_X":
                        if BRIDGE_MODE == "WEBSIM": res_output = sim.print_report("H")
                        else: res_output = call_pnp(pnp.PFrepx)
                    elif cmd_type == "ANNUL_INVOICE":
                        inv = payload.get('invoice_number', '')
                        if BRIDGE_MODE == "WEBSIM": res_output = f"G:{inv}"
                        else: res_output = call_pnp(pnp.PFComando, f"G|{inv}")
                    elif cmd_type == "CREDIT_NOTE":
                        inv_orig    = str(payload.get('invoice_number', ''))
                        machine_ser = str(payload.get('machine_serial', MACHINE_SERIAL))
                        
                        raw_date = str(payload.get('invoice_date', '')).replace("-", "").replace("/", "")
                        if len(raw_date) == 8: inv_date = raw_date[:4] + raw_date[6:] # DDMMYYYY -> DDMMAA
                        elif len(raw_date) == 6: inv_date = raw_date
                        else: inv_date = time.strftime("%d%m%y")
                            
                        inv_hour    = str(payload.get('invoice_hour', '0000')).replace(":", "")[:4]
                        refund_amt  = float(payload.get('refund_amount', 0))
                        client_name = clean_text(payload.get('client_name', 'CLIENTE GENERICO'), 80)
                        client_rif  = extract_client_rif(payload)
                        is_taxable  = bool(payload.get('is_taxable', True))
                        tax_rate    = "1600" if is_taxable else "0000"
                        price_u     = refund_amt / 1.16 if is_taxable else refund_amt

                        if BRIDGE_MODE == "WEBSIM":
                            res_output = sim._send_to_sim(["@:NC:V", "E:T"])
                        else:
                            # if hasattr(pnp, 'PFLogoClick'): call_pnp(pnp.PFLogoClick)
                            
                            # Uso de funcion nativa PFDevolucion para Notas de Credito (Prov. 0071)
                            if hasattr(pnp, 'PFDevolucion'):
                                res_open = call_pnp(pnp.PFDevolucion, client_name, client_rif, inv_orig, machine_ser, inv_date, inv_hour)
                            else:
                                res_open = call_pnp(pnp.PFComando, f"@|{client_name}|{client_rif}|{inv_orig}|{machine_ser}|{inv_date}|{inv_hour}|D")

                            call_pnp(pnp.PFrenglon, f"DEVOLUCION FAC {inv_orig}"[:40], "1.000", "{:.2f}".format(price_u), tax_rate)
                            res_output = get_pnp_res(pnp.PFtotal())
                            
                    elif cmd_type == "REPRINT_REPORT_Z":
                        z_num = str(payload.get('z_number', '1'))
                        if BRIDGE_MODE == "WEBSIM": res_output = f"U:{z_num}:{z_num}"
                        else:
                            if hasattr(pnp, 'PFRepMemoriaNumero'):
                                res_output = call_pnp(pnp.PFRepMemoriaNumero, z_num, z_num, "R")
                            else:
                                res_output = call_pnp(pnp.PFComando, f"U|{z_num}|{z_num}|R")
                    status = "success"
                except Exception as ex:
                    res_output = str(ex)
                    status = "error"

                requests.patch(f"{API_BASE_URL}/fiscal/commands/{cmd_id}/confirm", json={"status": status, "response": res_output}, verify=False)
    except Exception as e:
        import traceback
        print(f"\n[ERROR EN COMANDOS] {e}")
        traceback.print_exc()

if __name__ == "__main__":
    websim = WebSimPrinter(WEBSIM_URL)
    print("=========================================================")
    print("  SISTEMA PNP 88A - SENIAT IGTF (40 COLUMNAS / 80mm) ")
    print("=========================================================")
    
    if BRIDGE_MODE == "REAL":
        print(f"[DLL] Conectando COM{SERIAL_PORT_NUM}...")
        res = call_pnp(pnp.PFabrepuerto, SERIAL_PORT_NUM)
        if "ERROR" in res:
            print(f"[ERROR] No se accedio al puerto COM{SERIAL_PORT_NUM}.")
        else:
            print(f"[OK] COM{SERIAL_PORT_NUM} ABIERTO.")
            
            # Mantener la fuente y centrado nativo original de la impresora
            # (No forzar PFTipoImp '300' para no desplazar los espacios grabados en la memoria fiscal)

            # Auto-recuperación: Detectar si quedó una factura o documento trabado y liberarlo automáticamente
            if hasattr(pnp, 'PFestatus'):
                try:
                    res_st = get_pnp_res(pnp.PFestatus(b'N'))
                    if res_st and "ERROR" not in res_st:
                        parts = [p.strip() for p in res_st.replace(',', '|').split('|') if p.strip()]
                        if len(parts) >= 4 and parts[3] in ['01', '02', '05', '1']:
                            print(f"[AUTO-RECUPERACION] Documento pendiente detectado (Estado: {parts[3]}). Liberando papel...")
                            call_pnp(pnp.PFComando, "G")
                            if hasattr(pnp, 'PFCortar'):
                                call_pnp(pnp.PFCortar)
                            print("[AUTO-RECUPERACION] Impresora liberada con exito.")
                except Exception as rec_err:
                    pass

    while True:
        process_pending_invoices(websim)
        process_general_commands(websim)
        time.sleep(POLLING_INTERVAL)
