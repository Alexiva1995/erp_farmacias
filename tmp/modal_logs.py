import sys

path = r'c:\laragon\www\erp_farmacias\resources\js\components\dialogs\BuysModal.vue'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Log antes de emitir purchase-completed
old_emit = "emit(\n        \"purchase-completed\","
new_emit = """console.log("[FISCAL] Emitiendo purchase-completed desde BuysModal:", {
        orderId: props.orderData?.id,
        invoiceSwitch: invoiceSwitch.value,
        shouldApplySpe: shouldApplySpeRules.value
      });
      emit(
        "purchase-completed","""

if old_emit in content:
    content = content.replace(old_emit, new_emit)
    print("Logs added to BuysModal")
else:
    # Try different formatting
    old_emit_alt = "emit(\"purchase-completed\","
    if old_emit_alt in content:
        content = content.replace(old_emit_alt, new_emit)
        print("Logs added to BuysModal (alt)")
    else:
        print("Could not find emit in BuysModal")

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)
