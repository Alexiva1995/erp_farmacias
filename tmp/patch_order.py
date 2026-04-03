import sys

path = r'c:\laragon\www\erp_farmacias\resources\js\pages\tpv\orderUser.vue'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

placeholder = "const handleBuysCompletion = async ("
replacement = """const printFiscalPNP = async (order) => {
  try {
    const payload = {
      order_id: order.id,
      client_name: (order.client?.name || "CLIENTE") + " " + (order.client?.last_name || "GENERICO"),
      client_rif: order.client?.identification || "00000000",
      items: order.details.map(detail => ({
        name: detail.product?.name || "PRODUCTO",
        qty: parseFloat(detail.quantity),
        price: parseFloat(detail.unit_price_usd),
        tax_rate: (detail.product?.iva == 1 || detail.product?.iva == "1") ? "A" : "E"
      })),
      payment_method: "EFECTIVO"
    };

    await axios.post('http://localhost:5000/print-invoice', payload);
    toast.success("Factura enviada a impresora fiscal");
  } catch (error) {
    console.error("Error en impresión fiscal:", error);
    toast.error("Error: ¿Inició el servidor de impresora fiscal (Python)?");
  }
};

const handleBuysCompletion = async ("""

if placeholder in content:
    new_content = content.replace(placeholder, replacement)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Replace success")
else:
    print("Placeholder not found")
