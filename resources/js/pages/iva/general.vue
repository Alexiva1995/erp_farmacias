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
const retenciones = ref(0);
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

// Formateador de moneda (Bolívares)
const formatCurrency = (amount) => {
  const number = parseFloat(amount) || 0;
  return number.toLocaleString("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
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

    if (response.data?.status === "success") {
      const data = response.data.data || {};
      creditoFiscal.value = data.credito_fiscal || 0;
      retenciones.value = data.retenciones ?? (creditoFiscal.value * 0.75);
      detalleCredito.value = data.detalle_credito || {};
      if (!periodo.value.start_date) {
        periodo.value = data.periodo || {};
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

    if (response.data?.status === "success") {
      const data = response.data.data || {};
      debitoFiscal.value = data.debito_fiscal || 0;
      detalleDebito.value = data.detalle_debito || {};
      if (!periodo.value.start_date) {
        periodo.value = data.periodo || {};
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
    };

    if (startDate.value) params.start_date = startDate.value;
    if (endDate.value) params.end_date = endDate.value;

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

// Estado visual del IVA acumulado
const getIvaStatus = computed(() => {
  if (ivaAPagar.value > 0) {
    return {
      color: "error",
      icon: "tabler-trending-up",
      message: "Saldo a Pagar",
    };
  } else if (ivaAPagar.value < 0) {
    return {
      color: "success",
      icon: "tabler-trending-down",
      message: "Saldo a Favor",
    };
  } else {
    return {
      color: "info",
      icon: "tabler-equal",
      message: "Equilibrado",
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

// Inicializar rango con el mes actual
const initializeDefaults = () => {
  const now = new Date();
  const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  const formatOffsetDate = (d) => {
    const dateCopy = new Date(d);
    dateCopy.setMinutes(dateCopy.getMinutes() - dateCopy.getTimezoneOffset());
    return dateCopy.toISOString().split("T")[0];
  };

  startDate.value = formatOffsetDate(startOfMonth);
  endDate.value = formatOffsetDate(endOfMonth);
};

onMounted(() => {
  initializeDefaults();
  fetchAllData();
});
</script>

<template>
  <div class="iva-general-page pb-12">
    <div class="d-flex flex-column gap-3 mt-1">
      <!-- KPI Cards Responsivas sin Layout Shift -->
      <VRow dense class="mb-1">
        <!-- Débito Fiscal -->
        <VCol cols="12" sm="6" md="4" lg="2-4" class="flex-grow-1">
          <VSkeletonLoader v-if="loading" type="card" height="130" class="rounded-lg border-0" />
          <VCard v-else class="stats-card border-0 overflow-hidden h-100">
            <div class="card-bg-decoration" :style="{ background: 'linear-gradient(45deg, rgba(var(--v-theme-warning), 0.12), transparent)' }"></div>
            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar color="warning" variant="tonal" size="44" rounded="lg">
                  <VIcon icon="tabler-receipt-tax" size="24" />
                </VAvatar>
                <div class="text-right">
                  <span class="text-overline font-weight-bold text-disabled">Débito Fiscal</span>
                  <h4 class="text-h5 font-weight-black mt-1">
                    <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(debitoFiscal) }}
                  </h4>
                </div>
              </div>
              <VDivider class="mb-2 opacity-20" />
              <div class="d-flex align-center justify-space-between">
                <span class="text-caption font-weight-medium text-medium-emphasis">{{ detalleDebito.total_orders_with_iva || 0 }} ventas con IVA</span>
                <VIcon icon="tabler-trending-up" size="16" color="warning" class="opacity-70" />
              </div>
            </VCardText>
            <div class="accent-border" style="background-color: rgb(var(--v-theme-warning));"></div>
          </VCard>
        </VCol>

        <!-- Crédito Fiscal -->
        <VCol cols="12" sm="6" md="4" lg="2-4" class="flex-grow-1">
          <VSkeletonLoader v-if="loading" type="card" height="130" class="rounded-lg border-0" />
          <VCard v-else class="stats-card border-0 overflow-hidden h-100">
            <div class="card-bg-decoration" :style="{ background: 'linear-gradient(45deg, rgba(var(--v-theme-info), 0.12), transparent)' }"></div>
            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar color="info" variant="tonal" size="44" rounded="lg">
                  <VIcon icon="tabler-receipt-refund" size="24" />
                </VAvatar>
                <div class="text-right">
                  <span class="text-overline font-weight-bold text-disabled">Crédito Fiscal</span>
                  <h4 class="text-h5 font-weight-black mt-1">
                    <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(creditoFiscal) }}
                  </h4>
                </div>
              </div>
              <VDivider class="mb-2 opacity-20" />
              <div class="d-flex align-center justify-space-between">
                <span class="text-caption font-weight-medium text-medium-emphasis">{{ detalleCredito.total_expenses_with_iva || 0 }} gastos con IVA</span>
                <VIcon icon="tabler-trending-down" size="16" color="info" class="opacity-70" />
              </div>
            </VCardText>
            <div class="accent-border" style="background-color: rgb(var(--v-theme-info));"></div>
          </VCard>
        </VCol>

        <!-- Saldo IVA -->
        <VCol cols="12" sm="6" md="4" lg="2-4" class="flex-grow-1">
          <VSkeletonLoader v-if="loading" type="card" height="130" class="rounded-lg border-0" />
          <VCard v-else class="stats-card border-0 overflow-hidden h-100">
            <div class="card-bg-decoration" :style="{ background: `linear-gradient(45deg, rgba(var(--v-theme-${getIvaStatus.color}), 0.12), transparent)` }"></div>
            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar :color="getIvaStatus.color" variant="tonal" size="44" rounded="lg">
                  <VIcon :icon="getIvaStatus.icon" size="24" />
                </VAvatar>
                <div class="text-right">
                  <span class="text-overline font-weight-bold text-disabled">Saldo IVA</span>
                  <h4 class="text-h5 font-weight-black mt-1">
                    <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(Math.abs(ivaAPagar)) }}
                  </h4>
                </div>
              </div>
              <VDivider class="mb-2 opacity-20" />
              <div class="d-flex align-center justify-space-between">
                <span class="text-caption font-weight-medium text-medium-emphasis">{{ getIvaStatus.message }}</span>
                <VIcon :icon="getIvaStatus.icon" size="16" :color="getIvaStatus.color" class="opacity-70" />
              </div>
            </VCardText>
            <div class="accent-border" :style="{ backgroundColor: `rgb(var(--v-theme-${getIvaStatus.color}))` }"></div>
          </VCard>
        </VCol>

        <!-- Retenciones (75% del Crédito Fiscal) -->
        <VCol cols="12" sm="6" md="4" lg="2-4" class="flex-grow-1">
          <VSkeletonLoader v-if="loading" type="card" height="130" class="rounded-lg border-0" />
          <VCard v-else class="stats-card border-0 overflow-hidden h-100">
            <div class="card-bg-decoration" :style="{ background: 'linear-gradient(45deg, rgba(var(--v-theme-secondary), 0.12), transparent)' }"></div>
            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar color="secondary" variant="tonal" size="44" rounded="lg">
                  <VIcon icon="tabler-percentage" size="24" />
                </VAvatar>
                <div class="text-right">
                  <span class="text-overline font-weight-bold text-disabled">Retenciones</span>
                  <h4 class="text-h5 font-weight-black mt-1">
                    <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(retenciones) }}
                  </h4>
                </div>
              </div>
              <VDivider class="mb-2 opacity-20" />
              <div class="d-flex align-center justify-space-between">
                <span class="text-caption font-weight-medium text-medium-emphasis">75% Estimado Crédito</span>
                <VIcon icon="tabler-percentage" size="16" color="secondary" class="opacity-70" />
              </div>
            </VCardText>
            <div class="accent-border" style="background-color: rgb(var(--v-theme-secondary));"></div>
          </VCard>
        </VCol>

        <!-- IGTF (3% de ventas SPE) -->
        <VCol cols="12" sm="6" md="4" lg="2-4" class="flex-grow-1">
          <VSkeletonLoader v-if="loading" type="card" height="130" class="rounded-lg border-0" />
          <VCard v-else class="stats-card border-0 overflow-hidden h-100">
            <div class="card-bg-decoration" :style="{ background: 'linear-gradient(45deg, rgba(var(--v-theme-error), 0.1), transparent)' }"></div>
            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar color="error" variant="tonal" size="44" rounded="lg">
                  <VIcon icon="tabler-building-bank" size="24" />
                </VAvatar>
                <div class="text-right">
                  <span class="text-overline font-weight-bold text-disabled">IGTF (3%)</span>
                  <h4 class="text-h5 font-weight-black mt-1">
                    <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(igtfAmount) }}
                  </h4>
                </div>
              </div>
              <VDivider class="mb-2 opacity-20" />
              <div class="d-flex align-center justify-space-between">
                <span class="text-caption font-weight-medium text-medium-emphasis">{{ detalleDebito.total_spe_count || 0 }} ventas SPE</span>
                <VIcon icon="tabler-building-bank" size="16" color="error" class="opacity-70" />
              </div>
            </VCardText>
            <div class="accent-border" style="background-color: rgb(var(--v-theme-error));"></div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Filtros de Fecha -->
      <IvaFiscalFilters
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        :loading="loading"
        @apply-filter="handleApplyFilter"
        @clear-filter="handleClearFilter"
        @refresh="fetchAllData"
        class="mb-0"
      />

      <!-- Tablas de Débito y Crédito Fiscal -->
      <VRow class="ma-0 mt-3">
        <VCol cols="12" class="pa-0 mb-6">
          <DebitoFiscalTable
            :fiscal-data="fiscalData"
            :loading="tableLoading"
            :total-records="totalRecords"
            :items-per-page="itemsPerPage"
            :page="page"
            @update:options="handleTableOptionsUpdate"
          />
        </VCol>
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
  </div>
</template>

<style scoped>
.stats-card {
  border-radius: 10px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 90%) !important;
  box-shadow: 0 4px 18px 0 rgba(0, 0, 0, 4%) !important;
  transition: all 0.25s ease-in-out;
}

.stats-card:hover {
  box-shadow: 0 6px 22px 0 rgba(0, 0, 0, 8%) !important;
  transform: translateY(-3px);
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 90px;
  filter: blur(35px);
  inline-size: 90px;
  inset-block-start: -15px;
  inset-inline-end: -15px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 65%;
  border-end-end-radius: 4px;
  border-start-end-radius: 4px;
  inline-size: 4px;
  inset-block-start: 17.5%;
  inset-inline-start: 0;
  opacity: 0.85;
}

.text-h5 {
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: -0.5px !important;
}
</style>
