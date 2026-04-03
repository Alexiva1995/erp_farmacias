import sys

path = r'c:\laragon\www\erp_farmacias\resources\js\pages\tpv\orderUser.vue'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Log al inicio de handleBuysCompletion
old_start = "isFinishingOrder.value = true;\n\n    if (typeof updateTotalsTimer !== \"undefined\")"
new_start = """isFinishingOrder.value = true;
    console.log("[FISCAL] handleBuysCompletion disparado para orden:", orderId);
    console.log("[FISCAL] switchStates recibidos de BuysModal:", switchStates);

    if (typeof updateTotalsTimer !== "undefined")"""

# Log antes y después de updateOrderTotalsInBackend
old_update = "await updateOrderTotalsInBackend();"
new_update = """console.log("[FISCAL] Actualizando totales en backend...");
    await updateOrderTotalsInBackend();
    console.log("[FISCAL] Totales actualizados.");"""

# Log antes de la petición principal
old_post = "const response = await axios.post("
new_post = """console.log("[FISCAL] Enviando petición final de completado...");
    const response = await axios.post("""

content = content.replace(old_start, new_start)
content = content.replace(old_update, new_update)
content = content.replace(old_post, new_post)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("More logs added successfully")
