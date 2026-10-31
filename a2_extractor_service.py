import os
import sys
import struct
import datetime
import json
import re

class DBISAMTable:
    def __init__(self, dat_path: str):
        self.dat_path = dat_path
        if not os.path.exists(dat_path):
            raise FileNotFoundError(f"No existe el archivo {dat_path}")
        self.fields = []
        self.header_size = 0
        self.record_size = 0
        self._parse_schema()

    def _parse_schema(self):
        with open(self.dat_path, 'rb') as f:
            idx = 0
            while True:
                f.seek(512 + idx * 768)
                desc = f.read(200)
                if not desc or desc[0] == 0:
                    break
                namelen = desc[2]
                if namelen == 0 or namelen > 50:
                    break
                name = desc[3:3+namelen].decode('latin1', errors='ignore')
                type_code = desc[164]
                length = desc[166]
                offset = struct.unpack('<H', desc[172:174])[0]
                self.fields.append({
                    'name': name,
                    'type': type_code,
                    'len': length,
                    'offset': offset
                })
                idx += 1
            
            self.header_size = 512 + idx * 768
            if self.fields:
                last_fld = self.fields[-1]
                # Longitud calculada
                raw_len = last_fld['offset'] + (last_fld['len'] if last_fld['len'] > 0 else 8)
                # En DBISAM las tablas tienen padding/alineación
                self.record_size = raw_len

    @staticmethod
    def decode_date(raw_bytes: bytes) -> str:
        try:
            val_ms = struct.unpack('<d', raw_bytes)[0]
            if val_ms <= 0:
                return None
            days = val_ms / 86400000.0
            return datetime.date.fromordinal(int(days)).strftime('%Y-%m-%d')
        except:
            return None

    @staticmethod
    def decode_double(raw_bytes: bytes) -> float:
        try:
            return round(struct.unpack('<d', raw_bytes)[0], 4)
        except:
            return 0.0

    @staticmethod
    def decode_int32(raw_bytes: bytes) -> int:
        try:
            return struct.unpack('<i', raw_bytes)[0]
        except:
            return 0

    @staticmethod
    def decode_field_date(rec_bytes: bytes, offset: int) -> str:
        try:
            flag = rec_bytes[offset]
            if flag != 1:
                return None
            return DBISAMTable.decode_date(rec_bytes[offset+1:offset+9])
        except:
            return None

    @staticmethod
    def decode_field_double(rec_bytes: bytes, offset: int) -> float:
        try:
            flag = rec_bytes[offset]
            if flag != 1:
                return 0.0
            return DBISAMTable.decode_double(rec_bytes[offset+1:offset+9])
        except:
            return 0.0

    @staticmethod
    def decode_field_int32(rec_bytes: bytes, offset: int) -> int:
        try:
            flag = rec_bytes[offset]
            if flag != 1:
                return 0
            return DBISAMTable.decode_int32(rec_bytes[offset+1:offset+5])
        except:
            return 0

    @staticmethod
    def decode_str(rec_bytes: bytes, offset: int, max_len: int) -> str:
        try:
            # En DBISAM las cadenas Pascal/Null terminated comienzan 1 byte después con flag
            flag = rec_bytes[offset]
            part = rec_bytes[offset+1:offset+1+max_len].split(b'\x00')[0]
            return part.decode('latin1', errors='ignore').strip()
        except:
            return ""

def extract_suppliers(a2_data_dir: str):
    path = os.path.join(a2_data_dir, "Sproveedor.dat")
    table = DBISAMTable(path)
    record_len = 2192
    suppliers = []
    
    with open(path, 'rb') as f:
        f.seek(table.header_size)
        while True:
            rec = f.read(record_len)
            if len(rec) < record_len:
                break
            
            rif = DBISAMTable.decode_str(rec, 25, 30)
            name = DBISAMTable.decode_str(rec, 57, 110)
            phone = DBISAMTable.decode_str(rec, 267, 40)
            email = DBISAMTable.decode_str(rec, 349, 40)
            address = DBISAMTable.decode_str(rec, 239, 40)
            
            if rif or name:
                suppliers.append({
                    'rif': rif,
                    'name': name,
                    'phone': phone,
                    'email': email,
                    'address': address
                })
    return suppliers

def extract_products(a2_data_dir: str):
    inv_path = os.path.join(a2_data_dir, "Sinventario.dat")
    stock_path = os.path.join(a2_data_dir, "SinvDep.dat")
    costs_path = os.path.join(a2_data_dir, "a2InvCostosPrecios.dat")
    barcodes_path = os.path.join(a2_data_dir, "SCodeBar.dat")
    
    # Extraer productos
    table = DBISAMTable(inv_path)
    record_len = 1320
    products = {}
    
    with open(inv_path, 'rb') as f:
        f.seek(table.header_size)
        while True:
            rec = f.read(record_len)
            if len(rec) < record_len:
                break
            code = DBISAMTable.decode_str(rec, 25, 30)
            name = DBISAMTable.decode_str(rec, 57, 100)
            category = DBISAMTable.decode_str(rec, 159, 30)
            brand = DBISAMTable.decode_str(rec, 335, 30)
            unit = DBISAMTable.decode_str(rec, 191, 10)
            
            if code and name:
                products[code] = {
                    'code': code,
                    'name': name,
                    'category': category,
                    'brand': brand,
                    'unit': unit,
                    'stock': 0.0,
                    'cost_usd': 0.0,
                    'barcodes': [code] if len(code) >= 8 and code.isdigit() else []
                }
                
    return list(products.values())

def extract_operations(a2_data_dir: str, target_year: int = 2026):
    op_path = os.path.join(a2_data_dir, "SOperacionInv.dat")
    table = DBISAMTable(op_path)
    record_len = 3040
    operations = []
    
    with open(op_path, 'rb') as f:
        f.seek(table.header_size)
        while True:
            rec = f.read(record_len)
            if len(rec) < record_len:
                break
            
            doc = DBISAMTable.decode_str(rec, 30, 30)
            date_str = DBISAMTable.decode_date(rec[76:84])
            total_cost = DBISAMTable.decode_double(rec[119:127])
            op_type = struct.unpack('<I', rec[62:66])[0] if len(rec) >= 66 else 0
            
            if date_str and date_str.startswith(str(target_year)):
                operations.append({
                    'document': doc,
                    'date': date_str,
                    'total_cost': total_cost,
                    'op_type': op_type
                })
    return operations

if __name__ == '__main__':
    data_dir = r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data"
    print("Extrayendo proveedores...")
    supps = extract_suppliers(data_dir)
    print(f"Total proveedores en a2: {len(supps)}")
    print("Primeros 3:", supps[:3])
    
    print("\nExtrayendo operaciones 2026...")
    ops = extract_operations(data_dir, 2026)
    print(f"Total operaciones año 2026: {len(ops)}")
    print("Primeras 3:", ops[:3])


def extract_payables(a2_data_dir: str, target_year: int = 2026):
    cxp_path = os.path.join(a2_data_dir, "Scuentasxpagar.dat")
    if not os.path.exists(cxp_path):
        return []
    table = DBISAMTable(cxp_path)
    record_len = 1480
    payables = []
    
    with open(cxp_path, 'rb') as f:
        f.seek(table.header_size)
        while True:
            rec = f.read(record_len)
            if len(rec) < record_len:
                break
            
            # FCP_CODIGO offset 25, len 30
            supplier_code = DBISAMTable.decode_str(rec, 25, 30)
            if not supplier_code:
                continue
                
            # FCP_FECHAEMISION offset 57
            issue_date = DBISAMTable.decode_field_date(rec, 57)
            # FCP_NUMERO offset 76, len 40
            doc_num = DBISAMTable.decode_str(rec, 76, 40)
            # FCP_TIPOTRANSACCION offset 160 (1=Factura/Cargo, 4=Pago)
            tx_type = DBISAMTable.decode_field_int32(rec, 160)
            # FCP_FECHAVENCIMIENTO offset 320
            due_date = DBISAMTable.decode_field_date(rec, 320)
            # FCP_MONTODOCUMENTO offset 338
            total_amount = DBISAMTable.decode_field_double(rec, 338)
            # FCP_SALDODOCUMENTO offset 347
            balance = DBISAMTable.decode_field_double(rec, 347)
            # FCP_SALDOMONEDAEXT offset 356
            balance_usd = DBISAMTable.decode_field_double(rec, 356)
            # FCP_MONTOMONEDAEXT offset 566
            total_usd = DBISAMTable.decode_field_double(rec, 566)
            # FCP_FACTORCAMBIO offset 575
            rate = DBISAMTable.decode_field_double(rec, 575)
            # FCP_CONTROL offset 678, len 20
            control_num = DBISAMTable.decode_str(rec, 678, 20)
            
            # Solo facturas activas con saldo pendiente > 0
            if tx_type == 1 and balance > 0:
                if not target_year or (issue_date and issue_date.startswith(str(target_year))):
                    payables.append({
                        'supplier_code': supplier_code,
                        'invoice_number': doc_num,
                        'control_number': control_num,
                        'issue_date': issue_date,
                        'due_date': due_date or issue_date,
                        'total_amount': total_amount,
                        'pending_amount': balance,
                        'total_usd': total_usd if total_usd > 0 else (round(total_amount / rate, 2) if rate > 0 else 0.0),
                        'pending_usd': balance_usd if balance_usd > 0 else (round(balance / rate, 2) if rate > 0 else 0.0),
                        'exchange_rate': rate if rate > 0 else 1.0,
                    })
    return payables

def extract_receivables(a2_data_dir: str, target_year: int = 2026):
    cxc_path = os.path.join(a2_data_dir, "Scuentasxcobrar.dat")
    if not os.path.exists(cxc_path):
        return []
    table = DBISAMTable(cxc_path)
    record_len = 1600
    receivables = []
    
    with open(cxc_path, 'rb') as f:
        f.seek(table.header_size)
        while True:
            rec = f.read(record_len)
            if len(rec) < record_len:
                break
                
            # FCC_CODIGO offset 25, len 30
            client_code = DBISAMTable.decode_str(rec, 25, 30)
            if not client_code:
                continue
                
            # FCC_FECHAEMISION offset 57
            issue_date = DBISAMTable.decode_field_date(rec, 57)
            # FCC_NUMERO offset 135, len 40
            doc_num = DBISAMTable.decode_str(rec, 135, 40)
            # FCC_TIPOTRANSACCION offset 224 (1=Cargo/Factura, 4=Pago)
            tx_type = DBISAMTable.decode_field_int32(rec, 224)
            # FCC_FECHAVENCIMIENTO offset 250
            due_date = DBISAMTable.decode_field_date(rec, 250)
            # FCC_MONTODOCUMENTO offset 268
            total_amount = DBISAMTable.decode_field_double(rec, 268)
            # FCC_SALDODOCUMENTO offset 277
            balance = DBISAMTable.decode_field_double(rec, 277)
            # FCC_SALDOMONEDAEXT offset 286
            balance_usd = DBISAMTable.decode_field_double(rec, 286)
            
            # Solo cargos activos con saldo pendiente > 0.01
            if tx_type == 1 and balance >= 0.01:
                if not target_year or (issue_date and issue_date.startswith(str(target_year))):
                    receivables.append({
                        'client_code': client_code,
                        'invoice_number': doc_num,
                        'issue_date': issue_date,
                        'due_date': due_date or issue_date,
                        'total_amount': total_amount,
                        'pending_amount': balance,
                        'pending_usd': balance_usd,
                    })
    return receivables

def extract_clients(a2_data_dir: str):
    cli_path = os.path.join(a2_data_dir, "Sclientes.dat")
    if not os.path.exists(cli_path):
        return []
    table = DBISAMTable(cli_path)
    # Longitud de registro de clientes en a2
    record_len = 3120
    clients = []
    with open(cli_path, "rb") as fp:
        fp.seek(table.header_size)
        while True:
            rec = fp.read(record_len)
            if len(rec) < record_len:
                break
            code = DBISAMTable.decode_str(rec, 25, 30)
            name = DBISAMTable.decode_str(rec, 57, 100)
            phone = DBISAMTable.decode_str(rec, 267, 40)
            address = DBISAMTable.decode_str(rec, 239, 60)
            if code or name:
                clients.append({
                    "code": code,
                    "name": name,
                    "phone": phone,
                    "address": address
                })
    return clients

def extract_sales(a2_data_dir: str, target_year: int = 2026):
    # En SOperacionInv, op_type 1001 o 1002 corresponden a ventas
    all_ops = extract_operations(a2_data_dir, target_year)
    sales = [op for op in all_ops if op.get("op_type") in [1001, 1002, 1025, 1537]]
    return sales
