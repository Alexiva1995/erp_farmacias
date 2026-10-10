import os
import sys
import time
import socket
import subprocess
import requests
import urllib3
import re

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# ==============================================================================
# PUENTE FISCAL AUTONOMO THE FACTORY HKA - TOVA ERP (CLOUD BRIDGE)
# ==============================================================================
# 1. Inicia y garantiza la ejecucion en segundo plano de DemoTCPIP-PHP.exe.
# 2. Detecta la IP local de la maquina y valida el puerto 8090.
# 3. Consulta la nube (https://ensalud.tovaerp.com) por facturas y comandos pendientes.
# 4. Imprime automaticamente y confirma seriales y numeros de facturas fiscales reales.
# ==============================================================================

# --- CONFIGURACION ---
API_BASE_URL = "https://ensalud.tovaerp.com/api"
LOCAL_TCP_PORT = 8090
POLLING_INTERVAL = 3  # Segundos entre consultas
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
PROCESSED_FILE = os.path.join(BASE_DIR, "processed_invoices.txt")
PROCESSED_INVOICE_IDS = set()

if os.path.exists(PROCESSED_FILE):
    try:
        with open(PROCESSED_FILE, "r") as f:
            for line in f:
                line = line.strip()
                if line:
                    PROCESSED_INVOICE_IDS.add(int(line) if line.isdigit() else line)
    except Exception:
        pass


def mark_invoice_as_processed(inv_id):
    PROCESSED_INVOICE_IDS.add(inv_id)
    try:
        with open(PROCESSED_FILE, "a") as f:
            f.write(f"{inv_id}\n")
    except Exception:
        pass

LISTENER_EXE = os.path.join(
    BASE_DIR,
    "FACTORY",
    "TFHKA Venezuela - PHP SDK",
    "TFHKA Venezuela - WEB PHP con TCP SDK",
    "Socket TCPIP",
    "DemoTCPIP-PHP",
    "bin",
    "Debug",
    "DemoTCPIP-PHP.exe"
)


def get_local_ip() -> str:
    """Obtiene la IP local activa de la interfaz de red de la maquina."""
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.settimeout(0.5)
        s.connect(('8.8.8.8', 80))
        local_ip = s.getsockname()[0]
        s.close()
        return local_ip
    except Exception:
        return "127.0.0.1"


def ensure_hka_listener_running():
    """Verifica si DemoTCPIP-PHP.exe esta corriendo en Windows; si no, lo inicia."""
    if not os.path.exists(LISTENER_EXE):
        return

    try:
        tasks = subprocess.check_output('tasklist /FI "IMAGENAME eq DemoTCPIP-PHP.exe"', shell=True).decode('latin-1', errors='ignore')
        if "DemoTCPIP-PHP.exe" not in tasks:
            print("[AUTO-INICIO] Iniciando DemoTCPIP-PHP.exe en segundo plano...")
            subprocess.Popen([LISTENER_EXE], cwd=os.path.dirname(LISTENER_EXE), shell=False)
            time.sleep(2)
        else:
            print("[INFO] El listener DemoTCPIP-PHP.exe ya se encuentra en ejecucion.")
    except Exception as e:
        print(f"[AVISO] No se pudo verificar el proceso DemoTCPIP-PHP: {e}")


def send_raw_socket(payload: str, timeout: int = 5) -> str:
    """
    Envia una trama de comando terminada en NULL byte al socket TCP de HKA.
    Prueba tanto la IP local (192.168.x.x) como 127.0.0.1 / localhost.
    """
    target_ips = ["127.0.0.1", get_local_ip(), "localhost"]
    last_error = ""

    for target_ip in target_ips:
        try:
            with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
                s.settimeout(timeout)
                s.connect((target_ip, LOCAL_TCP_PORT))
                raw_data = (payload + "\0").encode("latin-1", errors="replace")
                s.sendall(raw_data)
                
                response = s.recv(4096)
                return response.decode("latin-1", errors="replace").strip("\0").strip()
        except Exception as e:
            last_error = f"{e} (en {target_ip}:{LOCAL_TCP_PORT})"
            continue

    return f"ERROR: {last_error}"


def get_fp_status() -> dict:
    """Consulta el estado y error descriptivo exacto de la impresora fiscal."""
    res = send_raw_socket("ReadFpStatus():1")
    if not res or res.startswith("ERROR"):
        return {"status": "Error", "error": res}
    if res.startswith("Resultado:"):
        res = res[10:]
    parts = res.split("|")
    return {
        "status_code": parts[0] if len(parts) > 0 else "",
        "status_desc": parts[1] if len(parts) > 1 else "",
        "error_code": parts[2] if len(parts) > 2 else "",
        "error_desc": parts[3] if len(parts) > 3 else "",
    }


def check_printer_present() -> bool:
    """Verifica si el Listener de HKA esta activo y la impresora responde."""
    res = send_raw_socket("CheckFprinter():1")
    if "Resultado:True" in res or (len(res) >= 11 and res[10] == "T"):
        return True
    return False


def execute_hka_cmd(cmd: str) -> bool:
    """Envia un comando SendCmd():<cmd> a la impresora fiscal."""
    res = send_raw_socket(f"SendCmd():{cmd}")
    if "Resultado:True" in res or (len(res) >= 11 and res[10] == "T"):
        return True
    
    st = get_fp_status()
    err_detail = f"{st.get('error_desc', '')} [Cod: {st.get('error_code', '')}]" if st.get('error_desc') else res
    print(f"[CMD RECHAZADO] Comando: '{cmd}' | Error: {err_detail}")
    return False


def get_status_s1() -> dict:
    """Obtiene y parsea el estado S1 de la memoria de la impresora."""
    res = send_raw_socket("UploadStatus():S1")
    if not res or res.startswith("ERROR"):
        return {}
    
    if res.startswith("Resultado:"):
        payload = res[10:]
    else:
        payload = res
        
    parts = payload.split("|")
    return {
        "cashier_number": parts[0] if len(parts) > 0 else None,
        "daily_sales": parts[1] if len(parts) > 1 else None,
        "last_invoice": parts[2] if len(parts) > 2 else None,
        "daily_invoices": parts[3] if len(parts) > 3 else None,
        "last_credit_note": parts[6] if len(parts) > 6 else None,
        "rif": parts[12] if len(parts) > 12 else None,
        "machine_serial": parts[13] if len(parts) > 13 else None,
        "printer_time": parts[14] if len(parts) > 14 else None,
        "printer_date": parts[15] if len(parts) > 15 else None,
    }


def clean_text(text: str, max_len: int = 40) -> str:
    """Limpia caracteres especiales no soportados por el protocolo ASCII de HKA."""
    if not text:
        return ""
    replacements = {
        'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ú': 'u',
        'Á': 'A', 'É': 'E', 'Í': 'I', 'Ó': 'O', 'Ú': 'U',
        'ñ': 'n', 'Ñ': 'N', 'ü': 'u', 'Ü': 'U'
    }
    for orig, rep in replacements.items():
        text = text.replace(orig, rep)
    cleaned = re.sub(r'[^a-zA-Z0-9\s.,#\-_/\(\)]', '', text)
    return cleaned.strip()[:max_len]


def format_gf_price(amount: float) -> str:
    """Formatea monto a 14 enteros + coma + 2 decimales: 00000000001558,66"""
    integer_part = int(amount)
    decimal_part = int(round((amount - integer_part) * 100))
    if decimal_part >= 100:
        integer_part += 1
        decimal_part = 0
    return f"{integer_part:014d},{decimal_part:02d}"


def format_gf_qty(qty: float) -> str:
    """Formatea cantidad a 14 enteros + coma + 3 decimales: 00000000000001,000"""
    integer_part = int(qty)
    decimal_part = int(round((qty - integer_part) * 1000))
    if decimal_part >= 1000:
        integer_part += 1
        decimal_part = 0
    return f"{integer_part:014d},{decimal_part:03d}"


# --- PROCESAMIENTO DE FACTURAS ---
def process_pending_invoices():
    try:
        resp = requests.get(f"{API_BASE_URL}/fiscal/pending", timeout=10, verify=False)
        if resp.status_code == 200:
            data = resp.json()
            if data and isinstance(data, dict) and 'id' in data:
                invoice_id = data['id']
                if invoice_id in PROCESSED_INVOICE_IDS:
                    return

                details = data.get('details', [])
                total_amount = float(data.get('total_amount', 0.0) or 0.0)
                
                if not details or len(details) == 0 or total_amount <= 0:
                    PROCESSED_INVOICE_IDS.add(invoice_id)
                    requests.patch(f"{API_BASE_URL}/fiscal/confirm/{invoice_id}", json={"invoice_number": f"ERR_VAC_{invoice_id}"}, verify=False)
                    return

                print(f"\n=======================================================")
                print(f"[FACTURA PENDIENTE] ID: {invoice_id} | Total: Bs {total_amount:.2f}")
                print(f"=======================================================")

                if not check_printer_present():
                    print("[ALERTA] La impresora fiscal no responde. Reintentando en proximo ciclo...")
                    return

                # Si quedo un documento fiscal abierto (status 5) se anula con el comando 7
                st_prev = get_fp_status()
                if str(st_prev.get('status_code')) == "5":
                    print("[AVISO] Documento fiscal abierto detectado. Anulando (cmd 7)...")
                    execute_hka_cmd("7")

                # 1. Encabezado de Cliente
                client_name = clean_text(data.get('business_name') or 'CLIENTE CONTADO', 40)
                raw_rif = str(data.get('identification') or 'V000000000')
                client_rif = re.sub(r'[^A-Za-z0-9]', '', raw_rif).upper() or 'V000000000'
                client_addr = clean_text(data.get('address') or '', 40)

                cmds = [
                    f"iS*{client_name}",
                    f"iR*{client_rif}",
                ]
                if client_addr:
                    cmds.append(f"i01{client_addr}")

                # 2. Renglones de Productos (Protocolo Extendido GF+ oficial SRP-812)
                for detail in details:
                    qty = float(detail.get('quantity', 1) or 1)
                    item_total = float(detail.get('total_amount', 0) or 0)
                    unit_price = item_total / qty if qty > 0 else item_total

                    is_taxable = (detail.get('vat_status') == 1 or detail.get('vat_status') is True or float(detail.get('iva_amount', 0) or 0) > 0)
                    
                    # Protocolo GF+: '0' = Exento, '1' = Tasa General (16%), '2' = Tasa Reducida (8%)
                    if is_taxable:
                        tax_code = '1'
                        base_price = unit_price / 1.16
                    else:
                        tax_code = '0'
                        base_price = unit_price

                    price_str = format_gf_price(base_price)
                    qty_str = format_gf_qty(qty)
                    p_name = clean_text(detail.get('product_name') or 'PRODUCTO', 38)

                    cmds.append(f"GF+{tax_code}{price_str}||{qty_str}||{p_name}")

                # 3. Medio de Pago (120 Divisas con IGTF 3% o 101 Bolivares) y Cierre (199)
                spe_raw = str(data.get('spe', '')).lower()
                spe_amount = float(data.get('spe_surcharge_amount', 0.0) or 0.0)
                is_spe = spe_raw in ['1', 'true'] or spe_amount > 0

                if is_spe:
                    print(f"[PAGO] Divisas / SPE detectado -> Aplicando Medio de Pago Divisa (120) con IGTF 3%...")
                    cmds.append("120")
                else:
                    print(f"[PAGO] Moneda Nacional detectada -> Aplicando Medio de Pago Efectivo Bs (101)...")
                    cmds.append("101")

                # Cierre oficial de documento fiscal (Flag 50 / IGTF)
                cmds.append("199")

                # 4. Enviar comandos paso a paso
                all_ok = True
                for c in cmds:
                    if not execute_hka_cmd(c):
                        all_ok = False
                        break

                if all_ok:
                    time.sleep(1)
                    s1 = get_status_s1()
                    last_inv = s1.get('last_invoice') or f"FAC-{invoice_id}"
                    m_serial = s1.get('machine_serial') or "Z1F0000379"

                    print(f"[EXITO] Factura #{last_inv} emitida correctamente. Serial: {m_serial}")
                    mark_invoice_as_processed(invoice_id)
                    
                    try:
                        conf_resp = requests.patch(
                            f"{API_BASE_URL}/fiscal/confirm/{invoice_id}",
                            json={"invoice_number": str(last_inv)},
                            verify=False,
                            timeout=10
                        )
                        if conf_resp.status_code == 200:
                            print(f"[CONFIRMACION CLOUD] Factura #{last_inv} guardada exitosamente en el ERP.")
                        else:
                            print(f"[AVISO CLOUD] Servidor respondio HTTP {conf_resp.status_code}: {conf_resp.text}")
                    except Exception as conf_err:
                        print(f"[ERROR CONFIRMACION CLOUD] {conf_err}")
                else:
                    print(f"[ERROR] No se pudo imprimir la factura {invoice_id}. Anulando documento...")
                    execute_hka_cmd("7")
    except Exception as e:
        print(f"[ERROR FACTURA] {e}")


# --- PROCESAMIENTO DE COMANDOS GENERALES (REPORTE X, Z, NOTA DE CREDITO) ---
def process_general_commands():
    try:
        resp = requests.get(f"{API_BASE_URL}/fiscal/commands/pending", timeout=10, verify=False)
        if resp.status_code == 200:
            full_data = resp.json()
            cmd_data = full_data.get('data') if full_data and 'data' in full_data else full_data
            
            if cmd_data and isinstance(cmd_data, dict) and 'id' in cmd_data:
                cmd_id = cmd_data['id']
                cmd_type = cmd_data.get('command')
                payload = cmd_data.get('payload', {}) or {}
                
                print(f"\n[COMANDO RECIBIDO] {cmd_type} (ID: {cmd_id})")
                res_output = "OK"
                status = "success"

                try:
                    if cmd_type == "REPORT_X":
                        print("[ACCION] Imprimiendo Reporte X...")
                        if not execute_hka_cmd("I0X"):
                            status = "error"
                            res_output = "Fallo al ejecutar Reporte X"
                        else:
                            # Manual HKA: esperar ~3s mientras emite tickets DNF
                            time.sleep(3)
                            
                    elif cmd_type == "REPORT_Z":
                        print("[ACCION] Imprimiendo Reporte Z (Cierre Diario)...")
                        if not execute_hka_cmd("I0Z"):
                            status = "error"
                            res_output = "Fallo al ejecutar Reporte Z"
                        else:
                            # Manual HKA: esperar ~20s hasta el reporte de estado de transmision
                            time.sleep(20)

                    elif cmd_type == "CREDIT_NOTE":
                        print("[ACCION] Imprimiendo Nota de Credito...")
                        client_name = clean_text(payload.get('client_name') or 'CLIENTE CONTADO', 40)
                        raw_rif = str(payload.get('client_rif') or 'V000000000')
                        client_rif = re.sub(r'[^A-Za-z0-9]', '', raw_rif).upper()
                        orig_inv = f"{int(str(payload.get('invoice_number', '0')).strip()):07d}"
                        mach_serial = str(payload.get('machine_serial', '')).strip().upper()
                        
                        raw_date = str(payload.get('invoice_date') or time.strftime("%Y-%m-%d"))
                        d_parts = raw_date.replace("/", "-").split("-")
                        if len(d_parts) == 3 and len(d_parts[0]) == 4:
                            formatted_date = f"{d_parts[2]}/{d_parts[1]}/{d_parts[0]}"
                        else:
                            formatted_date = time.strftime("%d/%m/%Y")

                        nc_cmds = [
                            f"iS*{client_name}",
                            f"iR*{client_rif}",
                            f"iF*{orig_inv}"
                        ]
                        if mach_serial:
                            nc_cmds.append(f"iI*{mach_serial}")
                        nc_cmds.append(f"iD*{formatted_date}")

                        is_tax = bool(payload.get('is_taxable', True))
                        refund_amt = float(payload.get('refund_amount', 0.0) or 0.0)
                        base_amt = (refund_amt / 1.16) if is_tax else refund_amt
                        tax_code = '1' if is_tax else '0'

                        price_str = format_gf_price(base_amt)
                        qty_str = format_gf_qty(1.0)
                        nc_cmds.append(f"GC+{tax_code}{price_str}||{qty_str}||DEVOLUCION DE MERCANCIA")
                        nc_cmds.append("101")
                        nc_cmds.append("199")

                        all_ok = True
                        for c in nc_cmds:
                            if not execute_hka_cmd(c):
                                all_ok = False
                                break
                        if not all_ok:
                            status = "error"
                            res_output = "Fallo al emitir Nota de Credito"

                    elif cmd_type == "REPRINT_REPORT_Z":
                        # Manual 17.1: RZ + inicio(7 digitos) + fin(7 digitos)
                        z_num = f"{int(str(payload.get('z_number', '1')).strip()):07d}"
                        print(f"[ACCION] Reimprimiendo Reporte Z #{z_num}...")
                        if not execute_hka_cmd(f"RZ{z_num}{z_num}"):
                            status = "error"
                            res_output = "Fallo al reimprimir Reporte Z"

                except Exception as cmd_err:
                    status = "error"
                    res_output = str(cmd_err)

                requests.patch(
                    f"{API_BASE_URL}/fiscal/commands/{cmd_id}/confirm",
                    json={"status": status, "response": res_output},
                    verify=False
                )
                print(f"[COMANDO CONFIRMADO] Estado: {status}")
    except Exception as e:
        print(f"[ERROR COMANDOS] {e}")


if __name__ == "__main__":
    local_ip = get_local_ip()
    print("================================================================")
    print("  PUENTE FISCAL AUTONOMO THE FACTORY HKA - TOVA ERP")
    print(f"  Servidor Cloud:   {API_BASE_URL}")
    print(f"  IP Local de la PC:{local_ip}")
    print(f"  Socket Local HKA: 127.0.0.1 / {local_ip}:{LOCAL_TCP_PORT}")
    print("================================================================")

    # 1. Asegurar ejecucion de DemoTCPIP-PHP
    ensure_hka_listener_running()

    # 2. Diagnostico inicial de conexion
    time.sleep(1)
    if check_printer_present():
        print("[OK] Impresora The Factory HKA conectada y lista para operar.")
        s1 = get_status_s1()
        if s1.get('machine_serial'):
            print(f"[IMPRESORA] Serial: {s1.get('machine_serial')} | RIF: {s1.get('rif')} | Ultima Factura: {s1.get('last_invoice')}")
    else:
        print("[AVISO] Esperando conexion activa en Demo TCP .Net...")

    print("\n[ESTADO] Escuchando ordenes y facturas desde la nube en tiempo real...\n")

    # 3. Bucle continuo de sincronizacion
    while True:
        try:
            process_pending_invoices()
            process_general_commands()
        except KeyboardInterrupt:
            print("\n[SALIR] Puente fiscal detenido.")
            break
        except Exception as e:
            print(f"[ERROR BUCLE] {e}")
        time.sleep(POLLING_INTERVAL)
