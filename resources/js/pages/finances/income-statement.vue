<script setup>
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
const selectedQuickFilter = ref(null);

const quickFilterOptions = [
  { title: "Todo el tiempo", value: "all" },
  { title: "Últimos 15 días", value: 15 },
  { title: "Últimos 30 días", value: 30 },
  { title: "Últimos 60 días", value: 60 },
  { title: "Últimos 90 días", value: 90 },
  { title: "Mes actual", value: "current_month" },
  { title: "Mes anterior", value: "last_month" },
];

const headers = [
  { title: "FECHA",         key: "date",        sortable: true },
  { title: "TIPO",          key: "type",        sortable: false },
  { title: "DESCRIPCIÓN",   key: "description", sortable: true },
  { title: "ENTIDAD/ORG",   key: "client",      sortable: true },
  { title: "MONTO (USD)",   key: "amount",      sortable: true, align: "end" },
  { title: "COSTOS (USD)",  key: "costs",       sortable: true, align: "end" },
  { title: "UTILIDAD (USD)",key: "profit",      sortable: true, align: "end" },
];

const loadSummary = async () => {
  loadingSummary.value = true;
  try {
    const params = new URLSearchParams();
    if (startDate.value) params.append("start_date", startDate.value);
    if (endDate.value) params.append("end_date", endDate.value);

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

const applyFilters = () => loadData();

const setQuickFilter = (days) => {
  const today = new Date();
  const start = new Date(today);

  if (days === "all") {
    startDate.value = null;
    endDate.value = null;
  } else if (days === "current_month") {
    start.setDate(1);
    startDate.value = start.toISOString().split("T")[0];
    endDate.value = today.toISOString().split("T")[0];
  } else if (days === "last_month") {
    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
    startDate.value = lastMonth.toISOString().split("T")[0];
    endDate.value = lastDay.toISOString().split("T")[0];
  } else {
    start.setDate(today.getDate() - days);
    startDate.value = start.toISOString().split("T")[0];
    endDate.value = today.toISOString().split("T")[0];
  }
};

const clearFilters = () => {
  startDate.value = null;
  endDate.value = null;
  selectedQuickFilter.value = null;
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
  }).format(amount || 0);
};

const formatDate = (date) => new Date(date).toLocaleDateString("es-VE");

// Helper para avatar
const getAvatarInitial = (name) => {
  if (!name || name === "N/A") return "?";
  return name.charAt(0).toUpperCase();
};

onMounted(() => {
  loadSummary();
});

watch([startDate, endDate], () => applyFilters());
</script>

<template>
  <div class="income-statement-view">
    <!-- Componente Header -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div>
        <h2 class="text-h4 font-weight-bold d-flex align-center gap-2">
          <VIcon icon="tabler-chart-bar" color="primary" size="32" />
          Estado de Resultados
        </h2>
        <span class="text-body-2 text-medium-emphasis">
          Resumen financiero de ingresos, costos y utilidad neta
        </span>
      </div>
    </div>

    <!-- Panel de Filtros Premium -->
    <VCard variant="outlined" class="mb-6 rounded-lg bg-surface">
      <VCardText class="pa-4 pb-2">
        <VRow dense align="center">
          <VCol cols="12" sm="3" md="4">
            <VSelect
              v-model="selectedQuickFilter"
              :items="quickFilterOptions"
              label="Filtro Rápido"
              prepend-inner-icon="tabler-calendar-stats"
              variant="outlined"
              density="compact"
              hide-details
              @update:model-value="setQuickFilter"
            />
          </VCol>
          <VCol cols="12" sm="4" md="3">
            <AppDateTimePicker
              v-model="startDate"
              label="Desde"
              density="compact"
              clearable
              hide-details
            />
          </VCol>
          <VCol cols="12" sm="4" md="3">
            <AppDateTimePicker
              v-model="endDate"
              label="Hasta"
              density="compact"
              clearable
              hide-details
            />
          </VCol>
          <VCol cols="12" sm="1" md="2" class="text-right">
            <VBtn
              color="secondary"
              variant="tonal"
              prepend-icon="tabler-filter-x"
              class="h-100"
              @click="clearFilters"
            >
              LIMPIAR
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Tarjetas de Resumen Dinámicas (Skeletons integrados) -->
    <VRow class="mb-6" dense>
      <!-- INGRESOS -->
      <VCol cols="12" sm="6" md="3">
        <VCard variant="outlined" class="rounded-lg border-success bg-white h-100">
          <VCardText class="pa-4">
            <div class="d-flex align-center gap-3">
              <VAvatar color="success" variant="tonal" rounded="lg" size="48">
                <VIcon icon="tabler-trending-up" size="28" />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span class="text-caption text-medium-emphasis font-weight-bold d-block text-uppercase mb-1">
                  Ingresos Totales
                </span>
                <span class="text-h5 font-weight-black text-success">
                  {{ formatCurrency(summary.income?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text, text" class="flex-grow-1" />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- COSTOS -->
      <VCol cols="12" sm="6" md="3">
        <VCard variant="outlined" class="rounded-lg border-warning bg-white h-100">
          <VCardText class="pa-4">
            <div class="d-flex align-center gap-3">
              <VAvatar color="warning" variant="tonal" rounded="lg" size="48">
                <VIcon icon="tabler-package" size="28" />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span class="text-caption text-medium-emphasis font-weight-bold d-block text-uppercase mb-1">
                  Costos Totales
                </span>
                <span class="text-h5 font-weight-black text-warning">
                  -{{ formatCurrency(summary.costs?.amount) }}
                </span>
              </div>
               <VSkeletonLoader v-else type="text, text" class="flex-grow-1" />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- GASTOS -->
      <VCol cols="12" sm="6" md="3">
        <VCard variant="outlined" class="rounded-lg border-error bg-white h-100">
          <VCardText class="pa-4">
            <div class="d-flex align-center gap-3">
              <VAvatar color="error" variant="tonal" rounded="lg" size="48">
                <VIcon icon="tabler-activity" size="28" />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span class="text-caption text-medium-emphasis font-weight-bold d-block text-uppercase mb-1">
                  Gastos Operativos
                </span>
                <span class="text-h5 font-weight-black text-error">
                  -{{ formatCurrency(summary.expenses?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text, text" class="flex-grow-1" />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- UTILIDAD NETA -->
      <VCol cols="12" sm="6" md="3">
        <VCard 
          variant="flat" 
          :color="summary.net_profit?.amount >= 0 ? 'success' : 'error'" 
          class="rounded-lg h-100"
        >
          <VCardText class="pa-4">
            <div class="d-flex align-center gap-3">
              <VAvatar color="white" variant="tonal" rounded="lg" size="48">
                <VIcon :icon="summary.net_profit?.amount >= 0 ? 'tabler-pig-money' : 'tabler-chart-down'" size="28" />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span class="text-caption text-white opacity-80 font-weight-bold d-block text-uppercase mb-1">
                  Utilidad Neta
                </span>
                <span class="text-h5 font-weight-black text-white">
                  {{ formatCurrency(summary.net_profit?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text, text" color="transparent" class="flex-grow-1" />
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de Detalles Premium -->
    <VCard variant="outlined" class="rounded-lg bg-surface">
      <VCardTitle class="px-6 py-4 border-b">
        <div class="d-flex justify-space-between align-center">
          <div class="d-flex align-center gap-2">
            <VAvatar color="primary" variant="tonal" size="32" rounded>
              <VIcon icon="tabler-list-details" size="18" />
            </VAvatar>
            <h3 class="text-h6 font-weight-bold mb-0">Detalles de Operaciones</h3>
          </div>
          <VChip color="primary" variant="flat" size="small" class="font-weight-bold">
            {{ totalItems }} Registros
          </VChip>
        </div>
      </VCardTitle>

      <VCardText class="pa-0">
        <!-- Progress Linear estético en el borde superior de la tabla -->
        <VProgressLinear 
          v-if="loadingDetails" 
          indeterminate 
          color="primary" 
          height="3" 
          class="position-absolute w-100" 
          style="z-index: 1;"
        />
        
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="transactions"
          :items-length="totalItems"
          :loading="loadingDetails"
          item-key="id"
          class="premium-table"
          hover
          @update:options="updateOptions"
        >
          <template #item.date="{ item }">
            <span class="text-body-2 font-weight-medium text-medium-emphasis">
              {{ formatDate(item.date) }}
            </span>
          </template>

          <template #item.type="{ item }">
            <VChip
              :color="item.type === 'sale' ? 'success' : 'error'"
              size="small"
              variant="tonal"
              class="font-weight-bold"
            >
              <VIcon start :icon="item.type === 'sale' ? 'tabler-arrow-up-right' : 'tabler-arrow-down-right'" size="14"/>
              {{ item.type === "sale" ? "INGRESO" : "EGRESO" }}
            </VChip>
          </template>

          <template #item.description="{ item }">
            <span class="text-body-2 font-weight-medium">
              {{ item.description }}
            </span>
          </template>

          <template #item.client="{ item }">
            <div class="d-flex align-center gap-2">
              <VAvatar 
                size="26" 
                variant="tonal" 
                :color="item.type === 'sale' ? 'primary' : 'secondary'"
              >
                <span class="text-xs font-weight-bold">
                  {{ getAvatarInitial(item.client || item.category) }}
                </span>
              </VAvatar>
              <span class="text-body-2">{{ item.client || item.category || "N/A" }}</span>
            </div>
          </template>

          <template #item.amount="{ item }">
             <span class="text-body-2 font-weight-bold" :class="item.type === 'sale' ? 'text-success' : 'text-error'">
              {{ item.type === "sale" ? "+" : "-" }}{{ formatCurrency(item.amount) }}
            </span>
          </template>

          <template #item.costs="{ item }">
            <span class="text-body-2 font-weight-medium text-medium-emphasis">
              {{ item.costs > 0 ? '-' + formatCurrency(item.costs) : '—' }}
            </span>
          </template>

          <template #item.profit="{ item }">
            <VChip
              :color="item.profit >= 0 ? 'success' : 'error'"
              variant="flat"
              size="small"
              class="font-weight-bold"
            >
              {{ item.profit >= 0 ? "+" : "" }}{{ formatCurrency(item.profit) }}
            </VChip>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.income-statement-view {
  min-block-size: 100vh;
}
.border-success { border: 1px solid rgba(40, 199, 111, 50%) !important; }
.border-warning { border: 1px solid rgba(255, 159, 67, 50%) !important; }
.border-error { border: 1px solid rgba(234, 84, 85, 50%) !important; }

/* Tabla Premium Styling */
:deep(.v-data-table.premium-table) {
  background: transparent;
}

:deep(.v-data-table.premium-table th) {
  background-color: #f8f9fa !important;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  color: rgba(var(--v-theme-on-surface), 0.6) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

:deep(.v-data-table.premium-table td) {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.4) !important;
  padding-block: 8px !important;
}
</style>
