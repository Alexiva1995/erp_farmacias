import os
import a2_extractor_service as a2

data_dir = r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data"
op_path = os.path.join(data_dir, "SOperacionInv.dat")
table = a2.DBISAMTable(op_path)
record_len = 3040

supps = a2.extract_suppliers(data_dir)
supp_codes = [s['rif'] for s in supps if s.get('rif')]
print("RIFs conocidos de proveedores:", supp_codes)

with open(op_path, 'rb') as f:
    f.seek(table.header_size)
    for _ in range(30):
        rec = f.read(record_len)
        if len(rec) < record_len:
            break
        op_type = a2.struct.unpack('<I', rec[62:66])[0] if len(rec) >= 66 else 0
        if op_type == 1537:
            doc = a2.DBISAMTable.decode_str(rec, 30, 30)
            date_str = a2.DBISAMTable.decode_date(rec[76:84])
            # Buscar dónde aparece alguno de los RIFs en todo el registro
            found = []
            for sc in supp_codes:
                pos = rec.find(sc.encode('latin1'))
                if pos != -1:
                    found.append((sc, pos))
            print(f"Doc: {doc}, Fecha: {date_str}, Proveedores encontrados en bytes:", found)
