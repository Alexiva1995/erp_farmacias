import sys

path = r'c:\laragon\www\erp_farmacias\resources\js\pages\tpv\orderUser.vue'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update printFiscalPNP with logs
old_func = """const printFiscalPNP = async (order) => {
  try {
    const payload = {
      order_id: order.id,"""

new_func = """const printFiscalPNP = async (order) => {
  console.log("[FISCAL] Iniciando proceso de impresión para orden:", order?.id);
  if (!order) {
    console.error("[FISCAL] Error: Datos de orden no disponibles.");
    return;
  }
  try {
    const payload = {
      order_id: order.id,"""

# 2. Update the post call log
old_post = "await axios.post('http://localhost:5000/print-invoice', payload);"
new_post = """console.log("[FISCAL] Enviando payload a localhost:5000:", payload);
    const bridgeResp = await axios.post('http://localhost:5000/print-invoice', payload);
    console.log("[FISCAL] Respuesta del bridge:", bridgeResp.data);"""

# 3. Update the trigger check
old_trigger = """      // DISPARAR IMPRESIÓN FISCAL SI ESTÁ ACTIVADO
      if (switchStates.invoice_switch || switchStates.generate_invoice) {
        printFiscalPNP(orderData.value);
      }"""

new_trigger = """      // DISPARAR IMPRESIÓN FISCAL SI ESTÁ ACTIVADO
      console.log("[FISCAL] Estado de switches:", { 
          invoice_switch: switchStates.invoice_switch, 
          generate_invoice: switchStates.generate_invoice 
      });
      if (switchStates.invoice_switch || switchStates.generate_invoice) {
        console.log("[FISCAL] Llamando a printFiscalPNP...");
        printFiscalPNP(orderData.value);
      } else {
        console.log("[FISCAL] Factura NO solicitada (switches apagados).");
      }"""

content = content.replace(old_func, new_func)
content = content.replace(old_post, new_post)
content = content.replace(old_trigger, new_trigger)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Debug logs applied successfully")
