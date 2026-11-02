import os
import a2_extractor_service as a2

data_dir = r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data"
op_path = os.path.join(data_dir, "SOperacionInv.dat")
table = a2.DBISAMTable(op_path)

# Encontrar los nombres de campos relevantes
field_names = [f['name'] for f in table.fields]
print("Campos que contienen CODIGO o PROV o CLI o DOC:")
for f in table.fields:
    fn = f['name'].upper()
    if any(k in fn for k in ['PROV', 'CLI', 'COD', 'DOC', 'NRO', 'NUM', 'MTO', 'TOTAL', 'NETO', 'TIPO']):
        print(f"  {f['name']}: offset {f['offset']}, len {f['len']}, type {f['type']}")
