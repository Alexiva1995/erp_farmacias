import serial
import time
import requests
import urllib.parse
# Desactivar advertencias de SSL para entorno local si es necesario
import urllib3
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# --- CONFIGURACIÓN ---
BRIDGE_MODE = "WEBSIM" 
SERIAL_PORT = "COM1"  
BAUD_RATE = 9600

# Usamos 127.0.0.1 para evitar problemas de DNS/SSL en Laragon
API_BASE_URL = "http://127.0.0.1/api" 
POLLING_INTERVAL = 5 

WEBSIM_URL = "https://desarrollospnp.com/sim/pf.php"

# --- PROTOCOLO PNP ---
class PNPPrinter:
    STX = b'\x02'
    ETX = b'\x03'
    SEP = b'\x1c'
    
    def __init__(self, port, baudrate):
        self.port = port
        self.baudrate = baudrate
        self.seq = 0x20

    def _next_seq(self):
        self.seq += 1
        if self.seq > 0x7F: self.seq = 0x20
        return bytes([self.seq])

    def _calculate_bcc(self, frame_body):
        xor_sum = 0
        for b in frame_body: xor_sum ^= b
        return format(xor_sum, '04X').encode('ascii')

    def send_command(self, cmd_byte, fields=[]):
        sec = self._next_seq()
        body = sec + cmd_byte
        for field in fields:
            body += self.SEP + str(field).encode('latin-1', errors='replace')
        body += self.ETX
        bcc = self._calculate_bcc(body)
        full_frame = self.STX + body + bcc
        
        print(f"[{BRIDGE_MODE}] Trama: {full_frame}")
        
        if BRIDGE_MODE == "MOCK":
            return b'\x06', "MOCK_OK"
            
        try:
            with serial.Serial(self.port, self.baudrate, timeout=2) as ser:
                ser.write(full_frame)
                res = ser.read(100)
                return res, res.decode('latin-1', errors='ignore')
        except Exception as e:
            print(f"[ERROR SERIAL] {e}")
            raise e

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
            # El sistema ya guarda el total_amount por item. Calculamos la base para el simulador.
            is_taxable = detail['vat_status'] == 1 or detail['vat_status'] is True
            price_unit = float(detail['total_amount']) / (1.16 if is_taxable else 1.0) / float(detail['quantity'])
            price_int = int(price_unit * 100)
            
            tax_val = 1600 if is_taxable else 0
            name_clean = detail['product_name'].replace("|", "").replace(":", "")
            commands.append(f"B:{name_clean[:20]}:{qty_int}:{price_int}:{tax_val}:M")
        
        total_int = int(float(data['total_amount']) * 100)
        commands.append(f"E:U:{total_int}")
        
        full_query = "|".join(commands)
        safe_query = urllib.parse.quote(full_query, safe="|:?=@")
        full_url = f"{self.url}?{safe_query}"
        
        headers = {"User-Agent": "Mozilla/5.0"}
        print(f"[WEBSIM] Enviando: {full_url}")
        resp = requests.get(full_url, headers=headers, timeout=10, verify=False)
        return resp.text

# --- WORKER LÓGICA ---
def process_pending_invoices():
    serial_printer = PNPPrinter(SERIAL_PORT, BAUD_RATE)
    web_printer = WebSimPrinter(WEBSIM_URL)

    print(f"--- Iniciando Worker Fiscal ({BRIDGE_MODE}) ---")
    print(f"Monitorizando: {API_BASE_URL}/fiscal/pending")

    while True:
        try:
            # Consultar con verify=False para evitar problemas de certificados auto-firmados
            response = requests.get(f"{API_BASE_URL}/fiscal/pending", timeout=5, verify=False)
            if response.status_code == 200:
                invoice_data = response.json()
                
                if invoice_data and 'id' in invoice_data:
                    invoice_id = invoice_data['id']
                    print(f"\n[NUEVA] Detectada factura ID: {invoice_id}")
                    
                    fiscal_res = ""
                    invoice_num = "PENDING"
                    
                    if BRIDGE_MODE == "WEBSIM":
                        fiscal_res = web_printer.print_invoice(invoice_data)
                        invoice_num = fiscal_res.split('|')[-1] if '|' in fiscal_res else "SIM" + str(invoice_id)
                    else:
                        # Modo REAL o MOCK
                        serial_printer.send_command(b'\x40', [invoice_data['business_name'][:40], invoice_data['identification'][:20]])
                        for detail in invoice_data.get('details', []):
                            is_taxable = detail['vat_status'] == 1 or detail['vat_status'] is True
                            tax_idx = "1" if is_taxable else "0"
                            price_unit = float(detail['total_amount']) / (1.16 if is_taxable else 1.0) / float(detail['quantity'])
                            serial_printer.send_command(b'\x42', [detail['product_name'][:40], int(float(detail['quantity'])*1000), int(price_unit*100), tax_idx])
                        _, fiscal_res = serial_printer.send_command(b'\x44', ["1", "0"])
                        invoice_num = "FIS_OK_" + str(invoice_id)

                    print(f"[OK] Impresa. Confirmando a Laravel...")
                    confirm_resp = requests.patch(f"{API_BASE_URL}/fiscal/confirm/{invoice_id}", json={
                        "invoice_number": invoice_num[:20],
                        "fiscal_id": "PNP_LOCAL_01"
                    }, timeout=5, verify=False)
                    
                    if confirm_resp.status_code == 200:
                        print(f"[EXITO] Factura {invoice_id} marcada como impresa.")
                    else:
                        print(f"[ERROR] No se pudo confirmar: {confirm_resp.status_code}")
                        print(confirm_resp.text)
                
            else:
                if response.status_code != 200:
                    print(f"[API ERROR] {response.status_code}")
                    
        except requests.exceptions.ConnectionError:
            print("[CONEXIÓN] Esperando servidor Laravel (127.0.0.1)...")
        except Exception as e:
            print(f"[ERROR] {e}")
            
        time.sleep(POLLING_INTERVAL)

if __name__ == "__main__":
    process_pending_invoices()
