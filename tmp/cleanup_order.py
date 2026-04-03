import sys

path = r'c:\laragon\www\erp_farmacias\resources\js\pages\tpv\orderUser.vue'
with open(path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Las líneas en view_file son 1-indexed. 
# Queremos borrar de la 2176 a la 2373 (inclusive el espacio después del comentario)
start_line = 2176
end_line = 2373

# En python la lista es 0-indexed
new_lines = lines[:start_line-1] + lines[end_line:]

with open(path, 'w', encoding='utf-8') as f:
    f.writelines(new_lines)

print(f"Removed lines {start_line} to {end_line}")
