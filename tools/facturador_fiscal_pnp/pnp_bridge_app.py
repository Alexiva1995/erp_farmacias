import os
import sys
import time
import json
import threading
import ctypes
import requests
import urllib3
from PIL import Image, ImageTk

# Registrar App ID de Windows para mostrar icono oficial de Tova en barra de tareas
try:
    myappid = 'tovaerp.facturador.fiscal.pnp.1.0'
    ctypes.windll.shell32.SetCurrentProcessExplicitAppUserModelID(myappid)
except Exception:
    pass

import customtkinter as ctk
from tkinter import filedialog, messagebox
import serial.tools.list_ports

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# ==============================================================================
# PALETA OFICIAL TOVA ERP — MODO OSCURO PREMIUM
# ==============================================================================
COLOR_PRIMARY = "#E20074"          # Fucsia / Magenta Tova
COLOR_PRIMARY_HOVER = "#C50064"    # Fucsia hover
COLOR_SECONDARY = "#7A0099"        # Morado corporativo
COLOR_SECONDARY_HOVER = "#62007A"
COLOR_SUCCESS = "#10B981"          # Verde esmeralda brillante
COLOR_DANGER = "#EF4444"           # Rojo alerta
COLOR_WARNING = "#F59E0B"          # Ambar advertencia
COLOR_ACCENT_BLUE = "#38BDF8"      # Azul información

COLOR_BG_ROOT = "#0B0F19"          # Fondo principal negro-azulado
COLOR_CARD = "#151D2E"             # Fondo tarjetas
COLOR_CARD_BORDER = "#222F45"      # Borde de tarjetas
COLOR_INPUT_BG = "#0D1424"         # Fondo inputs
COLOR_INPUT_BORDER = "#27354D"     # Borde inputs
COLOR_TEXT_TITLE = "#F8FAFC"       # Titulos
COLOR_TEXT_BODY = "#94A3B8"        # Texto secundario
COLOR_CONSOLE_BG = "#080C14"       # Consola ultra oscura
COLOR_CONSOLE_TEXT = "#CBD5E1"

def get_base_dir():
    if getattr(sys, 'frozen', False):
        return os.path.dirname(sys.executable)
    return os.path.dirname(os.path.abspath(__file__))

def get_resource_path(relative_path):
    if hasattr(sys, '_MEIPASS'):
        return os.path.join(sys._MEIPASS, relative_path)
    return os.path.join(get_base_dir(), relative_path)

BASE_DIR = get_base_dir()
CONFIG_FILE = os.path.join(BASE_DIR, "pnp_bridge_config.json")
ICON_FILE = get_resource_path("tova.ico")
ICON_PNG_FILE = get_resource_path("icon.png")

def get_available_com_ports():
    try:
        ports = [p.device for p in serial.tools.list_ports.comports()]
    except Exception:
        ports = []
    if not ports:
        ports = [f"COM{i}" for i in range(1, 11)]
    return sorted(list(set(ports)), key=lambda x: int(x.replace("COM", "")) if x.replace("COM", "").isdigit() else 99)

def load_config():
    default_dll = os.path.join(BASE_DIR, "pnpdll64.dll")
    if not os.path.exists(default_dll):
        default_dll = get_resource_path("pnpdll64.dll")

    default_cfg = {
        "api_base_url": "https://alkosto.tovaerp.com/api",
        "api_token": "",
        "dll_path": default_dll,
        "serial_port_num": "1",
        "machine_serial": "EOM0000310",
        "polling_interval": 5,
        "auto_start": False
    }
    if os.path.exists(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, "r", encoding="utf-8") as f:
                saved = json.load(f)
                default_cfg.update(saved)
        except Exception:
            pass
    return default_cfg

def save_config(cfg):
    try:
        with open(CONFIG_FILE, "w", encoding="utf-8") as f:
            json.dump(cfg, f, indent=4)
        return True
    except Exception:
        return False

# ==============================================================================
# MOTOR DEL PUENTE FISCAL PNP (DLL 64-BIT)
# ==============================================================================
def clean_text(text, max_len=40):
    if not text:
        return ""
    text = str(text).replace("|", "").replace(":", "").replace("@", "").strip()
    text = " ".join(text.split())
    return text[:max_len].upper()

def extract_client_name(data):
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
    rif = data.get('identification', 'V000000000')
    return "".join(filter(str.isalnum, str(rif)))[:12].upper()


class PnpFiscalService:
    def __init__(self, log_callback, update_metrics_callback, dll_path):
        self.log = log_callback
        self.update_metrics = update_metrics_callback
        self.dll_path = dll_path
        self.pnp = None
        self.dll_loaded = False
        self.port_opened = False
        self.is_running = False
        self.stop_event = threading.Event()
        self.worker_thread = None
        self.processed_invoice_ids = set()
        self.invoices_count = 0
        self.last_fiscal_doc = "#Sin registrar"
        self.load_dll()

    def set_dll_path(self, new_path):
        if new_path and os.path.exists(new_path):
            self.dll_path = new_path
            self.pnp = None
            self.dll_loaded = False
            return self.load_dll()
        return False

    def load_dll(self):
        if self.pnp is not None and self.dll_loaded:
            return True
        try:
            target_path = self.dll_path
            if not os.path.exists(target_path):
                # Intentar ruta local o empacada
                for alt in [get_resource_path("pnpdll64.dll"),
                            os.path.join(BASE_DIR, "pnpdll64.dll"),
                            os.path.join(BASE_DIR, "pnp", "pnpdll", "pnpdll64.dll")]:
                    if os.path.exists(alt):
                        target_path = alt
                        self.dll_path = alt
                        break

            if not os.path.exists(target_path):
                self.log(f"[DLL ERROR] No se encontro la DLL fiscal en: {target_path}", "warning")
                self.dll_loaded = False
                return False

            self.pnp = ctypes.WinDLL(target_path)
            self.pnp.PFabrepuerto.argtypes = [ctypes.c_char_p]
            self.pnp.PFabrepuerto.restype = ctypes.c_void_p

            self.pnp.PFabrefiscal.argtypes = [ctypes.c_char_p, ctypes.c_char_p]
            self.pnp.PFabrefiscal.restype = ctypes.c_void_p

            self.pnp.PFrenglon.argtypes = [ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p]
            self.pnp.PFrenglon.restype = ctypes.c_void_p

            self.pnp.PFtotal.restype = ctypes.c_void_p

            self.pnp.PFComando.argtypes = [ctypes.c_char_p]
            self.pnp.PFComando.restype = ctypes.c_void_p

            self.pnp.PFrepx.restype = ctypes.c_void_p
            self.pnp.PFrepz.restype = ctypes.c_void_p
            self.pnp.PFultimo.restype = ctypes.c_void_p

            if hasattr(self.pnp, 'PFCortar'):
                self.pnp.PFCortar.restype = ctypes.c_void_p

            if hasattr(self.pnp, 'PFestatus'):
                self.pnp.PFestatus.argtypes = [ctypes.c_char_p]
                self.pnp.PFestatus.restype = ctypes.c_void_p

            if hasattr(self.pnp, 'PFTipoImp'):
                self.pnp.PFTipoImp.argtypes = [ctypes.c_char_p]
                self.pnp.PFTipoImp.restype = ctypes.c_void_p

            if hasattr(self.pnp, 'PFLogoClick'):
                self.pnp.PFLogoClick.restype = ctypes.c_void_p

            if hasattr(self.pnp, 'PFDevolucion'):
                self.pnp.PFDevolucion.argtypes = [ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p]
                self.pnp.PFDevolucion.restype = ctypes.c_void_p

            if hasattr(self.pnp, 'PFRepMemoriaNumero'):
                self.pnp.PFRepMemoriaNumero.argtypes = [ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p]
                self.pnp.PFRepMemoriaNumero.restype = ctypes.c_void_p

            self.dll_loaded = True
            self.log(f"[DLL] Librería fiscal vinculada con éxito: {target_path}")
            return True
        except Exception as ex:
            self.log(f"[DLL ERROR] No se pudo cargar DLL ({self.dll_path}): {ex}", "error")
            self.dll_loaded = False
            return False

    def get_res(self, ptr):
        if ptr:
            try:
                return ctypes.string_at(ptr).decode('ansi', errors='ignore')
            except Exception:
                return ""
        return ""

    def call_pnp(self, func, *args):
        if not self.dll_loaded or not self.pnp:
            return "ERROR|DLL_NO_CARGADA"
        try:
            b_args = [str(arg).encode('ansi') for arg in args]
            ptr = func(*b_args)
            res = self.get_res(ptr)
            if res == "ER":
                err_ptr = self.pnp.PFultimo()
                err_msg = self.get_res(err_ptr)
                self.log(f"[DLL ERROR] PFultimo: '{err_msg}'", "error")
                return "ERROR|" + err_msg
            return res
        except Exception as e:
            self.log(f"[DLL EXCEPCION] {e}", "error")
            return f"ERROR|{e}"

    def open_port(self, port_num, silent=False):
        if not self.load_dll():
            return False
        port_str = str(port_num).replace("COM", "").replace("com", "").strip()
        if not silent:
            self.log(f"[PUERTO] Conectando COM{port_str}...")
        res = self.call_pnp(self.pnp.PFabrepuerto, port_str)
        if "ERROR" in str(res):
            self.port_opened = False
            if not silent:
                self.log(f"[ERROR] No se pudo abrir puerto COM{port_str}.", "error")
            return False
        self.port_opened = True
        if not silent:
            self.log(f"[OK] Puerto fiscal COM{port_str} conectado.", "success")
            self.auto_recover()
        return True

    def auto_recover(self):
        if not self.port_opened or not hasattr(self.pnp, 'PFestatus'):
            return
        try:
            res_st = self.get_res(self.pnp.PFestatus(b'N'))
            if res_st and "ERROR" not in res_st:
                parts = [p.strip() for p in res_st.replace(',', '|').split('|') if p.strip()]
                if len(parts) >= 4 and parts[3] in ['01', '02', '05', '1']:
                    self.log(f"[AUTO-RECUPERACION] Documento pendiente en buffer (Estado: {parts[3]}). Liberando papel...", "warning")
                    self.call_pnp(self.pnp.PFComando, "G")
                    if hasattr(self.pnp, 'PFCortar'):
                        self.call_pnp(self.pnp.PFCortar)
                    self.log("[AUTO-RECUPERACION] Impresora liberada con exito.", "success")
        except Exception as ex:
            self.log(f"[AUTO-RECUPERACION ERROR] {ex}", "warning")

    def test_connection(self, cfg):
        results = []
        # 1. Probar conexion al ERP
        url = cfg.get("api_base_url", "").rstrip("/")
        try:
            resp = requests.get(f"{url}/fiscal/pending", timeout=6, verify=False)
            if resp.status_code in [200, 404]:
                results.append("✓ Servidor Tova ERP: En línea (HTTP " + str(resp.status_code) + ")")
            else:
                results.append(f"✗ Servidor Tova ERP: HTTP {resp.status_code}")
        except Exception as e:
            results.append(f"✗ Servidor Tova ERP: Error ({e})")

        # 2. Probar impresora fiscal con DLL
        port = cfg.get("serial_port_num", "1")
        if not self.load_dll():
            results.append(f"✗ DLL Fiscal: No encontrada o inválida ({self.dll_path})")
        else:
            results.append("✓ Librería DLL Fiscal: Cargada correctamente")
            ok = self.open_port(port)
            if ok:
                results.append(f"✓ Impresora Fiscal: Comunicación exitosa en COM{port}")
                try:
                    self.pnp.PFestatus(b'N')
                    st = self.get_res(self.pnp.PFultimo())
                    if st and "ERROR" not in st:
                        results.append(f"  Respuesta fiscal: {st[:45]}")
                except Exception:
                    pass
            else:
                results.append(f"✗ Impresora Fiscal: Falló comunicación en COM{port}")

        return "\n".join(results)

    def print_x_report(self):
        if not self.port_opened:
            self.log("[ERROR] Puerto fiscal cerrado. Inicie el facturador o pruebe conexión.", "error")
            return False
        self.log("[COMANDO FISCAL] Imprimiendo Reporte X...")
        res = self.call_pnp(self.pnp.PFrepx)
        self.log(f"[REPORTE X RES] {res}", "success" if "ERROR" not in str(res) else "error")
        return "ERROR" not in str(res)

    def print_z_report(self):
        if not self.port_opened:
            self.log("[ERROR] Puerto fiscal cerrado. Inicie el facturador o pruebe conexión.", "error")
            return False
        self.log("[COMANDO FISCAL] Imprimiendo Cierre Diario (Reporte Z)...", "warning")
        res = self.call_pnp(self.pnp.PFrepz)
        self.log(f"[REPORTE Z RES] {res}", "success" if "ERROR" not in str(res) else "error")
        return "ERROR" not in str(res)

    def cut_paper(self):
        if self.pnp and hasattr(self.pnp, 'PFCortar'):
            self.call_pnp(self.pnp.PFCortar)
            self.log("[COMANDO] Corte de papel ejecutado.")
            return True
        return False

    def start(self, cfg):
        if self.is_running:
            return
        self.is_running = True
        self.stop_event.clear()
        self.worker_thread = threading.Thread(target=self._run_loop, args=(cfg,), daemon=True)
        self.worker_thread.start()

    def stop(self):
        if not self.is_running:
            return
        self.is_running = False
        self.stop_event.set()
        self.log("[SERVICIO] Deteniendo servicio fiscal...")

    def _run_loop(self, cfg):
        api_url = cfg.get("api_base_url", "").rstrip("/")
        port = cfg.get("serial_port_num", "1")
        machine_serial = cfg.get("machine_serial", "EOM0000310")
        interval = max(int(cfg.get("polling_interval", 5)), 2)

        self.log(f"[SERVICIO] Facturador Fiscal PNP iniciado en COM{port} | Intervalo: {interval}s", "success")

        if not self.open_port(port):
            self.log(f"[AVISO] COM{port} no respondió al inicio. Reintentando en cada ciclo...", "warning")

        while not self.stop_event.is_set():
            try:
                if not self.port_opened:
                    self.open_port(port)

                self._process_invoices(api_url)
                self._process_commands(api_url, machine_serial)
            except Exception as e:
                self.log(f"[ERROR BUCLE] {e}", "error")

            self.stop_event.wait(interval)

        self.log("[SERVICIO] Facturador Fiscal detenido.", "warning")

    def _process_invoices(self, api_url):
        try:
            resp = requests.get(f"{api_url}/fiscal/pending", timeout=10, verify=False)
            if resp.status_code != 200:
                return
            data = resp.json()
            if not data or 'id' not in data:
                return

            invoice_id = data['id']
            if invoice_id in self.processed_invoice_ids:
                return

            details = data.get('details', [])
            total_amount = float(data.get('total_amount', 0.0) or 0.0)

            if not details or len(details) == 0 or total_amount <= 0:
                self.processed_invoice_ids.add(invoice_id)
                requests.patch(f"{api_url}/fiscal/confirm/{invoice_id}", json={"invoice_number": f"ERR_VAC_{invoice_id}"}, verify=False)
                self.log(f"[FACTURA VACIA] ID {invoice_id} descartada por monto o items en 0.", "warning")
                return

            if not self.port_opened:
                self.log("[ERROR FISCAL] Impresora no conectada en puerto COM.", "error")
                return

            self.log(f"[FACTURA] Procesando Factura ID #{invoice_id} | Total: Bs. {total_amount:,.2f}", "info")

            # Abrir documento con Razón Social y RIF
            name = extract_client_name(data)
            rif = extract_client_rif(data)
            self.call_pnp(self.pnp.PFabrefiscal, name, rif)

            # Items
            items_printed = 0
            for detail in details:
                qty_val = float(detail.get('quantity', 0) or 0)
                amt_val = float(detail.get('total_amount', 0) or 0)
                if qty_val <= 0:
                    continue
                d_name = clean_text(detail.get('product_name', 'PRODUCTO'), 20)
                qty = "{:.3f}".format(qty_val)
                is_taxable = detail.get('vat_status') == 1 or detail.get('vat_status') is True
                price_u = amt_val / (1.16 if is_taxable else 1.0) / qty_val
                price = "{:.2f}".format(price_u)
                tax = "1600" if is_taxable else "0000"

                self.log(f"  -> {d_name} | Cant: {qty} | P: {price} | IVA: {tax}")
                r_res = self.call_pnp(self.pnp.PFrenglon, d_name, str(qty), str(price), tax)
                if "ERROR" not in str(r_res):
                    items_printed += 1

            if items_printed == 0:
                self.call_pnp(self.pnp.PFComando, "G")
                if hasattr(self.pnp, 'PFCortar'):
                    self.call_pnp(self.pnp.PFCortar)
                requests.patch(f"{api_url}/fiscal/confirm/{invoice_id}", json={"invoice_number": f"ERR_RNG_{invoice_id}"}, verify=False)
                self.log(f"[ERROR FACTURA] Ningun item pudo ser impreso. Factura cancelada.", "error")
                return

            # IGTF 3% o Cierre estándar
            spe_flag = data.get('spe', 0)
            spe_surcharge = float(data.get('spe_surcharge_amount', 0.0) or 0.0)
            if bool(spe_flag == 1 or spe_surcharge > 0):
                exempt_amt = float(data.get('exempt_amount', 0.0) or 0.0)
                taxable_amt = float(data.get('taxable_amount', 0.0) or 0.0)
                iva_amt = float(data.get('iva_amount', 0.0) or 0.0)
                base_divisa = (exempt_amt + taxable_amt + iva_amt) if (exempt_amt or taxable_amt or iva_amt) else (total_amount - spe_surcharge)
                if base_divisa <= 0:
                    base_divisa = total_amount
                igtf_base_cents = int(round(base_divisa * 100))
                igtf_cmd = f"E|U|{igtf_base_cents}"
                self.log(f"[IGTF 3%] Cierre divisas -> Base: Bs. {base_divisa:,.2f}")
                self.call_pnp(self.pnp.PFComando, igtf_cmd)
            else:
                self.log("[CIERRE] Finalizando ticket fiscal estandar...")
                self.get_res(self.pnp.PFtotal())

            if hasattr(self.pnp, 'PFCortar'):
                self.call_pnp(self.pnp.PFCortar)

            # Obtener número fiscal de la memoria
            inv_num = ""
            if hasattr(self.pnp, 'PFestatus'):
                try:
                    self.pnp.PFestatus(b'N')
                    res_st = self.get_res(self.pnp.PFultimo())
                    if res_st and "ERROR" not in res_st:
                        parts = [p.strip() for p in res_st.replace(',', '|').split('|') if p.strip()]
                        if len(parts) >= 10 and parts[9].isdigit() and int(parts[9]) > 0:
                            inv_num = str(int(parts[9])).zfill(8)
                        elif len(parts) >= 8 and parts[7].isdigit() and int(parts[7]) > 0:
                            inv_num = str(int(parts[7])).zfill(8)
                except Exception:
                    pass

            if not inv_num:
                try:
                    res_close = self.get_res(self.pnp.PFultimo())
                    if res_close and "ERROR" not in res_close:
                        parts = [p.strip() for p in res_close.replace(',', '|').split('|') if p.strip()]
                        if len(parts) >= 4 and parts[3].isdigit() and int(parts[3]) > 0:
                            inv_num = str(int(parts[3])).zfill(8)
                except Exception:
                    pass

            if not inv_num:
                inv_num = f"FAC{invoice_id:06d}"

            self.processed_invoice_ids.add(invoice_id)
            self.invoices_count += 1
            self.last_fiscal_doc = f"#{inv_num}"

            # Confirmar en API
            requests.patch(f"{api_url}/fiscal/confirm/{invoice_id}", json={"invoice_number": inv_num[:20]}, verify=False)
            self.log(f"[OK] Factura confirmada en Tova ERP con Nro Fiscal: {inv_num}", "success")
            self.update_metrics(self.invoices_count, self.last_fiscal_doc)

        except Exception as ex:
            self.log(f"[ERROR FACTURACION] {ex}", "error")

    def _process_commands(self, api_url, machine_serial):
        try:
            resp = requests.get(f"{api_url}/fiscal/commands/pending", timeout=10, verify=False)
            if resp.status_code != 200:
                return
            full_data = resp.json()
            cmd_data = full_data.get('data') if full_data and 'data' in full_data else full_data

            if not cmd_data or 'id' not in cmd_data:
                return

            cmd_id = cmd_data['id']
            cmd_type = cmd_data.get('command')
            payload = cmd_data.get('payload', {})
            self.log(f"[COMANDO PENDIENTE] {cmd_type} (ID: {cmd_id})", "info")

            res_output = "OK"
            status = "success"
            try:
                if cmd_type == "REPORT_Z":
                    res_output = self.call_pnp(self.pnp.PFrepz)
                elif cmd_type == "REPORT_X":
                    res_output = self.call_pnp(self.pnp.PFrepx)
                elif cmd_type == "ANNUL_INVOICE":
                    inv = payload.get('invoice_number', '')
                    res_output = self.call_pnp(self.pnp.PFComando, f"G|{inv}")
                elif cmd_type == "CREDIT_NOTE":
                    inv_orig = str(payload.get('invoice_number', ''))
                    machine_ser = str(payload.get('machine_serial', machine_serial))
                    raw_date = str(payload.get('invoice_date', '')).replace("-", "").replace("/", "")
                    if len(raw_date) == 8:
                        inv_date = raw_date[:4] + raw_date[6:]
                    elif len(raw_date) == 6:
                        inv_date = raw_date
                    else:
                        inv_date = time.strftime("%d%m%y")

                    inv_hour = str(payload.get('invoice_hour', '0000')).replace(":", "")[:4]
                    refund_amt = float(payload.get('refund_amount', 0))
                    client_name = clean_text(payload.get('client_name', 'CLIENTE GENERICO'), 80)
                    client_rif = extract_client_rif(payload)
                    is_taxable = bool(payload.get('is_taxable', True))
                    tax_rate = "1600" if is_taxable else "0000"
                    price_u = refund_amt / 1.16 if is_taxable else refund_amt

                    if hasattr(self.pnp, 'PFDevolucion'):
                        self.call_pnp(self.pnp.PFDevolucion, client_name, client_rif, inv_orig, machine_ser, inv_date, inv_hour)
                    else:
                        self.call_pnp(self.pnp.PFComando, f"@|{client_name}|{client_rif}|{inv_orig}|{machine_ser}|{inv_date}|{inv_hour}|D")

                    self.call_pnp(self.pnp.PFrenglon, f"DEVOLUCION FAC {inv_orig}"[:40], "1.000", "{:.2f}".format(price_u), tax_rate)
                    res_output = self.get_res(self.pnp.PFtotal())

                elif cmd_type == "REPRINT_REPORT_Z":
                    z_num = str(payload.get('z_number', '1'))
                    if hasattr(self.pnp, 'PFRepMemoriaNumero'):
                        res_output = self.call_pnp(self.pnp.PFRepMemoriaNumero, z_num, z_num, "R")
                    else:
                        res_output = self.call_pnp(self.pnp.PFComando, f"U|{z_num}|{z_num}|R")

                if res_output in ["TO", "ER"] or (isinstance(res_output, str) and res_output.startswith("ERROR")):
                    status = "error"
                else:
                    status = "success"

            except Exception as ex:
                res_output = str(ex)
                status = "error"

            requests.patch(f"{api_url}/fiscal/commands/{cmd_id}/confirm", json={"status": status, "response": res_output}, verify=False)
            self.log(f"[COMANDO CONFIRMADO] {cmd_type} -> {status}", "success" if status == "success" else "error")

        except Exception as ex:
            self.log(f"[ERROR COMANDO] {ex}", "error")


# ==============================================================================
# INTERFAZ GRAFICA MODERNA (CUSTOMTKINTER - MODO OSCURO PREMIUM)
# ==============================================================================
class TovaPnpApp(ctk.CTk):
    def __init__(self):
        super().__init__()

        ctk.set_appearance_mode("dark")
        ctk.set_default_color_theme("blue")

        self.title("Tova ERP — Facturador Fiscal PNP 88A")
        self.geometry("900x740")
        self.minsize(860, 680)
        self.configure(fg_color=COLOR_BG_ROOT)

        # Aplicar ícono en barra de título y barra de tareas
        if os.path.exists(ICON_FILE):
            try:
                self.iconbitmap(ICON_FILE)
            except Exception:
                pass

        if os.path.exists(ICON_PNG_FILE):
            try:
                self._icon_img = ImageTk.PhotoImage(file=ICON_PNG_FILE)
                self.iconphoto(True, self._icon_img)
            except Exception:
                pass

        self.cfg = load_config()
        self.fiscal_service = PnpFiscalService(
            self.append_log_threadsafe,
            self.update_metrics_threadsafe,
            self.cfg.get("dll_path", "")
        )

        self._build_ui()

        # Guardar automáticamente al cerrar
        self.protocol("WM_DELETE_WINDOW", self.on_closing)

        self.append_log("Facturador Fiscal PNP 88A inicializado en modo seguro.", "info")
        self.append_log(f"DLL Fiscal: {self.fiscal_service.dll_path}")

        if self.cfg.get("auto_start", False):
            self.after(1000, self.start_service)

    def _build_ui(self):
        # 1. ENCABEZADO SUPERIOR
        header_frame = ctk.CTkFrame(self, fg_color="transparent")
        header_frame.pack(fill="x", padx=24, pady=(18, 10))

        left_header = ctk.CTkFrame(header_frame, fg_color="transparent")
        left_header.pack(side="left", fill="y")

        # Isotipo de Tova
        if os.path.exists(ICON_PNG_FILE):
            try:
                pil_img = Image.open(ICON_PNG_FILE)
                self.logo_ctk = ctk.CTkImage(pil_img, size=(38, 38))
                logo_lbl = ctk.CTkLabel(left_header, image=self.logo_ctk, text="")
                logo_lbl.pack(side="left", padx=(0, 12))
            except Exception:
                pass

        title_box = ctk.CTkFrame(left_header, fg_color="transparent")
        title_box.pack(side="left")

        lbl_title = ctk.CTkLabel(
            title_box,
            text="Facturador Fiscal PNP 88A",
            font=ctk.CTkFont(size=20, weight="bold"),
            text_color=COLOR_TEXT_TITLE
        )
        lbl_title.pack(anchor="w")

        lbl_subtitle = ctk.CTkLabel(
            title_box,
            text="SENIAT Providencia 0071 • IGTF 3% • Impresora Fiscal PFT88A / 80mm",
            font=ctk.CTkFont(size=12),
            text_color=COLOR_TEXT_BODY
        )
        lbl_subtitle.pack(anchor="w")

        # Badge de estado del servicio
        self.status_badge = ctk.CTkLabel(
            header_frame,
            text="● DETENIDO",
            font=ctk.CTkFont(size=12, weight="bold"),
            text_color="#FFFFFF",
            fg_color=COLOR_DANGER,
            corner_radius=14,
            width=120,
            height=28
        )
        self.status_badge.pack(side="right", pady=4)

        # 2. METRICAS / ESTADISTICAS EN VIVO
        metrics_frame = ctk.CTkFrame(self, fg_color="transparent")
        metrics_frame.pack(fill="x", padx=24, pady=6)
        metrics_frame.grid_columnconfigure((0, 1, 2), weight=1, uniform="metric")

        self.card_invoices = self._create_metric_card(metrics_frame, 0, "FACTURAS IMPRESAS", "0 tickets", COLOR_PRIMARY)
        self.card_last_doc = self._create_metric_card(metrics_frame, 1, "ULTIMO NRO FISCAL", "#Sin registrar", COLOR_ACCENT_BLUE)
        self.card_printer_status = self._create_metric_card(metrics_frame, 2, "PUERTO IMPRESORA", f"COM{self.cfg.get('serial_port_num', '1')}", COLOR_WARNING)

        # 3. PANEL DE CONFIGURACION
        config_card = ctk.CTkFrame(self, fg_color=COLOR_CARD, corner_radius=12, border_width=1, border_color=COLOR_CARD_BORDER)
        config_card.pack(fill="x", padx=24, pady=10)

        cfg_inner = ctk.CTkFrame(config_card, fg_color="transparent")
        cfg_inner.pack(fill="both", padx=18, pady=14)

        # Fila 1: URL ERP
        row1 = ctk.CTkFrame(cfg_inner, fg_color="transparent")
        row1.pack(fill="x", pady=(0, 10))

        lbl_url = ctk.CTkLabel(row1, text="Servidor Tova ERP:", font=ctk.CTkFont(size=12, weight="bold"), text_color=COLOR_TEXT_TITLE, width=150, anchor="w")
        lbl_url.pack(side="left")

        self.entry_url = ctk.CTkEntry(
            row1,
            fg_color=COLOR_INPUT_BG,
            border_color=COLOR_INPUT_BORDER,
            text_color=COLOR_TEXT_TITLE,
            height=34,
            corner_radius=8
        )
        self.entry_url.insert(0, self.cfg.get("api_base_url", "https://alkosto.tovaerp.com/api"))
        self.entry_url.pack(side="left", fill="x", expand=True)
        self.entry_url.bind("<FocusOut>", lambda e: self.auto_save())

        # Fila 2: Ruta DLL Fiscal
        row2 = ctk.CTkFrame(cfg_inner, fg_color="transparent")
        row2.pack(fill="x", pady=(0, 10))

        lbl_dll = ctk.CTkLabel(row2, text="Ruta DLL PNP:", font=ctk.CTkFont(size=12, weight="bold"), text_color=COLOR_TEXT_TITLE, width=150, anchor="w")
        lbl_dll.pack(side="left")

        self.entry_dll = ctk.CTkEntry(
            row2,
            fg_color=COLOR_INPUT_BG,
            border_color=COLOR_INPUT_BORDER,
            text_color=COLOR_TEXT_TITLE,
            height=34,
            corner_radius=8
        )
        self.entry_dll.insert(0, self.fiscal_service.dll_path)
        self.entry_dll.pack(side="left", fill="x", expand=True, padx=(0, 8))
        self.entry_dll.bind("<FocusOut>", lambda e: self.on_dll_text_changed())

        btn_browse_dll = ctk.CTkButton(
            row2,
            text="Examinar...",
            font=ctk.CTkFont(size=12),
            fg_color=COLOR_INPUT_BG,
            border_width=1,
            border_color=COLOR_INPUT_BORDER,
            hover_color=COLOR_CARD_BORDER,
            width=90,
            height=34,
            corner_radius=8,
            command=self.browse_dll
        )
        btn_browse_dll.pack(side="left")

        # Fila 3: Puerto Serial COM y Auto-detección
        row3 = ctk.CTkFrame(cfg_inner, fg_color="transparent")
        row3.pack(fill="x", pady=(0, 10))

        lbl_port = ctk.CTkLabel(row3, text="Puerto COM:", font=ctk.CTkFont(size=12, weight="bold"), text_color=COLOR_TEXT_TITLE, width=150, anchor="w")
        lbl_port.pack(side="left")

        ports_list = get_available_com_ports()
        self.combo_port = ctk.CTkComboBox(
            row3,
            values=ports_list,
            fg_color=COLOR_INPUT_BG,
            border_color=COLOR_INPUT_BORDER,
            button_color=COLOR_SECONDARY,
            text_color=COLOR_TEXT_TITLE,
            width=110,
            height=34,
            corner_radius=8,
            command=lambda val: self.on_port_changed(val)
        )
        current_port = f"COM{self.cfg.get('serial_port_num', '1')}".replace("COMCOM", "COM")
        self.combo_port.set(current_port)
        self.combo_port.pack(side="left", padx=(0, 8))

        btn_autodetect = ctk.CTkButton(
            row3,
            text="🔍 Auto-detectar Puerto",
            font=ctk.CTkFont(size=12, weight="bold"),
            fg_color=COLOR_SECONDARY,
            hover_color=COLOR_SECONDARY_HOVER,
            width=165,
            height=34,
            corner_radius=8,
            command=self.auto_detect_printer_port
        )
        btn_autodetect.pack(side="left", padx=(0, 20))

        lbl_serial = ctk.CTkLabel(row3, text="Serial Fiscal:", font=ctk.CTkFont(size=12, weight="bold"), text_color=COLOR_TEXT_TITLE)
        lbl_serial.pack(side="left", padx=(0, 8))

        self.entry_machine_serial = ctk.CTkEntry(
            row3,
            fg_color=COLOR_INPUT_BG,
            border_color=COLOR_INPUT_BORDER,
            text_color=COLOR_TEXT_TITLE,
            width=120,
            height=34,
            corner_radius=8
        )
        self.entry_machine_serial.insert(0, self.cfg.get("machine_serial", "EOM0000310"))
        self.entry_machine_serial.pack(side="left")
        self.entry_machine_serial.bind("<FocusOut>", lambda e: self.auto_save())

        # Fila 4: Intervalo Polling y Auto Inicio
        row4 = ctk.CTkFrame(cfg_inner, fg_color="transparent")
        row4.pack(fill="x")

        lbl_interval = ctk.CTkLabel(row4, text="Frecuencia Polling (seg):", font=ctk.CTkFont(size=12, weight="bold"), text_color=COLOR_TEXT_TITLE, width=150, anchor="w")
        lbl_interval.pack(side="left")

        self.entry_interval = ctk.CTkEntry(
            row4,
            fg_color=COLOR_INPUT_BG,
            border_color=COLOR_INPUT_BORDER,
            text_color=COLOR_TEXT_TITLE,
            width=65,
            height=34,
            corner_radius=8
        )
        self.entry_interval.insert(0, str(self.cfg.get("polling_interval", 5)))
        self.entry_interval.pack(side="left", padx=(0, 25))
        self.entry_interval.bind("<FocusOut>", lambda e: self.auto_save())

        self.switch_auto = ctk.CTkSwitch(
            row4,
            text="Iniciar facturador automáticamente al abrir la app",
            font=ctk.CTkFont(size=12),
            text_color=COLOR_TEXT_TITLE,
            progress_color=COLOR_PRIMARY,
            command=self.auto_save
        )
        if self.cfg.get("auto_start", False):
            self.switch_auto.select()
        self.switch_auto.pack(side="left")

        # 4. BOTONERA DE ACCIONES (Sin botón Guardar Cambios)
        action_frame = ctk.CTkFrame(self, fg_color="transparent")
        action_frame.pack(fill="x", padx=24, pady=6)

        self.btn_toggle_service = ctk.CTkButton(
            action_frame,
            text="▶ INICIAR FACTURADOR",
            font=ctk.CTkFont(size=13, weight="bold"),
            fg_color=COLOR_SUCCESS,
            hover_color="#059669",
            height=36,
            corner_radius=8,
            command=self.toggle_service
        )
        self.btn_toggle_service.pack(side="left", padx=(0, 10))

        self.btn_test = ctk.CTkButton(
            action_frame,
            text="Probar Conexión",
            font=ctk.CTkFont(size=12, weight="bold"),
            fg_color=COLOR_CARD,
            border_width=1,
            border_color=COLOR_CARD_BORDER,
            hover_color=COLOR_SECONDARY,
            height=36,
            corner_radius=8,
            command=self.test_connection
        )
        self.btn_test.pack(side="left", padx=(0, 10))

        self.btn_report_x = ctk.CTkButton(
            action_frame,
            text="Reporte X",
            font=ctk.CTkFont(size=12),
            fg_color=COLOR_CARD,
            border_width=1,
            border_color=COLOR_CARD_BORDER,
            hover_color="#1E293B",
            height=36,
            corner_radius=8,
            command=self.run_report_x
        )
        self.btn_report_x.pack(side="left", padx=(0, 10))

        self.btn_report_z = ctk.CTkButton(
            action_frame,
            text="Cierre Z",
            font=ctk.CTkFont(size=12),
            fg_color=COLOR_CARD,
            border_width=1,
            border_color=COLOR_CARD_BORDER,
            hover_color="#7F1D1D",
            height=36,
            corner_radius=8,
            command=self.run_report_z
        )
        self.btn_report_z.pack(side="left", padx=(0, 10))

        self.btn_cut = ctk.CTkButton(
            action_frame,
            text="Cortar Papel",
            font=ctk.CTkFont(size=12),
            fg_color=COLOR_CARD,
            border_width=1,
            border_color=COLOR_CARD_BORDER,
            hover_color="#1E293B",
            height=36,
            corner_radius=8,
            command=self.fiscal_service.cut_paper
        )
        self.btn_cut.pack(side="left", padx=(0, 10))

        btn_clear_icon = ctk.CTkButton(
            action_frame,
            text="🗑",
            font=ctk.CTkFont(size=16),
            fg_color=COLOR_CARD,
            border_width=1,
            border_color=COLOR_CARD_BORDER,
            text_color=COLOR_TEXT_TITLE,
            hover_color="#334155",
            height=36,
            width=42,
            corner_radius=8,
            command=self.clear_logs
        )
        btn_clear_icon.pack(side="left")

        # 5. CONSOLA DE AUDITORIA Y ACTIVIDAD
        console_card = ctk.CTkFrame(self, fg_color=COLOR_CARD, corner_radius=12, border_width=1, border_color=COLOR_CARD_BORDER)
        console_card.pack(fill="both", expand=True, padx=24, pady=(8, 18))

        console_header = ctk.CTkFrame(console_card, fg_color="transparent")
        console_header.pack(fill="x", padx=16, pady=(10, 4))

        lbl_log_title = ctk.CTkLabel(
            console_header,
            text="ACTIVIDAD DEL FACTURADOR FISCAL EN VIVO",
            font=ctk.CTkFont(size=11, weight="bold"),
            text_color=COLOR_TEXT_BODY
        )
        lbl_log_title.pack(side="left")

        btn_clear_top = ctk.CTkButton(
            console_header,
            text="🗑 Limpiar",
            font=ctk.CTkFont(size=11),
            fg_color="transparent",
            text_color=COLOR_TEXT_BODY,
            hover_color=COLOR_INPUT_BG,
            height=24,
            width=80,
            corner_radius=6,
            command=self.clear_logs
        )
        btn_clear_top.pack(side="right")

        self.txt_console = ctk.CTkTextbox(
            console_card,
            fg_color=COLOR_CONSOLE_BG,
            text_color=COLOR_CONSOLE_TEXT,
            font=ctk.CTkFont(family="Consolas", size=11),
            corner_radius=8,
            activate_scrollbars=True
        )
        self.txt_console.pack(fill="both", expand=True, padx=14, pady=(0, 14))

    def _create_metric_card(self, parent, col, title, initial_val, accent_color):
        card = ctk.CTkFrame(parent, fg_color=COLOR_CARD, corner_radius=10, border_width=1, border_color=COLOR_CARD_BORDER)
        card.grid(row=0, column=col, sticky="nsew", padx=6, pady=4)

        lbl_t = ctk.CTkLabel(card, text=title, font=ctk.CTkFont(size=10, weight="bold"), text_color=COLOR_TEXT_BODY)
        lbl_t.pack(anchor="w", padx=14, pady=(10, 2))

        lbl_v = ctk.CTkLabel(card, text=initial_val, font=ctk.CTkFont(size=18, weight="bold"), text_color=accent_color)
        lbl_v.pack(anchor="w", padx=14, pady=(0, 10))
        return lbl_v

    def append_log(self, text, kind="normal"):
        timestamp = time.strftime("[%H:%M:%S]")
        formatted = f"{timestamp} {text}\n"
        self.txt_console.insert("end", formatted)
        self.txt_console.see("end")

    def append_log_threadsafe(self, text, kind="normal"):
        self.after(0, lambda: self.append_log(text, kind))

    def update_metrics_threadsafe(self, count, last_doc):
        self.after(0, lambda: self._update_metrics(count, last_doc))

    def _update_metrics(self, count, last_doc):
        self.card_invoices.configure(text=f"{count} tickets")
        self.card_last_doc.configure(text=str(last_doc))

    def clear_logs(self):
        self.txt_console.delete("1.0", "end")

    def get_current_settings(self):
        port_clean = self.combo_port.get().replace("COM", "").replace("com", "").strip()
        return {
            "api_base_url": self.entry_url.get().strip(),
            "api_token": self.cfg.get("api_token", ""),
            "dll_path": self.entry_dll.get().strip(),
            "serial_port_num": port_clean if port_clean else "1",
            "machine_serial": self.entry_machine_serial.get().strip(),
            "polling_interval": int(self.entry_interval.get().strip() or "5"),
            "auto_start": bool(self.switch_auto.get())
        }

    def auto_save(self):
        try:
            cfg = self.get_current_settings()
            save_config(cfg)
            self.cfg = cfg
        except Exception:
            pass

    def on_port_changed(self, value):
        self.card_printer_status.configure(text=str(value), text_color=COLOR_WARNING)
        self.auto_save()

    def on_dll_text_changed(self):
        path = self.entry_dll.get().strip()
        if path and os.path.exists(path):
            ok = self.fiscal_service.set_dll_path(path)
            if ok:
                self.auto_save()
                self.append_log(f"Librería DLL actualizada: {path}", "success")
            else:
                self.append_log(f"No se pudo cargar la DLL desde: {path}", "error")

    def browse_dll(self):
        selected = filedialog.askopenfilename(
            title="Seleccionar Librería Fiscal PNP (DLL)",
            filetypes=[("Librería Dinámica (*.dll)", "*.dll"), ("Todos los archivos", "*.*")],
            initialdir=BASE_DIR
        )
        if selected:
            self.entry_dll.delete(0, "end")
            self.entry_dll.insert(0, selected)
            ok = self.fiscal_service.set_dll_path(selected)
            if ok:
                self.auto_save()
                self.append_log(f"Librería DLL seleccionada y cargada: {selected}", "success")
                messagebox.showinfo("DLL Cargada", f"Librería DLL vinculada con éxito:\n\n{selected}")
            else:
                self.append_log(f"Error cargando DLL seleccionada: {selected}", "error")
                messagebox.showerror("Error", f"No se pudo inicializar la DLL desde:\n{selected}")

    def auto_detect_printer_port(self):
        self.append_log("[AUTO-DETECT] Escaneando puertos COM disponibles en Windows...", "info")
        ports = get_available_com_ports()

        def _scan():
            detected_port = None
            for p in ports:
                p_num = p.replace("COM", "").replace("com", "").strip()
                self.append_log_threadsafe(f"  -> Probando comunicación en {p}...")
                try:
                    if self.fiscal_service.open_port(p_num, silent=True):
                        if hasattr(self.fiscal_service.pnp, 'PFestatus'):
                            self.fiscal_service.pnp.PFestatus(b'N')
                            st = self.fiscal_service.get_res(self.fiscal_service.pnp.PFultimo())
                            if st and "ERROR" not in st and len(st) > 4:
                                detected_port = p
                                break
                except Exception:
                    pass

            if detected_port:
                self.after(0, lambda: self._apply_detected_port(detected_port, ports))
            else:
                self.after(0, lambda: self._handle_detection_failure(ports))

        threading.Thread(target=_scan, daemon=True).start()

    def _apply_detected_port(self, port, ports):
        self.combo_port.configure(values=ports)
        self.combo_port.set(port)
        self.card_printer_status.configure(text=str(port), text_color=COLOR_SUCCESS)
        self.auto_save()
        self.append_log(f"[AUTO-DETECT] ¡Impresora fiscal PNP detectada en {port}!", "success")
        messagebox.showinfo("Impresora Detectada", f"Se detectó respuesta exitosa de la impresora fiscal en {port}.")

    def _handle_detection_failure(self, ports):
        self.combo_port.configure(values=ports)
        if ports:
            self.append_log(f"[AUTO-DETECT] Puertos COM en Windows: {', '.join(ports)}. La impresora no respondió en ninguno.", "warning")
            messagebox.showwarning(
                "Sin Respuesta Fiscal",
                f"Puertos COM encontrados: {', '.join(ports)}.\n\nLa impresora fiscal no respondió.\nVerifique que esté encendida, con papel y el cable USB/Serial conectado."
            )
        else:
            self.append_log("[AUTO-DETECT] No se encontraron puertos COM registrados en Windows.", "error")
            messagebox.showerror("Sin Puertos COM", "No se detectaron puertos seriales COM en Windows.")

    def toggle_service(self):
        if self.fiscal_service.is_running:
            self.stop_service()
        else:
            self.start_service()

    def start_service(self):
        cfg = self.get_current_settings()
        self.cfg = cfg
        save_config(cfg)

        self.btn_toggle_service.configure(text="⏹ DETENER FACTURADOR", fg_color=COLOR_DANGER, hover_color="#DC2626")
        self.status_badge.configure(text="● EN LÍNEA", fg_color=COLOR_SUCCESS)
        self.card_printer_status.configure(text=f"COM{cfg['serial_port_num']}", text_color=COLOR_SUCCESS)

        self.fiscal_service.start(cfg)

    def stop_service(self):
        self.fiscal_service.stop()
        self.btn_toggle_service.configure(text="▶ INICIAR FACTURADOR", fg_color=COLOR_SUCCESS, hover_color="#059669")
        self.status_badge.configure(text="● DETENIDO", fg_color=COLOR_DANGER)
        self.card_printer_status.configure(text="Detenido", text_color=COLOR_WARNING)

    def test_connection(self):
        cfg = self.get_current_settings()
        self.append_log("Iniciando prueba de conectividad y hardware fiscal...", "info")

        def _worker():
            res = self.fiscal_service.test_connection(cfg)
            self.append_log_threadsafe("\n" + res)

        threading.Thread(target=_worker, daemon=True).start()

    def run_report_x(self):
        threading.Thread(target=self.fiscal_service.print_x_report, daemon=True).start()

    def run_report_z(self):
        confirm = messagebox.askyesno(
            "Confirmación de Cierre Z",
            "¿Está seguro de emitir el Reporte Z (Cierre Diario)?\n\nEsta acción cierra las operaciones del día en la memoria fiscal."
        )
        if confirm:
            threading.Thread(target=self.fiscal_service.print_z_report, daemon=True).start()

    def on_closing(self):
        self.auto_save()
        if self.fiscal_service.is_running:
            self.fiscal_service.stop()
        self.destroy()


if __name__ == "__main__":
    app = TovaPnpApp()
    app.mainloop()
