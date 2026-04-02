<script setup>
import CreditoFiscalTable from "@/components/CreditoFiscalTable.vue";
import DebitoFiscalTable from "@/components/DebitoFiscalTable.vue";
import IvaFiscalFilters from "@/components/IvaFiscalFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

// Estados reactivos para las cards de resumen
const debitoFiscal = ref(0);
const creditoFiscal = ref(0);
const loading = ref(false);
const periodo = ref({
  start_date: null,
  end_date: null,
});
const detalleCredito = ref({});
const detalleDebito = ref({});

// Estados para los filtros
const startDate = ref("");
const endDate = ref("");

// Estados para la tabla de débito fiscal
const fiscalData = ref([]);
const totalRecords = ref(0);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([]);
const tableLoading = ref(false);

// Estados para la tabla de crédito fiscal
const expensesData = ref([]);
const totalExpensesRecords = ref(0);
const expensesPage = ref(1);
const expensesItemsPerPage = ref(10);
const expensesTableLoading = ref(false);

// Cálculo automático del IVA a pagar (Débito Fiscal - Crédito Fiscal)
const ivaAPagar = computed(() => {
  return debitoFiscal.value - creditoFiscal.value;
});

// Función para obtener crédito fiscal
const fetchCreditoFiscal = async () => {
  try {
    const params = {};
    if (startDate.value) params.start_date = startDate.value;
    if (endDate.value) params.end_date = endDate.value;

    const response = await axios.get(
      "/finances/pending-payments/credito-fiscal",
      { params },
    );

    if (response.data.status === "success") {
      const data = response.data.data;
      creditoFiscal.value = data.credito_fiscal;
      detalleCredito.value = data.detalle_credito;
      if (!periodo.value.start_date) {
        periodo.value = data.periodo;
      }
    }
  } catch (error) {
    console.error("Error al obtener crédito fiscal:", error);
  }
};

// Función para obtener débito fiscal
const fetchDebitoFiscal = async () => {
  try {
    const params = {};
    if (startDate.value) params.start_date = startDate.value;
    if (endDate.value) params.end_date = endDate.value;

    const response = await axios.get("/debito-fiscal", { params });

    if (response.data.status === "success") {
      const data = response.data.data;
      debitoFiscal.value = data.debito_fiscal;
      detalleDebito.value = data.detalle_debito;
      if (!periodo.value.start_date) {
        periodo.value = data.periodo;
      }
    }
  } catch (error) {
    console.error("Error al obtener débito fiscal:", error);
  }
};

// Función para obtener datos de la tabla fiscal
const fetchFiscalHistoryData = async () => {
  tableLoading.value = true;
  try {
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
    };

    if (sortBy.value && sortBy.value.length > 0) {
      params.sortBy = sortBy.value[0].key;
      params.orderBy = sortBy.value[0].order;
    }

    if (startDate.value) params.start_date = startDate.value;
    if (endDate.value) params.end_date = endDate.value;

    const response = await axios.get("/fiscal-history", { params });

    if (response.data.status === "success") {
      const data = response.data.data;
      fiscalData.value = data.data;
      totalRecords.value = data.pagination.total;
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
    };

    if (startDate.value) params.start_date = startDate.value;
    if (endDate.value) params.end_date = endDate.value;

    const response = await axios.get(
      "/finances/pending-payments/expenses-history",
      { params },
    );

    if (response.data.status === "success") {
      const data = response.data.data;
      expensesData.value = data.data;
      totalExpensesRecords.value = data.pagination.total;
    }
  } catch (error) {
    console.error("Error al obtener datos de gastos:", error);
  } finally {
    expensesTableLoading.value = false;
  }
};

// Función para cargar todos los datos
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

// Función para formatear moneda venezolana (Bolívares)
const formatCurrency = (amount) => {
  const number = parseFloat(amount) || 0;
  return number.toLocaleString("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

// Formatear fecha para mostrar
const formatDate = (dateString, format = "long") => {
  if (!dateString) return "";
  const options =
    format === "long"
      ? { year: "numeric", month: "long", day: "numeric" }
      : { month: "short", day: "numeric" };
  return new Date(dateString).toLocaleDateString("es-VE", options);
};

// Determinar color y estado según el resultado
const getIvaStatus = computed(() => {
  if (ivaAPagar.value > 0) {
    return {
      color: "error",
      icon: "tabler-trending-up",
      message: "Saldo a Pagar",
      chipColor: "error",
      gradient: "linear-gradient(135deg, #FF4C51 0%, #B23539 100%)",
    };
  } else if (ivaAPagar.value < 0) {
    return {
      color: "success",
      icon: "tabler-trending-down",
      message: "Saldo a Favor",
      chipColor: "success",
      gradient: "linear-gradient(135deg, #28C76F 0%, #1C8B4E 100%)",
    };
  } else {
    return {
      color: "info",
      icon: "tabler-equal",
      message: "Equilibrado",
      chipColor: "info",
      gradient: "linear-gradient(135deg, #00CFE8 0%, #0091A2 100%)",
    };
  }
});

// Manejar aplicación de filtros
const handleApplyFilter = () => {
  page.value = 1;
  expensesPage.value = 1;
  fetchAllData();
};

// Manejar limpieza de filtros
const handleClearFilter = () => {
  initializeDefaults();
  fetchAllData();
};

const handleTableOptionsUpdate = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy) {
    sortBy.value = options.sortBy;
  }
  fetchFiscalHistoryData();
};

const handleExpensesTableOptionsUpdate = (options) => {
  expensesPage.value = options.page;
  expensesItemsPerPage.value = options.itemsPerPage;
  fetchExpensesData();
};

// Inicializar con mes actual
const initializeDefaults = () => {
  const now = new Date();
  const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  startDate.value = startOfMonth.toISOString().split("T")[0];
  endDate.value = endOfMonth.toISOString().split("T")[0];
};

onMounted(() => {
  initializeDefaults();
  fetchAllData();
});
</script>

<template>
  <div class="iva-general-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Dashboard Premium de IVA -->
      <VCard
        class="ma-0 mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface"
      >
        <VCardTitle class="pa-4 px-6 d-flex align-center">
          <div class="d-flex align-center gap-2">
            <VAvatar
              color="primary"
              variant="tonal"
              size="38"
              class="rounded-lg"
            >
              <VIcon icon="tabler-calculator" size="22" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span
                class="text-sm font-weight-black uppercase leading-none mb-1"
                >Cálculo IVA Fiscal</span
              >
              <div class="d-flex align-center gap-1">
                <span
                  class="text-super-xs text-disabled font-weight-medium"
                  v-if="periodo.start_date"
                >
                  Período: {{ formatDate(periodo.start_date, "short") }} -
                  {{ formatDate(periodo.end_date, "short") }}
                </span>
              </div>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-refresh"
            variant="tonal"
            color="secondary"
            size="38"
            :loading="loading"
            @click="fetchAllData"
            class="rounded-circle shadow-sm"
          />
        </VCardTitle>

        <VDivider class="opacity-10" />

        <VDivider class="opacity-10" />

        <VCardText class="pa-6">
          <VRow v-if="loading">
            <VCol cols="12" class="text-center pa-10">
              <VProgressCircular indeterminate color="primary" size="48" />
              <div
                class="text-xs font-weight-black text-disabled mt-4 uppercase"
              >
                Sincronizando registros fiscales...
              </div>
            </VCol>
          </VRow>

          <VRow v-else class="match-height">
            <!-- Card Débito Fiscal -->
            <VCol cols="12" md="4">
              <VCard
                class="rounded-lg border shadow-lg premium-summary-card bg-warning-gradient overflow-hidden h-100"
              >
                <VCardText
                  class="pa-6 d-flex flex-column align-center text-center"
                >
                  <VAvatar
                    color="white"
                    variant="tonal"
                    size="44"
                    class="mb-3 rounded-lg"
                  >
                    <VIcon icon="tabler-receipt-tax" size="24" color="white" />
                  </VAvatar>
                  <span
                    class="text-xs font-weight-bold uppercase text-white opacity-70 mb-1"
                    >Débito Fiscal</span
                  >
                  <div class="text-h4 font-weight-black text-white mb-2">
                    <span class="text-xs font-weight-medium me-1">Bs.</span
                    >{{ formatCurrency(debitoFiscal) }}
                  </div>
                  <div class="d-flex align-center gap-2 mt-auto">
                    <VChip
                      size="x-small"
                      color="white"
                      variant="flat"
                      class="font-weight-black rounded"
                    >
                      {{ detalleDebito.total_orders_with_iva || 0 }} VENTAS
                    </VChip>
                    <span class="text-super-xs text-white opacity-80"
                      >IVA Cobrado</span
                    >
                  </div>
                </VCardText>
                <div class="card-wave"></div>
              </VCard>
            </VCol>

            <!-- Card Crédito Fiscal -->
            <VCol cols="12" md="4">
              <VCard
                class="rounded-lg border shadow-lg premium-summary-card bg-info-gradient overflow-hidden h-100"
              >
                <VCardText
                  class="pa-6 d-flex flex-column align-center text-center"
                >
                  <VAvatar
                    color="white"
                    variant="tonal"
                    size="44"
                    class="mb-3 rounded-lg"
                  >
                    <VIcon
                      icon="tabler-receipt-refund"
                      size="24"
                      color="white"
                    />
                  </VAvatar>
                  <span
                    class="text-xs font-weight-bold uppercase text-white opacity-70 mb-1"
                    >Crédito Fiscal</span
                  >
                  <div class="text-h4 font-weight-black text-white mb-2">
                    <span class="text-xs font-weight-medium me-1">Bs.</span
                    >{{ formatCurrency(creditoFiscal) }}
                  </div>
                  <div class="d-flex align-center gap-2 mt-auto">
                    <VChip
                      size="x-small"
                      color="white"
                      variant="flat"
                      class="font-weight-black rounded"
                    >
                      {{ detalleCredito.total_expenses_with_iva || 0 }} GASTOS
                    </VChip>
                    <span class="text-super-xs text-white opacity-80"
                      >IVA Pagado</span
                    >
                  </div>
                </VCardText>
                <div class="card-wave"></div>
              </VCard>
            </VCol>

            <!-- Card Resultado Final -->
            <VCol cols="12" md="4">
              <VCard
                class="rounded-lg border shadow-lg premium-summary-card overflow-hidden h-100 transition-all shadow-lg"
                :style="{ background: getIvaStatus.gradient }"
              >
                <VCardText
                  class="pa-6 d-flex flex-column align-center text-center"
                >
                  <VAvatar
                    color="white"
                    variant="tonal"
                    size="44"
                    class="mb-3 rounded-lg shadow-sm"
                  >
                    <VIcon :icon="getIvaStatus.icon" size="24" color="white" />
                  </VAvatar>
                  <span
                    class="text-xs font-weight-bold uppercase text-white opacity-70 mb-1"
                    >Estado de IVA</span
                  >
                  <div class="text-h4 font-weight-black text-white mb-2">
                    <span class="text-xs font-weight-medium me-1">Bs.</span
                    >{{ formatCurrency(Math.abs(ivaAPagar)) }}
                  </div>
                  <div class="d-flex align-center gap-2 mt-auto">
                    <VChip
                      size="x-small"
                      color="white"
                      variant="flat"
                      class="font-weight-black rounded uppercase"
                    >
                      {{ getIvaStatus.message }}
                    </VChip>
                    <span
                      v-if="ivaAPagar < 0"
                      class="text-super-xs text-white font-weight-bold"
                      >A TU FAVOR</span
                    >
                  </div>
                </VCardText>
                <div class="card-wave pulse"></div>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Filtros Mejorados -->
      <IvaFiscalFilters
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        :loading="loading"
        @apply-filter="handleApplyFilter"
        @clear-filter="handleClearFilter"
        class="mb-0"
      />

      <!-- Tablas de Historial -->
      <VRow class="ma-0 mt-5 mb-n1 mx-n1">
        <VCol cols="12" class="pa-1 mb-7">
          <DebitoFiscalTable
            :fiscal-data="fiscalData"
            :loading="tableLoading"
            :total-records="totalRecords"
            :items-per-page="itemsPerPage"
            :page="page"
            @update:options="handleTableOptionsUpdate"
          />
        </VCol>
        <VCol cols="12" class="pa-1">
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
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.leading-none {
  line-height: 1;
}

.bg-warning-gradient {
  background: linear-gradient(135deg, #ff9f43 0%, #ff6b00 100%) !important;
}

.bg-info-gradient {
  background: linear-gradient(135deg, #00cfe8 0%, #0091a2 100%) !important;
}

.premium-summary-card {
  position: relative;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.premium-summary-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.2) !important;
}

.card-wave {
  position: absolute;
  bottom: -20px;
  right: -20px;
  width: 120px;
  height: 120px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;
  pointer-events: none;
}

.card-wave.pulse {
  animation: pulse-wave 4s infinite linear;
}

@keyframes pulse-wave {
  0% {
    transform: scale(1) rotate(0deg);
    opacity: 0.1;
  }
  50% {
    transform: scale(1.2) rotate(180deg);
    opacity: 0.2;
  }
  100% {
    transform: scale(1) rotate(360deg);
    opacity: 0.1;
  }
}

:deep(.v-card-text) {
  z-index: 1;
}

.transition-all {
  transition: all 0.3s ease;
}
</style>
