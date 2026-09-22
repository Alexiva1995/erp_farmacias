<script setup>
import CreditoFiscalTable from "@/components/CreditoFiscalTable.vue";
import DebitoFiscalTable from "@/components/DebitoFiscalTable.vue";
import DetailHistoryShowDialog from "@/components/dialogs/DetailHistoryShowDialog.vue";
import HistoryFilters from "@/components/HistoryFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

// --- Estados Reactivos Principales ---
const debitoFiscal = ref(0);
const creditoFiscal = ref(0);
const retenciones = ref(0);
const loading = ref(false);
const isCeEnabled = ref(false);
const searchQuery = ref("");
const detalleCredito = ref({});
const detalleDebito = ref({});

// Estados para la tabla de débito fiscal (Ventas)
const fiscalData = ref([]);
const totalRecords = ref(0);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref(undefined);
const orderBy = ref(undefined);
const tableLoading = ref(false);

// Estados para la tabla de crédito fiscal (Gastos con IVA)
const expensesData = ref([]);
const totalExpensesRecords = ref(0);
const expensesPage = ref(1);
const expensesItemsPerPage = ref(10);
const expensesTableLoading = ref(false);

// Diálogo de detalle de factura
const isDetailDialogVisible = ref(false);
const selectedHistory = ref({});
const currentHistoryDetails = ref([]);
const currentHistoryUser = ref({});
const historyIdToEdit = ref(null);
const historyNameToEdit = ref("");

// Formateador de moneda (Bolívares)
const formatCurrency = (amount) => {
  const number = parseFloat(amount) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(number);
};

// Cálculo automático del IVA a pagar (Débito Fiscal - Crédito Fiscal)
const ivaAPagar = computed(() => {
  return debitoFiscal.value - creditoFiscal.value;
});

// IGTF: 3% del total de ventas marcadas como SPE
const igtfAmount = computed(() => {
  const speSalesTotal = parseFloat(detalleDebito.value.total_spe_sales_amount ?? 0);
  return speSalesTotal * 0.03;
});

// Resumen de KPIs con la misma estructura visual que facturas
const kpiCards = computed(() => [
  {
    title: "DÉBITO FISCAL",
    value: `Bs. ${formatCurrency(debitoFiscal.value)}`,
    subtitle: `${detalleDebito.value.total_orders_with_iva || totalRecords.value || 0} ventas registradas`,
    icon: "tabler-receipt-tax",
    color: "warning",
    bgColor: "bg-warning-tonal",
  },
  {
    title: "CRÉDITO FISCAL",
    value: `Bs. ${formatCurrency(creditoFiscal.value)}`,
    subtitle: `${detalleCredito.value.total_expenses_with_iva || totalExpensesRecords.value || 0} gastos con IVA`,
    icon: "tabler-receipt-refund",
    color: "info",
    bgColor: "bg-info-tonal",
  },
  {
    title: "SALDO IVA",
    value: `Bs. ${formatCurrency(Math.abs(ivaAPagar.value))}`,
    subtitle: ivaAPagar.value > 0 ? "Saldo a Pagar" : (ivaAPagar.value < 0 ? "Saldo a Favor" : "Equilibrado"),
    icon: ivaAPagar.value > 0 ? "tabler-trending-up" : "tabler-trending-down",
    color: ivaAPagar.value > 0 ? "error" : "success",
    bgColor: ivaAPagar.value > 0 ? "bg-error-tonal" : "bg-success-tonal",
  },
  {
    title: "RETENCIONES",
    value: `Bs. ${formatCurrency(retenciones.value)}`,
    subtitle: "75% Estimado Crédito",
    icon: "tabler-percentage",
    color: "secondary",
    bgColor: "bg-secondary-tonal",
  },
  {
    title: "TOTAL IGTF (3%)",
    value: `Bs. ${formatCurrency(igtfAmount.value)}`,
    subtitle: `${detalleDebito.value.total_spe_count || 0} ventas SPE`,
    icon: "tabler-building-bank",
    color: "error",
    bgColor: "bg-error-tonal",
  },
]);

// Cálculo de fechas iniciales según CE (Quincenal si CE activo, Mensual si no)
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

// --- Operaciones API ---
const fetchGeneralSettings = async () => {
  try {
    const response = await axios.get("/general-settings", {
      params: { only: "enable_ce" },
    });
    const ce = !!response.data?.data?.enable_ce;
    isCeEnabled.value = ce;

    const calibrated = getDefaultDates(ce);
    startDate.value = calibrated.start;
    endDate.value = calibrated.end;
  } catch (error) {
    console.error("Error al cargar configuración general:", error);
  }
};

// Función para obtener crédito fiscal
const fetchCreditoFiscal = async () => {
  try {
    const params = {
      start_date: startDate.value || undefined,
      end_date: endDate.value || undefined,
    };

    const response = await axios.get(
      "/finances/pending-payments/credito-fiscal",
      { params },
    );

    if (response.data?.status === "success") {
      const data = response.data.data || {};
      creditoFiscal.value = data.credito_fiscal || 0;
      retenciones.value = data.retenciones ?? (creditoFiscal.value * 0.75);
      detalleCredito.value = data.detalle_credito || {};
    }
  } catch (error) {
    console.error("Error al obtener crédito fiscal:", error);
  }
};

// Función para obtener débito fiscal
const fetchDebitoFiscal = async () => {
  try {
    const params = {
      start_date: startDate.value || undefined,
      end_date: endDate.value || undefined,
    };

    const response = await axios.get("/debito-fiscal", { params });

    if (response.data?.status === "success") {
      const data = response.data.data || {};
      debitoFiscal.value = data.debito_fiscal || 0;
      detalleDebito.value = data.detalle_debito || {};
    }
  } catch (error) {
    console.error("Error al obtener débito fiscal:", error);
  }
};

// Función para obtener datos de la tabla fiscal de ventas
const fetchFiscalHistoryData = async () => {
  tableLoading.value = true;
  try {
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value || undefined,
      orderBy: orderBy.value || undefined,
      startDate: startDate.value || undefined,
      endDate: endDate.value || undefined,
      q: searchQuery.value || undefined,
    };

    const response = await axios.get("/fiscal-history", { params });

    if (response.data?.status === "success") {
      const data = response.data.data || {};
      fiscalData.value = data.data || [];
      totalRecords.value = data.pagination?.total || 0;
    }
  } catch (error) {
    console.error("Error al obtener datos fiscales:", error);
  } finally {
    tableLoading.value = false;
  }
};

// Función para obtener datos de gastos con IVA
const fetchExpensesData = async () => {
  expensesTableLoading.value = true;
  try {
    const params = {
      page: expensesPage.value,
      itemsPerPage: expensesItemsPerPage.value,
      start_date: startDate.value || undefined,
      end_date: endDate.value || undefined,
    };

    const response = await axios.get(
      "/finances/pending-payments/expenses-history",
      { params },
    );

    if (response.data?.status === "success") {
      const data = response.data.data || {};
      expensesData.value = data.data || [];
      totalExpensesRecords.value = data.pagination?.total || 0;
    }
  } catch (error) {
    console.error("Error al obtener datos de gastos:", error);
  } finally {
    expensesTableLoading.value = false;
  }
};

// Función para cargar todos los datos en paralelo
const fetchAllData = async () => {
  loading.value = true;
  try {
    await Promise.all([
      fetchCreditoFiscal(),
      fetchDebitoFiscal(),
      fetchFiscalHistoryData(),
      fetchExpensesData(),
    ]);
  } catch (error) {
    console.error("Error al obtener datos de IVA fiscal:", error);
    toast.error("Error al sincronizar datos fiscales");
  } finally {
    loading.value = false;
  }
};

// Debounce para filtros reactivos
let debounceTimer = null;
const triggerDebouncedFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchAllData();
  }, 300);
};

// Watchers para filtros
watch([searchQuery, startDate, endDate], () => {
  page.value = 1;
  expensesPage.value = 1;
  triggerDebouncedFetch();
});

watch([page, itemsPerPage, sortBy, orderBy], () => {
  fetchFiscalHistoryData();
});

watch([expensesPage, expensesItemsPerPage], () => {
  fetchExpensesData();
});

const handleTableOptionsUpdate = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  } else {
    sortBy.value = undefined;
    orderBy.value = undefined;
  }
};

const handleExpensesTableOptionsUpdate = (options) => {
  expensesPage.value = options.page;
  expensesItemsPerPage.value = options.itemsPerPage;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  const dates = getDefaultDates(isCeEnabled.value);
  startDate.value = dates.start;
  endDate.value = dates.end;
  sortBy.value = undefined;
  orderBy.value = undefined;
  page.value = 1;
  expensesPage.value = 1;
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const handleExport = async (format) => {
  const params = {
    q: searchQuery.value || undefined,
    startDate: startDate.value || undefined,
    endDate: endDate.value || undefined,
    format: format,
  };

  try {
    const response = await axios.get("/history/export", {
      params,
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = response.headers["content-disposition"];
    let fileName = `IvaGeneral_${startDate.value}_${endDate.value}.${format}`;
    if (contentDisposition) {
      const fileNameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
      if (fileNameMatch && fileNameMatch[1]) {
        fileName = fileNameMatch[1];
      }
    }

    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    toast.success("Archivo exportado con éxito.");
  } catch (error) {
    console.error("Error al exportar reporte de IVA general:", error);
    toast.error("No se pudo exportar el reporte.");
  }
};

const handleShowDetailHistory = (history) => {
  selectedHistory.value = { ...history };
  currentHistoryDetails.value = history.details || [];
  currentHistoryUser.value = history.user || {};
  isDetailDialogVisible.value = true;
  historyIdToEdit.value = history.id;
  historyNameToEdit.value = history.business_name || "";
};

onMounted(async () => {
  await fetchGeneralSettings();
  fetchAllData();
});
</script>

<template>
  <div class="iva-general-page pb-12">
    <!-- Resumen KPI con el mismo diseño que Facturas / Historial Fiscal -->
    <VRow class="mb-2">
      <VCol
        v-for="(card, index) in kpiCards"
        :key="index"
        cols="12"
        sm="6"
        md="4"
        lg=""
        class="flex-grow-1"
      >
        <VCard border variant="flat" class="kpi-card pa-3">
          <VSkeletonLoader v-if="loading" type="list-item-two-line" />
          <div v-else class="d-flex align-center gap-3">
            <div :class="['pa-3', 'kpi-icon-wrapper', card.bgColor]">
              <VIcon :icon="card.icon" size="24" :color="card.color" />
            </div>
            <div class="d-flex flex-column overflow-hidden">
              <span class="text-caption font-weight-bold text-disabled text-uppercase truncate">
                {{ card.title }}
              </span>
              <span class="text-h6 font-weight-black text-high-emphasis truncate">
                {{ card.value }}
              </span>
              <span class="text-super-xs text-medium-emphasis truncate font-weight-medium">
                {{ card.subtitle }}
              </span>
            </div>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filtros de Búsqueda y Rango de Fechas (Mensual / Quincenal según CE) -->
    <div class="d-flex flex-column gap-3">
      <HistoryFilters
        v-model:searchQuery="searchQuery"
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        :is-ce-enabled="isCeEnabled"
        :loading="loading"
        @clear="handleClearFilters"
        @export="handleExport"
        @sort="handleSort"
      />

      <!-- Tablas de Débito y Crédito Fiscal -->
      <VRow class="ma-0 mt-1">
        <!-- Tabla de Débito Fiscal (Ventas) -->
        <VCol cols="12" class="pa-0 mb-6">
          <DebitoFiscalTable
            :fiscal-data="fiscalData"
            :loading="tableLoading"
            :total-records="totalRecords"
            :items-per-page="itemsPerPage"
            :page="page"
            @update:options="handleTableOptionsUpdate"
            @show-detailHistory="handleShowDetailHistory"
          />
        </VCol>

        <!-- Tabla de Crédito Fiscal (Compras / Gastos con IVA) -->
        <VCol cols="12" class="pa-0">
          <CreditoFiscalTable
            :expenses-data="expensesData"
            :loading="expensesTableLoading"
            :total-records="totalExpensesRecords"
            :items-per-page="expensesItemsPerPage"
            :page="expensesPage"
            @update:options="handleExpensesTableOptionsUpdate"
          />
        </VCol>
      </VRow>
    </div>

    <!-- Modal de Detalle de Factura / Venta Fiscal -->
    <DetailHistoryShowDialog
      v-model="isDetailDialogVisible"
      :histories="selectedHistory"
      :details="currentHistoryDetails"
      :user="currentHistoryUser"
      :history-id="historyIdToEdit"
      :history-name="historyNameToEdit"
    />
  </div>
</template>

<style scoped>
.kpi-card {
  border-radius: 5px !important;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-icon-wrapper {
  border-radius: 5px !important;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
}

.bg-success-tonal {
  background-color: rgba(var(--v-theme-success), 0.1);
}

.bg-primary-tonal {
  background-color: rgba(var(--v-theme-primary), 0.1);
}

.bg-warning-tonal {
  background-color: rgba(var(--v-theme-warning), 0.1);
}

.bg-error-tonal {
  background-color: rgba(var(--v-theme-error), 0.1);
}

.bg-info-tonal {
  background-color: rgba(var(--v-theme-info), 0.1);
}

.bg-secondary-tonal {
  background-color: rgba(var(--v-theme-secondary), 0.1);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.1;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
