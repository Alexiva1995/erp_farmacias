import os
import a2_extractor_service as a2

data_dir = r"C:\laragon\www\erp_farmacias\A2\GEST - copia\GEST - copia\Empre001\Data"
ops = a2.extract_operations(data_dir, 2026)
print(f"Total operaciones 2026 en SOperacionInv: {len(ops)}")
print("Muestra de 10 operaciones:")
for o in ops[:10]:
    print(o)

# Ver cuántos tipos de operación hay
types = {}
for o in ops:
    t = o.get('op_type')
    types[t] = types.get(t, 0) + 1
print("\nConteo por op_type:", types)
