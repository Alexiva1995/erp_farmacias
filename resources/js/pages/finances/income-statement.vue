<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";
import IncomeStatementFilters from "@/components/IncomeStatementFilters.vue";

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

const headers = [
  { title: "FECHA",         key: "date",        sortable: true },
  { title: "TIPO",          key: "type",        sortable: false, align: 'center' },
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

const clearFilters = () => {
  startDate.value = null;
  endDate.value = null;
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
  if (!date) return '—';
  return new Date(date).toLocaleDateString("es-VE");
};

const getAvatarInitial = (name) => {
  if (!name || name === "N/A") return "?";
  return name.charAt(0).toUpperCase();
};

onMounted(() => {
  loadSummary();
  loadDetails(); // Cargar detalles iniciales también
});

watch([startDate, endDate], () => loadData());
</script>

<template>
  <div class="income-statement-view pa-4 pa-md-6">
    <!-- Header Premium -->
    <VCard class="rounded-xl overflow-hidden mb-6 border-0 shadow-sm">
      <div class="header-bg py-8 px-6 text-white position-relative">
        <div class="d-flex align-center gap-4 mb-2">
          <VAvatar color="white" variant="tonal" size="48" class="rounded-lg shadow-soft">
            <VIcon icon="tabler-report-analytics" color="white" size="28" />
          </VAvatar>
          <div>
            <h1 class="text-h4 font-weight-black letter-spacing-tight">Estado de Resultados</h1>
            <p class="text-body-2 opacity-80 font-weight-bold uppercase letter-spacing-widest mt-1">
              Análisis de rentabilidad y flujo financiero histórico
            </p>
          </div>
        </div>
      </div>
    </VCard>

    <!-- Filtros Colapsables -->
    <IncomeStatementFilters
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      @clear="clearFilters"
    />

    <!-- Tarjetas de Resumen Premium -->
    <VRow class="mb-8" dense>
      <!-- INGRESOS -->
      <VCol cols="12" sm="6" md="3">
        <VCard class="rounded-xl border shadow-sm h-100 overflow-hidden kpi-card">
          <div class="pa-5">
            <div class="d-flex align-center gap-4">
              <VAvatar color="success" variant="tonal" rounded="xl" size="54">
                <VIcon icon="tabler-trending-up" size="30" />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1 letter-spacing-widest">
                  Ingresos Brutos
                </span>
                <span class="text-h5 font-weight-black text-success">
                  {{ formatCurrency(summary.income?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text, text" class="flex-grow-1" />
            </div>
          </div>
          <div class="bg-success opacity-10 h-1 w-100 position-absolute bottom-0"></div>
        </VCard>
      </VCol>

      <!-- COSTOS -->
      <VCol cols="12" sm="6" md="3">
        <VCard class="rounded-xl border shadow-sm h-100 overflow-hidden kpi-card">
          <div class="pa-5">
            <div class="d-flex align-center gap-4">
              <VAvatar color="warning" variant="tonal" rounded="xl" size="54">
                <VIcon icon="tabler-package" size="30" />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1 letter-spacing-widest">
                  Costos de Venta
                </span>
                <span class="text-h5 font-weight-black text-warning">
                  -{{ formatCurrency(summary.costs?.amount) }}
                </span>
              </div>
               <VSkeletonLoader v-else type="text, text" class="flex-grow-1" />
            </div>
          </div>
          <div class="bg-warning opacity-10 h-1 w-100 position-absolute bottom-0"></div>
        </VCard>
      </VCol>

      <!-- GASTOS -->
      <VCol cols="12" sm="6" md="3">
        <VCard class="rounded-xl border shadow-sm h-100 overflow-hidden kpi-card">
          <div class="pa-5">
            <div class="d-flex align-center gap-4">
              <VAvatar color="error" variant="tonal" rounded="xl" size="54">
                <VIcon icon="tabler-activity" size="30" />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1 letter-spacing-widest">
                  Gastos Operativos
                </span>
                <span class="text-h5 font-weight-black text-error">
                  -{{ formatCurrency(summary.expenses?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text, text" class="flex-grow-1" />
            </div>
          </div>
          <div class="bg-error opacity-10 h-1 w-100 position-absolute bottom-0"></div>
        </VCard>
      </VCol>

      <!-- UTILIDAD NETA -->
      <VCol cols="12" sm="6" md="3">
        <VCard 
          variant="elevated"
          :class="summary.net_profit?.amount >= 0 ? 'bg-gradient-success' : 'bg-gradient-error'" 
          class="rounded-xl h-100 shadow-soft overflow-hidden"
        >
          <VCardText class="pa-5">
            <div class="d-flex align-center gap-4">
              <VAvatar color="white" variant="tonal" rounded="xl" size="54">
                <VIcon :icon="summary.net_profit?.amount >= 0 ? 'tabler-pig-money' : 'tabler-chart-down'" size="30" color="white" />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span class="text-super-xs text-white opacity-80 font-weight-black d-block text-uppercase mb-1 letter-spacing-widest">
                  Utilidad Neta
                </span>
                <span class="text-h4 font-weight-black text-white leading-none">
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
    <VCard class="rounded-xl border shadow-sm overflow-hidden bg-surface">
      <div class="px-6 py-4 bg-surface-variant-light border-b d-flex justify-space-between align-center flex-wrap gap-4">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="elevated" size="36" class="rounded-lg shadow-sm">
            <VIcon icon="tabler-list-details" color="white" size="20" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-black leading-none">Detalles de Operaciones</span>
            <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1">Desglose línea a línea de ingresos y egresos</span>
          </div>
        </div>
        <VChip color="primary" variant="flat" size="small" class="font-weight-black px-4 rounded-lg">
          {{ totalItems }} REGISTROS ENCONTRADOS
        </VChip>
      </div>

      <VCardText class="pa-0">
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
          class="premium-table"
          @update:options="updateOptions"
        >
          <!-- Vista Móvil: Tarjetas -->
          <template v-if="$vuetify.display.smAndDown" #item="{ item }">
            <div class="px-4 py-3">
              <VCard variant="outlined" class="rounded-xl border-dashed pa-4 bg-white shadow-xs mb-1">
                <div class="d-flex justify-space-between align-start mb-3">
                  <div class="d-flex align-center gap-3">
                    <VAvatar :color="item.type === 'sale' ? 'success' : 'error'" variant="tonal" size="40" class="rounded-lg">
                      <VIcon :icon="item.type === 'sale' ? 'tabler-arrow-up-right' : 'tabler-arrow-down-left'" size="22" />
                    </VAvatar>
                    <div class="d-flex flex-column">
                      <span class="text-xs font-weight-black text-disabled uppercase">{{ formatDate(item.date) }}</span>
                      <span class="text-super-xs font-weight-black" :class="item.type === 'sale' ? 'text-success' : 'text-error'">
                        {{ item.type === 'sale' ? 'INGRESO' : 'EGRESO' }}
                      </span>
                    </div>
                  </div>
                  <div class="text-right">
                    <div :class="['text-base font-weight-black', item.type === 'sale' ? 'text-success' : 'text-error']">
                      {{ item.type === 'sale' ? '+' : '-' }} {{ formatCurrency(item.amount) }}
                    </div>
                    <span v-if="item.costs" class="text-super-xs font-weight-bold text-disabled d-block">Costos: -{{ formatCurrency(item.costs) }}</span>
                  </div>
                </div>

                <div class="text-body-2 font-weight-bold mb-3 d-flex align-center gap-2">
                  <span class="text-primary opacity-60">#</span> {{ item.description }}
                </div>

                <div class="d-flex justify-space-between align-center pt-3 border-t border-dashed">
                  <div class="d-flex align-center gap-2">
                    <VAvatar size="20" variant="tonal" :color="item.type === 'sale' ? 'primary' : 'secondary'">
                      <span class="text-super-xs font-weight-black">{{ getAvatarInitial(item.client || item.category) }}</span>
                    </VAvatar>
                    <span class="text-super-xs font-weight-black text-disabled uppercase">{{ item.client || item.category || "N/A" }}</span>
                  </div>
                  <VChip :color="item.profit >= 0 ? 'success' : 'error'" size="x-small" variant="flat" class="font-weight-black">
                    Ut. {{ item.profit >= 0 ? '+' : '' }}{{ formatCurrency(item.profit) }}
                  </VChip>
                </div>
              </VCard>
            </div>
          </template>

          <template #item.date="{ item }">
            <span class="text-body-2 font-weight-black text-medium-emphasis">
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
            <span class="text-body-2 font-weight-bold">
              {{ item.description }}
            </span>
          </template>

          <template #item.client="{ item }">
            <div class="d-flex align-center gap-3">
              <VAvatar 
                size="26" 
                variant="tonal" 
                :color="item.type === 'sale' ? 'primary' : 'secondary'"
                class="rounded-lg shadow-soft"
              >
                <span class="text-xs font-weight-black">
                  {{ getAvatarInitial(item.client || item.category) }}
                </span>
              </VAvatar>
              <span class="text-xs font-weight-medium text-high-emphasis">{{ item.client || item.category || "N/A" }}</span>
            </div>
          </template>

          <template #item.amount="{ item }">
             <span class="text-base font-weight-black" :class="item.type === 'sale' ? 'text-success' : 'text-error'">
              {{ item.type === "sale" ? "+" : "-" }}{{ formatCurrency(item.amount) }}
            </span>
          </template>

          <template #item.costs="{ item }">
             <span class="text-body-2 font-weight-black text-warning">
              {{ item.costs > 0 ? '-' + formatCurrency(item.costs) : '—' }}
            </span>
          </template>

          <template #item.profit="{ item }">
            <VChip
              :color="item.profit >= 0 ? 'success' : 'error'"
              variant="tonal"
              size="small"
              class="font-weight-black px-4 rounded-xl"
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
  background-color: #f8fafc;
}

.header-bg {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #4a90e2 100%);
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
  background-color: rgba(var(--v-theme-surface-variant), 0.05);
}

.border-dashed {
  border-style: dashed !important;
}

.letter-spacing-tight { letter-spacing: -0.02em; }
.letter-spacing-widest { letter-spacing: 0.1em; }
.shadow-soft { box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.08) !important; }
.shadow-xs { box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.05) !important; }

.kpi-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.kpi-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px 0 rgba(0, 0, 0, 0.1) !important;
}

/* Tabla Premium Styling */
:deep(.v-data-table.premium-table) {
  background: white;
}

:deep(.v-data-table.premium-table th) {
  block-size: 52px !important;
  background-color: #f8fafc !important;
  border-block-end: 2px solid rgba(var(--v-border-color), 0.05) !important;
  color: rgba(var(--v-theme-on-surface), 0.6) !important;
  font-size: 0.7rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

:deep(.v-data-table.premium-table td) {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.1) !important;
  padding-block: 12px !important;
  height: 64px !important;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
