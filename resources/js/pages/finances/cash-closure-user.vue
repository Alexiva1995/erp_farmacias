<script setup>
import CashSummary from "@/components/CashSummary.vue";
import ClosingHistoryTable from "@/components/ClosingHistoryTable.vue";
import OrderCashCloseTable from "@/components/OrderCashCloseTable.vue";
import ClosedCashClosure from "@/components/dialogs/ClosedCashClosure.vue";
import OrderViewModal from "@/components/dialogs/OrderViewModal.vue";
import CashClosurePrintContainers from "@/components/CashClosurePrintContainers.vue";
import UserCashFilters from "@/components/UserCashFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, nextTick, onMounted, reactive, ref, watch } from "vue";

// Estado de carga y datos principales
const loading = ref(false);
const cashClosure = ref(null);
const isCloseCashModalVisible = ref(false);

// Parámetros y datos del Historial de Cierres (Agrupados en reactivos estructurados)
const closingState = reactive({
  items: [],
  total: 0,
  loading: false,
  options: {
    page: 1,
    itemsPerPage: 10,
    sortBy: null,
    orderBy: null,
  }
});

// Parámetros y datos de Órdenes del Turno (Agrupados en reactivos estructurados)
const ordersState = reactive({
  items: [],
  total: 0,
  loading: false,
  options: {
    page: 1,
    itemsPerPage: 10,
    sortBy: null,
    orderBy: null,
  }
});

// Estado de impresión y descarga
const isPrinting = ref(false);
const cashData = ref(null);
const isDownload = ref(false);
const isDownloadingPdf = ref(false);

// Modal e información de orden para visualización
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

const filters = ref({
  search: "",
  date_start: null,
  date_end: null,
});

const handleFilterUpdate = (newFilters) => {
  filters.value = { ...newFilters };
};

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

const fetchClosingHistory = async () => {
  closingState.loading = true;
  const params = {
    page: closingState.options.page,
    itemsPerPage: closingState.options.itemsPerPage,
    sortBy: closingState.options.sortBy,
    orderBy: closingState.options.orderBy,
    search: filters.value.search,
    date_start: filters.value.date_start,
    date_end: filters.value.date_end,
  };
  
  // Limpiar parámetros vacíos
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/finances/cash-closure/closingHistory", { params });
    closingState.items = response.data.data;
    closingState.total = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los cierres:", error);
    toast.error("Error al obtener los cierres.");
  } finally {
    closingState.loading = false;
  }
};

const fetchOrders = async () => {
  ordersState.loading = true;
  const params = {
    page: ordersState.options.page,
    itemsPerPage: ordersState.options.itemsPerPage,
    sortBy: ordersState.options.sortBy,
    orderBy: ordersState.options.orderBy,
    search: filters.value.search,
    date_start: filters.value.date_start,
    date_end: filters.value.date_end,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/finances/cash-closure/orders", { params });
    ordersState.items = response.data.data;
    ordersState.total = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener las órdenes:", error);
    toast.error("Error al obtener las órdenes.");
  } finally {
    ordersState.loading = false;
  }
};

const handleRequestCloseCash = () => {
  isCloseCashModalVisible.value = true;
};

const updateTableOptions = (options) => {
  closingState.options.page = options.page;
  closingState.options.itemsPerPage = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    closingState.options.sortBy = options.sortBy[0].key;
    closingState.options.orderBy = options.sortBy[0].order;
  } else {
    closingState.options.sortBy = null;
    closingState.options.orderBy = null;
  }
};

const updateTableOptionsOrders = (options) => {
  ordersState.options.page = options.page;
  ordersState.options.itemsPerPage = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    ordersState.options.sortBy = options.sortBy[0].key;
    ordersState.options.orderBy = options.sortBy[0].order;
  } else {
    ordersState.options.sortBy = null;
    ordersState.options.orderBy = null;
  }
};

// Impresión segura mediante Iframe oculto para evitar bloqueadores de popups
const printCash = async (cash) => {
  try {
    isDownloadingPdf.value = false;
    cashData.value = cash;
    isPrinting.value = true;
    await nextTick();
    
    const printContents = document.getElementById("CashClosurePrint");
    if (!printContents) {
      console.warn("Elemento #CashClosurePrint no encontrado.");
      window.print();
      return;
    }

    // Creación dinámica de un iframe invisible para impresión segura
    let iframe = document.getElementById("secure-print-iframe");
    if (!iframe) {
      iframe = document.createElement("iframe");
      iframe.id = "secure-print-iframe";
      iframe.style.position = "absolute";
      iframe.style.width = "0px";
      iframe.style.height = "0px";
      iframe.style.border = "none";
      document.body.appendChild(iframe);
    }

    const doc = iframe.contentWindow.document || iframe.contentDocument;
    doc.open();
    doc.write("<html><head><title>Imprimir Cierre de Caja</title>");
    
    // Inyectar hojas de estilo existentes
    const styleSheets = document.styleSheets;
    for (let i = 0; i < styleSheets.length; i++) {
      const sheet = styleSheets[i];
      try {
        if (sheet.cssRules) {
          let cssText = "";
          for (let j = 0; j < sheet.cssRules.length; j++) {
            cssText += sheet.cssRules[j].cssText;
          }
          doc.write(`<style>${cssText}</style>`);
        } else if (sheet.href) {
          doc.write(`<link rel="stylesheet" href="${sheet.href}">`);
        }
      } catch (e) {
        // Ignorar hojas de estilo con restricciones CORS
      }
    }
    
    doc.write("</head><body>");
    doc.write(printContents.innerHTML);
    doc.write("</body></html>");
    doc.close();

    // Esperar a que se carguen recursos e imprimir
    setTimeout(() => {
      iframe.contentWindow.focus();
      iframe.contentWindow.print();
    }, 250);

  } catch (error) {
    console.error("Error al imprimir los detalles del cierre de caja:", error);
    toast.error("No se pudo cargar los detalles del cierre de caja.");
  } finally {
    setTimeout(() => {
      isPrinting.value = false;
      cashData.value = null;
    }, 500);
  }
};

const ticketStyles = `
.pa-2 { padding: 8px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.mb-2 { margin-bottom: 8px; }
.tbody-bordered { border: 1px solid #dfdfdff9; background-color: #f9f8f8; }`;

/** Estilos para reporte PDF A4 */
const reportStyles = `
  @page { margin: 10mm; size: A4; }
  body { margin: 0; padding: 0; background: #fff; font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #333; }
  * { box-sizing: border-box; }
  table { width: 100% !important; border-collapse: collapse; }
  .v-card--variant-outlined { border: none !important; }
  .v-card { box-shadow: none !important; border: none !important; background: transparent !important; display: block !important; }
  div, span, p, h1, h2, h3, h4, td, th { display: revert; }
`;

/**
 * Sanitiza propiedades CSS lógicas a físicas para compatibilidad con DomPDF (CSS 2.1)
 */
const sanitizeHtmlForDompdf = (html) => {
  return html
    .replace(/inline-size/g, 'width')
    .replace(/block-size/g, 'height')
    .replace(/min-inline-size/g, 'min-width')
    .replace(/max-inline-size/g, 'max-width')
    .replace(/min-block-size/g, 'min-height')
    .replace(/max-block-size/g, 'max-height')
    .replace(/margin-block-start/g, 'margin-top')
    .replace(/margin-block-end/g, 'margin-bottom')
    .replace(/margin-block/g, 'margin-top')
    .replace(/margin-inline-start/g, 'margin-left')
    .replace(/margin-inline-end/g, 'margin-right')
    .replace(/margin-inline/g, 'margin-left')
    .replace(/padding-block-start/g, 'padding-top')
    .replace(/padding-block-end/g, 'padding-bottom')
    .replace(/padding-block/g, 'padding-top')
    .replace(/padding-inline-start/g, 'padding-left')
    .replace(/padding-inline-end/g, 'padding-right')
    .replace(/padding-inline/g, 'padding-left')
    .replace(/border-block-start/g, 'border-top')
    .replace(/border-block-end/g, 'border-bottom')
    .replace(/border-block/g, 'border-top')
    .replace(/border-inline-start/g, 'border-left')
    .replace(/border-inline-end/g, 'border-right')
    .replace(/border-inline/g, 'border-left')
    .replace(/text-align:\s*start/g, 'text-align: left')
    .replace(/text-align:\s*end/g, 'text-align: right');
};

const downloadcash = async (cash) => {
  try {
    toast.info("Generando reporte de cierre...");

    const response = await axios.get(
      `/finances/cash-closure/download-pdf/${cash.id}`,
      { responseType: "blob" }
    );

    const blob = new Blob([response.data], { type: "application/pdf" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `Cierre-Caja-${cash.id}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => {
      window.URL.revokeObjectURL(url);
    }, 1000);
    toast.success("PDF descargado con éxito.");
  } catch (error) {
    console.error("Error al descargar el PDF:", error);
    toast.error("Hubo un error al generar y descargar el PDF.");
  }
};

const handleCompleteClosure = async ([closureData, cashClosureData]) => {
  try {
    const cashToDownload = {
      ...cashClosureData,
      is_blind: closureData.is_blind,
      declared_cop: closureData.declared_cop,
      declared_cop_transfer: closureData.declared_cop_transfer,
      declared_usd: closureData.declared_usd,
      declared_credit: closureData.declared_credit,
      declared_bs_mobile: closureData.declared_bs_mobile,
      declared_bs_card: closureData.declared_bs_card,
    };
    cashData.value = cashToDownload;
    isDownloadingPdf.value = true;
    isPrinting.value = true;

    isDownload.value = true;
    orderDataHistory.value = cashClosureData.orders;

    await nextTick();
    const printContents = document.getElementById("CashClosurePrint");
    const historyContents = document.getElementById("HistoryDownload");

    const htmlContent = printContents ? `<style>${ticketStyles}</style>${printContents.innerHTML}` : '';
    const historyTicketHtml = historyContents ? `<style>${ticketStyles}</style>${historyContents.innerHTML}` : '';

    const payload = {
      id: closureData.cierre_id,
      total_cop: closureData.total_cop,
      sobrante_en_peso: closureData.sobrante_en_peso,
      entregar_efectivo_cop: closureData.entregar_efectivo_cop,
      is_blind: closureData.is_blind ? 1 : 0,
      declared_cop: closureData.declared_cop,
      declared_cop_transfer: closureData.declared_cop_transfer,
      declared_usd: closureData.declared_usd,
      declared_credit: closureData.declared_credit,
      declared_bs_mobile: closureData.declared_bs_mobile,
      declared_bs_card: closureData.declared_bs_card,
      ticket_html: htmlContent,
      history_html: historyTicketHtml,
    };

    const response = await axios.post("/finances/cash-closure/close", payload);
    toast.success("Cierre de caja completado con éxito");
    isCloseCashModalVisible.value = false;
    const completedCashData = response.data.cash_closure_data;
    await printCash(completedCashData);
    
    // Actualizar datos
    fetchCashClosure();
    fetchClosingHistory();
    fetchOrders();
    
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

const handleViewOrder = async (orderId) => {
  try {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    if (response.data && response.data.data && response.data.data.order) {
      const order = response.data.data.order;
      orderData.value = order;
      currency.value = order.currency.toUpperCase();
      orderItems.value = order.details.map((detail) => ({
        title: detail.product.name,
        selectedQuantity: detail.quantity,
        taxRate: 0,
        unit_price: detail.quantity > 0 ? parseFloat(detail.price) / detail.quantity : parseFloat(detail.price),
        price_bs: parseFloat(detail.price),
        price_cop: parseFloat(detail.price),
        price: parseFloat(detail.price),
        price_before_discount: parseFloat(detail.price_before_discount),
      }));
      paymentsForPrint.value = order.payment_methods;
      changeAmountForPrint.value = parseFloat(order.money_returns);
      amountForPrint.value = parseFloat(order.total_amount);
      creditAmountForPrint.value = response.data.data.hasCreditPayment
        ? parseFloat(order.total_amount)
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
  if (!orderId) return;
  Swal.fire({
    title: "¿Cancelar Orden?",
    text: `¿Estás seguro de cancelar la orden #${orderId}?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#6e7881",
    confirmButtonText: "Sí, Cancelar Orden",
    cancelButtonText: "No, Mantener",
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        await axios.patch(`/tpv/orders/${orderId}/cancelled`);
        toast.success(`Orden #${orderId} cancelada exitosamente.`);
        if (viewModal.value) handleCloseViewModal();
        fetchOrders();
        fetchCashClosure();
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
    }
  });
};

// Watcher Unificado para consultas de historial
let debounceTimer;
watch(
  [
    () => closingState.options.page,
    () => closingState.options.itemsPerPage,
    () => closingState.options.sortBy,
    () => closingState.options.orderBy,
    filters
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      fetchClosingHistory();
    }, 300);
  },
  { deep: true }
);

// Watcher Unificado para consultas de órdenes
let debounceTimerOrder;
watch(
  [
    () => ordersState.options.page,
    () => ordersState.options.itemsPerPage,
    () => ordersState.options.sortBy,
    () => ordersState.options.orderBy,
    filters
  ],
  () => {
    clearTimeout(debounceTimerOrder);
    debounceTimerOrder = setTimeout(() => {
      fetchOrders();
    }, 300);
  },
  { deep: true }
);

const isSpecialTaxpayer = computed(() => {
  if (!orderData.value) return false;
  const amount = parseFloat(orderData.value.spe_surcharge_amount || 0);
  const rate = parseFloat(orderData.value.spe_surcharge_rate || 0);
  return amount > 0 || rate > 0;
});

onMounted(() => {
  fetchCashClosure();
  fetchClosingHistory();
  fetchOrders();
});
</script>

<template>
  <div class="cash-closure-user-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      
      <!-- Cargador de Carga Principal -->
      <div v-if="loading" class="pa-12 text-center rounded-lg border bg-white my-4 shadow-sm">
        <VProgressCircular indeterminate color="primary" size="42" class="mb-3" />
        <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando cierre de caja...</div>
      </div>
      
      <template v-else>
        <!-- Resumen de Caja Superior -->
        <div v-if="cashClosure && Object.keys(cashClosure).length > 0" class="mb-2">
          <CashSummary
            :cash-closure-data="cashClosure"
            :loading="loading"
            @requestCloseCash="handleRequestCloseCash"
            class="mb-4"
          />
        </div>
        <VAlert
          v-else
          type="info"
          variant="tonal"
          class="rounded-lg mb-2"
        >
          No hay datos de cierre de caja disponibles.
        </VAlert>

        <template v-if="cashClosure && Object.keys(cashClosure).length > 0">
          <!-- Filtros -->
          <div>
            <UserCashFilters
              :loading="closingState.loading || ordersState.loading"
              @update:filters="handleFilterUpdate"
              @refresh="
                () => {
                  fetchCashClosure();
                  fetchClosingHistory();
                  fetchOrders();
                }
              "
            />
          </div>

          <!-- Tablas de Historial y Órdenes con Transición Suave -->
          <VRow class="ma-0 mx-n2">
            <!-- Histórico de Cierre -->
            <VCol v-if="!cashClosure.blind_cash_closure" cols="12" md="4" class="pa-2">
              <VCard class="rounded-lg border shadow-sm h-100 bg-surface">
                <VCardText class="pa-0">
                  <div class="px-6 py-4 border-b d-flex align-center gap-2">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      size="32"
                      class="rounded-lg"
                    >
                      <VIcon icon="tabler-history" size="18" />
                    </VAvatar>
                    <span class="text-subtitle-1 font-weight-black uppercase"
                      >Historial</span
                    >
                  </div>
                  <ClosingHistoryTable
                    :closing="closingState.items"
                    :loading="closingState.loading"
                    :total-closing="closingState.total"
                    :items-per-page="closingState.options.itemsPerPage"
                    :page="closingState.options.page"
                    @update:options="updateTableOptions"
                    @print-cash="printCash"
                  />
                </VCardText>
              </VCard>
            </VCol>

            <!-- Lista de Órdenes -->
            <VCol cols="12" :md="cashClosure.blind_cash_closure ? 12 : 8" class="pa-2">
              <VCard class="rounded-lg border shadow-sm h-100 bg-surface">
                <VCardText class="pa-0">
                  <div class="px-6 py-4 border-b d-flex align-center gap-2">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      size="32"
                      class="rounded-lg"
                    >
                      <VIcon icon="tabler-list-details" size="18" />
                    </VAvatar>
                    <span class="text-subtitle-1 font-weight-black uppercase"
                      >Órdenes del Turno</span
                    >
                  </div>
                  <OrderCashCloseTable
                    :orders="ordersState.items"
                    :loading="ordersState.loading"
                    :total-orders="ordersState.total"
                    :items-per-page="ordersState.options.itemsPerPage"
                    :page="ordersState.options.page"
                    :is-blind="cashClosure && cashClosure.blind_cash_closure"
                    @update:options="updateTableOptionsOrders"
                    @view-order="handleViewOrder"
                    @cancelar-order="cancelarOrder"
                  />
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </template>
      </template>
    </div>

    <!-- Diálogos -->
    <ClosedCashClosure
      v-model:isDialogVisible="isCloseCashModalVisible"
      :cash-closure-data="cashClosure"
      @complete-cash-closure="handleCompleteClosure"
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
      @cancel-order="cancelarOrder"
      :is-special-taxpayer="isSpecialTaxpayer"
      :is-blind="cashClosure && cashClosure.blind_cash_closure"
    />
  </div>

  <CashClosurePrintContainers
    :is-printing="isPrinting"
    :is-download="isDownload"
    :cash-data="cashData"
    :is-downloading-pdf="isDownloadingPdf"
    :order-data-history="orderDataHistory"
  />
</template>

