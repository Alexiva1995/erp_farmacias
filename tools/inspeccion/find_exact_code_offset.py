import os
import a2_extractor_service as a2

data_dir = r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data"
op_path = os.path.join(data_dir, "SOperacionInv.dat")
table = a2.DBISAMTable(op_path)
record_len = 3040

with open(op_path, 'rb') as f:
    f.seek(table.header_size)
    for _ in range(50):
        rec = f.read(record_len)
        if len(rec) < record_len:
            break
        op_type = a2.struct.unpack('<I', rec[62:66])[0] if len(rec) >= 66 else 0
        if op_type == 1537:
            doc = a2.DBISAMTable.decode_str(rec, 30, 30)
            date_str = a2.DBISAMTable.decode_date(rec[76:84])
            # Imprimir offsets 370 a 420
            print(f"Doc {doc}:")
            for o in range(370, 400):
                val = a2.DBISAMTable.decode_str(rec, o, 30)
                if val and any(c.isdigit() for c in val):
                    print(f"   offset {o}: '{val}'")
            break
