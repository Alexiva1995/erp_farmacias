import { ref, nextTick } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { THERMAL_54MM_CSS } from "@/constants/thermalTicket54.js";

export function useOrderPrint() {
  const isPrinting = ref(false);
  const orderData = ref(null);
  const orderItems = ref([]);
  const paymentsForPrint = ref([]);
  const changeAmountForPrint = ref(0);
  const creditAmountForPrint = ref(0);
  const amountForPrint = ref(0);
  const creditForPrint = ref(false);
  const currency = ref("COP");

  const resetPrintData = () => {
    isPrinting.value = false;
    paymentsForPrint.value = [];
    orderData.value = null;
    orderItems.value = [];
    changeAmountForPrint.value = 0;
    creditAmountForPrint.value = 0;
    amountForPrint.value = 0;
    creditForPrint.value = false;
  };

  const prepareOrderData = (data) => {
    const { order, hasCreditPayment } = data;
    orderData.value = order;
    currency.value = order.currency.toUpperCase();
    orderItems.value = order.details.map((detail) => ({
      id: detail.product?.id ?? detail.dish?.id ?? detail.court?.id ?? detail.product_id ?? detail.dish_id ?? detail.court_id,
      product_id: detail.product_id ?? detail.product?.id,
      dish_id: detail.dish_id ?? detail.dish?.id,
      court_id: detail.court_id ?? detail.court?.id,
      title: detail.product?.name ?? detail.dish?.name ?? detail.court?.name ?? '—',
      active_ingredient: detail.court ? 'Cancha' : (detail.product?.active_ingredient || null),
      laboratory: detail.court ? 'Alquiler Deportivo' : (detail.product?.laboratory?.name ?? detail.product?.laboratory ?? null),
      selectedQuantity: detail.quantity,
      taxRate: detail.product?.iva || 0,
      unit_price: detail.quantity > 0 ? parseFloat(detail.price) / detail.quantity : parseFloat(detail.price),
      price_bs: parseFloat(detail.price),
      price_cop: parseFloat(detail.price),
      price: parseFloat(detail.price),
      price_before_discount: parseFloat(detail.price_before_discount),
      notes: detail.notes,
    }));
    paymentsForPrint.value = order.payment_methods || [];
    changeAmountForPrint.value = parseFloat(order.money_returns || 0);
    amountForPrint.value = parseFloat(order.total_amount || 0);
    creditAmountForPrint.value = hasCreditPayment ? parseFloat(order.total_amount || 0) : 0;
    creditForPrint.value = Boolean(hasCreditPayment);
  };

  const printOrder = async (orderId) => {
    try {
      const response = await axios.get(`/tpv/orders/${orderId}/print`);
      if (response.data?.data?.order) {
        prepareOrderData(response.data.data);
        isPrinting.value = true;
        await nextTick();
        const printContents = document.getElementById("orderPrint");

        if (printContents) {
          const printWindow = window.open("", "", "height=600,width=800");
          printWindow.document.write("<html><head><title>Farmacia Barrio Sucre</title>");

          const styleSheets = document.styleSheets;
          for (let i = 0; i < styleSheets.length; i++) {
            const sheet = styleSheets[i];
            try {
              if (sheet.cssRules) {
                let cssText = "";
                for (let j = 0; j < sheet.cssRules.length; j++) {
                  cssText += sheet.cssRules[j].cssText;
                }
                printWindow.document.write(`<style>${cssText}</style>`);
              } else if (sheet.href) {
                printWindow.document.write(`<link rel="stylesheet" href="${sheet.href}">`);
              }
            } catch (e) {
              // Ignorar hojas inaccesibles
            }
          }

          printWindow.document.write("</head><body>");
          printWindow.document.write(printContents.innerHTML);
          printWindow.document.write("</body></html>");
          printWindow.document.close();
          printWindow.focus();
          printWindow.print();
          printWindow.close();
        } else {
          window.print();
        }
      } else {
        toast.error("La respuesta del servidor no tiene el formato esperado.");
      }
    } catch (error) {
      toast.error(error.response?.data?.message || "Hubo un problema al imprimir la orden.");
      resetPrintData();
    } finally {
      setTimeout(resetPrintData, 500);
    }
  };

  const printOrderThermal54 = async (orderId) => {
    try {
      const response = await axios.get(`/tpv/orders/${orderId}/print`);
      if (response.data?.data?.order) {
        prepareOrderData(response.data.data);
        isPrinting.value = true;
        await nextTick();
        const printContents = document.getElementById("orderPrintThermal54");
        if (printContents) {
          const win = window.open("", "", "height=400,width=280");
          win.document.write("<html><head><title>Ticket 54mm - Farmacia Barrio Sucre</title>");
          win.document.write("<style>" + THERMAL_54MM_CSS + "</style>");
          win.document.write("</head><body>");
          win.document.write(printContents.innerHTML);
          win.document.write("</body></html>");
          win.document.close();
          win.focus();
          win.print();
          win.close();
        } else {
          toast.error("No se encontró el contenido del ticket térmico.");
        }
      } else {
        toast.error("La respuesta del servidor no tiene el formato esperado.");
      }
    } catch (error) {
      toast.error(error.response?.data?.message || "Error al imprimir el ticket térmico.");
    } finally {
      setTimeout(resetPrintData, 500);
    }
  };

  return {
    isPrinting,
    orderData,
    orderItems,
    paymentsForPrint,
    changeAmountForPrint,
    creditAmountForPrint,
    amountForPrint,
    creditForPrint,
    currency,
    prepareOrderData,
    resetPrintData,
    printOrder,
    printOrderThermal54,
  };
}
