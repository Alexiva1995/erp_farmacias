import os
import a2_extractor_service as a2

data_dir = r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data"
cxp_path = os.path.join(data_dir, "Scuentasxpagar.dat")

table = a2.DBISAMTable(cxp_path)

records = []
with open(cxp_path, 'rb') as f:
    f.seek(table.header_size)
    for _ in range(50):
        rec = f.read(table.record_size)
        if len(rec) < table.record_size:
            break
        prov_code = a2.DBISAMTable.decode_str(rec, 25, 30)
        # Probemos varios rangos para la fecha
        raw_emision = rec[57:65]
        fecha_emision = a2.DBISAMTable.decode_date(raw_emision)
        num_doc = a2.DBISAMTable.decode_str(rec, 76, 40)
        monto = a2.DBISAMTable.decode_double(rec[338:346])
        saldo = a2.DBISAMTable.decode_double(rec[347:355])
        tipo_op = a2.DBISAMTable.decode_str(rec, 165, 10)
        records.append((prov_code, num_doc, raw_emision.hex(), fecha_emision, tipo_op, monto, saldo))

print(f"Muestra de 10 registros:")
for r in records[:10]:
    print(r)
