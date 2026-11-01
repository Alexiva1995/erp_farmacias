import os
import sys
import time
import json
import threading
import requests
import urllib3
import importlib
from PIL import Image

import customtkinter as ctk
from tkinter import filedialog, messagebox

import a2_extractor_service as a2

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# ==============================================================================
# PALETA OFICIAL TOVA ERP — MODO OSCURO PREMIUM
# ==============================================================================
COLOR_PRIMARY = "#E20074"          # Fucsia / Magenta Tova
COLOR_PRIMARY_HOVER = "#C50064"    # Fucsia oscuro hover
COLOR_SECONDARY = "#7A0099"        # Morado corporativo
COLOR_SECONDARY_HOVER = "#62007A"
COLOR_SUCCESS = "#10B981"          # Verde esmeralda brillante
COLOR_DANGER = "#EF4444"           # Rojo alerta
COLOR_ACCENT_BLUE = "#38BDF8"      # Azul información / métrica

COLOR_BG_ROOT = "#0B0F19"          # Fondo principal negro-azulado profundo
COLOR_CARD = "#151D2E"             # Fondo de tarjetas elevadas
COLOR_CARD_BORDER = "#222F45"      # Borde sutil de tarjeta
COLOR_INPUT_BG = "#0D1424"         # Fondo de campos de texto
COLOR_INPUT_BORDER = "#27354D"     # Borde campos
COLOR_TEXT_TITLE = "#F8FAFC"       # Texto blanco nítido
COLOR_TEXT_BODY = "#94A3B8"        # Texto secundario gris slate
COLOR_CONSOLE_BG = "#080C14"       # Consola ultra oscura
COLOR_CONSOLE_TEXT = "#CBD5E1"

def get_base_dir():
    if getattr(sys, 'frozen', False):
        return os.path.dirname(sys.executable)
    return os.path.dirname(os.path.abspath(__file__))

BASE_DIR = get_base_dir()
CONFIG_FILE = os.path.join(BASE_DIR, "a2_bridge_config.json")
CHECKPOINT_FILE = os.path.join(BASE_DIR, "a2_stream_checkpoint.json")
ICON_FILE = os.path.join(BASE_DIR, "tova.ico")
LOGO_FILE = os.path.join(BASE_DIR, "logo.png")

def load_config():
    default_cfg = {
        "api_base_url": "https://alkosto.tovaerp.com/api",
        "api_token": "",
        "a2_data_dir": r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data",
        "polling_interval": 3,
        "stream_interval": 15,
        "stream_enabled": True,
        "filter_year": 2026
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

def load_checkpoint():
    default_cp = {
        "last_sale_doc": "",
        "last_sync_time": "",
        "stream_enabled": True
    }
    if os.path.exists(CHECKPOINT_FILE):
        try:
            with open(CHECKPOINT_FILE, "r", encoding="utf-8") as f:
                saved = json.load(f)
                default_cp.update(saved)
        except Exception:
            pass
    return default_cp

def save_checkpoint(cp):
    try:
        with open(CHECKPOINT_FILE, "w", encoding="utf-8") as f:
            json.dump(cp, f, indent=4)
    except Exception:
        pass


class TovaModernBridgeApp(ctk.CTk):
    def __init__(self):
        super().__init__()

        # Modo oscuro permanente de alta definición
        ctk.set_appearance_mode("dark")
        ctk.set_default_color_theme("blue")

        self.title("Tova ERP — Conector a2 Softway")
        self.geometry("880x740")
        self.minsize(820, 680)
        self.configure(fg_color=COLOR_BG_ROOT)

        if os.path.exists(ICON_FILE):
            try:
                self.iconbitmap(ICON_FILE)
            except Exception:
                pass

        self.cfg = load_config()
        self.is_running = False
        self.worker_thread = None
        self.synced_sales_count = 0
        self.synced_items_count = 0

        self._build_interface()
        self.log("Conector a2 Softway inicializado correctamente en modo seguro.", "SUCCESS")

    def _build_interface(self):
        self.grid_columnconfigure(0, weight=1)
        self.grid_rowconfigure(3, weight=1)

        # -------------------------------------------------------------
        # 1. ENCABEZADO CON LOGO OFICIAL TOVA + SOFTWARE CONECTADO
        # -------------------------------------------------------------
        header = ctk.CTkFrame(
            self,
            corner_radius=0,
            height=80,
            fg_color=COLOR_CARD,
            border_width=1,
            border_color=COLOR_CARD_BORDER
        )
        header.grid(row=0, column=0, sticky="ew")
        header.grid_propagate(False)

        header.columnconfigure(0, weight=1)
        header.columnconfigure(1, weight=0)

        left_box = ctk.CTkFrame(header, fg_color="transparent")
        left_box.pack(side="left", padx=24, pady=12)

        # Intentar cargar el logo corporativo de Tova (PNG con fondo transparente)
        has_logo = False
        if os.path.exists(LOGO_FILE):
            try:
                raw_img = Image.open(LOGO_FILE)
                # Mantener proporción aproximada
                aspect = raw_img.width / raw_img.height
                target_h = 32
                target_w = int(target_h * aspect)
                logo_ctk = ctk.CTkImage(light_image=raw_img, dark_image=raw_img, size=(target_w, target_h))
                logo_label = ctk.CTkLabel(left_box, image=logo_ctk, text="")
                logo_label.pack(side="left", padx=(0, 16))
                has_logo = True
            except Exception:
                pass

        if not has_logo:
            fallback_logo = ctk.CTkLabel(
                left_box,
                text="tova",
                font=ctk.CTkFont(family="Segoe UI", size=24, weight="bold"),
                text_color=COLOR_PRIMARY
            )
            fallback_logo.pack(side="left", padx=(0, 16))

        # Divisor vertical sutil
        div_bar = ctk.CTkFrame(left_box, width=1, height=36, fg_color=COLOR_CARD_BORDER)
        div_bar.pack(side="left", padx=(0, 16))

        # Título y subtítulo del software que se conecta
        title_box = ctk.CTkFrame(left_box, fg_color="transparent")
        title_box.pack(side="left")

        app_title = ctk.CTkLabel(
            title_box,
            text="Conector a2 Softway",
            font=ctk.CTkFont(family="Segoe UI", size=16, weight="bold"),
            text_color=COLOR_TEXT_TITLE
        )
        app_title.pack(anchor="w")

        app_desc = ctk.CTkLabel(
            title_box,
            text="Sincronización continua de ventas, clientes y catálogo de inventario",
            font=ctk.CTkFont(family="Segoe UI", size=11),
            text_color=COLOR_TEXT_BODY
        )
        app_desc.pack(anchor="w")

        # Píldora de estado en la esquina derecha
        self.status_pill = ctk.CTkLabel(
            header,
            text="● DETENIDO",
            font=ctk.CTkFont(family="Segoe UI", size=11, weight="bold"),
            fg_color="#36171D",
            text_color=COLOR_DANGER,
            corner_radius=20,
            padx=18,
            pady=7
        )
        self.status_pill.pack(side="right", padx=24, pady=18)

        # -------------------------------------------------------------
        # 2. MÉTRICAS CLAVE EN TARJETAS (KPIs)
        # -------------------------------------------------------------
        kpi_row = ctk.CTkFrame(self, fg_color="transparent")
        kpi_row.grid(row=1, column=0, sticky="ew", padx=24, pady=(16, 6))
        kpi_row.columnconfigure(0, weight=1)
        kpi_row.columnconfigure(1, weight=1)
        kpi_row.columnconfigure(2, weight=1)

        # KPI 1: Ventas en la Nube
        kpi1 = ctk.CTkFrame(kpi_row, corner_radius=12, fg_color=COLOR_CARD, border_width=1, border_color=COLOR_CARD_BORDER)
        kpi1.grid(row=0, column=0, sticky="ew", padx=(0, 10))
        ctk.CTkLabel(kpi1, text="VENTAS SUBIDAS AL ERP", font=ctk.CTkFont(size=10, weight="bold"), text_color=COLOR_TEXT_BODY).pack(anchor="w", padx=16, pady=(12, 2))
        self.kpi_sales = ctk.CTkLabel(kpi1, text="0 ventas", font=ctk.CTkFont(family="Segoe UI", size=20, weight="bold"), text_color=COLOR_PRIMARY)
        self.kpi_sales.pack(anchor="w", padx=16, pady=(0, 12))

        # KPI 2: Ítems Descontados
        kpi2 = ctk.CTkFrame(kpi_row, corner_radius=12, fg_color=COLOR_CARD, border_width=1, border_color=COLOR_CARD_BORDER)
        kpi2.grid(row=0, column=1, sticky="ew", padx=5)
        ctk.CTkLabel(kpi2, text="PRODUCTOS DESCONTADOS", font=ctk.CTkFont(size=10, weight="bold"), text_color=COLOR_TEXT_BODY).pack(anchor="w", padx=16, pady=(12, 2))
        self.kpi_items = ctk.CTkLabel(kpi2, text="0 items", font=ctk.CTkFont(family="Segoe UI", size=20, weight="bold"), text_color=COLOR_ACCENT_BLUE)
        self.kpi_items.pack(anchor="w", padx=16, pady=(0, 12))

        # KPI 3: Último Documento Sincronizado
        cp = load_checkpoint()
        last_doc_init = cp.get("last_sale_doc") or "Sin registrar"
        kpi3 = ctk.CTkFrame(kpi_row, corner_radius=12, fg_color=COLOR_CARD, border_width=1, border_color=COLOR_CARD_BORDER)
        kpi3.grid(row=0, column=2, sticky="ew", padx=(10, 0))
        ctk.CTkLabel(kpi3, text="ÚLTIMO DOC a2 SINCRONIZADO", font=ctk.CTkFont(size=10, weight="bold"), text_color=COLOR_TEXT_BODY).pack(anchor="w", padx=16, pady=(12, 2))
        self.kpi_doc = ctk.CTkLabel(kpi3, text=f"#{last_doc_init}", font=ctk.CTkFont(family="Segoe UI", size=20, weight="bold"), text_color=COLOR_SUCCESS)
        self.kpi_doc.pack(anchor="w", padx=16, pady=(0, 12))

        # -------------------------------------------------------------
        # 3. CARD DE CONFIGURACIÓN Y CONTROLES
        # -------------------------------------------------------------
        config_card = ctk.CTkFrame(
            self,
            corner_radius=12,
            fg_color=COLOR_CARD,
            border_width=1,
            border_color=COLOR_CARD_BORDER
        )
        config_card.grid(row=2, column=0, sticky="ew", padx=24, pady=12)
        config_card.columnconfigure(1, weight=1)

        # Fila 1: URL del ERP
        lbl_url = ctk.CTkLabel(config_card, text="Servidor Tova ERP:", font=ctk.CTkFont(size=12, weight="bold"), text_color=COLOR_TEXT_TITLE)
        lbl_url.grid(row=0, column=0, sticky="w", padx=20, pady=(16, 6))

        self.url_input = ctk.CTkEntry(
            config_card,
            height=38,
            corner_radius=8,
            font=ctk.CTkFont(size=12),
            fg_color=COLOR_INPUT_BG,
            border_color=COLOR_INPUT_BORDER,
            text_color=COLOR_TEXT_TITLE,
            placeholder_text="https://alkosto.tovaerp.com/api"
        )
        self.url_input.insert(0, self.cfg.get("api_base_url", "https://alkosto.tovaerp.com/api"))
        self.url_input.grid(row=0, column=1, columnspan=2, sticky="ew", padx=(0, 20), pady=(16, 6))

        # Fila 2: Carpeta de a2
        lbl_dir = ctk.CTkLabel(config_card, text="Directorio a2 (Data):", font=ctk.CTkFont(size=12, weight="bold"), text_color=COLOR_TEXT_TITLE)
        lbl_dir.grid(row=1, column=0, sticky="w", padx=20, pady=6)

        self.dir_input = ctk.CTkEntry(
            config_card,
            height=38,
            corner_radius=8,
            font=ctk.CTkFont(size=12),
            fg_color=COLOR_INPUT_BG,
            border_color=COLOR_INPUT_BORDER,
            text_color=COLOR_TEXT_TITLE,
            placeholder_text=r"C:\a2\Empre001\Data"
        )
        self.dir_input.insert(0, self.cfg.get("a2_data_dir", ""))
        self.dir_input.grid(row=1, column=1, sticky="ew", padx=(0, 10), pady=6)

        browse_btn = ctk.CTkButton(
            config_card,
            text="Examinar...",
            width=110,
            height=38,
            corner_radius=8,
            font=ctk.CTkFont(size=12, weight="bold"),
            fg_color="#1E2A3F",
            text_color=COLOR_TEXT_TITLE,
            hover_color="#2A3B57",
            command=self._browse_directory
        )
        browse_btn.grid(row=1, column=2, sticky="e", padx=(0, 20), pady=6)

        # Fila 3: Parámetros del streaming
        params_row = ctk.CTkFrame(config_card, fg_color="transparent")
        params_row.grid(row=2, column=0, columnspan=3, sticky="ew", padx=20, pady=(8, 16))

        ctk.CTkLabel(params_row, text="Año Operativo:", font=ctk.CTkFont(size=11, weight="bold"), text_color=COLOR_TEXT_BODY).pack(side="left", padx=(0, 6))
        self.year_input = ctk.CTkEntry(params_row, width=70, height=32, corner_radius=6, fg_color=COLOR_INPUT_BG, border_color=COLOR_INPUT_BORDER, text_color=COLOR_TEXT_TITLE, font=ctk.CTkFont(size=11))
        self.year_input.insert(0, str(self.cfg.get("filter_year", 2026)))
        self.year_input.pack(side="left", padx=(0, 20))

        ctk.CTkLabel(params_row, text="Frecuencia Polling (seg):", font=ctk.CTkFont(size=11, weight="bold"), text_color=COLOR_TEXT_BODY).pack(side="left", padx=(0, 6))
        self.interval_input = ctk.CTkEntry(params_row, width=60, height=32, corner_radius=6, fg_color=COLOR_INPUT_BG, border_color=COLOR_INPUT_BORDER, text_color=COLOR_TEXT_TITLE, font=ctk.CTkFont(size=11))
        self.interval_input.insert(0, str(self.cfg.get("stream_interval", 15)))
        self.interval_input.pack(side="left", padx=(0, 25))

        self.stream_switch = ctk.CTkSwitch(
            params_row,
            text="Sincronización continua de ventas en tiempo real",
            font=ctk.CTkFont(size=11, weight="bold"),
            text_color=COLOR_TEXT_TITLE,
            progress_color=COLOR_PRIMARY
        )
        if self.cfg.get("stream_enabled", True):
            self.stream_switch.select()
        else:
            self.stream_switch.deselect()
        self.stream_switch.pack(side="left")

        # Fila 4: Botonera con jerarquía visual clara
        btn_bar = ctk.CTkFrame(config_card, fg_color="transparent")
        btn_bar.grid(row=3, column=0, columnspan=3, sticky="ew", padx=20, pady=(0, 18))

        # Acción Primaria: Fucsia oficial Tova
        self.main_btn = ctk.CTkButton(
            btn_bar,
            text="▶  INICIAR SERVICIO",
            height=42,
            width=220,
            corner_radius=9,
            font=ctk.CTkFont(family="Segoe UI", size=13, weight="bold"),
            fg_color=COLOR_PRIMARY,
            hover_color=COLOR_PRIMARY_HOVER,
            command=self._toggle_service
        )
        self.main_btn.pack(side="left", padx=(0, 14))

        # Acción Secundaria: Probar Conexión
        test_btn = ctk.CTkButton(
            btn_bar,
            text="Probar Conexión",
            height=42,
            width=150,
            corner_radius=9,
            font=ctk.CTkFont(size=12, weight="bold"),
            fg_color="#1E2A3F",
            text_color=COLOR_TEXT_TITLE,
            hover_color="#2A3B57",
            command=self._test_connection
        )
        test_btn.pack(side="left", padx=(0, 12))

        # Guardar Configuración
        save_btn = ctk.CTkButton(
            btn_bar,
            text="Guardar Cambios",
            height=42,
            width=150,
            corner_radius=9,
            font=ctk.CTkFont(size=12, weight="bold"),
            fg_color="transparent",
            border_width=1,
            border_color=COLOR_CARD_BORDER,
            text_color=COLOR_TEXT_BODY,
            hover_color="#1E2A3F",
            command=self._save_settings_action
        )
        save_btn.pack(side="left")

        # Limpiar
        clear_btn = ctk.CTkButton(
            btn_bar,
            text="Limpiar Registro",
            height=42,
            width=110,
            corner_radius=9,
            font=ctk.CTkFont(size=11),
            fg_color="transparent",
            text_color=COLOR_TEXT_BODY,
            hover_color="#1E2A3F",
            command=self._clear_logs
        )
        clear_btn.pack(side="right")

        # -------------------------------------------------------------
        # 4. CONSOLA DE REGISTRO EN VIVO (Terminal Dark)
        # -------------------------------------------------------------
        console_frame = ctk.CTkFrame(
            self,
            corner_radius=12,
            fg_color=COLOR_CARD,
            border_width=1,
            border_color=COLOR_CARD_BORDER
        )
        console_frame.grid(row=3, column=0, sticky="nsew", padx=24, pady=(4, 20))
        console_frame.columnconfigure(0, weight=1)
        console_frame.rowconfigure(1, weight=1)

        console_bar = ctk.CTkFrame(console_frame, fg_color="transparent")
        console_bar.grid(row=0, column=0, sticky="ew", padx=18, pady=(10, 6))

        ctk.CTkLabel(
            console_bar,
            text="ACTIVIDAD DEL CONECTOR EN VIVO",
            font=ctk.CTkFont(family="Segoe UI", size=11, weight="bold"),
            text_color=COLOR_TEXT_BODY
        ).pack(side="left")

        self.console_box = ctk.CTkTextbox(
            console_frame,
            corner_radius=8,
            font=ctk.CTkFont(family="Consolas", size=11),
            fg_color=COLOR_CONSOLE_BG,
            text_color=COLOR_CONSOLE_TEXT,
            wrap="word"
        )
        self.console_box.grid(row=1, column=0, sticky="nsew", padx=14, pady=(0, 14))

    # -----------------------------------------------------------------
    # MÉTODOS Y EVENTOS
    # -----------------------------------------------------------------
    def log(self, message, tag="INFO"):
        now_str = time.strftime("%H:%M:%S")
        prefix = f"[{now_str}] "
        self.console_box.insert("end", f"{prefix}{message}\n")
        self.console_box.see("end")

    def _clear_logs(self):
        self.console_box.delete("1.0", "end")

    def _browse_directory(self):
        chosen = filedialog.askdirectory(initialdir=self.dir_input.get().strip() or "C:\\")
        if chosen:
            norm = os.path.normpath(chosen)
            self.dir_input.delete(0, "end")
            self.dir_input.insert(0, norm)

    def _save_settings(self):
        self.cfg["api_base_url"] = self.url_input.get().strip().rstrip('/')
        self.cfg["a2_data_dir"] = self.dir_input.get().strip()

        try:
            self.cfg["filter_year"] = int(self.year_input.get().strip())
        except ValueError:
            self.cfg["filter_year"] = 2026

        try:
            self.cfg["stream_interval"] = max(5, int(self.interval_input.get().strip()))
        except ValueError:
            self.cfg["stream_interval"] = 15

        self.cfg["stream_enabled"] = bool(self.stream_switch.get())
        return save_config(self.cfg)

    def _save_settings_action(self):
        if self._save_settings():
            self.log("Parámetros guardados exitosamente.", "SUCCESS")
            messagebox.showinfo("Configuración", "Preferencias guardadas en a2_bridge_config.json")
        else:
            self.log("Error al escribir el archivo de configuración.", "ERROR")
            messagebox.showerror("Error", "No se pudo guardar la configuración.")

    def _test_connection(self):
        url = self.url_input.get().strip().rstrip('/')
        endpoint = f"{url}/configuration/data-import/a2/poll-tasks"
        self.log(f"Comprobando enlace con {endpoint}...")

        def _run():
            try:
                resp = requests.get(endpoint, timeout=8, verify=False)
                if resp.status_code == 200:
                    self.after(0, lambda: self.log("¡Conexión verificada con Tova ERP (HTTP 200)! Servidor listo.", "SUCCESS"))
                    self.after(0, lambda: messagebox.showinfo("Conexión Exitosa", "Se estableció enlace con Tova ERP sin problemas."))
                else:
                    self.after(0, lambda c=resp.status_code: self.log(f"Respuesta del servidor: HTTP {c}.", "ERROR"))
                    self.after(0, lambda c=resp.status_code: messagebox.showwarning("Aviso", f"El servidor respondió con código {c}."))
            except Exception as e:
                self.after(0, lambda err=str(e): self.log(f"Fallo de conexión: {err}", "ERROR"))
                self.after(0, lambda err=str(e): messagebox.showerror("Error de Red", f"No se pudo conectar al ERP:\n{err}"))

        threading.Thread(target=_run, daemon=True).start()

    def _toggle_service(self):
        if self.is_running:
            self._stop_service()
        else:
            self._start_service()

    def _start_service(self):
        data_dir = self.dir_input.get().strip()
        if not os.path.exists(data_dir):
            messagebox.showerror("Directorio No Encontrado", f"La ruta de datos de a2 especificada no existe:\n{data_dir}")
            return

        self._save_settings()
        self.is_running = True

        self.main_btn.configure(
            text="⏹  DETENER SERVICIO",
            fg_color=COLOR_DANGER,
            hover_color="#DC2626"
        )
        self.status_pill.configure(
            text="● EN LÍNEA",
            fg_color="#064E3B",
            text_color=COLOR_SUCCESS
        )
        self.log(f"Servicio iniciado. Streaming automático cada {self.cfg.get('stream_interval', 15)} segundos.", "SUCCESS")

        self.worker_thread = threading.Thread(target=self._worker_loop, daemon=True)
        self.worker_thread.start()

    def _stop_service(self):
        self.is_running = False

        self.main_btn.configure(
            text="▶  INICIAR SERVICIO",
            fg_color=COLOR_PRIMARY,
            hover_color=COLOR_PRIMARY_HOVER
        )
        self.status_pill.configure(
            text="● DETENIDO",
            fg_color="#36171D",
            text_color=COLOR_DANGER
        )
        self.log("Servicio detenido por el usuario.", "INFO")

    def _worker_loop(self):
        last_stream_time = 0

        while self.is_running:
            api_url = self.cfg["api_base_url"].rstrip('/')
            data_dir = self.cfg["a2_data_dir"]
            target_year = self.cfg.get("filter_year", 2026)
            stream_interval = self.cfg.get("stream_interval", 15)
            headers = {"Accept": "application/json", "User-Agent": "TovaSyncA2-Pro/2.0"}

            # 1. Tareas de Importación Web
            try:
                poll_url = f"{api_url}/configuration/data-import/a2/poll-tasks"
                resp = requests.get(poll_url, headers=headers, timeout=10, verify=False)
                if resp.status_code == 200:
                    task = resp.json().get("task")
                    if task and task.get("id"):
                        tid = task["id"]
                        sec = task.get("section", "all")
                        yr = task.get("year", target_year)
                        self.after(0, lambda m=f"Petición web #{tid} detectada ({sec}). Extrayendo...": self.log(m, "INFO"))

                        try:
                            importlib.reload(a2)
                        except Exception:
                            pass

                        extracted = {}
                        if sec in ["suppliers_payables", "all"]:
                            extracted["suppliers"] = a2.extract_suppliers(data_dir)
                            extracted["payables"] = a2.extract_payables(data_dir, yr)
                        if sec in ["products_lots", "all"]:
                            extracted["products"] = a2.extract_products(data_dir)
                        if sec in ["operations", "sales", "all"]:
                            ops = a2.extract_operations(data_dir, yr)
                            extracted["operations"] = ops
                            extracted["sales"] = [o for o in ops if o.get("op_type") == 2817]
                        if sec in ["clients", "receivables", "clients_receivables", "all"]:
                            extracted["clients"] = a2.extract_clients(data_dir)
                            extracted["receivables"] = a2.extract_receivables(data_dir, yr)

                        sub_url = f"{api_url}/configuration/data-import/a2/submit-task/{tid}"
                        sub_resp = requests.post(sub_url, json={"status": "completed", "data": extracted}, headers=headers, timeout=30, verify=False)
                        if sub_resp.status_code == 200:
                            self.after(0, lambda: self.log(f"Petición web #{tid} completada y cargada a la nube.", "SUCCESS"))
            except Exception:
                pass

            # 2. Streaming Continuo de Ventas e Inventario
            now = time.time()
            if self.cfg.get("stream_enabled", True) and (now - last_stream_time >= stream_interval):
                last_stream_time = now
                cp = load_checkpoint()
                last_doc = cp.get("last_sale_doc", "")

                try:
                    new_sales = a2.extract_sales_stream(data_dir, target_year, min_doc=last_doc)
                    if new_sales:
                        batch = new_sales[:100]
                        f_doc = batch[0]["document"]
                        l_doc = batch[-1]["document"]
                        self.after(0, lambda m=f"Detectadas {len(batch)} ventas en a2 (Docs #{f_doc} a #{l_doc}). Sincronizando...": self.log(m, "INFO"))

                        st_url = f"{api_url}/configuration/data-import/a2/stream-sync"
                        st_resp = requests.post(
                            st_url,
                            json={"sales": batch, "checkpoint": {"last_doc": l_doc}},
                            headers=headers,
                            timeout=60,
                            verify=False
                        )
                        if st_resp.status_code == 200:
                            cp["last_sale_doc"] = l_doc
                            cp["last_sync_time"] = time.strftime("%Y-%m-%d %H:%M:%S")
                            save_checkpoint(cp)

                            res = st_resp.json().get("data", {}).get("stats", {})
                            synced = res.get("sales_synced", 0)
                            items = res.get("order_details_created", 0)
                            self.synced_sales_count += synced
                            self.synced_items_count += items

                            self.after(0, lambda m=f"✓ Sincronizadas {synced} ventas ({items} items descontados) hasta doc #{l_doc}.": self.log(m, "SUCCESS"))
                            self.after(0, lambda s=self.synced_sales_count: self.kpi_sales.configure(text=f"{s} ventas"))
                            self.after(0, lambda it=self.synced_items_count: self.kpi_items.configure(text=f"{it} items"))
                            self.after(0, lambda d=l_doc: self.kpi_doc.configure(text=f"#{d}"))
                        else:
                            self.after(0, lambda c=st_resp.status_code: self.log(f"Error de sincronización con servidor: HTTP {c}", "ERROR"))
                except Exception as se:
                    self.after(0, lambda e=str(se): self.log(f"Fallo en lectura de ventas a2: {e}", "ERROR"))

            time.sleep(self.cfg.get("polling_interval", 3))


if __name__ == "__main__":
    app = TovaModernBridgeApp()
    app.mainloop()
