import serial
import time
import requests
from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List, Optional
import uvicorn

app = FastAPI(title="Fiscal Printer Bridge - PNP Protocol")

# CONFIGURACIÓN DE CORS (Para permitir llamadas desde el ERP)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"], # En producción se puede restringir a la URL del ERP
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.middleware("http")
async def log_requests(request: Request, call_next):
    print(f"[CONEXIÓN] {request.method} {request.url}")
    response = await call_next(request)
    print(f"[CONEXIÓN] Respuesta: {response.status_code}")
    return response

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
    tax_rate: str  # 'E' (Exento), 'A' (16%), etc.

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
        
        # 1. Abrir Factura: @:RazonSocial:RIF
        # Se limpia el RIF de caracteres no alfanuméricos para el simulador
        rif_clean = "".join(filter(str.isalnum, data.client_rif))
        commands.append(f"@:{data.client_name[:39]}:{rif_clean[:12]}")
        
        # 2. Renglones: B:Descripcion:Cantidad:Precio:IVA:M
        # Cantidad (5.3) -> 1.000 = 1000
        # Precio (10.2)  -> 10.00 = 1000
        # IVA (4.2)      -> 16.00 = 1600
        for item in data.items:
            qty_int = int(item.qty * 1000)
            price_int = int(item.price * 100)
            
            tax_val = 1600 # Default 16%
            if item.tax_rate == "E": tax_val = 0
            elif item.tax_rate == "B": tax_val = 800
                
            commands.append(f"B:{item.name[:20]}:{qty_int}:{price_int}:{tax_val}:M")
        
        # 3. Cerrar: E:U:Monto
        # El simulador pide el total para cerrar con IGTF o normal
        total_int = int(sum(i.price * i.qty for i in data.items) * 1.16 * 100) # Aproximación
        commands.append(f"E:U:{total_int}")
        
        full_query = "|".join(commands)
        full_url = f"{self.url}?{full_query}"
        
        print(f"Enviando al simulador web: {full_url}")
        
        try:
            resp = requests.get(full_url, timeout=5)
            print(f"Respuesta simulador: {resp.text}")
            return resp.text
        except Exception as e:
            print(f"Error con simulador web: {e}")
            raise e

# Instanciar impresoras
serial_printer = PNPPrinter(SERIAL_PORT, BAUD_RATE)
web_printer = WebSimPrinter(WEBSIM_URL)

@app.post("/print-invoice")
async def print_invoice(data: OrderData):
    try:
        if BRIDGE_MODE == "WEBSIM":
            result = web_printer.print_invoice(data)
            return {"status": "success", "message": "Enviado al simulador web", "response": result}
        else:
            # Modo REAL o MOCK usando el protocolo binario
            serial_printer.send_command(b'\x40', [data.client_name[:40], data.client_rif[:20]])
            for item in data.items:
                tax_map = {"E": "0", "A": "1", "G": "1"} 
                printer_tax = tax_map.get(item.tax_rate, "1")
                serial_printer.send_command(b'\x42', [item.name[:40], int(item.qty*1000), int(item.price*100), printer_tax])
            serial_printer.send_command(b'\x44', ["1", "0"])
            return {"status": "success", "message": f"Comandos binarios ({BRIDGE_MODE}) enviados."}
            
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=5000)
