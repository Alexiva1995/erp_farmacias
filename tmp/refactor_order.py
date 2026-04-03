import sys

path = r'c:\laragon\www\erp_farmacias\resources\js\pages\tpv\orderUser.vue'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Buscamos el inicio del bloque de éxito
start_marker = 'if (response.status === 200 || response.status === 201) {'
if start_marker not in content:
    print("Start marker not found")
    sys.exit(1)

# Queremos mover el bloque de impresión fiscal al principio del IF
# Primero buscamos el bloque actual para removerlo
import re
print_block_pattern = r'// DISPARAR IMPRESIÓN FISCAL SI ESTÁ ACTIVADO.*?if \(switchStates\.invoice_switch \|\| switchStates\.generate_invoice\) \{.*?printFiscalPNP\(orderData\.value\);.*?\}'
# Pero tengo logs ahora, así que el pattern es más complejo.
# Usemos un pattern más genérico que cubra mi inyección previa

# Bloque inyectado previamente:
print_block_pattern = r'// DISPARAR IMPRESIÓN FISCAL SI ESTÁ ACTIVADO.*?console\.log\("\[FISCAL\].*?if \(switchStates\.invoice_switch \|\| switchStates\.generate_invoice\) \{.*?printFiscalPNP\(orderData\.value\);.*?\} else \{.*?console\.log\("\[FISCAL\].*?\}'

# Remover el bloque de su posición actual
content = re.sub(print_block_pattern, '', content, flags=re.DOTALL)

# Insertar el nuevo bloque optimizado justo al inicio del IF
new_success_start = """if (response.status === 200 || response.status === 201) {
      toast.success("¡Compra finalizada y registrada con éxito!");

      // DISPARAR IMPRESIÓN FISCAL INMEDIATAMENTE
      const orderCompletada = response.data.data.orderCompletada;
      console.log("[FISCAL] Verificando condición inmediata:", { 
          inv: switchStates.invoice_switch, 
          gen: switchStates.generate_invoice 
      });

      if (switchStates.invoice_switch || switchStates.generate_invoice) {
        console.log("[FISCAL] Llamando a printFiscalPNP con:", orderCompletada?.id);
        printFiscalPNP(orderCompletada);
      } else {
        console.log("[FISCAL] Factura omitida.");
      }
"""

content = content.replace(start_marker + '\n      toast.success("¡Compra finalizada y registrada con éxito!");', new_success_start)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Refactor success")
