import serial
import time
import requests
import urllib.parse
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List, Optional
import uvicorn

app = FastAPI(title="Fiscal Printer Bridge - PNP Protocol")

# CONFIGURACIÓN DE CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# CONFIGURACIÓN DE MODO
# Modos disponibles: 
# "REAL"   -> Envía a la impresora física por puerto SERIAL.
# "MOCK"   -> Solo imprime la trama en consola (Simulación local).
# "WEBSIM" -> Envía al simulador online de PNP (Visualización web).
BRIDGE_MODE = "WEBSIM" 

# Configuración Serial (Solo para modo REAL)
SERIAL_PORT = "COM1"  
BAUD_RATE = 9600

# Configuración Web Simulator
WEBSIM_URL = "https://desarrollospnp.com/sim/pf.php"

class ProductItem(BaseModel):
    name: str
    qty: float
    price: float
    tax_rate: str

class OrderData(BaseModel):
    order_id: int
    client_name: str
    client_rif: str
    items: List[ProductItem]
    payment_method: str

class PNPPrinter:
    """Maneja el protocolo binario para impresoras físicas"""
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
        
        print(f"Modo: {BRIDGE_MODE} | Trama: {full_frame}")
        
        if BRIDGE_MODE == "MOCK":
            return b'\x06'
            
        try:
            with serial.Serial(self.port, self.baudrate, timeout=2) as ser:
                ser.write(full_frame)
                return ser.read(100)
        except Exception as e:
            raise e

class WebSimPrinter:
    """Maneja el protocolo ASCII para el simulador web de PNP"""
    def __init__(self, url):
        self.url = url

    def print_invoice(self, data: OrderData):
        commands = []
        
        rif_clean = "".join(filter(str.isalnum, data.client_rif))
        commands.append(f"@:{data.client_name[:39]}:{rif_clean[:12]}")
        
        for item in data.items:
            qty_int = int(item.qty * 1000)
            price_int = int(item.price * 100)
            tax_val = 1600 if item.tax_rate == "A" else 0
            name_clean = item.name.replace("|", "").replace(":", "")
            commands.append(f"B:{name_clean[:20]}:{qty_int}:{price_int}:{tax_val}:M")
        
        total_int = int(sum(i.price * i.qty for i in data.items) * 1.16 * 100)
        commands.append(f"E:U:{total_int}")
        
        full_query = "|".join(commands)
        safe_query = urllib.parse.quote(full_query, safe="|:?=@")
        full_url = f"{self.url}?{safe_query}"
        
        print(f"[WEBSIM] Enviando factura: {full_url}")
        
        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
        }
        
        try:
            resp = requests.get(full_url, headers=headers, timeout=10)
            print(f"[WEBSIM] Respuesta del servidor: {resp.status_code}")
            return resp.text
        except Exception as e:
            print(f"[WEBSIM] Error: {e}")
            raise e

# Instanciar impresoras
serial_printer = PNPPrinter(SERIAL_PORT, BAUD_RATE)
web_printer = WebSimPrinter(WEBSIM_URL)

@app.post("/print-invoice")
async def print_invoice(data: OrderData):
    try:
        if BRIDGE_MODE == "WEBSIM":
            result = web_printer.print_invoice(data)
            return {"status": "success", "response": result}
        else:
            # Modo REAL o MOCK usando el protocolo binario
            serial_printer.send_command(b'\x40', [data.client_name[:40], data.client_rif[:20]])
            for item in data.items:
                tax_map = {"E": "0", "A": "1", "G": "1"} 
                t_idx = tax_map.get(item.tax_rate, "1")
                # Precios en entero (centavos) para la impresora
                serial_printer.send_command(b'\x42', [item.name[:40], int(item.qty*1000), int(item.price*100), t_idx])
            serial_printer.send_command(b'\x44', ["1", "0"])
            return {"status": "success", "message": f"Comandos ({BRIDGE_MODE}) enviados."}
            
    except Exception as e:
        print(f"[ERROR] {e}")
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=5000)
