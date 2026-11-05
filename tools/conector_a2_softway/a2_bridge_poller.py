import os
import sys
import time
import json
import requests
import urllib3
import importlib
import a2_extractor_service as a2

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# ==============================================================================
# PUENTE AUTÓNOMO a2 SOFTWAY (DBISAM) -> TOVA ERP
# ==============================================================================

CONFIG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "a2_bridge_config.json")
CHECKPOINT_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "a2_stream_checkpoint.json")

def load_config():
    default_config = {
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
            with open(CONFIG_FILE, "r") as f:
                saved = json.load(f)
                default_config.update(saved)
        except Exception:
            pass
    return default_config

def save_config(cfg):
    try:
        with open(CONFIG_FILE, "w") as f:
            json.dump(cfg, f, indent=4)
    except Exception:
        pass

def load_checkpoint():
    default_cp = {
        "last_sale_doc": "",
        "last_sync_time": "",
        "stream_enabled": True
    }
    if os.path.exists(CHECKPOINT_FILE):
        try:
            with open(CHECKPOINT_FILE, "r") as f:
                saved = json.load(f)
                default_cp.update(saved)
        except Exception:
            pass
    return default_cp

def save_checkpoint(cp):
    try:
        with open(CHECKPOINT_FILE, "w") as f:
            json.dump(cp, f, indent=4)
    except Exception:
        pass

def poll_and_process():
    cfg = load_config()
    data_dir = cfg["a2_data_dir"]
    api_url = cfg["api_base_url"].rstrip('/')
    token = cfg.get("api_token", "")
    target_year = cfg.get("filter_year", 2026)
    stream_interval = cfg.get("stream_interval", 15)
    
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
    print(f"Streaming en tiempo real activo: cada {stream_interval}s")
    print("Esperando solicitudes de extracción o nuevas ventas en a2...")

    last_stream_time = 0

    while True:
        try:
            # 1. Consultar tareas manuales solicitadas desde la interfaz web (previsualización/importación)
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
                    
                    try:
                        importlib.reload(a2)
                    except Exception:
                        pass
                    
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
                        all_ops = a2.extract_operations(data_dir, year)
                        extracted["operations"] = all_ops
                        extracted["sales"] = [op for op in all_ops if op.get("op_type") == 2817]
                    if section in ["clients", "receivables", "clients_receivables", "all"]:
                        print(" -> Extrayendo Clientes y Cuentas por Cobrar...")
                        extracted["clients"] = a2.extract_clients(data_dir)
                        extracted["receivables"] = a2.extract_receivables(data_dir, year)
                        
                    # Responder con los datos a la API para generar la previsualización
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
            print(f"[ERROR] En loop de tareas manuales: {e}")

        # 2. Polling continuo de Streaming en segundo plano (ventas, clientes, stock)
        now = time.time()
        if now - last_stream_time >= stream_interval:
            last_stream_time = now
            cp = load_checkpoint()
            if cp.get("stream_enabled", True) and cfg.get("stream_enabled", True):
                last_doc = cp.get("last_sale_doc", "")
                try:
                    new_sales = a2.extract_sales_stream(data_dir, target_year, min_doc=last_doc)
                    if new_sales:
                        # Limitar por batch para no saturar memoria en transferencias masivas
                        batch_sales = new_sales[:100]
                        print(f"\n[STREAM DETECTADO] Sincronizando {len(batch_sales)} ventas nuevas desde doc #{batch_sales[0]['document']} hasta #{batch_sales[-1]['document']}...")
                        stream_endpoint = f"{api_url}/configuration/data-import/a2/stream-sync"
                        stream_resp = requests.post(
                            stream_endpoint,
                            json={
                                "sales": batch_sales,
                                "checkpoint": {"last_doc": batch_sales[-1]["document"]}
                            },
                            headers=headers,
                            timeout=60,
                            verify=False
                        )
                        if stream_resp.status_code == 200:
                            last_synced_doc = batch_sales[-1]["document"]
                            cp["last_sale_doc"] = last_synced_doc
                            cp["last_sync_time"] = time.strftime("%Y-%m-%d %H:%M:%S")
                            save_checkpoint(cp)
                            res_data = stream_resp.json().get("data", {})
                            stats = res_data.get("stats", {})
                            print(f" -> [OK STREAM] Ventas sincronizadas: {stats.get('sales_synced', 0)} | Items creados: {stats.get('order_details_created', 0)} | Omitidas: {stats.get('sales_skipped', 0)}")
                        else:
                            print(f" -> [ERROR STREAM] Servidor respondio {stream_resp.status_code}: {stream_resp.text[:150]}")
                except Exception as se:
                    print(f" -> [ERROR STREAM] Error procesando streaming: {se}")
            
        time.sleep(cfg.get("polling_interval", 3))

if __name__ == '__main__':
    poll_and_process()
