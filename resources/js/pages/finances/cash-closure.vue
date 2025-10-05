<script setup>
import SellerBoxTable from "@/components/SellerBoxTable.vue";
import DailyCashClosingTable from "@/components/DailyCashClosingTable.vue";
import MonthlyCashClosingTable from "@/components/MonthlyCashClosingTable.vue";
import CashAverage from "@/components/cards/CashAverage.vue";
import axios from "@/plugins/axios";
import { ref, onMounted } from 'vue';
import SellerCashFilters from "@/components/SellerCashFilters.vue";
import MonthlyCashModal from "@/components/dialogs/MonthlyCashModal.vue";
import HistoryCashClosureTicke from "@/components/HistoryCashClosureTicke.vue";
import { toast } from "@/plugins/sweetalert";
import CashClosureTicke from "@/components/CashClosureTicke.vue";
import DailyCashModal from "@/components/dialogs/DailyCashModal.vue";
import ReferenceModal from "@/components/dialogs/ReferenceModal.vue";
import CashClosingSellersTicke from "@/components/CashClosingSellersTicke.vue"; 

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

const reference = ref(null);
const viewModalReference = ref(false);

const monthlyCashDataSellers = ref(null)
const isDownloadCashDataSellers = ref(false);

const summaryData = ref({
    current_month_average: '0.00',
    last_month_average: '0.00',
    percentage_change: '0.0',
    is_positive: true,
});

const filterSearchQuery = ref("");

const fetchSummaryData = async () => {
    try {
        const response = await axios.get('/finances/cash-closure/sales/summary');
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
        const response = await axios.get('/finances/cash-closure/dailyCash',{params});
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
    sortBy: sortBySellerCash.value,
    orderBy: orderByMonthlyCash.value,
  };
    Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get('/finances/cash-closure/monthlyCash',{params});
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
    const response = await axios.get('/finances/cash-closure/sellerCash',{params});
    sellerCash.value = response.data.data;
    totalSellerCash.value = response.data.total;
  } catch (error) {
      console.error("Hubo un error al obtener los cierres de los vendedor:", error);
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
  [
    pageDailyCash,
    itemsPerPageDailyCash,
    sortByDailyCash,
    orderByDailyCash,
  ],
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
    filterSearchQuery
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
      closingMonthlyIds: cash.daily_closure_ids
     };
    const response = await axios.get('/finances/cash-closure/monthlyCashclosing',{params});
    monthlyCashData.value =  response.data.data
    viewModal.value = true;
  } catch (error) {
    console.error("Error al obtener los detalles del cierre:", error);
    toast.error("Error al obtener los detalles del cierre.");
  }
}

const handleCloseViewModal = () => {
  viewModal.value = false;
};

const ticketStyles = `
.pa-2 { padding: 8px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-left { text-align: left; }
.mb-2 { margin-bottom: 8px; }
.tbody-bordered { border: 1px solid #dfdfdff9; background-color: #f9f8f8; }
.center-block { margin-left: auto; margin-right: auto; }
.single-report-center { width: 50%; margin-left: auto; margin-right: auto; }
.w-75 {width: 75% !important;}
.w-100 {width: 100% !important;}
.mx-auto { margin-left: auto !important; margin-right: auto !important; }
.pdf-row-2col {
  width: 100%;
  display: block; 
}
.pdf-col-multi {
 float: left;
 /* ¡CLAVE! Reducir el ancho para dejar espacio */
width: 48%; 
box-sizing: border-box;
padding: 0 5px; 
 margin-right: 2%; /* Espacio entre columnas */
  min-height: 1px;
}

.pdf-row-multi:after {
 content: "";
 display: table; 
 clear: both;
}}
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

const viewDailyCash = async (daily) => {
try {
    const itemDaily = daily;
    dailyCashData.value =  itemDaily;
    viewModalDaily.value = true;
  } catch (error) {
    console.error("Error al obtener los detalles del cierre diario:", error);
    toast.error("Error al obtener los detalles del cierre diario.");
  }
}

const referenceDaily = async (daily) => {
  try {
   if (!daily || !daily.cash_closings || daily.cash_closings.length === 0) {
        console.warn("No hay cierres de caja para procesar.");
        return [];
    }
     const allPaymentReferences = daily.cash_closings.flatMap(closing => {
        const completedOrders = closing.orders.filter(order =>
            order.status === 'Completed'
        );
        return completedOrders.flatMap(order => {
            if (!order.payment_methods || order.payment_methods.length === 0) {
                return [];
            }
          const methodsWithReference = order.payment_methods.filter(method =>
                method.reference !== null && method.reference !== '' && method.reference !== 'null'
            );
            return methodsWithReference.map(method => ({
                ...method,
                order_id: order.id,
                order_currency: order.currency,
            }));
        });
    });

    reference.value = allPaymentReferences;
    const itemDaily = daily;
    dailyCashData.value =  itemDaily;
    viewModalReference.value = true;
  } catch (error) {
    console.error("Error al obtener las referencias del cierre diario:", error);
    toast.error("Error al obtener las referencias del cierre diario.");
  }
}

const closingCashAllSellers = async (cash) => {
  try {
    const paramsData = {
      closingMonthlyIds: cash.daily_closure_ids
     };

    const responseData = await axios.get('/finances/cash-closure/monthlyCashclosingAllSellers',{params: paramsData});
    monthlyCashDataSellers.value =  responseData.data.data

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
    console.error("Error al obtener los detalles del cierre por vendedor:", error);
    toast.error("Error al obtener los detalles del cierre por vendedor.");
  }finally {
    isDownloadCashDataSellers.value = false;
    monthlyCashDataSellers.value = null;
  }
}
</script>
<template>
  <CashAverage
    :average-amount="summaryData.current_month_average"
    :last-month-average="summaryData.last_month_average"
    :percentage-change="summaryData.percentage_change"
    :is-positive="summaryData.is_positive"
  />
  <div class="mb-5"></div>
  <SellerCashFilters
    v-model:searchQuery="filterSearchQuery"
    v-model:startDate="startDateFilter"
    v-model:endDate="endDateFilter"
    @clear="handleClearFilters"
    :showDateFilters="true"
    :showStateFilters="true"
  ></SellerCashFilters>

  <div class="mb-5"></div>
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
  <div class="mb-5"></div>
  <DailyCashClosingTable
    :dailyCash="dailyCash"
    :loading="loadingDailyCash"
    :total-dailyCash="totalDailyCash"
    :items-per-page="itemsPerPageDailyCash"
    :page="pageDailyCash"
    @update:options="updateTableOptionsDailyCash"
    @view-cash="viewDailyCash"
    @reference="referenceDaily"
  />
  <div class="mb-5"></div>
  <MonthlyCashClosingTable
    :monthlyCash="monthlyCash"
    :loading="loadingMonthlyCash"
    :total-monthlyCash="totalMonthlyCash"
    :items-per-page="itemsPerPageMonthlyCash"
    :page="pageMonthlyCash"
    @update:options="updateTableOptionsMonthlyCash"
    @view-cash="viewMonthlyCash"
    @seller-cash="closingCashAllSellers"
  />

  <MonthlyCashModal
    v-model:isDialogVisible="viewModal"
    :monthlyCash-data="monthlyCashData"
    :original-ids="originalMonthlyIds"
    @close="handleCloseViewModal"
  />

  <DailyCashModal
    v-model:isDialogVisible="viewModalDaily"
    :cashData="dailyCashData"
    @close="handleCloseViewModalDaily"
  />

  <ReferenceModal
    v-model:isDialogVisible="viewModalReference"
    :reference="reference"
    :cashData="dailyCashData"
    @close="handleCloseViewModalReference"
  />

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
