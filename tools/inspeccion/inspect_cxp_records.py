import os, struct
import a2_extractor_service as a2

data_dir = r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data"
cxp_path = os.path.join(data_dir, "Scuentasxpagar.dat")

table = a2.DBISAMTable(cxp_path)
print(f"Header: {table.header_size}, Record size: {table.record_size}")

records = []
with open(cxp_path, 'rb') as f:
    f.seek(table.header_size)
    count = 0
    while count < 3000:
        rec = f.read(table.record_size)
        if len(rec) < table.record_size:
            break
        count += 1
        
        # FCP_CODIGO (Proveedor) offset 25, len 30
        prov_code = a2.DBISAMTable.decode_str(rec, 25, 30)
        # FCP_FECHAEMISION offset 57
        fecha_emision = a2.DBISAMTable.decode_date(rec[57:65])
        # FCP_NUMERO offset 76, len 40
        num_doc = a2.DBISAMTable.decode_str(rec, 76, 40)
        # FCP_TIPOOPERACION offset 165, len 10
        tipo_op = a2.DBISAMTable.decode_str(rec, 165, 10)
        # FCP_DOCUMENTOORIGEN offset 177, len 100
        doc_origen = a2.DBISAMTable.decode_str(rec, 177, 100)
        # FCP_FECHAVENCIMIENTO offset 320
        fecha_venc = a2.DBISAMTable.decode_date(rec[320:328])
        # FCP_MONTODOCUMENTO offset 338
        monto = a2.DBISAMTable.decode_double(rec[338:346])
        # FCP_SALDODOCUMENTO offset 347
        saldo = a2.DBISAMTable.decode_double(rec[347:355])
        # FCP_FACTORCAMBIO offset 575
        factor = a2.DBISAMTable.decode_double(rec[575:583])
        # FCP_CONTROL offset 678, len 20
        control = a2.DBISAMTable.decode_str(rec, 678, 20)

        if fecha_emision and fecha_emision.startswith('2026'):
            records.append({
                'prov_code': prov_code,
                'fecha_emision': fecha_emision,
                'num_doc': num_doc,
                'tipo_op': tipo_op,
                'doc_origen': doc_origen,
                'fecha_venc': fecha_venc,
                'monto': monto,
                'saldo': saldo,
                'factor': factor,
                'control': control,
            })

print(f"Total registros 2026 encontrados en Scuentasxpagar: {len(records)}")
for r in records[:10]:
    print(r)
