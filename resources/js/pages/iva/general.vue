<script setup>
import CreditoFiscalTable from "@/components/CreditoFiscalTable.vue";
import DebitoFiscalTable from "@/components/DebitoFiscalTable.vue";
import IvaFiscalFilters from "@/components/IvaFiscalFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref } from "vue";
import { useDisplay } from "vuetify";
import { useBrandingStore } from "@/stores/useBrandingStore";

const { mobile } = useDisplay();
const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings?.business_type === 'restaurant');

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

// Cálculo automático del IVA a pagar (Débito Fiscal - Crédito Fiscal)
const ivaAPagar = computed(() => {
  return debitoFiscal.value - creditoFiscal.value;
});

// IGTF: 3% del total de ventas marcadas como SPE (no del IVA)
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

    if (response.data.status === "success") {
      const data = response.data.data;
      creditoFiscal.value = data.credito_fiscal;
      retenciones.value = data.retenciones ?? creditoFiscal.value * 0.75;
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
      <!-- KPI Cards: una sola fila -->
      <VRow dense class="mb-2 flex-nowrap">
        <!-- Cargando -->
        <VCol v-if="loading" cols="12" class="text-center pa-8">
          <VProgressCircular indeterminate color="primary" size="40" />
          <div class="text-xs font-weight-black text-disabled mt-3 uppercase">Sincronizando datos fiscales...</div>
        </VCol>

        <template v-else>
          <!-- Débito Fiscal -->
          <VCol cols="auto" class="flex-grow-1">
            <VCard class="stats-card border-0 overflow-hidden mb-2">
              <div class="card-bg-decoration" :style="{ background: 'linear-gradient(45deg, rgba(var(--v-theme-warning), 0.1), transparent)' }"></div>
              <VCardText class="pa-5 relative-content">
                <div class="d-flex align-center justify-space-between mb-4">
                  <VAvatar color="warning" variant="tonal" size="48" rounded="lg" class="elevation-1">
                    <VIcon icon="tabler-receipt-tax" size="26" />
                  </VAvatar>
                  <div class="text-right">
                    <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important;">Débito Fiscal</span>
                    <h4 class="text-h5 font-weight-black mt-1">
                      <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(debitoFiscal) }}
                    </h4>
                  </div>
                </div>
                <VDivider class="mb-3 opacity-20" />
                <div class="d-flex align-center justify-space-between">
                  <span class="text-caption font-weight-medium text-medium-emphasis">{{ detalleDebito.total_orders_with_iva || 0 }} ventas con IVA</span>
                  <VIcon icon="tabler-trending-up" size="16" color="warning" class="opacity-50" />
                </div>
              </VCardText>
              <div class="accent-border" style="background-color: rgb(var(--v-theme-warning));"></div>
            </VCard>
          </VCol>

          <!-- Crédito Fiscal -->
          <VCol cols="auto" class="flex-grow-1">
            <VCard class="stats-card border-0 overflow-hidden mb-2">
              <div class="card-bg-decoration" :style="{ background: 'linear-gradient(45deg, rgba(var(--v-theme-info), 0.1), transparent)' }"></div>
              <VCardText class="pa-5 relative-content">
                <div class="d-flex align-center justify-space-between mb-4">
                  <VAvatar color="info" variant="tonal" size="48" rounded="lg" class="elevation-1">
                    <VIcon icon="tabler-receipt-refund" size="26" />
                  </VAvatar>
                  <div class="text-right">
                    <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important;">Crédito Fiscal</span>
                    <h4 class="text-h5 font-weight-black mt-1">
                      <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(creditoFiscal) }}
                    </h4>
                  </div>
                </div>
                <VDivider class="mb-3 opacity-20" />
                <div class="d-flex align-center justify-space-between">
                  <span class="text-caption font-weight-medium text-medium-emphasis">{{ detalleCredito.total_expenses_with_iva || 0 }} gastos con IVA</span>
                  <VIcon icon="tabler-trending-down" size="16" color="info" class="opacity-50" />
                </div>
              </VCardText>
              <div class="accent-border" style="background-color: rgb(var(--v-theme-info));"></div>
            </VCard>
          </VCol>

          <!-- Saldo IVA -->
          <VCol cols="auto" class="flex-grow-1">
            <VCard class="stats-card border-0 overflow-hidden mb-2">
              <div class="card-bg-decoration" :style="{ background: `linear-gradient(45deg, rgba(var(--v-theme-${getIvaStatus.color}), 0.1), transparent)` }"></div>
              <VCardText class="pa-5 relative-content">
                <div class="d-flex align-center justify-space-between mb-4">
                  <VAvatar :color="getIvaStatus.color" variant="tonal" size="48" rounded="lg" class="elevation-1">
                    <VIcon :icon="getIvaStatus.icon" size="26" />
                  </VAvatar>
                  <div class="text-right">
                    <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important;">Saldo IVA</span>
                    <h4 class="text-h5 font-weight-black mt-1">
                      <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(Math.abs(ivaAPagar)) }}
                    </h4>
                  </div>
                </div>
                <VDivider class="mb-3 opacity-20" />
                <div class="d-flex align-center justify-space-between">
                  <span class="text-caption font-weight-medium text-medium-emphasis">{{ getIvaStatus.message }}</span>
                  <VIcon :icon="getIvaStatus.icon" size="16" :color="getIvaStatus.color" class="opacity-50" />
                </div>
              </VCardText>
              <div class="accent-border" :style="{ backgroundColor: `rgb(var(--v-theme-${getIvaStatus.color}))` }"></div>
            </VCard>
          </VCol>

          <!-- Retenciones (75% del Crédito Fiscal) -->
          <VCol v-if="!isRestaurant" cols="auto" class="flex-grow-1">
            <VCard class="stats-card border-0 overflow-hidden mb-2">
              <div class="card-bg-decoration" :style="{ background: 'linear-gradient(45deg, rgba(var(--v-theme-secondary), 0.1), transparent)' }"></div>
              <VCardText class="pa-5 relative-content">
                <div class="d-flex align-center justify-space-between mb-4">
                  <VAvatar color="secondary" variant="tonal" size="48" rounded="lg" class="elevation-1">
                    <VIcon icon="tabler-percentage" size="26" />
                  </VAvatar>
                  <div class="text-right">
                    <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important;">Retenciones</span>
                    <h4 class="text-h5 font-weight-black mt-1">
                      <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(retenciones) }}
                    </h4>
                  </div>
                </div>
                <VDivider class="mb-3 opacity-20" />
                <div class="d-flex align-center justify-space-between">
                  <span class="text-caption font-weight-medium text-medium-emphasis">75% del Crédito Fiscal</span>
                  <VIcon icon="tabler-percentage" size="16" color="secondary" class="opacity-50" />
                </div>
              </VCardText>
              <div class="accent-border" style="background-color: rgb(var(--v-theme-secondary));"></div>
            </VCard>
          </VCol>

          <!-- IGTF (3% de compras SPE) -->
          <VCol cols="auto" class="flex-grow-1">
            <VCard class="stats-card border-0 overflow-hidden mb-2">
              <div class="card-bg-decoration" :style="{ background: 'linear-gradient(45deg, rgba(var(--v-theme-error), 0.08), transparent)' }"></div>
              <VCardText class="pa-5 relative-content">
                <div class="d-flex align-center justify-space-between mb-4">
                  <VAvatar color="error" variant="tonal" size="48" rounded="lg" class="elevation-1">
                    <VIcon icon="tabler-building-bank" size="26" />
                  </VAvatar>
                  <div class="text-right">
                    <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important;">IGTF</span>
                    <h4 class="text-h5 font-weight-black mt-1">
                      <span class="text-xs font-weight-medium me-1">Bs.</span>{{ formatCurrency(igtfAmount) }}
                    </h4>
                  </div>
                </div>
                <VDivider class="mb-3 opacity-20" />
                <div class="d-flex align-center justify-space-between">
                  <span class="text-caption font-weight-medium text-medium-emphasis">3% del total — {{ detalleDebito.total_spe_count || 0 }} ventas con IGTF</span>
                  <VIcon icon="tabler-building-bank" size="16" color="error" class="opacity-50" />
                </div>
              </VCardText>
              <div class="accent-border" style="background-color: rgb(var(--v-theme-error));"></div>
            </VCard>
          </VCol>
        </template>
      </VRow>

      <!-- Filtros Mejorados -->
      <IvaFiscalFilters
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        :loading="loading"
        @apply-filter="handleApplyFilter"
        @clear-filter="handleClearFilter"
        @refresh="fetchAllData"
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
/* Estilos portados de SupplierStatsCards — paridad visual total */
.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 80%) !important;
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 5%) !important;
  transition: all 0.3s ease;
}

.stats-card:hover {
  box-shadow: 0 8px 25px 0 rgba(0, 0, 0, 8%) !important;
  transform: translateY(-5px);
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 100px;
  filter: blur(40px);
  inline-size: 100px;
  inset-block-start: -20px;
  inset-inline-end: -20px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 70%;
  border-end-end-radius: 4px;
  border-start-end-radius: 4px;
  inline-size: 4px;
  inset-block-start: 15%;
  inset-inline-start: 0;
  opacity: 0.8;
}

.text-h5 {
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: -0.5px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}
</style>
