import os
import sys
import time
import json
import requests
import urllib3
import a2_extractor_service as a2

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# ==============================================================================
# PUENTE AUTÓNOMO a2 SOFTWAY (DBISAM) -> TOVA ERP
# ==============================================================================

CONFIG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "a2_bridge_config.json")

def load_config():
    default_config = {
        "api_base_url": "https://ensalud.tovaerp.com/api",
        "api_token": "",
        "a2_data_dir": r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data",
        "polling_interval": 3,
        "filter_year": 2026
    }
    if os.path.exists(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, "r") as f:
                saved = json.load(f)
                default_config.update(saved)
        except:
            pass
    return default_config

def save_config(cfg):
    try:
        with open(CONFIG_FILE, "w") as f:
            json.dump(cfg, f, indent=4)
    except:
        pass

def poll_and_process():
    cfg = load_config()
    data_dir = cfg["a2_data_dir"]
    api_url = cfg["api_base_url"].rstrip('/')
    token = cfg.get("api_token", "")
    target_year = cfg.get("filter_year", 2026)
    
    headers = {
        "Accept": "application/json",
        "User-Agent": "TovaERP-A2Bridge/1.0"
    }
    if token:
        headers["Authorization"] = f"Bearer {token}"
        
    print(f"=== INICIANDO PUENTE a2 SOFTWAY (DBISAM) ===")
    print(f"Directorio de Datos a2: {data_dir}")
    print(f"Conectando con: {api_url}")
    print(f"Filtro Año Operativo: {target_year}")
    print("Esperando solicitudes de extracción desde la nube o sincronización...")

    while True:
        try:
            # 1. Consultar tareas pendientes solicitadas desde la vista de Importar Datos
            poll_endpoint = f"{api_url}/configuration/data-import/a2/poll-tasks"
            resp = requests.get(poll_endpoint, headers=headers, timeout=10, verify=False)
            
            if resp.status_code == 200:
                data = resp.json()
                task = data.get("task")
                if task and task.get("id"):
                    task_id = task["id"]
                    section = task.get("section", "all")
                    year = task.get("year", target_year)
                    print(f"\n[SOLICITUD RECIBIDA] Tarea #{task_id} - Seccion: {section} - Año: {year}")
                    
                    extracted = {}
                    if section in ["suppliers_payables", "all"]:
                        print(" -> Extrayendo Proveedores y Cuentas por Pagar...")
                        extracted["suppliers"] = a2.extract_suppliers(data_dir)
                        extracted["payables"] = a2.extract_payables(data_dir, year)
                    if section in ["products_lots", "all"]:
                        print(" -> Extrayendo Productos e Inventario...")
                        extracted["products"] = a2.extract_products(data_dir)
                    if section in ["operations", "sales", "all"]:
                        print(f" -> Extrayendo Operaciones del año {year}...")
                        extracted["operations"] = a2.extract_operations(data_dir, year)
                    if section in ["clients", "receivables", "all"]:
                        print(" -> Extrayendo Clientes y Cuentas por Cobrar...")
                        extracted["clients"] = a2.extract_clients(data_dir)
                        extracted["receivables"] = a2.extract_receivables(data_dir, year)
                        
                    # 2. Responder con los datos a la API para generar la previsualización
                    submit_endpoint = f"{api_url}/configuration/data-import/a2/submit-task/{task_id}"
                    sub_resp = requests.post(
                        submit_endpoint,
                        json={"status": "completed", "data": extracted},
                        headers=headers,
                        timeout=30,
                        verify=False
                    )
                    if sub_resp.status_code == 200:
                        print(f"[OK] Datos de la Tarea #{task_id} enviados con exito.")
                    else:
                        print(f"[ERROR] Al enviar tarea: {sub_resp.status_code} - {sub_resp.text}")
        except requests.exceptions.ConnectionError:
            pass
        except Exception as e:
            print(f"[ERROR] En loop de polling: {e}")
            
        time.sleep(cfg.get("polling_interval", 3))

if __name__ == '__main__':
    poll_and_process()
