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

const formatDate = (date) => {
  if (!date) return "—";
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

watch([startDate, endDate, searchQuery, selectedType], () => loadData());
</script>

<template>
  <div class="income-statement-view pa-4 pa-md-6">
    <!-- Header Premium -->
    <VCard class="rounded-lg border shadow-sm mb-6 overflow-hidden">
      <div class="header-bg py-8 px-6 text-white position-relative">
        <div class="d-flex align-center gap-4 mb-2">
          <VAvatar
            color="white"
            variant="tonal"
            size="48"
            class="rounded-lg shadow-soft"
          >
            <VIcon icon="tabler-report-analytics" color="white" size="28" />
          </VAvatar>
          <div>
            <h1
              class="text-h4 font-weight-black letter-spacing-tight text-white"
            >
              Estado de Resultados
            </h1>
            <p
              class="text-white text-body-2 opacity-80 font-weight-bold uppercase letter-spacing-widest mt-1"
            >
              Análisis de rentabilidad y flujo financiero histórico
            </p>
          </div>
        </div>
      </div>
    </VCard>

    <!-- Filtros Colapsables -->
    <IncomeStatementFilters
      v-model:searchQuery="searchQuery"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      v-model:selectedType="selectedType"
      @clear="clearFilters"
    />

    <!-- Tarjetas de Resumen Premium -->
    <VRow class="mb-8" dense>
      <!-- INGRESOS -->
      <VCol cols="12" sm="6" md="3">
        <VCard
          class="rounded-lg border shadow-sm h-100 overflow-hidden kpi-card"
        >
          <div class="pa-5">
            <div class="d-flex align-center gap-4">
              <VAvatar
                color="success"
                variant="tonal"
                class="rounded-lg"
                size="54"
              >
                <VIcon icon="tabler-trending-up" size="30" />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1 letter-spacing-widest"
                >
                  Ingresos Brutos
                </span>
                <span class="text-h5 font-weight-black text-success">
                  {{ formatCurrency(summary.income?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text, text" class="flex-grow-1" />
            </div>
          </div>
          <div
            class="bg-success opacity-10 h-1 w-100 position-absolute bottom-0"
          ></div>
        </VCard>
      </VCol>

      <!-- COSTOS -->
      <VCol cols="12" sm="6" md="3">
        <VCard
          class="rounded-lg border shadow-sm h-100 overflow-hidden kpi-card"
        >
          <div class="pa-5">
            <div class="d-flex align-center gap-4">
              <VAvatar
                color="warning"
                variant="tonal"
                class="rounded-lg"
                size="54"
              >
                <VIcon icon="tabler-package" size="30" />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1 letter-spacing-widest"
                >
                  Costos de Venta
                </span>
                <span class="text-h5 font-weight-black text-warning">
                  -{{ formatCurrency(summary.costs?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text, text" class="flex-grow-1" />
            </div>
          </div>
          <div
            class="bg-warning opacity-10 h-1 w-100 position-absolute bottom-0"
          ></div>
        </VCard>
      </VCol>

      <!-- GASTOS -->
      <VCol cols="12" sm="6" md="3">
        <VCard
          class="rounded-lg border shadow-sm h-100 overflow-hidden kpi-card"
        >
          <div class="pa-5">
            <div class="d-flex align-center gap-4">
              <VAvatar
                color="error"
                variant="tonal"
                class="rounded-lg"
                size="54"
              >
                <VIcon icon="tabler-activity" size="30" />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1 letter-spacing-widest"
                >
                  Gastos Operativos
                </span>
                <span class="text-h5 font-weight-black text-error">
                  -{{ formatCurrency(summary.expenses?.amount) }}
                </span>
              </div>
              <VSkeletonLoader v-else type="text, text" class="flex-grow-1" />
            </div>
          </div>
          <div
            class="bg-error opacity-10 h-1 w-100 position-absolute bottom-0"
          ></div>
        </VCard>
      </VCol>

      <!-- UTILIDAD NETA -->
      <VCol cols="12" sm="6" md="3">
        <VCard
          variant="elevated"
          :class="
            summary.net_profit?.amount >= 0
              ? 'bg-gradient-success'
              : 'bg-gradient-error'
          "
          class="rounded-lg h-100 shadow-soft overflow-hidden"
        >
          <VCardText class="pa-5">
            <div class="d-flex align-center gap-4">
              <VAvatar
                color="white"
                variant="tonal"
                class="rounded-lg"
                size="54"
              >
                <VIcon
                  :icon="
                    summary.net_profit?.amount >= 0
                      ? 'tabler-pig-money'
                      : 'tabler-chart-down'
                  "
                  size="30"
                  color="white"
                />
              </VAvatar>
              <div v-if="!loadingSummary" class="flex-grow-1">
                <span
                  class="text-super-xs text-white opacity-80 font-weight-black d-block text-uppercase mb-1 letter-spacing-widest"
                >
                  Utilidad Neta
                </span>
                <span class="text-h4 font-weight-black text-white leading-none">
                  {{ formatCurrency(summary.net_profit?.amount) }}
                </span>
              </div>
              <VSkeletonLoader
                v-else
                type="text, text"
                color="transparent"
                class="flex-grow-1"
              />
            </div>
          </VCardText>
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
        <VChip
          color="primary"
          variant="flat"
          size="small"
          class="font-weight-black px-4 rounded-lg"
        >
          {{ totalItems }} REGISTROS ENCONTRADOS
        </VChip>
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
                <span class="text-sm font-weight-medium text-high-emphasis">{{
                  item.client || item.category || "N/A"
                }}</span>
              </div>
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

        <!-- Vista Móvil: Grid de Tarjetas Reales (No Tabla) -->
        <div v-else class="pa-4">
          <div v-if="loadingDetails" class="text-center py-12">
            <VProgressCircular indeterminate color="primary" size="48" />
            <p
              class="text-caption mt-2 font-weight-bold text-disabled uppercase"
            >
              Cargando...
            </p>
          </div>

          <template v-else-if="transactions.length > 0">
            <div class="d-flex flex-column gap-4">
              <VCard
                v-for="item in transactions"
                :key="item.id"
                variant="flat"
                class="border rounded-lg px-4 py-4 bg-white shadow-xs"
                :class="
                  item.type === 'sale'
                    ? 'border-success-subtle'
                    : 'border-error-subtle'
                "
              >
                <div class="d-flex justify-space-between align-start mb-4">
                  <div class="d-flex align-center gap-3">
                    <VAvatar
                      :color="item.type === 'sale' ? 'success' : 'error'"
                      variant="tonal"
                      size="44"
                      class="rounded-lg"
                    >
                      <VIcon
                        :icon="
                          item.type === 'sale'
                            ? 'tabler-arrow-up-right'
                            : 'tabler-arrow-down-left'
                        "
                        size="24"
                      />
                    </VAvatar>
                    <div class="d-flex flex-column">
                      <span
                        class="text-super-xs font-weight-black text-disabled uppercase"
                        >{{ formatDate(item.date) }}</span
                      >
                      <span
                        class="text-xs font-weight-black"
                        :class="
                          item.type === 'sale' ? 'text-success' : 'text-error'
                        "
                      >
                        {{ item.type === "sale" ? "INGRESO" : "EGRESO" }}
                      </span>
                    </div>
                  </div>
                  <div class="text-right">
                    <div
                      :class="[
                        'text-xl font-weight-black',
                        item.type === 'sale' ? 'text-success' : 'text-error',
                      ]"
                    >
                      {{ item.type === "sale" ? "+" : "-"
                      }}{{ formatCurrency(item.amount) }}
                    </div>
                  </div>
                </div>

                <div
                  class="text-sm font-weight-bold mb-4 bg-surface-variant-light pa-3 rounded-lg border-dashed"
                >
                  {{ item.description }}
                </div>

                <div
                  class="d-flex justify-space-between align-center pt-3 border-t border-dashed"
                >
                  <div class="d-flex align-center gap-2">
                    <VAvatar
                      size="24"
                      variant="tonal"
                      :color="item.type === 'sale' ? 'primary' : 'secondary'"
                      class="rounded-lg"
                    >
                      <span class="text-xs font-weight-black">{{
                        getAvatarInitial(item.client || item.category)
                      }}</span>
                    </VAvatar>
                    <span
                      class="text-super-xs font-weight-black text-disabled uppercase"
                      >{{ item.client || item.category || "N/A" }}</span
                    >
                  </div>

                  <div class="d-flex flex-column align-end">
                    <span
                      class="text-super-xs font-weight-black text-primary uppercase"
                      >Utilidad de Op.</span
                    >
                    <span
                      :class="[
                        'text-sm font-weight-black',
                        item.profit >= 0 ? 'text-success' : 'text-error',
                      ]"
                    >
                      {{ item.profit >= 0 ? "+" : ""
                      }}{{ formatCurrency(item.profit) }}
                    </span>
                  </div>
                </div>
              </VCard>
            </div>

            <!-- Paginación Móvil -->
            <VCard
              class="rounded-lg border shadow-sm pa-3 d-flex justify-center mt-6"
            >
              <VPagination
                v-model="page"
                :length="Math.ceil(totalItems / itemsPerPage)"
                density="compact"
                total-visible="3"
                active-color="primary"
                @update:model-value="loadDetails"
              />
            </VCard>
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
</template>

<style scoped>
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
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.1) !important;
  padding-block: 12px !important;
}

.line-clamp-2 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
</style>
