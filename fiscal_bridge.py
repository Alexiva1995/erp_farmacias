import serial
import time
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import List, Optional
import uvicorn

app = FastAPI(title="Fiscal Printer Bridge - PNP Protocol")

# Configuración del puerto Serial (Ajustar según sea necesario)
SERIAL_PORT = "COM1"  # Puerto de la impresora física o virtual
BAUD_RATE = 9600
MOCK_MODE = True      # Si es True, no intentará abrir el puerto serial, solo imprimirá la trama.

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
    STX = b'\x02'
    ETX = b'\x03'
    SEP = b'\x1c'
    
    def __init__(self, port, baudrate):
        self.port = port
        self.baudrate = baudrate
        self.seq = 0x20 # Secuencia inicial

    def _next_seq(self):
        self.seq += 1
        if self.seq > 0x7F:
            self.seq = 0x20
        return bytes([self.seq])

    def _calculate_bcc(self, frame_body):
        # El BCC es el XOR de todos los bytes incluyendo SEC y ETX
        xor_sum = 0
        for b in frame_body:
            xor_sum ^= b
        # Retorna el BCC como string hex de 4 caracteres (formato PNP)
        return format(xor_sum, '04X').encode('ascii')

    def send_command(self, cmd_byte, fields=[]):
        sec = self._next_seq()
        # Construir el cuerpo: SEC + CMD + (SEP + Campos)*
        body = sec + cmd_byte
        for field in fields:
            body += self.SEP + str(field).encode('latin-1', errors='replace')
        
        body += self.ETX
        bcc = self._calculate_bcc(body)
        
        full_frame = self.STX + body + bcc
        
        print(f"Enviando trama: {full_frame}")
        
        if MOCK_MODE:
            print("--- MODO SIMULACIÓN ACTIVO (MOCK) ---")
            print(f"Trama Hex: {full_frame.hex().upper()}")
            time.sleep(0.5)
            return b'\x06' # Simular un ACK (0x06)
            
        try:
            with serial.Serial(self.port, self.baudrate, timeout=2) as ser:
                ser.write(full_frame)
                response = ser.read(100) # Leer respuesta (ACK/NAK + Status)
                print(f"Respuesta impresora: {response}")
                return response
        except Exception as e:
            print(f"Error de comunicación: {e}")
            raise e

    def open_invoice(self, name, rif):
        # Comando 0x40: Abrir Factura Fiscal
        # Campos: Nombre, RIF
        return self.send_command(b'\x40', [name[:40], rif[:20]])

    def add_item(self, name, qty, price, tax_index="1"):
        # Comando 0x42: Venta de ítem
        # Campos: Descripción (40), Cantidad (10.3), Precio (10.2), Tasa (1)
        qty_str = "{:.3f}".format(qty).replace('.', '')[:10]
        price_str = "{:.2f}".format(price).replace('.', '')[:10]
        return self.send_command(b'\x42', [name[:40], qty_str, price_str, tax_index])

    def close_invoice(self):
        # Comando 0x44: Cerrar Factura / Subtotal + Pago
        # PNP suele requerir un comando de pago (0x44) para totalizar
        return self.send_command(b'\x44', ["1", "0"]) # "1" Pago efectivo, "0" sin decimales?

printer = PNPPrinter(SERIAL_PORT, BAUD_RATE)

@app.post("/print-invoice")
async def print_invoice(data: OrderData):
    try:
        # 1. Abrir Factura
        printer.open_invoice(data.client_name, data.client_rif)
        
        # 2. Agregar ítems
        for item in data.items:
            # Mapeo de tasas: 1=A (16%), 2=B (8%), etc. Simplificado para este ejemplo.
            tax_map = {"E": "0", "A": "1", "G": "1"} 
            t_idx = tax_map.get(item.tax_rate, "1")
            printer.add_item(item.name, item.qty, item.price, t_idx)
        
        # 3. Cerrar
        printer.close_invoice()
        
        return {"status": "success", "message": f"Factura {data.order_id} enviada a impresora fiscal."}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=5000)
