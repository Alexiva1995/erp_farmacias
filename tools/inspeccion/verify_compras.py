import os
import a2_extractor_service as a2

data_dir = r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data"
op_path = os.path.join(data_dir, "SOperacionInv.dat")
table = a2.DBISAMTable(op_path)
record_len = 3040

ops = []
with open(op_path, 'rb') as f:
    f.seek(table.header_size)
    while True:
        rec = f.read(record_len)
        if len(rec) < record_len:
            break
        op_type = a2.struct.unpack('<I', rec[62:66])[0] if len(rec) >= 66 else 0
        date_str = a2.DBISAMTable.decode_date(rec[76:84])
        
        # op_type 1537 = Compras a Proveedores
        if op_type == 1537 and date_str and date_str.startswith('2026'):
            doc = a2.DBISAMTable.decode_str(rec, 30, 30)
            supplier_code = a2.DBISAMTable.decode_str(rec, 383, 30)
            total_cost = a2.DBISAMTable.decode_double(rec[119:127])
            # Total neto o bruto
            total_neto = a2.DBISAMTable.decode_double(rec[643:651])
            ops.append({
                'invoice_number': doc,
                'date': date_str,
                'supplier_rif': supplier_code,
                'total_cost': total_cost,
                'total_neto': total_neto
            })

print(f"Total Facturas de Compras 2026 encontradas: {len(ops)}")
for op in ops[:10]:
    print(op)
