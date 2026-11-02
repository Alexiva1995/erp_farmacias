import os
import a2_extractor_service as a2

data_dir = r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data"
op_path = os.path.join(data_dir, "SOperacionInv.dat")
table = a2.DBISAMTable(op_path)

records = []
with open(op_path, 'rb') as f:
    f.seek(table.header_size)
    while True:
        rec = f.read(table.record_size)
        if len(rec) < table.record_size:
            break
        doc = a2.DBISAMTable.decode_str(rec, 30, 30)
        date_str = a2.DBISAMTable.decode_date(rec[76:84])
        rif_tercero = a2.DBISAMTable.decode_str(rec, 662, 50)
        total_neto = a2.DBISAMTable.decode_double(rec[643:651])
        total_costo = a2.DBISAMTable.decode_double(rec[118:126])
        nro_control = a2.DBISAMTable.decode_str(rec, 1611, 15)
        doc_origen = a2.DBISAMTable.decode_str(rec, 1134, 30)
        op_type = a2.struct.unpack('<I', rec[62:66])[0] if len(rec) >= 66 else 0

        if date_str and date_str.startswith('2026'):
            records.append({
                'doc': doc,
                'date': date_str,
                'op_type': op_type,
                'rif': rif_tercero,
                'total_neto': total_neto,
                'total_costo': total_costo,
                'control': nro_control,
                'origen': doc_origen
            })

print(f"Total registros 2026: {len(records)}")
# Filtrar operaciones donde op_type sea 1537 (Compras a proveedores)
compras = [r for r in records if r['op_type'] == 1537]
print(f"Total compras (op_type 1537) en 2026: {len(compras)}")
print("Primeras 10 compras:")
for c in compras[:10]:
    print(c)
