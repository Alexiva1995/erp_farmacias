<script setup>
import CashSummary from "@/components/CashSummary.vue";
import axios from "@/plugins/axios";
import { ref, onMounted } from "vue";
import ClosedCashClosure from "@/components/dialogs/ClosedCashClosure.vue";
import ClosingHistoryTable from "@/components/ClosingHistoryTable.vue";
import { toast } from "@/plugins/sweetalert";
import CashClosureTicke from "@/components/CashClosureTicke.vue";
import OrderCashCloseTable from "@/components/OrderCashCloseTable.vue";
import OrderViewModal from "@/components/dialogs/OrderViewModal.vue";
import HistoryCashClosureTicke from "@/components/HistoryCashClosureTicke.vue";

const loading = ref(false);
const cashClosure = ref([]);
const isCloseCashModalVisible = ref(false);

const closing = ref([]);
const totalClosing = ref(0);
const loadingClosing = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const isPrinting = ref(false);
const cashData = ref(null);
const isDownload = ref(false);

const isDownloadingPdf = ref(false);

const orders = ref([]);
const totalOrders = ref(0);
const loadingOrders = ref(false);
const pageOrders = ref(1);
const itemsPerPageOrders = ref(10);
const sortByOrders = ref();
const orderByOrders = ref();

const viewModal = ref(false);
const orderData = ref(null);
const paymentsForPrint = ref([]);
const changeAmountForPrint = ref(0);
const creditAmountForPrint = ref(0);
const amountForPrint = ref(0);
const creditForPrint = ref(false);
const currency = ref("COP");
const orderItems = ref([]);

const orderDataHistory = ref(null);

const fetchCashClosure = async () => {
  try {
    loading.value = true;
    const response = await axios.get("/finances/cash-closure/");
    cashClosure.value = response.data;
  } catch (error) {
    console.error("Hubo un error al obtener el resumen de caja:", error);
    toast.error("Error al obtener el resumen de caja.");
    cashClosure.value = null;
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchCashClosure();
  fetchClosingHistory();
  fetchOrder();
});

const handleRequestCloseCash = () => {
  isCloseCashModalVisible.value = true;
};

const fetchClosingHistory = async () => {
  loadingClosing.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/finances/cash-closure/closingHistory", {
      params,
    });
    closing.value = response.data.data;
    totalClosing.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los cierres:", error);
    toast.error("Error al obtener los cierres.");
  } finally {
    loadingClosing.value = false;
  }
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  } else {
    sortBy.value = null;
    orderBy.value = null;
  }
};

const printCash = async (cash) => {
  try {
    isDownloadingPdf.value = false;
    const cashToPrint = cash;
    cashData.value = cashToPrint;
    isPrinting.value = true;
    await nextTick();
    const printContents = document.getElementById("CashClosurePrint");
    if (!printContents) {
      console.warn("Elemento #CashClosurePrint no encontrado.");
      window.print();
      return;
    }

    const printWindow = window.open("", "", "height=600,width=800");
    printWindow.document.write(
      "<html><head><title>Farmacia Barrio Sucre</title>"
    );
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
          printWindow.document.write(
            `<link rel="stylesheet" href="${sheet.href}">`
          );
        }
      } catch (e) {
        console.warn(
          "No se pudo acceder a la hoja de estilo:",
          sheet.href || sheet,
          e
        );
      }
    }
    printWindow.document.write("</head><body>");
    printWindow.document.write(printContents.innerHTML);
    printWindow.document.write("</body></html>");
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
  } catch (error) {
    console.error("Error al imprimir los detalles del cierre de caja:", error);
    toast.error("No se pudo cargar los detalles del cierre de caja.");
    isPrinting.value = false;
    cashData.value = null;
    isDownloadingPdf.value = false;
  } finally {
    setTimeout(() => {
      isPrinting.value = false;
      cashData.value = null;
      isDownloadingPdf.value = false;
    }, 500);
  }
};

const ticketStyles = `
.pa-2 { padding: 8px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.mb-2 { margin-bottom: 8px; }
.tbody-bordered { border: 1px solid #dfdfdff9; background-color: #f9f8f8; }`;

const downloadcash = async (cash) => {
  try {
    const orderToDownload = cash.orders;
    const cashToDownload = cash;
    orderDataHistory.value = orderToDownload;
    cashData.value = cashToDownload;
    isDownload.value = true;
    await nextTick();
    const printContents = document.getElementById("HistoryDownload");
    if (!printContents) {
      console.error("Elemento 'HistoryDownload' no encontrado.");
      toast.error("Hubo un error al generar el PDF. Contenido no disponible.");
      return;
    }
    const htmlContent = printContents.innerHTML;

    const response = await axios.post(
      "/finances/cash-closure/generate-pdf",
      {
        html: `<style>${ticketStyles}</style>${htmlContent}`,
        filename: `historico-${cash.id}.pdf`,
      },
      {
        responseType: "blob",
      }
    );
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `Cierre-Caja-${cash.id}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success("PDF generado y descargado con éxito.");
  } catch (error) {
    console.error("Error al descargar el PDF:", error);
    toast.error("Hubo un error al generar y descargar el PDF.");
  } finally {
    isDownload.value = false;
    orderDataHistory.value = null;
    cashData.value = null;
  }
};

const handleCompleteClosure = async ([closureData, cashClosureData]) => {
  try {
    const cashToDownload = cashClosureData;
    cashData.value = cashToDownload;
    isDownloadingPdf.value = true;
    isPrinting.value = true; 

    isDownload.value = true;
    orderDataHistory.value = cashClosureData.orders;

    await nextTick();
    const printContents = document.getElementById("CashClosurePrint");
    const historyContents = document.getElementById("HistoryDownload");

    if (!printContents) {
      console.error("Elemento 'CashClosurePrint' no encontrado.");
      toast.error("Hubo un error al generar el PDF. Contenido no disponible.");
      isPrinting.value = false;
      cashData.value = null;
      isDownloadingPdf.value = false;
      return;
    }

    if (!historyContents) {
      console.error("Elemento 'HistoryDownload' no encontrado.");
      toast.error("No se pudo generar el historial para el correo.");
      isDownload.value = false;
      return;
    }

    const htmlContent = `<style>${ticketStyles}</style>${printContents.innerHTML}`;
    const historyTicketHtml = `<style>${ticketStyles}</style>${historyContents.innerHTML}`;

     const payload = {
      id: closureData.cierre_id,
      total_cop: closureData.total_cop,
      sobrante_en_peso: closureData.sobrante_en_peso,
      entregar_efectivo_cop: closureData.entregar_efectivo_cop,
      ticket_html: htmlContent,
      history_html: historyTicketHtml,
    };

    const response = await axios.post("/finances/cash-closure/close", payload);
    toast.success("Cierre de caja completado con éxito:");
    isCloseCashModalVisible.value = false;
    const completedCashData = response.data.cash_closure_data;
    await printCash(completedCashData);
    fetchCashClosure();
    fetchClosingHistory();
    isPrinting.value = false;
    cashData.value = null;
    isDownloadingPdf.value = false;
    isDownload.value = false;
  } catch (error) {
    console.error("Error al completar el cierre de caja:", error);
    if (error.response && error.response.data && error.response.data.message) {
      toast.error(error.response.data.message);
    } else {
      toast.error("Error al completar el cierre de caja.");
    }
    isPrinting.value = false;
    cashData.value = null;
    isDownloadingPdf.value = false;
    isDownload.value = false;
  }
};

const updateTableOptionsOrders = (options) => {
  pageOrders.value = options.page;
  itemsPerPageOrders.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByOrders.value = options.sortBy[0].key;
    orderByOrders.value = options.sortBy[0].order;
  } else {
    sortByOrders.value = null;
    orderByOrders.value = null;
  }
};

const fetchOrder = async () => {
  loadingOrders.value = true;
  const params = {
    page: pageOrders.value,
    itemsPerPage: itemsPerPageOrders.value,
    sortBy: sortByOrders.value,
    orderBy: orderByOrders.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/finances/cash-closure/orders", {
      params,
    });
    orders.value = response.data.data;
    totalOrders.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener las ordenes:", error);
    toast.error("Error al obtener las ordenes.");
  } finally {
    loadingOrders.value = false;
  }
};

const handleViewOrder = async (orderId) => {
  try {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    if (response.data && response.data.data && response.data.data.order) {
      orderData.value = response.data.data.order;
      currency.value = response.data.data.order.currency.toUpperCase();
      orderItems.value = response.data.data.order.details.map((detail) => ({
        title: detail.product.name,
        selectedQuantity: detail.quantity,
        taxRate: detail.product.iva,
        price_bs: parseFloat(detail.price),
        price_cop: parseFloat(detail.price),
        price: parseFloat(detail.price),
        price_before_discount: parseFloat(detail.price_before_discount),
      }));
      paymentsForPrint.value = response.data.data.order.payment_methods;
      
      changeAmountForPrint.value = parseFloat(
        response.data.data.order.money_returns
      );

      amountForPrint.value = parseFloat(response.data.data.order.total_amount);
      creditAmountForPrint.value = response.data.data.hasCreditPayment
        ? parseFloat(response.data.data.order.total_amount)
        : 0;
      creditForPrint.value = response.data.data.hasCreditPayment;
      viewModal.value = true;
    } else {
      console.error("Respuesta de API con formato incorrecto:", response.data);
      toast.error("La respuesta del servidor no tiene el formato esperado.");
    }
  } catch (error) {
    console.error("Error al obtener los detalles de la orden:", error);
    toast.error("Error al obtener los detalles de la orden.");
  }
};

const handleCloseViewModal = () => {
  viewModal.value = false;
  orderData.value = null;
  orderItems.value = [];
  paymentsForPrint.value = [];
  changeAmountForPrint.value = 0;
  amountForPrint.value = 0;
  creditAmountForPrint.value = 0;
  creditForPrint.value = false;
};

const cancelarOrder = async (orderId) => {
  try {
    await axios.patch(`/tpv/orders/${orderId}/cancelled`);
    toast.success("Orden cancelada exitosamente.");
    fetchOrder();
  } catch (error) {
    console.error(
      "Error al cancelar la orden:",
      error.response ? error.response.data : error.message
    );
    const errorMessage =
      error.response?.data?.message ||
      "Error al cancelar la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
  }
};


let debounceTimerOrder;
watch(
  [
    pageOrders,
    itemsPerPageOrders,
    sortByOrders,
    orderByOrders,
  ],
  () => {
    clearTimeout(debounceTimerOrder);
    debounceTimerOrder = setTimeout(() => {
      fetchOrder();
    }, 300);
  },
  { deep: true }
);


let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      fetchClosingHistory();
    }, 300);
  },
  { deep: true }
);
</script>

<template>
  <div>
    <p v-if="loading">Cargando resumen de caja...</p>
    <p v-else-if="!cashClosure">No hay datos de cierre de caja disponibles.</p>
    <CashSummary
      v-else
      :cash-closure-data="cashClosure"
      @requestCloseCash="handleRequestCloseCash"
    />

    <ClosedCashClosure
      v-model:isDialogVisible="isCloseCashModalVisible"
      :cash-closure-data="cashClosure"
      @complete-cash-closure="handleCompleteClosure"
    />
  </div>
  <div class="mb-5"></div>
  <VCard title="Histórico de cierre">
    <div class="mb-2"></div>
    <ClosingHistoryTable
      :closing="closing"
      :loading="loadingClosing"
      :total-closing="totalClosing"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @print-cash="printCash"
      @download-cash="downloadcash"
    />
  </VCard>
  <div class="mb-5"></div>
  <VCard title="Lista de Ordenes">
    <div class="mb-2"></div>
    <OrderCashCloseTable
      :orders="orders"
      :loading="loadingOrders"
      :total-orders="totalOrders"
      :items-per-page="itemsPerPageOrders"
      :page="pageOrders"
      @update:options="updateTableOptionsOrders"
      @view-order="handleViewOrder"
      @cancelar-order="cancelarOrder"
    />

    <OrderViewModal
      v-model:isDialogVisible="viewModal"
      :order-data="orderData"
      :order-products="orderItems"
      :total-amount="amountForPrint"
      :selected-currency="currency"
      :payments="paymentsForPrint"
      :change-amount="changeAmountForPrint"
      :credit-amount="creditAmountForPrint"
      :credit="creditForPrint"
      @close="handleCloseViewModal"
    />
  </VCard>

    <div id="CashClosurePrint" :class="{ 'd-none': !isPrinting, 'print-container': true }">
      <CashClosureTicke
        v-if="isPrinting && cashData"
        :cash-data="cashData"
        :isPdf="isDownloadingPdf"
      />
    </div>

    <div id="HistoryDownload" :class="{ 'd-none': !isDownload, 'print-container': true }">
      <HistoryCashClosureTicke
        v-if="isDownload && orderDataHistory"
        :order-data="orderDataHistory"
        :cash-data="cashData"
      />
    </div>
</template>
