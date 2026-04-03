import sys

path = r'c:\laragon\www\erp_farmacias\resources\js\components\dialogs\BuysModal.vue'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Buscamos el switch y le agregamos @update:model-value
old_switch = '<VSwitch v-model="invoiceSwitch" density="compact" color="primary" hide-details />'
new_switch = '<VSwitch v-model="invoiceSwitch" density="compact" color="primary" hide-details @update:model-value="(val) => console.log(\'[FISCAL] Click en switch Factura:\', val)" />'

if old_switch in content:
    content = content.replace(old_switch, new_switch)
    print("Log added to switch")
else:
    print("Could not find the switch tag")

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)
