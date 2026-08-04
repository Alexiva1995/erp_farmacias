<script setup>
import IncomeStatementFilters from "@/components/IncomeStatementFilters.vue";
import IncomeStatementMobileList from "@/components/IncomeStatementMobileList.vue";
import IncomeStatementSummaryCards from "@/components/IncomeStatementSummaryCards.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

// Estado reactivo principal
const loadingSummary = ref(false);
const loadingDetails = ref(false);

const summary = ref({});
const transactions = ref([]);
const totalItems = ref(0);
const itemsPerPage = ref(50);
const page = ref(1);

const startDate = ref(null);
const endDate = ref(null);
const searchQuery = ref("");
const selectedType = ref(null);

const headers = [
  { title: "FECHA", key: "date", sortable: true },
  { title: "TIPO", key: "type", sortable: false, align: "center" },
  { title: "DESCRIPCIÓN", key: "description", sortable: true },
  { title: "ENTIDAD/ORG", key: "client", sortable: true },
  { title: "MONTO (USD)", key: "amount", sortable: true, align: "end" },
  { title: "COSTOS (USD)", key: "costs", sortable: true, align: "end" },
  { title: "UTILIDAD (USD)", key: "profit", sortable: true, align: "end" },
];

const loadSummary = async () => {
  loadingSummary.value = true;
  try {
    const params = new URLSearchParams();
    if (startDate.value) params.append("start_date", startDate.value);
    if (endDate.value) params.append("end_date", endDate.value);
    if (searchQuery.value) params.append("search", searchQuery.value);

    const response = await axios.get(`/finances/income-statement/summary?${params}`);
    if (response.data?.success) {
      summary.value = response.data.data;
    }
  } catch (error) {
    toast.error("Error al cargar el resumen del estado de resultados");
  } finally {
    loadingSummary.value = false;
  }
};

const loadDetails = async () => {
  loadingDetails.value = true;
  try {
    const params = new URLSearchParams();
    if (startDate.value) params.append("start_date", startDate.value);
    if (endDate.value) params.append("end_date", endDate.value);
    if (searchQuery.value) params.append("search", searchQuery.value);
    if (selectedType.value) params.append("type", selectedType.value);
    params.append("page", page.value);
    params.append("per_page", itemsPerPage.value);

    const response = await axios.get(`/finances/income-statement/details?${params}`);
    if (response.data?.success) {
      transactions.value = response.data.data.transactions || [];
      totalItems.value = response.data.data.pagination?.total || 0;
    }
  } catch (error) {
    toast.error("Error al cargar los detalles del estado de resultados");
  } finally {
    loadingDetails.value = false;
  }
};

const loadData = async () => {
  page.value = 1;
  await Promise.all([loadSummary(), loadDetails()]);
};

const updateOptions = (opts) => {
  page.value = opts.page;
  itemsPerPage.value = opts.itemsPerPage;
  loadDetails();
};

const clearFilters = () => {
  startDate.value = null;
  endDate.value = null;
  searchQuery.value = "";
  selectedType.value = null;
  loadData();
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
  }).format(amount || 0);
};

const formatDate = (date) => {
  if (!date) return "—";
  return new Date(date).toLocaleDateString("es-VE");
};

const handleReset = async () => {
  const result = await toast.fire({
    title: "¿Estás seguro?",
    text: "El reporte se reiniciará para tomar datos desde hoy en adelante por defecto. Podrás volver a ver datos antiguos seleccionando el rango de fechas manualmente.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, reiniciar",
    cancelButtonText: "Cancelar",
    customClass: {
      confirmButton: "v-btn v-btn--elevated v-theme--light bg-error v-btn--density-default v-btn--size-default v-btn--variant-elevated",
      cancelButton: "v-btn v-btn--flat v-theme--light bg-secondary v-btn--density-default v-btn--size-default v-btn--variant-text",
    },
  });

  if (result.isConfirmed) {
    try {
      await axios.post("/finances/income-statement/reset");
      toast.success("Reporte reiniciado con éxito");
      clearFilters();
    } catch (error) {
      toast.error("Error al reiniciar el reporte");
    }
  }
};

let debounceTimeout = null;
const debouncedLoadData = () => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    loadData();
  }, 400);
};

onMounted(() => {
  loadData();
});

watch([startDate, endDate, selectedType], () => loadData());
watch(searchQuery, () => debouncedLoadData());
</script>

<template>
  <div class="income-statement-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Componente Filtros -->
      <IncomeStatementFilters
        v-model:searchQuery="searchQuery"
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        v-model:selectedType="selectedType"
        class="mb-5"
        @clear="clearFilters"
        @reset="handleReset"
      />

      <!-- Componente Tarjetas de Resumen KPI -->
      <IncomeStatementSummaryCards
        :summary="summary"
        :loading="loadingSummary"
      />

      <!-- Tabla / Tarjetas de Detalles -->
      <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
        <div class="px-6 py-4 bg-white border-b d-flex justify-space-between align-center flex-wrap gap-4">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="elevated" size="36" class="rounded-lg shadow-sm">
              <VIcon icon="tabler-list-details" color="white" size="20" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-h6 font-weight-black leading-none">Detalles de Operaciones</span>
              <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1">
                Desglose línea a línea de ingresos y egresos
              </span>
            </div>
          </div>
        </div>

        <VCardText class="pa-0">
          <!-- Vista Desktop -->
          <template v-if="!$vuetify.display.smAndDown">
            <VProgressLinear
              v-if="loadingDetails"
              indeterminate
              color="primary"
              height="3"
              class="position-absolute w-100 progress-overlay"
            />

            <VDataTableServer
              v-model:items-per-page="itemsPerPage"
              v-model:page="page"
              :headers="headers"
              :items="transactions"
              :items-length="totalItems"
              :loading="loadingDetails"
              class="premium-table"
              @update:options="updateOptions"
            >
              <template #item.date="{ item }">
                <span class="text-sm font-weight-black text-medium-emphasis">
                  {{ formatDate(item.date) }}
                </span>
              </template>

              <template #item.type="{ item }">
                <VChip
                  :color="item.type === 'sale' ? 'success' : 'error'"
                  size="x-small"
                  variant="flat"
                  class="font-weight-black px-3 rounded-lg"
                >
                  {{ item.type === "sale" ? "INGRESO" : "EGRESO" }}
                </VChip>
              </template>

              <template #item.description="{ item }">
                <span class="text-sm font-weight-bold">
                  {{ item.description }}
                </span>
              </template>

              <template #item.client="{ item }">
                <span class="text-sm font-weight-medium text-high-emphasis">
                  {{ item.client || item.category || "N/A" }}
                </span>
              </template>

              <template #item.amount="{ item }">
                <span
                  class="text-sm font-weight-black"
                  :class="item.type === 'sale' ? 'text-success' : 'text-error'"
                >
                  {{ item.type === "sale" ? "+" : "-" }}{{ formatCurrency(item.amount) }}
                </span>
              </template>

              <template #item.costs="{ item }">
                <span class="text-sm font-weight-black text-warning">
                  {{ item.costs > 0 ? "-" + formatCurrency(item.costs) : "—" }}
                </span>
              </template>

              <template #item.profit="{ item }">
                <VChip
                  :color="item.profit >= 0 ? 'success' : 'error'"
                  variant="tonal"
                  size="small"
                  class="font-weight-black px-4 rounded-lg"
                >
                  {{ item.profit >= 0 ? "+" : "" }}{{ formatCurrency(item.profit) }}
                </VChip>
              </template>
            </VDataTableServer>
          </template>

          <!-- Vista Móvil Refactorizada -->
          <IncomeStatementMobileList
            v-else
            v-model:page="page"
            :transactions="transactions"
            :loading="loadingDetails"
            :total-items="totalItems"
            :items-per-page="itemsPerPage"
            @update:page="loadDetails"
          />
        </VCardText>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.income-statement-view {
  background-color: #f8fafc;
  min-block-size: 100vh;
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.progress-overlay {
  z-index: 1;
}

:deep(.v-data-table.premium-table) {
  background: white;
}

:deep(.v-data-table.premium-table th) {
  background-color: white !important;
  block-size: 52px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

:deep(.v-data-table.premium-table td) {
  block-size: 64px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  padding-block: 12px !important;
}
</style>
