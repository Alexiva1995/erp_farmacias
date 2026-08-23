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
import MismatchesModal from "@/components/dialogs/MismatchesModal.vue";
import MonthlyCashModal from "@/components/dialogs/MonthlyCashModal.vue";
import ConsolidationReferenceModal from "@/components/dialogs/ReferenceModal.vue";
import { useCashClosurePrint } from "@/composables/useCashClosurePrint";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const authStore = useAuthStore();
const { mobile } = useDisplay();
const isInitialized = ref(false);

const isSupervisor = computed(() => authStore.user?.role_id === 2);
const activeTab = ref(authStore.user?.role_id === 2 ? "daily" : "sellers");

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

const viewModalDaily = ref(false);
const dailyCashData = ref({});
const viewModalMismatch = ref(false);

const referenceData = ref([]);
const viewModalReference = ref(false);

const viewModalClosing = ref(false);
const tpvPaymentMethods = ref({ COP: [], USD: [], BS: [] });

const summaryData = ref({
  current_month_average: "0.00",
  last_month_average: "0.00",
  percentage_change: "0.0",
  is_positive: true,
});

const filterSearchQuery = ref("");

const {
  isDownload,
  isDownloadingPdf,
  isPrinting,
  downloadingCashId,
  cashData,
  orderDataHistory,
  isDownloadCashDataSellers,
  monthlyCashDataSellers,
  downloadcash,
  printCash,
  closingCashAllSellers,
} = useCashClosurePrint();

const fetchTpvSettings = async () => {
  try {
    const { data } = await axios.get("/general-settings");
    if (data.data && data.data.tpv_payment_methods) {
      tpvPaymentMethods.value = data.data.tpv_payment_methods;
    }
  } catch (error) {
    console.error("Error al obtener métodos de pago del TPV:", error);
  }
};

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
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );
  try {
    const response = await axios.get("/finances/cash-closure/dailyCash", { params });
    dailyCash.value = response.data.data;
    totalDailyCash.value = response.data.total;

    if (viewModalReference.value && dailyCashData.value.id) {
      const updatedDaily = dailyCash.value.find(d => d.id === dailyCashData.value.id);
      if (updatedDaily) syncReferenceData(updatedDaily);
    }
    if (viewModalMismatch.value && dailyCashData.value.id) {
      const updatedDaily = dailyCash.value.find(d => d.id === dailyCashData.value.id);
      if (updatedDaily) dailyCashData.value = updatedDaily;
    }
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
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );
  try {
    const response = await axios.get("/finances/cash-closure/monthlyCash", { params });
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
    ...(startDateFilter.value !== null && { start_date: startDateFilter.value }),
    ...(endDateFilter.value !== null && { end_date: endDateFilter.value }),
    page: pageSellerCash.value,
    itemsPerPage: itemsPerPageSellerCash.value,
    sortBy: sortBySellerCash.value,
    orderBy: orderBySellerCash.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );
  try {
    const response = await axios.get("/finances/cash-closure/sellerCash", { params });
    sellerCash.value = response.data.data;
    totalSellerCash.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los cierres de los vendedor:", error);
    toast.error("Error al obtener los cierres de los vendedor.");
  } finally {
    loadingSellerCash.value = false;
  }
};

const refreshAllData = async () => {
  await Promise.all([
    fetchTpvSettings(),
    fetchSummaryData(),
    fetchDailyCashData(),
    fetchMonthlyCashData(),
    fetchSellerCashData()
  ]);
};

onMounted(async () => {
  await refreshAllData();
  isInitialized.value = true;
});

let debounceTimerDaily;
watch(
  [pageDailyCash, itemsPerPageDailyCash, sortByDailyCash, orderByDailyCash],
  () => {
    if (!isInitialized.value) return;
    clearTimeout(debounceTimerDaily);
    debounceTimerDaily = setTimeout(() => { fetchDailyCashData(); }, 300);
  },
  { deep: true },
);

let debounceTimerMonthly;
watch(
  [pageMonthlyCash, itemsPerPageMonthlyCash, sortByMonthlyCash, orderByMonthlyCash],
  () => {
    if (!isInitialized.value) return;
    clearTimeout(debounceTimerMonthly);
    debounceTimerMonthly = setTimeout(() => { fetchMonthlyCashData(); }, 300);
  },
  { deep: true },
);

let debounceTimerSellerCashData;
watch(
  [pageSellerCash, itemsPerPageSellerCash, sortBySellerCash, orderBySellerCash, startDateFilter, endDateFilter, filterSearchQuery],
  () => {
    if (!isInitialized.value) return;
    clearTimeout(debounceTimerSellerCashData);
    debounceTimerSellerCashData = setTimeout(() => { fetchSellerCashData(); }, 300);
  },
  { deep: true },
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

watch([filterSearchQuery], () => { pageSellerCash.value = 1; });

const viewMonthlyCash = async (cash) => {
  try {
    originalMonthlyIds.value = cash.daily_closure_ids;
    const params = { closingMonthlyIds: cash.daily_closure_ids };
    const response = await axios.get("/finances/cash-closure/monthlyCashclosing", { params });
    const serverData = response.data.data;
    
    serverData.totalSalesUsd = cash.amount_usd;
    serverData.totalSalesBs = cash.amount_bs;
    serverData.totalSalesCop = cash.amount_cop;
    serverData.totalSalesGlobal = cash.total_usd_equivalent;
    serverData.totalSalesCredits = cash.amount_credits;
    
    const firstClosing = serverData.summary && serverData.summary[0];
    serverData.totalSalesBsInUSD = cash.total_bs_in_usd ?? (firstClosing ? firstClosing.total_bs_in_usd : '0,00');
    serverData.totalSalesGlobalCopInUsd = cash.total_cop_in_usd ?? (firstClosing ? firstClosing.total_cop_in_usd : '0,00');

    monthlyCashData.value = serverData;
    viewModal.value = true;
  } catch (error) {
    console.error("Error al obtener los detalles del cierre:", error);
    toast.error("Error al obtener los detalles del cierre.");
  }
};

const handleCloseViewModal = () => { viewModal.value = false; };
const handleCloseViewModalDaily = () => { viewModalDaily.value = false; };
const handleCloseViewModalMismatch = () => { viewModalMismatch.value = false; };
const handleCloseViewModalReference = () => { viewModalReference.value = false; };
const handleCloseViewModalClosing = () => { viewModalClosing.value = false; };

const viewDailyCash = async (daily) => {
  try {
    dailyCashData.value = daily;
    viewModalDaily.value = true;
  } catch (error) {
    console.error("Error al obtener los detalles del cierre diario:", error);
    toast.error("Error al obtener los detalles del cierre diario.");
  }
};

const mismatchDaily = async (daily) => {
  try {
    dailyCashData.value = daily;
    viewModalMismatch.value = true;
  } catch (error) {
    console.error("Error al disparar el modal de descuadres:", error);
  }
};

const loadingRefId = ref(null);

const syncReferenceData = (daily) => {
  if (!daily || !daily.cash_closings) return;
  const allPaymentReferences = daily.cash_closings.flatMap((closing) => {
    return (closing.orders || []).flatMap((order) => {
      let paymentMethods = order.payment_methods;
      if (typeof paymentMethods === "string") {
        try { paymentMethods = JSON.parse(paymentMethods); } catch (e) { paymentMethods = []; }
      }
      if (!Array.isArray(paymentMethods)) paymentMethods = paymentMethods ? [paymentMethods] : [];
      return (paymentMethods || []).filter(m => m && m.reference).map((method) => ({
        ...method,
        order_id: order.id,
        order_currency: order.currency,
        seller_name: closing.seller?.username || "N/A",
        is_confirmed: method.is_confirmed || false,
      }));
    });
  });
  referenceData.value = allPaymentReferences;
  dailyCashData.value = daily;
};

const referenceDaily = async (daily) => {
  try {
    if (!daily || !daily.cash_closings || daily.cash_closings.length === 0) return [];
    const allPaymentReferences = daily.cash_closings.flatMap((closing) => {
      return (closing.orders || []).flatMap((order) => {
        let paymentMethods = order.payment_methods;
        if (typeof paymentMethods === "string") {
          try { paymentMethods = JSON.parse(paymentMethods); } catch (e) { paymentMethods = []; }
        }
        if (!Array.isArray(paymentMethods)) paymentMethods = paymentMethods ? [paymentMethods] : [];
        if (paymentMethods.length === 0) return [];
        const methodsWithReference = paymentMethods.filter(
          (method) =>
            method &&
            method.reference !== undefined &&
            method.reference !== null &&
            String(method.reference).trim() !== "" &&
            String(method.reference).toLowerCase() !== "null",
        );
        return methodsWithReference.map((method) => ({
          ...method,
          order_id: order.id,
          order_currency: order.currency,
          seller_name: closing.seller?.username || "N/A",
          is_confirmed: method.is_confirmed || false,
        }));
      });
    });

    viewModalReference.value = true;
    await nextTick();
    referenceData.value = allPaymentReferences;
    dailyCashData.value = daily;
  } catch (error) {
    console.error("Error al obtener las referencias del cierre diario:", error);
    toast.error("Error al obtener las referencias del cierre diario.");
  }
};

const deliveryModalRef = ref(null);
const dailyCashModalRef = ref(null);

const closingDaily = async (daily) => {
  try {
    dailyCashData.value = daily;
    toast.info("Generando reportes de Cierre y Acta...");
    viewModalDaily.value = true;

    setTimeout(async () => {
      if (dailyCashModalRef.value?.printReport) await dailyCashModalRef.value.printReport();
      if (deliveryModalRef.value?.printReport) await deliveryModalRef.value.printReport();
    }, 500);
  } catch (error) {
    console.error("Error al procesar la impresión dual:", error);
    toast.error("Error al generar los reportes.");
  }
};
</script>

<template>
  <div class="cash-closure-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <CashAverage
        v-if="authStore.isAdmin"
        :average-amount="summaryData.current_month_average"
        :last-month-average="summaryData.last_month_average"
        :percentage-change="summaryData.percentage_change"
        :is-positive="summaryData.is_positive"
        class="mb-6"
      />

      <SellerCashFilters
        v-model:searchQuery="filterSearchQuery"
        v-model:startDate="startDateFilter"
        v-model:endDate="endDateFilter"
        :loading="loadingSellerCash || loadingDailyCash || loadingMonthlyCash"
        :showDateFilters="true"
        :showStateFilters="true"
        @clear="handleClearFilters"
        @refresh="refreshAllData"
      />

      <VTabs v-model="activeTab" class="mb-6 rounded-lg border bg-surface" color="primary" grow>
        <VTab v-if="!isSupervisor" value="sellers">
          <VIcon start icon="tabler-users" />
          Historial Vendedores
        </VTab>
        <VTab value="daily">
          <VIcon start icon="tabler-calendar-stats" />
          Cierres Diarios
        </VTab>
        <VTab v-if="authStore.isAdmin" value="monthly">
          <VIcon start icon="tabler-calendar-due" />
          Cierres Mensuales
        </VTab>
      </VTabs>

      <VWindow v-model="activeTab">
        <VWindowItem v-if="!isSupervisor" value="sellers">
          <SellerBoxTable
            :sellerCash="sellerCash"
            :loading="loadingSellerCash"
            :total-sellerCash="totalSellerCash"
            :items-per-page="itemsPerPageSellerCash"
            :page="pageSellerCash"
            :tpv-payment-methods="tpvPaymentMethods"
            :downloading-cash-id="downloadingCashId"
            @update:options="updateTableOptionsSellerCash"
            @print-cash="printCash"
            @download-cash="downloadcash"
            class="mb-6"
          />
        </VWindowItem>

        <VWindowItem value="daily">
          <DailyCashClosingTable
            :dailyCash="dailyCash"
            :loading="loadingDailyCash"
            :total-dailyCash="totalDailyCash"
            :items-per-page="itemsPerPageDailyCash"
            :page="pageDailyCash"
            :loading-id="loadingRefId"
            :tpv-payment-methods="tpvPaymentMethods"
            @update:options="updateTableOptionsDailyCash"
            @view-cash="viewDailyCash"
            @reference="referenceDaily"
            @closing-daily="closingDaily"
            @mismatch="mismatchDaily"
            class="mb-2"
          />
        </VWindowItem>

        <VWindowItem v-if="authStore.isAdmin" value="monthly">
          <MonthlyCashClosingTable
            :monthlyCash="monthlyCash"
            :loading="loadingMonthlyCash"
            :total-monthlyCash="totalMonthlyCash"
            :items-per-page="itemsPerPageMonthlyCash"
            :page="pageMonthlyCash"
            :tpv-payment-methods="tpvPaymentMethods"
            @update:options="updateTableOptionsMonthlyCash"
            @view-cash="viewMonthlyCash"
          />
        </VWindowItem>
      </VWindow>
    </div>

    <!-- Modales -->
    <MonthlyCashModal
      v-model:isDialogVisible="viewModal"
      :monthlyCash-data="monthlyCashData"
      :original-ids="originalMonthlyIds"
      :tpvPaymentMethods="tpvPaymentMethods"
      @close="handleCloseViewModal"
    />

    <DailyCashModal
      ref="dailyCashModalRef"
      v-model:isDialogVisible="viewModalDaily"
      :cashData="dailyCashData"
      :tpvPaymentMethods="tpvPaymentMethods"
      @close="handleCloseViewModalDaily"
    />

    <MismatchesModal
      v-if="viewModalMismatch"
      v-model:isDialogVisible="viewModalMismatch"
      :cashData="dailyCashData"
      @refresh="fetchDailyCashData"
      @close="handleCloseViewModalMismatch"
    />

    <ConsolidationReferenceModal
      v-if="viewModalReference"
      v-model:isDialogVisible="viewModalReference"
      :reference="referenceData"
      :cashData="dailyCashData"
      @refresh="fetchDailyCashData"
      @close="handleCloseViewModalReference"
    />

    <ClosingModal
      v-model:isDialogVisible="viewModalClosing"
      :reference="referenceData"
      :cashData="dailyCashData"
      @close="handleCloseViewModalClosing"
    />
  </div>

  <div id="CashClosurePrint" :class="{ 'd-none': !isPrinting, 'print-container': true }">
    <CashClosureTicke v-if="isPrinting && cashData" :cash-data="cashData" :isPdf="isDownloadingPdf" />
  </div>

  <div id="HistoryDownload" :class="{ 'd-none': !isDownload, 'print-container': true }">
    <HistoryCashClosureTicke v-if="isDownload && orderDataHistory" :order-data="orderDataHistory" :cash-data="cashData" />
  </div>

  <div id="cashClosingSellersDownload" :class="{ 'd-none': !isDownloadCashDataSellers, 'print-container': true }">
    <CashClosingSellersTicke v-if="isDownloadCashDataSellers && monthlyCashDataSellers" :monthly-cash-data-sellers="monthlyCashDataSellers" />
  </div>
</template>
