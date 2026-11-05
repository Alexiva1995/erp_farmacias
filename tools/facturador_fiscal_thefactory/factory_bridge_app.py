import os
import sys
import time
import json
import threading
import socket
import subprocess
import requests
import urllib3
import re
from PIL import Image, ImageTk

# Registrar App ID de Windows para mostrar icono oficial de Tova en barra de tareas
try:
    myappid = 'tovaerp.facturador.fiscal.thefactory.1.0'
    ctypes_shell = __import__('ctypes').windll.shell32
    ctypes_shell.SetCurrentProcessExplicitAppUserModelID(myappid)
except Exception:
    pass

import customtkinter as ctk
from tkinter import filedialog, messagebox

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
CONFIG_FILE = os.path.join(BASE_DIR, "factory_bridge_config.json")
ICON_FILE = get_resource_path("tova.ico")
ICON_PNG_FILE = get_resource_path("icon.png")

def find_default_listener_path():
    candidates = [
        os.path.join(BASE_DIR, "FACTORY", "TFHKA Venezuela - PHP SDK", "TFHKA Venezuela - WEB PHP con TCP SDK", "Socket TCPIP", "DemoTCPIP-PHP", "bin", "Debug", "DemoTCPIP-PHP.exe"),
        os.path.join(BASE_DIR, "DemoTCPIP-PHP.exe"),
        r"C:\DemoTCPIP-PHP\DemoTCPIP-PHP.exe",
        r"C:\TFHKA\DemoTCPIP-PHP.exe"
    ]
    for c in candidates:
        if os.path.exists(c):
            return c
    return candidates[0]

def load_config():
    default_cfg = {
        "api_base_url": "https://ensalud.tovaerp.com/api",
        "api_token": "",
        "tcp_port": 8090,
        "polling_interval": 3,
        "listener_exe": find_default_listener_path(),
        "auto_start_listener": True,
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
# MOTOR DEL PUENTE FISCAL THE FACTORY HKA (SOCKET TCP PROTOCOL)
# ==============================================================================
def get_local_ip() -> str:
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.settimeout(0.5)
        s.connect(('8.8.8.8', 80))
        local_ip = s.getsockname()[0]
        s.close()
        return local_ip
    except Exception:
        return "127.0.0.1"

def clean_text(text: str, max_len: int = 40) -> str:
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
    integer_part = int(amount)
    decimal_part = int(round((amount - integer_part) * 100))
    if decimal_part >= 100:
        integer_part += 1
        decimal_part = 0
    return f"{integer_part:014d},{decimal_part:02d}"

def format_gf_qty(qty: float) -> str:
    integer_part = int(qty)
    decimal_part = int(round((qty - integer_part) * 1000))
    if decimal_part >= 1000:
        integer_part += 1
        decimal_part = 0
    return f"{integer_part:014d},{decimal_part:03d}"


class TheFactoryFiscalService:
    def __init__(self, log_callback, update_metrics_callback):
        self.log = log_callback
        self.update_metrics = update_metrics_callback
        self.is_running = False
        self.stop_event = threading.Event()
        self.worker_thread = None
        self.processed_invoice_ids = set()
        self.invoices_count = 0
        self.last_fiscal_doc = "#Sin registrar"
        self.tcp_port = 8090
        self.listener_process = None

    def start_listener(self, listener_path, silent=False):
        if not listener_path or not os.path.exists(listener_path):
            if not silent:
                self.log(f"[LISTENER ERROR] No se encontró el ejecutable en: {listener_path}", "error")
            return False

        # Verificar si ya está corriendo en Windows
        try:
            tasks = subprocess.check_output('tasklist /FI "IMAGENAME eq DemoTCPIP-PHP.exe"', shell=True).decode('latin-1', errors='ignore')
            if "DemoTCPIP-PHP.exe" in tasks:
                if not silent:
                    self.log("[LISTENER HKA] DemoTCPIP-PHP ya se encuentra en ejecución en segundo plano.", "info")
                return True
        except Exception:
            pass

        try:
            self.log(f"[LISTENER HKA] Lanzando {os.path.basename(listener_path)}...", "info")
            self.listener_process = subprocess.Popen(
                [listener_path],
                cwd=os.path.dirname(listener_path),
                shell=False
            )
            time.sleep(2)
            self.log("[LISTENER HKA] Servicio iniciado exitosamente en puerto 8090.", "success")
            return True
        except Exception as ex:
            self.log(f"[LISTENER ERROR] Falló al iniciar el proceso: {ex}", "error")
            return False

    def is_listener_running(self):
        try:
            tasks = subprocess.check_output('tasklist /FI "IMAGENAME eq DemoTCPIP-PHP.exe"', shell=True).decode('latin-1', errors='ignore')
            return "DemoTCPIP-PHP.exe" in tasks
        except Exception:
            return False

    def send_raw_socket(self, payload: str, timeout: int = 5) -> str:
        target_ips = ["127.0.0.1", get_local_ip(), "localhost"]
        last_error = ""

        for target_ip in target_ips:
            try:
                with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
                    s.settimeout(timeout)
                    s.connect((target_ip, self.tcp_port))
                    raw_data = (payload + "\0").encode("latin-1", errors="replace")
                    s.sendall(raw_data)
                    response = s.recv(4096)
                    return response.decode("latin-1", errors="replace").strip("\0").strip()
            except Exception as e:
                last_error = f"{e} ({target_ip}:{self.tcp_port})"
                continue

        return f"ERROR: {last_error}"

    def check_printer_present(self) -> bool:
        res = self.send_raw_socket("CheckFprinter():1", timeout=3)
        if "Resultado:True" in res or (len(res) >= 11 and res[10] == "T"):
            return True
        return False

    def get_fp_status(self) -> dict:
        res = self.send_raw_socket("ReadFpStatus():1", timeout=3)
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

    def execute_hka_cmd(self, cmd: str, timeout: int = 5) -> bool:
        res = self.send_raw_socket(f"SendCmd():{cmd}", timeout=timeout)
        if "Resultado:True" in res or (len(res) >= 11 and res[10] == "T"):
            return True

        st = self.get_fp_status()
        err_code = str(st.get('error_code', '')).strip()
        err_desc = st.get('error_desc', '')

        # Si es Reporte Z y dio 137 (timeout mientras la impresora corta e imprime)
        if cmd == "I0Z" and (err_code == "137" or "No Answer" in err_desc):
            time.sleep(3)
            if self.check_printer_present():
                self.log("[AVISO HKA] Reporte Z emitido (impresora ocupada durante corte). Estado recuperado.", "warning")
                return True

        err_detail = f"{err_desc} [Cod: {err_code}]" if err_desc else res
        self.log(f"[CMD RECHAZADO] '{cmd}' | Error: {err_detail}", "error")
        return False

    def get_status_s1(self) -> dict:
        res = self.send_raw_socket("UploadStatus():S1", timeout=4)
        if not res or res.startswith("ERROR"):
            return {}
        payload = res[10:] if res.startswith("Resultado:") else res
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

    def test_connection(self, cfg):
        results = []
        url = cfg.get("api_base_url", "").rstrip("/")
        self.tcp_port = int(cfg.get("tcp_port", 8090))

        # 1. Servidor Tova ERP
        try:
            resp = requests.get(f"{url}/fiscal/pending", timeout=6, verify=False)
            if resp.status_code in [200, 404]:
                results.append("✓ Servidor Tova ERP: Conectado (HTTP " + str(resp.status_code) + ")")
            else:
                results.append(f"✗ Servidor Tova ERP: HTTP {resp.status_code}")
        except Exception as e:
            results.append(f"✗ Servidor Tova ERP: Error ({e})")

        # 2. Listener DemoTCPIP
        listener_ok = self.is_listener_running()
        if listener_ok:
            results.append(f"✓ Listener The Factory (DemoTCPIP-PHP.exe): En ejecución en segundo plano")
        else:
            results.append("✗ Listener The Factory: No se encuentra en ejecución. Use '🚀 Iniciar Listener HKA'")

        # 3. Impresora Fiscal
        if self.check_printer_present():
            results.append(f"✓ Impresora Fiscal HKA: Conectada y respondiendo en puerto TCP {self.tcp_port}")
            s1 = self.get_status_s1()
            if s1.get('machine_serial'):
                results.append(f"  Serial Fiscal: {s1.get('machine_serial')} | RIF: {s1.get('rif')} | Última Factura: #{s1.get('last_invoice')}")
        else:
            results.append(f"✗ Impresora Fiscal HKA: Sin respuesta en puerto TCP {self.tcp_port}")

        return "\n".join(results)

    def print_x_report(self):
        self.log("[COMANDO FISCAL] Imprimiendo Reporte X...", "info")
        ok = self.execute_hka_cmd("I0X")
        if ok:
            time.sleep(2)
            self.log("[OK] Reporte X emitido exitosamente.", "success")
        return ok

    def print_z_report(self):
        self.log("[COMANDO FISCAL] Imprimiendo Cierre Diario (Reporte Z)...", "warning")
        ok = self.execute_hka_cmd("I0Z", timeout=45)
        if ok:
            time.sleep(3)
            self.log("[OK] Cierre Diario (Reporte Z) finalizado.", "success")
        return ok

    def cancel_current_document(self):
        self.log("[COMANDO FISCAL] Enviando anulación de documento en buffer (Comando 7)...", "warning")
        ok = self.execute_hka_cmd("7")
        if ok:
            self.log("[OK] Buffer de la impresora fiscal liberado.", "success")
        return ok

    def start(self, cfg):
        if self.is_running:
            return
        self.is_running = True
        self.stop_event.clear()
        self.tcp_port = int(cfg.get("tcp_port", 8090))
        self.worker_thread = threading.Thread(target=self._run_loop, args=(cfg,), daemon=True)
        self.worker_thread.start()

    def stop(self):
        if not self.is_running:
            return
        self.is_running = False
        self.stop_event.set()
        self.log("[SERVICIO] Deteniendo facturador The Factory...")

    def _run_loop(self, cfg):
        api_url = cfg.get("api_base_url", "").rstrip("/")
        listener_path = cfg.get("listener_exe", "")
        auto_listener = cfg.get("auto_start_listener", True)
        interval = max(int(cfg.get("polling_interval", 3)), 2)

        self.log(f"[SERVICIO] Facturador The Factory HKA activo | Puerto TCP: {self.tcp_port} | Intervalo: {interval}s", "success")

        # Iniciar listener automáticamente si está habilitado y no está corriendo
        if auto_listener and not self.is_listener_running():
            self.start_listener(listener_path, silent=False)

        while not self.stop_event.is_set():
            try:
                self._process_invoices(api_url)
                self._process_commands(api_url)
            except Exception as e:
                self.log(f"[ERROR BUCLE] {e}", "error")

            self.stop_event.wait(interval)

        self.log("[SERVICIO] Facturador The Factory HKA detenido.", "warning")

    def _process_invoices(self, api_url):
        try:
            resp = requests.get(f"{api_url}/fiscal/pending", timeout=10, verify=False)
            if resp.status_code != 200:
                return
            data = resp.json()
            if not data or not isinstance(data, dict) or 'id' not in data:
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

            if not self.check_printer_present():
                self.log("[ALERTA] La impresora fiscal The Factory no responde en socket TCP.", "error")
                return

            self.log(f"[FACTURA] Procesando Factura #{invoice_id} | Total: Bs. {total_amount:,.2f}", "info")

            # Liberar documento atascado si el status previo es 5
            st_prev = self.get_fp_status()
            if str(st_prev.get('status_code')) == "5":
                self.log("[AUTO-RECUPERACION] Documento pendiente detectado. Anulando (cmd 7)...", "warning")
                self.execute_hka_cmd("7")

            # Detectar modalidad crédito
            order_data = data.get('order') or {}
            payment_methods = order_data.get('payment_methods') if isinstance(order_data, dict) else []
            if not isinstance(payment_methods, list):
                payment_methods = []

            is_credit = any(
                str((pm or {}).get('method', '')).strip().lower() in ['credit', 'credito', 'crédito']
                for pm in payment_methods
                if isinstance(pm, dict)
            )

            if is_credit:
                self.log("[PAGO] Modalidad Crédito detectada -> Descriptor PE16CREDITO...")
                self.execute_hka_cmd("PE16CREDITO")

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
            if is_credit:
                cmds.append("i02CONDICION DE PAGO: CREDITO")

            # 2. Renglones de Productos
            for detail in details:
                qty = float(detail.get('quantity', 1) or 1)
                item_total = float(detail.get('total_amount', 0) or 0)
                unit_price = item_total / qty if qty > 0 else item_total

                is_taxable = (detail.get('vat_status') == 1 or detail.get('vat_status') is True or float(detail.get('iva_amount', 0) or 0) > 0)
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

            # 3. Enviar comandos
            all_ok = True
            for c in cmds:
                if not self.execute_hka_cmd(c):
                    all_ok = False
                    break

            # 4. Medios de Pago y Cierre
            if all_ok:
                spe_raw = str(data.get('spe', '')).lower()
                spe_amount = float(data.get('spe_surcharge_amount', 0.0) or 0.0)
                is_spe = spe_raw in ['1', 'true'] or spe_amount > 0

                if is_credit and not is_spe:
                    self.log("[PAGO] Modalidad Crédito en Bs -> Aplicando 116...")
                    if not self.execute_hka_cmd("116"):
                        all_ok = self.execute_hka_cmd("101")
                elif is_spe:
                    self.log("[PAGO] Divisas / SPE -> Aplicando 120 (IGTF 3%)...")
                    all_ok = self.execute_hka_cmd("120")
                else:
                    self.log("[PAGO] Efectivo Bolívares -> Aplicando 101...")
                    all_ok = self.execute_hka_cmd("101")

                if all_ok:
                    self.execute_hka_cmd("199")

            if all_ok:
                time.sleep(1)
                s1 = self.get_status_s1()
                last_inv = s1.get('last_invoice') or f"FAC-{invoice_id}"
                m_serial = s1.get('machine_serial') or "THEFACTORY"

                self.processed_invoice_ids.add(invoice_id)
                self.invoices_count += 1
                self.last_fiscal_doc = f"#{last_inv}"

                requests.patch(
                    f"{api_url}/fiscal/confirm/{invoice_id}",
                    json={"invoice_number": str(last_inv)},
                    verify=False,
                    timeout=10
                )
                self.log(f"[OK] Factura #{last_inv} confirmada en el ERP. Serial: {m_serial}", "success")
                self.update_metrics(self.invoices_count, self.last_fiscal_doc)
            else:
                self.log(f"[ERROR] No se pudo imprimir la factura #{invoice_id}. Anulando ticket...", "error")
                self.execute_hka_cmd("7")

        except Exception as ex:
            self.log(f"[ERROR FACTURACION] {ex}", "error")

    def _process_commands(self, api_url):
        try:
            resp = requests.get(f"{api_url}/fiscal/commands/pending", timeout=10, verify=False)
            if resp.status_code != 200:
                return
            full_data = resp.json()
            cmd_data = full_data.get('data') if full_data and 'data' in full_data else full_data

            if not cmd_data or not isinstance(cmd_data, dict) or 'id' not in cmd_data:
                return

            cmd_id = cmd_data['id']
            cmd_type = cmd_data.get('command')
            payload = cmd_data.get('payload', {}) or {}
            self.log(f"[COMANDO PENDIENTE] {cmd_type} (ID: {cmd_id})", "info")

            res_output = "OK"
            status = "success"
            try:
                if cmd_type == "REPORT_X":
                    if not self.execute_hka_cmd("I0X"):
                        status = "error"
                        res_output = "Fallo al ejecutar Reporte X"
                    else:
                        time.sleep(3)
                elif cmd_type == "REPORT_Z":
                    if not self.execute_hka_cmd("I0Z", timeout=45):
                        status = "error"
                        res_output = "Fallo al ejecutar Reporte Z"
                    else:
                        time.sleep(5)
                elif cmd_type == "CREDIT_NOTE":
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
                        if not self.execute_hka_cmd(c):
                            all_ok = False
                            break
                    if not all_ok:
                        status = "error"
                        res_output = "Fallo al emitir Nota de Credito"

                elif cmd_type == "REPRINT_REPORT_Z":
                    z_num = f"{int(str(payload.get('z_number', '1')).strip()):07d}"
                    if not self.execute_hka_cmd(f"RZ{z_num}{z_num}"):
                        status = "error"
                        res_output = "Fallo al reimprimir Reporte Z"

            except Exception as ex:
                status = "error"
                res_output = str(ex)

            requests.patch(
                f"{api_url}/fiscal/commands/{cmd_id}/confirm",
                json={"status": status, "response": res_output},
                verify=False
            )
            self.log(f"[COMANDO CONFIRMADO] {cmd_type} -> {status}", "success" if status == "success" else "error")

        except Exception as ex:
            self.log(f"[ERROR COMANDO] {ex}", "error")


# ==============================================================================
# INTERFAZ GRAFICA MODERNA (CUSTOMTKINTER - MODO OSCURO PREMIUM)
# ==============================================================================
class TovaFactoryApp(ctk.CTk):
    def __init__(self):
        super().__init__()

        ctk.set_appearance_mode("dark")
        ctk.set_default_color_theme("blue")

        self.title("Tova ERP — Facturador Fiscal The Factory HKA")
        self.geometry("900x740")
        self.minsize(860, 680)
        self.configure(fg_color=COLOR_BG_ROOT)

        # Ícono oficial en barra de título y barra de tareas
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
        self.fiscal_service = TheFactoryFiscalService(
            self.append_log_threadsafe,
            self.update_metrics_threadsafe
        )

        self._build_ui()

        # Guardar automáticamente al cerrar
        self.protocol("WM_DELETE_WINDOW", self.on_closing)

        self.append_log("Facturador Fiscal The Factory HKA inicializado en modo seguro.", "info")
        self.append_log(f"Listener HKA: {self.cfg.get('listener_exe', '')}")

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
            text="Facturador Fiscal The Factory HKA",
            font=ctk.CTkFont(size=20, weight="bold"),
            text_color=COLOR_TEXT_TITLE
        )
        lbl_title.pack(anchor="w")

        lbl_subtitle = ctk.CTkLabel(
            title_box,
            text="Bixolon • Dascom • SRP-812 • HKA112 • Protocolo TCP/IP :8090 • Prov. 0071",
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
        self.card_printer_status = self._create_metric_card(metrics_frame, 2, "ESTADO SERVICIO", "Puerto TCP 8090", COLOR_WARNING)

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
        self.entry_url.insert(0, self.cfg.get("api_base_url", "https://ensalud.tovaerp.com/api"))
        self.entry_url.pack(side="left", fill="x", expand=True)
        self.entry_url.bind("<FocusOut>", lambda e: self.auto_save())

        # Fila 2: Ruta Listener The Factory HKA (DemoTCPIP-PHP.exe)
        row2 = ctk.CTkFrame(cfg_inner, fg_color="transparent")
        row2.pack(fill="x", pady=(0, 10))

        lbl_listener = ctk.CTkLabel(row2, text="Listener The Factory:", font=ctk.CTkFont(size=12, weight="bold"), text_color=COLOR_TEXT_TITLE, width=150, anchor="w")
        lbl_listener.pack(side="left")

        self.entry_listener = ctk.CTkEntry(
            row2,
            fg_color=COLOR_INPUT_BG,
            border_color=COLOR_INPUT_BORDER,
            text_color=COLOR_TEXT_TITLE,
            height=34,
            corner_radius=8
        )
        self.entry_listener.insert(0, self.cfg.get("listener_exe", ""))
        self.entry_listener.pack(side="left", fill="x", expand=True, padx=(0, 8))
        self.entry_listener.bind("<FocusOut>", lambda e: self.auto_save())

        btn_browse_listener = ctk.CTkButton(
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
            command=self.browse_listener
        )
        btn_browse_listener.pack(side="left")

        # Fila 3: Puerto TCP y Frecuencia Polling
        row3 = ctk.CTkFrame(cfg_inner, fg_color="transparent")
        row3.pack(fill="x", pady=(0, 10))

        lbl_tcp = ctk.CTkLabel(row3, text="Puerto Socket TCP:", font=ctk.CTkFont(size=12, weight="bold"), text_color=COLOR_TEXT_TITLE, width=150, anchor="w")
        lbl_tcp.pack(side="left")

        self.entry_tcp_port = ctk.CTkEntry(
            row3,
            fg_color=COLOR_INPUT_BG,
            border_color=COLOR_INPUT_BORDER,
            text_color=COLOR_TEXT_TITLE,
            width=80,
            height=34,
            corner_radius=8
        )
        self.entry_tcp_port.insert(0, str(self.cfg.get("tcp_port", 8090)))
        self.entry_tcp_port.pack(side="left", padx=(0, 20))
        self.entry_tcp_port.bind("<FocusOut>", lambda e: self.auto_save())

        lbl_interval = ctk.CTkLabel(row3, text="Frecuencia Polling (seg):", font=ctk.CTkFont(size=12, weight="bold"), text_color=COLOR_TEXT_TITLE)
        lbl_interval.pack(side="left", padx=(0, 8))

        self.entry_interval = ctk.CTkEntry(
            row3,
            fg_color=COLOR_INPUT_BG,
            border_color=COLOR_INPUT_BORDER,
            text_color=COLOR_TEXT_TITLE,
            width=65,
            height=34,
            corner_radius=8
        )
        self.entry_interval.insert(0, str(self.cfg.get("polling_interval", 3)))
        self.entry_interval.pack(side="left")
        self.entry_interval.bind("<FocusOut>", lambda e: self.auto_save())

        # Fila 4: Switches de automatización
        row4 = ctk.CTkFrame(cfg_inner, fg_color="transparent")
        row4.pack(fill="x")

        self.switch_auto_listener = ctk.CTkSwitch(
            row4,
            text="Auto-iniciar Listener HKA (DemoTCPIP-PHP) al conectar",
            font=ctk.CTkFont(size=12),
            text_color=COLOR_TEXT_TITLE,
            progress_color=COLOR_SUCCESS,
            command=self.auto_save
        )
        if self.cfg.get("auto_start_listener", True):
            self.switch_auto_listener.select()
        self.switch_auto_listener.pack(side="left", padx=(0, 25))

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

        # 4. BOTONERA DE ACCIONES (Con botón dedicado para iniciar Listener HKA)
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

        # Botón dedicado para iniciar el servicio/listener de conexión con la impresora
        self.btn_launch_listener = ctk.CTkButton(
            action_frame,
            text="🚀 Iniciar Listener HKA",
            font=ctk.CTkFont(size=12, weight="bold"),
            fg_color=COLOR_SECONDARY,
            hover_color=COLOR_SECONDARY_HOVER,
            height=36,
            corner_radius=8,
            command=self.launch_listener_manual
        )
        self.btn_launch_listener.pack(side="left", padx=(0, 10))

        self.btn_test = ctk.CTkButton(
            action_frame,
            text="Probar Conexión",
            font=ctk.CTkFont(size=12, weight="bold"),
            fg_color=COLOR_CARD,
            border_width=1,
            border_color=COLOR_CARD_BORDER,
            hover_color="#1E293B",
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

        self.btn_cancel_doc = ctk.CTkButton(
            action_frame,
            text="Anular Doc Actual",
            font=ctk.CTkFont(size=12),
            fg_color=COLOR_CARD,
            border_width=1,
            border_color=COLOR_CARD_BORDER,
            hover_color="#1E293B",
            height=36,
            corner_radius=8,
            command=self.fiscal_service.cancel_current_document
        )
        self.btn_cancel_doc.pack(side="left", padx=(0, 10))

        # Botón compacto de ícono para limpiar registros
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
        return {
            "api_base_url": self.entry_url.get().strip(),
            "api_token": self.cfg.get("api_token", ""),
            "listener_exe": self.entry_listener.get().strip(),
            "tcp_port": int(self.entry_tcp_port.get().strip() or "8090"),
            "polling_interval": int(self.entry_interval.get().strip() or "3"),
            "auto_start_listener": bool(self.switch_auto_listener.get()),
            "auto_start": bool(self.switch_auto.get())
        }

    def auto_save(self):
        try:
            cfg = self.get_current_settings()
            save_config(cfg)
            self.cfg = cfg
        except Exception:
            pass

    def browse_listener(self):
        selected = filedialog.askopenfilename(
            title="Seleccionar Ejecutable de Listener The Factory HKA",
            filetypes=[("Ejecutables (*.exe)", "*.exe"), ("Todos los archivos", "*.*")],
            initialdir=BASE_DIR
        )
        if selected:
            self.entry_listener.delete(0, "end")
            self.entry_listener.insert(0, selected)
            self.auto_save()
            self.append_log(f"Ruta de listener HKA configurada: {selected}", "success")

    def launch_listener_manual(self):
        path = self.entry_listener.get().strip()
        threading.Thread(target=lambda: self.fiscal_service.start_listener(path, silent=False), daemon=True).start()

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
        self.card_printer_status.configure(text=f"TCP :{cfg['tcp_port']}", text_color=COLOR_SUCCESS)

        self.fiscal_service.start(cfg)

    def stop_service(self):
        self.fiscal_service.stop()
        self.btn_toggle_service.configure(text="▶ INICIAR FACTURADOR", fg_color=COLOR_SUCCESS, hover_color="#059669")
        self.status_badge.configure(text="● DETENIDO", fg_color=COLOR_DANGER)
        self.card_printer_status.configure(text="Detenido", text_color=COLOR_WARNING)

    def test_connection(self):
        cfg = self.get_current_settings()
        self.append_log("Iniciando prueba de conectividad con Tova ERP y The Factory HKA...", "info")

        def _worker():
            res = self.fiscal_service.test_connection(cfg)
            self.append_log_threadsafe("\n" + res)

        threading.Thread(target=_worker, daemon=True).start()

    def run_report_x(self):
        threading.Thread(target=self.fiscal_service.print_x_report, daemon=True).start()

    def run_report_z(self):
        confirm = messagebox.askyesno(
            "Confirmación de Cierre Z",
            "¿Está seguro de emitir el Reporte Z (Cierre Diario)?\n\nEsta acción cierra las ventas del día en la memoria fiscal."
        )
        if confirm:
            threading.Thread(target=self.fiscal_service.print_z_report, daemon=True).start()

    def on_closing(self):
        self.auto_save()
        if self.fiscal_service.is_running:
            self.fiscal_service.stop()
        self.destroy()


if __name__ == "__main__":
    app = TovaFactoryApp()
    app.mainloop()
