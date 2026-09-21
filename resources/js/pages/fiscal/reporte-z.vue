<script setup>
import ZReportDetailDialog from "@/components/fiscal/z-reports/ZReportDetailDialog.vue";
import ZReportFilters from "@/components/fiscal/z-reports/ZReportFilters.vue";
import ZReportKpiCards from "@/components/fiscal/z-reports/ZReportKpiCards.vue";
import ZReportTable from "@/components/fiscal/z-reports/ZReportTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

// --- Estados Reactivos ---
const reports = ref([]);
const totalReports = ref(0);
const summary = ref({
  total_reports: 0,
  total_invoices: 0,
  total_exempt: 0,
  total_base_16: 0,
  total_iva: 0,
  total_igtf_base: 0,
  total_igtf: 0,
  grand_total: 0,
});
const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(15);
const sortBy = ref("report_number");
const orderBy = ref("desc");
const searchQuery = ref("");
const isCeEnabled = ref(false);

// Modal de Detalle
const isDetailDialogVisible = ref(false);
const selectedReport = ref(null);

// Fechas predeterminadas
const getDefaultDates = (ceActive = false) => {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();
  const day = now.getDate();

  const formatOffsetDate = (d) => {
    const date = new Date(d);
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().split("T")[0];
  };

  if (ceActive) {
    if (day <= 15) {
      const start = new Date(year, month, 1);
      const end = new Date(year, month, 15);
      return { start: formatOffsetDate(start), end: formatOffsetDate(end) };
    } else {
      const start = new Date(year, month, 16);
      const end = new Date(year, month + 1, 0);
      return { start: formatOffsetDate(start), end: formatOffsetDate(end) };
    }
  } else {
    const start = new Date(year, month, 1);
    const end = new Date(year, month + 1, 0);
    return { start: formatOffsetDate(start), end: formatOffsetDate(end) };
  }
};

const initialDates = getDefaultDates(false);
const startDate = ref(initialDates.start);
const endDate = ref(initialDates.end);

// Cargar Configuración General (CE)
const fetchGeneralSettings = async () => {
  try {
    const response = await axios.get("/general-settings", {
      params: { only: "enable_ce" },
    });
    const ce = !!response.data?.data?.enable_ce;
    isCeEnabled.value = ce;

    const calibratedDates = getDefaultDates(ce);
    startDate.value = calibratedDates.start;
    endDate.value = calibratedDates.end;
  } catch (error) {
    console.error("Error al cargar configuración general:", error);
  }
};

// Cargar Reportes Z desde la API
const fetchZReports = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value || undefined,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value || undefined,
    orderBy: orderBy.value || undefined,
    startDate: startDate.value || undefined,
    endDate: endDate.value || undefined,
  };

  try {
    const response = await axios.get("/fiscal/z-reports", { params });
    reports.value = response.data.data || [];
    totalReports.value = response.data.total || 0;
    if (response.data.summary) {
      summary.value = response.data.summary;
    }
  } catch (error) {
    console.error("Error al obtener los reportes Z:", error);
    toast.error("Error al cargar los reportes Z.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer = null;
const triggerDebouncedFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchZReports();
  }, 300);
};

watch([searchQuery, startDate, endDate], () => {
  page.value = 1;
  triggerDebouncedFetch();
});

watch([page, itemsPerPage, sortBy, orderBy], () => {
  triggerDebouncedFetch();
});

onMounted(async () => {
  await fetchGeneralSettings();
  fetchZReports();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  } else {
    sortBy.value = "report_number";
    orderBy.value = "desc";
  }
};

const handleViewDetail = (item) => {
  selectedReport.value = item;
  isDetailDialogVisible.value = true;
};

const handleSort = (sortOption) => {
  sortBy.value = sortOption.key;
  orderBy.value = sortOption.order;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  const calibratedDates = getDefaultDates(isCeEnabled.value);
  startDate.value = calibratedDates.start;
  endDate.value = calibratedDates.end;
};
</script>

<template>
  <div class="reporte-z-page">
    <!-- Tarjetas KPI -->
    <ZReportKpiCards :summary="summary" :loading="loading" />

    <!-- Barra de Filtros -->
    <ZReportFilters
      v-model:search-query="searchQuery"
      v-model:start-date="startDate"
      v-model:end-date="endDate"
      :loading="loading"
      :is-ce-enabled="isCeEnabled"
      class="mb-3"
      @clear="handleClearFilters"
      @sort="handleSort"
    />

    <!-- Tabla Principal de Reportes Z -->
    <ZReportTable
      :reports="reports"
      :loading="loading"
      :total-reports="totalReports"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @view-detail="handleViewDetail"
    />

    <!-- Modal de Ticket Corte Z -->
    <ZReportDetailDialog
      v-model="isDetailDialogVisible"
      :report="selectedReport"
    />
  </div>
</template>

<style scoped>
.reporte-z-page {
  animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
