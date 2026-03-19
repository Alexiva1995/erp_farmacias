<script setup>
import CashClosingSellersTicke from "@/components/CashClosingSellersTicke.vue";
import CashClosureTicke from "@/components/CashClosureTicke.vue";
import DailyCashClosingTable from "@/components/DailyCashClosingTable.vue";
import HistoryCashClosureTicke from "@/components/HistoryCashClosureTicke.vue";
import MonthlyCashClosingTable from "@/components/MonthlyCashClosingTable.vue";
import SellerBoxTable from "@/components/SellerBoxTable.vue";
import SellerCashFilters from "@/components/SellerCashFilters.vue";
import CashAverage from "@/components/cards/CashAverage.vue";
import ClosingModal from "@/components/dialogs/ClosingModal.vue";
import DailyCashModal from "@/components/dialogs/DailyCashModal.vue";
import DeliveryModal from "@/components/dialogs/DeliveryModal.vue";
import MonthlyCashModal from "@/components/dialogs/MonthlyCashModal.vue";
import ConsolidationReferenceModal from "@/components/dialogs/ReferenceModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { nextTick, onMounted, ref } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const sellerCash = ref([]);
const totalSellerCash = ref(0);
const loadingSellerCash = ref(false);
const pageSellerCash = ref(1);
const itemsPerPageSellerCash = ref(10);
const sortBySellerCash = ref();
const orderBySellerCash = ref();
const startDateFilter = ref(null);
const endDateFilter = ref(null);

const dailyCash = ref([]);
const totalDailyCash = ref(0);
const loadingDailyCash = ref(false);
const pageDailyCash = ref(1);
const itemsPerPageDailyCash = ref(10);
const sortByDailyCash = ref();
const orderByDailyCash = ref();

const monthlyCash = ref([]);
const totalMonthlyCash = ref(0);
const loadingMonthlyCash = ref(false);
const pageMonthlyCash = ref(1);
const itemsPerPageMonthlyCash = ref(10);
const sortByMonthlyCash = ref();
const orderByMonthlyCash = ref();

const viewModal = ref(false);
const monthlyCashData = ref(null);

const originalMonthlyIds = ref([]);

const orderDataHistory = ref(null);
const isDownload = ref(false);
const cashData = ref(null);
const isDownloadingPdf = ref(false);
const isPrinting = ref(false);

const viewModalDaily = ref(false);
const dailyCashData = ref({});

const referenceData = ref([]);
const viewModalReference = ref(false);

const monthlyCashDataSellers = ref(null);
const isDownloadCashDataSellers = ref(false);

const delivery = ref(null);
const viewModalDelivery = ref(false);

const viewModalClosing = ref(false);

const summaryData = ref({
  current_month_average: "0.00",
  last_month_average: "0.00",
  percentage_change: "0.0",
  is_positive: true,
});

const filterSearchQuery = ref("");

const fetchSummaryData = async () => {
  try {
    const response = await axios.get("/finances/cash-closure/sales/summary");
    summaryData.value = response.data;
  } catch (error) {
    console.error("Error al obtener el resumen de ventas:", error);
  }
};

const fetchDailyCashData = async () => {
  loadingDailyCash.value = true;
  const params = {
    page: pageDailyCash.value,
    itemsPerPage: itemsPerPageDailyCash.value,
    sortBy: sortByDailyCash.value,
    orderBy: orderByDailyCash.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/finances/cash-closure/dailyCash", {
      params,
    });
    dailyCash.value = response.data.data;
    totalDailyCash.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los cierres diarios:", error);
    toast.error("Error al obtener los cierres diarios.");
  } finally {
    loadingDailyCash.value = false;
  }
};

const fetchMonthlyCashData = async () => {
  loadingMonthlyCash.value = true;
  const params = {
    page: pageMonthlyCash.value,
    itemsPerPage: itemsPerPageMonthlyCash.value,
    sortBy: sortByMonthlyCash.value,
    orderBy: orderByMonthlyCash.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/finances/cash-closure/monthlyCash", {
      params,
    });
    monthlyCash.value = response.data.data;
    totalMonthlyCash.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los cierres mensuales:", error);
    toast.error("Error al obtener los cierres mensuales.");
  } finally {
    loadingMonthlyCash.value = false;
  }
};

const fetchSellerCashData = async () => {
  loadingSellerCash.value = true;
  const params = {
    q: filterSearchQuery.value,
    ...(startDateFilter.value !== null && {
      start_date: startDateFilter.value,
    }),
    ...(endDateFilter.value !== null && {
      end_date: endDateFilter.value,
    }),
    page: pageSellerCash.value,
    itemsPerPage: itemsPerPageSellerCash.value,
    sortBy: sortBySellerCash.value,
    orderBy: orderBySellerCash.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/finances/cash-closure/sellerCash", {
      params,
    });
    sellerCash.value = response.data.data;
    totalSellerCash.value = response.data.total;
  } catch (error) {
    console.error(
      "Hubo un error al obtener los cierres de los vendedor:",
      error
    );
    toast.error("Error al obtener los cierres de los vendedor.");
  } finally {
    loadingSellerCash.value = false;
  }
};

onMounted(() => {
  fetchSummaryData();
  fetchDailyCashData();
  fetchMonthlyCashData();
  fetchSellerCashData();
});

let debounceTimerDaily;
watch(
  [pageDailyCash, itemsPerPageDailyCash, sortByDailyCash, orderByDailyCash],
  () => {
    clearTimeout(debounceTimerDaily);
    debounceTimerDaily = setTimeout(() => {
      fetchDailyCashData();
    }, 300);
  },
  { deep: true }
);

let debounceTimerMonthly;
watch(
  [
    pageMonthlyCash,
    itemsPerPageMonthlyCash,
    sortByMonthlyCash,
    orderByMonthlyCash,
  ],
  () => {
    clearTimeout(debounceTimerMonthly);
    debounceTimerMonthly = setTimeout(() => {
      fetchMonthlyCashData();
    }, 300);
  },
  { deep: true }
);

let debounceTimerSellerCashData;
watch(
  [
    pageSellerCash,
    itemsPerPageSellerCash,
    sortBySellerCash,
    orderBySellerCash,
    startDateFilter,
    endDateFilter,
    filterSearchQuery,
  ],
  () => {
    clearTimeout(debounceTimerSellerCashData);
    debounceTimerSellerCashData = setTimeout(() => {
      fetchSellerCashData();
    }, 300);
  },
  { deep: true }
);

const updateTableOptionsDailyCash = (options) => {
  pageDailyCash.value = options.page;
  itemsPerPageDailyCash.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByDailyCash.value = options.sortBy[0].key;
    orderByDailyCash.value = options.sortBy[0].order;
  } else {
    sortByDailyCash.value = null;
    orderByDailyCash.value = null;
  }
};
const updateTableOptionsMonthlyCash = (options) => {
  pageMonthlyCash.value = options.page;
  itemsPerPageMonthlyCash.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByMonthlyCash.value = options.sortBy[0].key;
    orderByMonthlyCash.value = options.sortBy[0].order;
  } else {
    sortByMonthlyCash.value = null;
    orderByMonthlyCash.value = null;
  }
};
const updateTableOptionsSellerCash = (options) => {
  pageSellerCash.value = options.page;
  itemsPerPageSellerCash.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBySellerCash.value = options.sortBy[0].key;
    orderBySellerCash.value = options.sortBy[0].order;
  } else {
    sortBySellerCash.value = null;
    orderBySellerCash.value = null;
  }
};

const handleClearFilters = () => {
  filterSearchQuery.value = "";
  sortBySellerCash.value = undefined;
  orderBySellerCash.value = undefined;
  startDateFilter.value = null;
  endDateFilter.value = null;
};

watch([filterSearchQuery], () => {
  pageSellerCash.value = 1;
});

const viewMonthlyCash = async (cash) => {
  try {
    originalMonthlyIds.value = cash.daily_closure_ids;
    const params = {
      closingMonthlyIds: cash.daily_closure_ids,
    };
    const response = await axios.get(
      "/finances/cash-closure/monthlyCashclosing",
      { params }
    );
    monthlyCashData.value = response.data.data;
    viewModal.value = true;
  } catch (error) {
    console.error("Error al obtener los detalles del cierre:", error);
    toast.error("Error al obtener los detalles del cierre.");
  }
};

const handleCloseViewModal = () => {
  viewModal.value = false;
};

const ticketStyles = `
/* CSS Adaptado para Ticket Térmico POS */
@page {
  margin: 0;
  size: 80mm auto; /* Formato térmico estándar de 80mm */
}
body {
  margin: 0;
  padding: 5px;
  background-color: #fff;
  font-family: 'Courier New', Courier, monospace; /* Fuente monospace obligatoria */
  font-size: 13px !important;
  color: #000 !important;
  line-height: 1.2;
}
* {
  box-sizing: border-box;
}
.pa-2 { padding: 4px; }
.pa-4 { padding: 8px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-left { text-align: left; }
.mb-2 { margin-bottom: 6px; }
.tbody-bordered { border: none; }
.center-block { margin-left: auto; margin-right: auto; }
.w-75, .w-100 { width: 100% !important; }
.mx-auto { margin-left: auto !important; margin-right: auto !important; }
table { width: 100% !important; border-collapse: collapse; }
td, th { padding: 1px 0; }
hr { border: none; border-top: 1px dashed #000; margin: 5px 0; }
.pdf-row-2col { width: 100%; display: block; }
.pdf-col-multi {
  float: left;
  width: 48%; 
  padding: 0 2px; 
  margin-right: 2%;
}
.pdf-row-multi:after {
  content: "";
  display: table; 
  clear: both;
}
.ticket-bold { font-weight: bold; }
/* Ocultar bordes de VCard para impresión */
.v-card--variant-outlined { border: none !important; }
.v-card {
   box-shadow: none !important;
   border: none !important;
   background: transparent !important;
}
`;

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

const handleCloseViewModalDaily = () => {
  viewModalDaily.value = false;
};

const handleCloseViewModalReference = () => {
  viewModalReference.value = false;
};

const handleCloseViewModalDelivery = () => {
  viewModalDelivery.value = false;
};

const handleCloseViewModalClosing = () => {
  viewModalClosing.value = false;
};

const viewDailyCash = async (daily) => {
  try {
    const itemDaily = daily;
    dailyCashData.value = itemDaily;
    viewModalDaily.value = true;
  } catch (error) {
    console.error("Error al obtener los detalles del cierre diario:", error);
    toast.error("Error al obtener los detalles del cierre diario.");
  }
};

const loadingRefId = ref(null);

const referenceDaily = async (daily) => {
  try {
    if (!daily || !daily.cash_closings || daily.cash_closings.length === 0) {
      console.warn("No hay cierres de caja para procesar.");
      return [];
    }
    const allPaymentReferences = daily.cash_closings.flatMap((closing) => {
      // Procesar todas las órdenes que tengan pagos, independientemente de su estado para asegurar visibilidad
      return (closing.orders || []).flatMap((order) => {
        let paymentMethods = order.payment_methods;
        
        // Manejar caso donde paymentMethods sea un string JSON
        if (typeof paymentMethods === 'string') {
          try {
            paymentMethods = JSON.parse(paymentMethods);
          } catch (e) {
            paymentMethods = [];
          }
        }
        
        // Asegurar que sea un array
        if (!Array.isArray(paymentMethods)) {
          paymentMethods = paymentMethods ? [paymentMethods] : [];
        }

        if (paymentMethods.length === 0) return [];
        
        // Filtrar solo métodos que tengan una referencia válida
        const methodsWithReference = paymentMethods.filter(
          (method) =>
            method &&
            method.reference !== undefined &&
            method.reference !== null &&
            String(method.reference).trim() !== "" &&
            String(method.reference).toLowerCase() !== "null"
        );

        return methodsWithReference.map((method) => ({
          ...method,
          order_id: order.id,
          order_currency: order.currency,
          seller_name: closing.seller?.username || 'N/A',
          is_confirmed: method.is_confirmed || false
        }));
      });
    });

    const references = allPaymentReferences;
    viewModalReference.value = true;
    
    // Usar nextTick para asegurar que el componente esté montado antes de pasarle datos complejos
    await nextTick();
    referenceData.value = references;
    dailyCashData.value = daily;
  } catch (error) {
    console.error("Error al obtener las referencias del cierre diario:", error);
    toast.error("Error al obtener las referencias del cierre diario.");
  }
};

const deliveryDaily = async (daily) => {
  try {
    if (!daily || !daily.cash_closings || daily.cash_closings.length === 0) {
      console.warn("No hay cierres de caja para procesar.");
      return [];
    }
    const cash = daily;
    dailyCashData.value = cash;
    viewModalDelivery.value = true;
  } catch (error) {
    console.error(
      "Error al obtener las tipos de entrega del cierre diario:",
      error
    );
    toast.error("Error al obtener las tipos de entrega  del cierre diario.");
  }
};

const closingCashAllSellers = async (cash) => {
  try {
    const paramsData = {
      closingMonthlyIds: cash.daily_closure_ids,
    };

    const responseData = await axios.get(
      "/finances/cash-closure/monthlyCashclosingAllSellers",
      { params: paramsData }
    );
    monthlyCashDataSellers.value = responseData.data.data;

    isDownloadCashDataSellers.value = true;
    await nextTick();
    const printContents = document.getElementById("cashClosingSellersDownload");
    if (!printContents) {
      toast.error("Hubo un error al generar el PDF. Contenido no disponible.");
      return;
    }
    const htmlContent = printContents.innerHTML;

    const params = {
      html_content: `<style>${ticketStyles}</style>${htmlContent}`,
      filename: "Cierre de caja",
    };

    const response = await axios.post(
      "/finances/cash-closure/downloadReport",
      params,
      {
        responseType: "blob",
      }
    );
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    let filename = "CierreCaja.pdf";
    link.href = url;
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success("PDF generado y descargado con éxito.");
  } catch (error) {
    console.error(
      "Error al obtener los detalles del cierre por vendedor:",
      error
    );
    toast.error("Error al obtener los detalles del cierre por vendedor.");
  } finally {
    isDownloadCashDataSellers.value = false;
    monthlyCashDataSellers.value = null;
  }
};

const deliveryModalRef = ref(null);
const dailyCashModalRef = ref(null);

const closingDaily = async (daily) => {
  try {
    dailyCashData.value = daily;
    
    // Abrimos los modales internamente y disparamos la impresión
    // Nota: Necesitaremos exponer las funciones de impresión en los componentes hijos
    toast.info("Generando reportes de Cierre y Acta...");
    
    viewModalDaily.value = true;
    viewModalDelivery.value = true;

    // Pequeño delay para asegurar que los componentes estén montados
    setTimeout(async () => {
      if (dailyCashModalRef.value?.printReport) {
        await dailyCashModalRef.value.printReport();
      }
      if (deliveryModalRef.value?.printReport) {
        await deliveryModalRef.value.printReport();
      }
    }, 500);

  } catch (error) {
    console.error("Error al procesar la impresión dual:", error);
    toast.error("Error al generar los reportes.");
  }
};
</script>
<template>
  <div :class="mobile ? 'pa-0 pb-16' : 'pa-4'">
    <CashAverage
      :average-amount="summaryData.current_month_average"
      :last-month-average="summaryData.last_month_average"
      :percentage-change="summaryData.percentage_change"
      :is-positive="summaryData.is_positive"
    />
    
    <div class="mb-6"></div>

    <SellerCashFilters
      v-model:searchQuery="filterSearchQuery"
      v-model:startDate="startDateFilter"
      v-model:endDate="endDateFilter"
      :loading="loadingSellerCash || loadingDailyCash || loadingMonthlyCash"
      :showDateFilters="true"
      :showStateFilters="true"
      @clear="handleClearFilters"
      @refresh="onMounted(() => { fetchSummaryData(); fetchDailyCashData(); fetchMonthlyCashData(); fetchSellerCashData(); })"
    />

    <div class="mb-4"></div>

    <SellerBoxTable
      :sellerCash="sellerCash"
      :loading="loadingSellerCash"
      :total-sellerCash="totalSellerCash"
      :items-per-page="itemsPerPageSellerCash"
      :page="pageSellerCash"
      @update:options="updateTableOptionsSellerCash"
      @print-cash="printCash"
      @download-cash="downloadcash"
    />

    <div class="mb-6"></div>

    <DailyCashClosingTable
      :dailyCash="dailyCash"
      :loading="loadingDailyCash"
      :total-dailyCash="totalDailyCash"
      :items-per-page="itemsPerPageDailyCash"
      :page="pageDailyCash"
      :loading-id="loadingRefId"
      @update:options="updateTableOptionsDailyCash"
      @view-cash="viewDailyCash"
      @delivery="deliveryDaily"
      @reference="referenceDaily"
      @closing-daily="closingDaily"
    />

    <div class="mb-6"></div>

    <MonthlyCashClosingTable
      :monthlyCash="monthlyCash"
      :loading="loadingMonthlyCash"
      :total-monthlyCash="totalMonthlyCash"
      :items-per-page="itemsPerPageMonthlyCash"
      :page="pageMonthlyCash"
      @update:options="updateTableOptionsMonthlyCash"
      @view-cash="viewMonthlyCash"
    />

    <!-- Modales -->
    <MonthlyCashModal
      v-model:isDialogVisible="viewModal"
      :monthlyCash-data="monthlyCashData"
      :original-ids="originalMonthlyIds"
      @close="handleCloseViewModal"
    />

    <DailyCashModal
      ref="dailyCashModalRef"
      v-model:isDialogVisible="viewModalDaily"
      :cashData="dailyCashData"
      @close="handleCloseViewModalDaily"
    />

    <ConsolidationReferenceModal
      v-if="viewModalReference"
      v-model:isDialogVisible="viewModalReference"
      :reference="referenceData"
      :cashData="dailyCashData"
      @close="handleCloseViewModalReference"
    />

    <DeliveryModal
      ref="deliveryModalRef"
      v-model:isDialogVisible="viewModalDelivery"
      :cashData="dailyCashData"
      @close="handleCloseViewModalDelivery"
    />

    <ClosingModal
      v-model:isDialogVisible="viewModalClosing"
      :reference="referenceData"
      :cashData="dailyCashData"
      @close="handleCloseViewModalClosing"
    />
  </div>

  <div
    id="CashClosurePrint"
    :class="{ 'd-none': !isPrinting, 'print-container': true }"
  >
    <CashClosureTicke
      v-if="isPrinting && cashData"
      :cash-data="cashData"
      :isPdf="isDownloadingPdf"
    />
  </div>

  <div
    id="HistoryDownload"
    :class="{ 'd-none': !isDownload, 'print-container': true }"
  >
    <HistoryCashClosureTicke
      v-if="isDownload && orderDataHistory"
      :order-data="orderDataHistory"
      :cash-data="cashData"
    />
  </div>

  <div
    id="cashClosingSellersDownload"
    :class="{ 'd-none': !isDownloadCashDataSellers, 'print-container': true }"
  >
    <CashClosingSellersTicke
      v-if="isDownloadCashDataSellers && monthlyCashDataSellers"
      :monthly-cash-data-sellers="monthlyCashDataSellers"
    />
  </div>
</template>
