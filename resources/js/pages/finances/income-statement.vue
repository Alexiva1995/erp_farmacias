<script setup>
import IncomeStatementFilters from "@/components/IncomeStatementFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

// Estado reactivo
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
    if (selectedType.value) params.append("type", selectedType.value);

    const response = await axios.get(
      `/finances/income-statement/summary?${params}`,
    );
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

    const response = await axios.get(
      `/finances/income-statement/details?${params}`,
    );
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
      
      // Limpiar filtros para que el backend use la nueva fecha de corte por defecto
      startDate.value = null;
      endDate.value = null;
      searchQuery.value = "";
      selectedType.value = null;
      
      loadData();
    } catch (error) {
      toast.error("Error al reiniciar el reporte");
    }
  }
};

const formatDate = (date) => {
  if (!date) return "—";
  return new Date(date).toLocaleDateString("es-VE");
};


onMounted(() => {
  loadSummary();
  loadDetails(); // Cargar detalles iniciales también
});

watch([startDate, endDate, searchQuery, selectedType], () => loadData());
</script>

<template>
  <div class="income-statement-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Filtros Colapsables -->
      <VExpansionPanels class="mb-5 custom-expansion-panels">
        <VExpansionPanel class="border shadow-sm rounded-lg">
          <VExpansionPanelTitle class="py-2 px-4 min-h-0">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-filter" size="18" color="primary" />
              <span class="text-sm font-weight-black uppercase">Filtros Avanzados</span>
              <VChip v-if="startDate || endDate || selectedType" color="primary" size="x-small" density="comfortable" class="ms-2">
                Activos
              </VChip>
            </div>
          </VExpansionPanelTitle>
          <VExpansionPanelText class="pa-0">
            <IncomeStatementFilters
              v-model:searchQuery="searchQuery"
              v-model:startDate="startDate"
              v-model:endDate="endDate"
              v-model:selectedType="selectedType"
              @clear="clearFilters"
              @reset="handleReset"
            />
          </VExpansionPanelText>
        </VExpansionPanel>
      </VExpansionPanels>

      <!-- Tarjetas de Resumen Premium -->
      <VRow class="ma-0 mx-n1 mb-5" dense>
        <!-- INGRESOS -->
        <VCol cols="12" sm="6" md="3" class="pa-1">
          <VCard
            class="stats-card h-100 border-0 overflow-hidden shadow-sm position-relative"
          >
            <div class="card-bg-decoration bg-success-opacity-1"></div>
            <VCardText class="pa-4 relative-content h-100 d-flex flex-column">
              <div class="d-flex align-center gap-3 mb-3">
                <VAvatar
                  color="success"
                  variant="tonal"
                  size="38"
                  class="rounded-lg"
                >
                  <VIcon icon="tabler-trending-up" size="20" />
                </VAvatar>
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-widest"
                >
                  Ingresos Brutos
                </span>
              </div>
              <div v-if="!loadingSummary" class="mt-auto">
                <span
                  class="text-h5 font-weight-black text-success leading-none"
                >
                  {{ formatCurrency(summary.income?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text" class="mt-auto" />
            </VCardText>
            <div class="accent-border bg-success"></div>
          </VCard>
        </VCol>

        <!-- COSTOS -->
        <VCol cols="12" sm="6" md="3" class="pa-1">
          <VCard
            class="stats-card h-100 border-0 overflow-hidden shadow-sm position-relative"
          >
            <div class="card-bg-decoration bg-warning-opacity-1"></div>
            <VCardText class="pa-4 relative-content h-100 d-flex flex-column">
              <div class="d-flex align-center gap-3 mb-3">
                <VAvatar
                  color="warning"
                  variant="tonal"
                  size="38"
                  class="rounded-lg"
                >
                  <VIcon icon="tabler-package" size="20" />
                </VAvatar>
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-widest"
                >
                  Costos de Venta
                </span>
              </div>
              <div v-if="!loadingSummary" class="mt-auto">
                <span
                  class="text-h5 font-weight-black text-warning leading-none"
                >
                  -{{ formatCurrency(summary.costs?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text" class="mt-auto" />
            </VCardText>
            <div class="accent-border bg-warning"></div>
          </VCard>
        </VCol>

        <!-- GASTOS -->
        <VCol cols="12" sm="6" md="3" class="pa-1">
          <VCard
            class="stats-card h-100 border-0 overflow-hidden shadow-sm position-relative"
          >
            <div class="card-bg-decoration bg-error-opacity-1"></div>
            <VCardText class="pa-4 relative-content h-100 d-flex flex-column">
              <div class="d-flex align-center gap-3 mb-3">
                <VAvatar
                  color="error"
                  variant="tonal"
                  size="38"
                  class="rounded-lg"
                >
                  <VIcon icon="tabler-activity" size="20" />
                </VAvatar>
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-widest"
                >
                  Gastos Operativos
                </span>
              </div>
              <div v-if="!loadingSummary" class="mt-auto">
                <span class="text-h5 font-weight-black text-error leading-none">
                  -{{ formatCurrency(summary.expenses?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text" class="mt-auto" />
            </VCardText>
            <div class="accent-border bg-error"></div>
          </VCard>
        </VCol>

        <!-- UTILIDAD NETA -->
        <VCol cols="12" sm="6" md="3" class="pa-1">
          <VCard
            class="stats-card h-100 border-0 overflow-hidden shadow-sm position-relative"
          >
            <div class="card-bg-decoration bg-info-opacity-1"></div>
            <VCardText class="pa-4 relative-content h-100 d-flex flex-column">
              <div class="d-flex align-center gap-3 mb-3">
                <VAvatar
                  color="info"
                  variant="tonal"
                  size="38"
                  class="rounded-lg"
                >
                  <VIcon
                    :icon="
                      summary.net_profit?.amount >= 0
                        ? 'tabler-pig-money'
                        : 'tabler-chart-down'
                    "
                    color="info"
                    size="20"
                  />
                </VAvatar>
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-widest"
                >
                  Utilidad Neta
                </span>
              </div>
              <div v-if="!loadingSummary" class="mt-auto">
                <span class="text-h4 font-weight-black text-info leading-none">
                  {{ formatCurrency(summary.net_profit?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text" class="mt-auto" />
            </VCardText>
            <div class="accent-border bg-info"></div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Tabla de Detalles Premium -->
      <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
        <div
          class="px-6 py-4 bg-white border-b d-flex justify-space-between align-center flex-wrap gap-4"
        >
          <div class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="elevated"
              size="36"
              class="rounded-lg shadow-sm"
            >
              <VIcon icon="tabler-list-details" color="white" size="20" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-h6 font-weight-black leading-none"
                >Detalles de Operaciones</span
              >
              <span
                class="text-super-xs text-disabled font-weight-bold uppercase mt-1"
                >Desglose línea a línea de ingresos y egresos</span
              >
            </div>
          </div>
        </div>

        <VCardText class="pa-0">
          <!-- Vista Desktop: Tabla Tradicional -->
          <template v-if="!$vuetify.display.smAndDown">
            <VProgressLinear
              v-if="loadingDetails"
              indeterminate
              color="primary"
              height="3"
              class="position-absolute w-100"
              style="z-index: 1"
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
                <span class="text-sm font-weight-medium text-high-emphasis">{{
                  item.client || item.category || "N/A"
                }}</span>
              </template>

              <template #item.amount="{ item }">
                <span
                  class="text-sm font-weight-black"
                  :class="item.type === 'sale' ? 'text-success' : 'text-error'"
                >
                  {{ item.type === "sale" ? "+" : "-"
                  }}{{ formatCurrency(item.amount) }}
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
                  {{ item.profit >= 0 ? "+" : ""
                  }}{{ formatCurrency(item.profit) }}
                </VChip>
              </template>
            </VDataTableServer>
          </template>

          <!-- Vista Móvil: Grid de Tarjetas (2 por fila) -->
          <div v-else class="pa-2">
            <div v-if="loadingDetails" class="text-center py-12">
              <VProgressCircular indeterminate color="primary" size="32" />
              <p class="text-caption mt-2 font-weight-bold text-disabled uppercase">
                Cargando...
              </p>
            </div>

            <template v-else-if="transactions.length > 0">
              <VRow dense>
                <VCol
                  v-for="item in transactions"
                  :key="item.id"
                  cols="6"
                  md="3"
                  class="pa-1"
                >
                  <VCard
                    variant="flat"
                    class="h-100 border rounded-lg pa-3 bg-white shadow-xs position-relative overflow-hidden"
                  >
                    <div
                      :class="['position-absolute top-0 left-0 h-1 w-100', item.type === 'sale' ? 'bg-success' : 'bg-error']"
                    ></div>
                    
                    <div class="d-flex justify-space-between align-start mb-2 mt-1">
                      <span class="text-super-xs font-weight-black text-disabled uppercase">
                        {{ formatDate(item.date) }}
                      </span>
                      <VChip
                        :color="item.type === 'sale' ? 'success' : 'error'"
                        size="x-small"
                        variant="tonal"
                        class="font-weight-bold"
                        style="height: 16px; font-size: 8px;"
                      >
                        {{ item.type === "sale" ? "ING" : "EGR" }}
                      </VChip>
                    </div>

                    <p class="text-xs font-weight-bold text-high-emphasis line-clamp-2 mb-2" style="min-height: 2.5em;">
                      {{ item.description }}
                    </p>

                    <VDivider class="my-2 opacity-10" />

                    <div class="d-flex flex-column gap-1">
                      <div class="d-flex justify-space-between align-center text-super-xs">
                        <span class="text-disabled">MONTO:</span>
                        <span :class="['font-weight-black', item.type === 'sale' ? 'text-success' : 'text-error']">
                          {{ item.type === 'sale' ? '+' : '-' }}{{ formatCurrency(item.amount) }}
                        </span>
                      </div>
                      <div v-if="item.costs > 0" class="d-flex justify-space-between align-center text-super-xs">
                        <span class="text-disabled">COSTO:</span>
                        <span class="font-weight-black text-warning">-{{ formatCurrency(item.costs) }}</span>
                      </div>
                      <div class="d-flex justify-space-between align-center text-xs pt-1 border-t mt-1">
                        <span class="font-weight-bold text-disabled">UTIL:</span>
                        <span :class="['font-weight-black', item.profit >= 0 ? 'text-info' : 'text-error']">
                          {{ formatCurrency(item.profit) }}
                        </span>
                      </div>
                    </div>
                  </VCard>
                </VCol>
              </VRow>

              <!-- Paginación Móvil -->
              <div class="pa-4 d-flex justify-center mt-4">
                <VPagination
                  v-model="page"
                  :length="Math.ceil(totalItems / itemsPerPage)"
                  density="compact"
                  total-visible="3"
                  active-color="primary"
                  @update:model-value="loadDetails"
                />
              </div>
            </template>

            <div
              v-else
              class="text-center py-12 text-disabled border-2 border-dashed rounded-lg"
            >
              <VIcon icon="tabler-database-x" size="40" class="mb-2" />
              <p class="text-body-2 font-weight-bold">No hay registros</p>
            </div>
          </div>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 90%) !important;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.stats-card:not(.no-hover):hover {
  transform: translateY(-4px);
  background: rgba(var(--v-theme-surface), 98%) !important;
  box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.15) !important;
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 60px;
  filter: blur(35px);
  inline-size: 60px;
  inset-block-start: -10px;
  inset-inline-end: -10px;
  pointer-events: none;
  opacity: 0.5;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 100%;
  inline-size: 4px;
  inset-block-start: 0;
  inset-inline-start: 0;
  opacity: 0.7;
}

.bg-success-opacity-1 {
  background: rgba(var(--v-theme-success), 0.1);
}
.bg-warning-opacity-1 {
  background: rgba(var(--v-theme-warning), 0.1);
}
.bg-error-opacity-1 {
  background: rgba(var(--v-theme-error), 0.1);
}
.bg-info-opacity-1 {
  background: rgba(var(--v-theme-info), 0.1);
}

.income-statement-view {
  background-color: #f8fafc;
  min-block-size: 100vh;
}

.header-bg {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #4a90e2 100%
  );
}

.bg-gradient-success {
  background: linear-gradient(135deg, #28c76f 0%, #48da89 100%) !important;
}

.bg-gradient-error {
  background: linear-gradient(135deg, #ea5455 0%, #f08182 100%) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 5%);
}

.border-dashed {
  border-style: dashed !important;
}

.letter-spacing-tight {
  letter-spacing: -0.02em;
}

.letter-spacing-widest {
  letter-spacing: 0.1em;
}

.shadow-soft {
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important;
}

.shadow-xs {
  box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 5%) !important;
}

.kpi-card {
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.kpi-card:hover {
  box-shadow: 0 8px 24px 0 rgba(0, 0, 0, 10%) !important;
  transform: translateY(-4px);
}

/* Tabla Premium Styling */
:deep(.v-data-table.premium-table) {
  background: white;
}

:deep(.v-data-table.premium-table th) {
  background-color: white !important;
  block-size: 52px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  color: rgba(
    var(--v-theme-on-surface),
    var(--v-medium-emphasis-opacity)
  ) !important;
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

.line-clamp-2 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
</style>
