import os
import a2_extractor_service as a2

data_dir = r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data"
op_path = os.path.join(data_dir, "SOperacionInv.dat")
table = a2.DBISAMTable(op_path)
record_len = 3040

records = []
with open(op_path, 'rb') as f:
    f.seek(table.header_size)
    while True:
        rec = f.read(record_len)
        if len(rec) < record_len:
            break
        doc = a2.DBISAMTable.decode_str(rec, 30, 30)
        date_str = a2.DBISAMTable.decode_date(rec[76:84])
        rif_tercero = a2.DBISAMTable.decode_str(rec, 662, 50)
        total_costo = a2.DBISAMTable.decode_double(rec[119:127])
        nro_control = a2.DBISAMTable.decode_str(rec, 1611, 15)
        doc_origen = a2.DBISAMTable.decode_str(rec, 1134, 30)
        op_type = a2.struct.unpack('<I', rec[62:66])[0] if len(rec) >= 66 else 0

        # Proveedor suele estar en los primeros offsets o cerca de doc
        # Probemos varios offsets de texto
        str1 = a2.DBISAMTable.decode_str(rec, 662, 50) # RIF
        
        if date_str and date_str.startswith('2026') and op_type == 1537:
            records.append({
                'doc': doc,
                'date': date_str,
                'op_type': op_type,
                'total_cost': total_costo,
                'rec_hex_start': rec[0:50].hex(),
                'text_scan': [a2.DBISAMTable.decode_str(rec, i, 30) for i in range(0, 300, 30) if a2.DBISAMTable.decode_str(rec, i, 30)]
            })

print(f"Total compras (op_type 1537) en 2026: {len(records)}")
for r in records[:5]:
    print(r)
