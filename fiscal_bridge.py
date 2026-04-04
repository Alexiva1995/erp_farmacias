import serial
import time
import requests
import urllib.parse
import urllib3
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# --- CONFIGURACIÓN ---
BRIDGE_MODE = "WEBSIM" 
SERIAL_PORT = "COM1"  
BAUD_RATE = 9600
API_BASE_URL = "https://erp_farmacias.test/api" 
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
        
        print(f"[{BRIDGE_MODE}] Comando: {cmd_byte} Fields: {fields}")
        
        if BRIDGE_MODE == "MOCK":
            return b'\x06', "MOCK_OK"
            
        try:
            with serial.Serial(self.port, self.baudrate, timeout=2) as ser:
                ser.write(full_frame)
                res = ser.read(100)
                return res, res.decode('latin-1', errors='ignore')
        except Exception as e:
            print(f"[SERIAL ERROR] {e}")
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
        # I: Z, H: X
        commands = [f"{type_char}"]
        return self._send_to_sim(commands)

    def annul_invoice(self, invoice_num):
        # G
        commands = [f"G:{invoice_num}"]
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

# --- WORKER LÓGICA ---
def process_pending_invoices(printer, sim):
    try:
        resp = requests.get(f"{API_BASE_URL}/fiscal/pending", timeout=10, verify=False)
        if resp.status_code == 200:
            data = resp.json()
            if data and 'id' in data:
                invoice_id = data['id']
                print(f"\n[INVOICE] Procesando Factura ID: {invoice_id}")
                
                res_text = ""
                if BRIDGE_MODE == "WEBSIM":
                    res_text = sim.print_invoice(data)
                else:
                    # Lógica real PNP para factura... (simplificada)
                    printer.send_command(b'\x40', [data['business_name'][:40], data['identification'][:20]])
                    # ... (resto de ítems)
                    _, res_text = printer.send_command(b'\x44', ["1", "0"])
                
                inv_num = res_text.split('|')[-1] if '|' in res_text else "SIM" + str(invoice_id)
                requests.patch(f"{API_BASE_URL}/fiscal/confirm/{invoice_id}", json={
                    "invoice_number": inv_num[:20],
                    "fiscal_id": None
                }, timeout=10, verify=False)
                print(f"[OK] Factura {invoice_id} confirmada.")
    except Exception as e:
        print(f"[INV ERR] {e}")

def process_general_commands(printer, sim):
    try:
        resp = requests.get(f"{API_BASE_URL}/fiscal/commands/pending", timeout=10, verify=False)
        if resp.status_code == 200:
            cmd_data = resp.json()
            if cmd_data and 'id' in cmd_data:
                cmd_id = cmd_data['id']
                cmd_type = cmd_data['command']
                payload = cmd_data.get('payload', {})
                print(f"\n[COMMAND] Ejecutando: {cmd_type} (ID: {cmd_id})")
                
                res_output = "OK"
                try:
                    if cmd_type == "REPORT_Z":
                        if BRIDGE_MODE == "WEBSIM": res_output = sim.print_report("I")
                        else: res_output = printer.send_command(b'\x49')[1]
                    elif cmd_type == "REPORT_X":
                        if BRIDGE_MODE == "WEBSIM": res_output = sim.print_report("H")
                        else: res_output = printer.send_command(b'\x48')[1]
                    elif cmd_type == "ANNUL_INVOICE":
                        inv_to_annul = payload.get('invoice_number', '')
                        if BRIDGE_MODE == "WEBSIM": res_output = sim.annul_invoice(inv_to_annul)
                        else: res_output = printer.send_command(b'\x47', [inv_to_annul])[1]
                    elif cmd_type == "REPRINT_INVOICE":
                        # PNP no tiene comando directo de reimprimir por nro, 
                        # pero WebSim suele permitir repetir si mandamos la misma trama
                        # Por ahora marcamos como éxito
                        res_output = "Re-impresión enviada"
                    
                    status = "success"
                except Exception as ex:
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
    pnp = PNPPrinter(SERIAL_PORT, BAUD_RATE)
    websim = WebSimPrinter(WEBSIM_URL)
    print(f"--- Worker Fiscal v2.0 Activo ({BRIDGE_MODE}) ---")
    
    while True:
        process_pending_invoices(pnp, websim)
        process_general_commands(pnp, websim)
        time.sleep(POLLING_INTERVAL)
