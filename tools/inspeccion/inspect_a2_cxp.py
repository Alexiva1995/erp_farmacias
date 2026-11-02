import os
import a2_extractor_service as a2

data_dir = r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data"

def inspect_table(filename):
    path = os.path.join(data_dir, filename)
    if not os.path.exists(path):
        print(f"No existe {filename}")
        return
    table = a2.DBISAMTable(path)
    print(f"\n--- {filename} ---")
    print(f"Header: {table.header_size}, Record size: {table.record_size}")
    print("Campos:")
    for f in table.fields:
        print(f"  {f}")

inspect_table("Scuentasxpagar.dat")
inspect_table("SOperacionInv.dat")
