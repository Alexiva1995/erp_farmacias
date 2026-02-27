<script setup>
import CreditoFiscalTable from "@/components/CreditoFiscalTable.vue";
import DebitoFiscalTable from "@/components/DebitoFiscalTable.vue";
import IvaFiscalFilters from "@/components/IvaFiscalFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref } from "vue";

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
      { params }
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
    toast.error("Error al cargar crédito fiscal");
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
    toast.error("Error al cargar débito fiscal");
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
    toast.error("Error al cargar datos de facturas");
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
      { params }
    );

    if (response.data.status === "success") {
      const data = response.data.data;
      expensesData.value = data.data;
      totalExpensesRecords.value = data.pagination.total;
    }
  } catch (error) {
    console.error("Error al obtener datos de gastos:", error);
    toast.error("Error al cargar datos de gastos");
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
  } finally {
    loading.value = false;
  }
};

// Función para formatear moneda venezolana (Bolívares)
const formatCurrency = (amount) => {
  const number = parseFloat(amount) || 0;
  return "Bs. " + number.toLocaleString("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

// Formatear fecha para mostrar
const formatDate = (dateString) => {
  if (!dateString) return "";
  return new Date(dateString).toLocaleDateString("es-VE", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

// Determinar color y estado según el resultado
const getIvaStatus = computed(() => {
  if (ivaAPagar.value > 0) {
    return {
      color: "error",
      icon: "tabler-trending-up",
      message: "A pagar",
      chipColor: "error",
    };
  } else if (ivaAPagar.value < 0) {
    return {
      color: "success",
      icon: "tabler-trending-down",
      message: "A favor",
      chipColor: "success",
    };
  } else {
    return {
      color: "info",
      icon: "tabler-equal",
      message: "Equilibrado",
      chipColor: "info",
    };
  }
});

// Manejar aplicación de filtros
const handleApplyFilter = () => {
  // Resetear paginación al aplicar filtros
  page.value = 1;
  expensesPage.value = 1;
  fetchAllData();
};

// Manejar limpieza de filtros
const handleClearFilter = () => {
  startDate.value = "";
  endDate.value = "";
  page.value = 1;
  expensesPage.value = 1;

  // Establecer fechas por defecto (mes actual)
  const now = new Date();
  const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  startDate.value = startOfMonth.toISOString().split("T")[0];
  endDate.value = endOfMonth.toISOString().split("T")[0];

  fetchAllData();
};

const handleTableOptionsUpdate = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy) {
    sortBy.value = options.sortBy;
  }

  // Solo recargar datos de la tabla de débito
  fetchFiscalHistoryData();
};

// Manejar cambios en la tabla de gastos
const handleExpensesTableOptionsUpdate = (options) => {
  expensesPage.value = options.page;
  expensesItemsPerPage.value = options.itemsPerPage;

  // Solo recargar datos de la tabla de gastos
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

// Cargar datos al montar el componente
onMounted(() => {
  initializeDefaults();
  fetchAllData();
});
</script>

<template>
  <div>
    <!-- Filtros de fecha -->
    <IvaFiscalFilters
      v-model:start-date="startDate"
      v-model:end-date="endDate"
      :loading="loading"
      @apply-filter="handleApplyFilter"
      @clear-filter="handleClearFilter"
    />

    <!-- Cards de Cálculo IVA Fiscal -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-calculator" class="me-2" />
        Cálculo IVA Fiscal
        <VSpacer />

        <!-- Indicador de período -->
        <VChip
          v-if="periodo.start_date"
          color="info"
          size="small"
          variant="outlined"
          class="me-2"
        >
          {{ formatDate(periodo.start_date) }} -
          {{ formatDate(periodo.end_date) }}
        </VChip>

        <!-- Estado del cálculo -->
        <VChip :color="getIvaStatus.chipColor" size="small" variant="tonal">
          <VIcon :icon="getIvaStatus.icon" size="14" class="me-1" />
          {{ getIvaStatus.message }}
        </VChip>

        <!-- Botón de recarga -->
        <VBtn
          icon="tabler-refresh"
          variant="text"
          size="small"
          :loading="loading"
          @click="fetchAllData"
          class="ms-2"
        />
      </VCardTitle>

      <VDivider />

      <VCardText>
        <!-- Loading state -->
        <div v-if="loading" class="text-center pa-6">
          <VProgressCircular indeterminate color="primary" size="64" />
          <div class="text-h6 mt-4">Cargando datos de IVA fiscal...</div>
        </div>

        <!-- Contenido principal -->
        <template v-else>
          <VRow>
            <!-- Débito Fiscal -->
            <VCol cols="12" md="4">
              <VCard variant="tonal" color="warning" class="h-100">
                <VCardText
                  class="text-center d-flex flex-column"
                  style="min-block-size: 180px;"
                >
                  <div class="d-flex align-center justify-center mb-2">
                    <VIcon
                      icon="tabler-arrow-up-circle"
                      size="28"
                      class="me-2"
                    />
                    <span class="text-h6 font-weight-bold">Débito Fiscal</span>
                  </div>
                  <div class="text-h4 font-weight-bold text-warning-darken-2">
                    {{ formatCurrency(debitoFiscal) }}
                  </div>
                  <div class="text-caption text-medium-emphasis mt-1">
                    IVA cobrado en ventas
                  </div>

                  <!-- Información adicional del débito -->
                  <div class="mt-auto pt-2">
                    <VChip
                      v-if="detalleDebito.total_orders_with_iva"
                      color="warning"
                      size="x-small"
                      variant="flat"
                    >
                      {{ detalleDebito.total_orders_with_iva }} órdenes con IVA
                    </VChip>
                    <VChip
                      v-else
                      color="warning"
                      size="x-small"
                      variant="outlined"
                      style="visibility: hidden;"
                    >
                      0 órdenes
                    </VChip>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Crédito Fiscal -->
            <VCol cols="12" md="4">
              <VCard variant="tonal" color="info" class="h-100">
                <VCardText
                  class="text-center d-flex flex-column"
                  style="min-block-size: 180px;"
                >
                  <div class="d-flex align-center justify-center mb-2">
                    <VIcon
                      icon="tabler-arrow-down-circle"
                      size="28"
                      class="me-2"
                    />
                    <span class="text-h6 font-weight-bold">Crédito Fiscal</span>
                  </div>
                  <div class="text-h4 font-weight-bold text-info-darken-2">
                    {{ formatCurrency(creditoFiscal) }}
                  </div>
                  <div class="text-caption text-medium-emphasis mt-1">
                    IVA pagado en compras
                  </div>

                  <!-- Información adicional del crédito -->
                  <div class="mt-auto pt-2">
                    <VChip
                      v-if="detalleCredito.total_expenses_with_iva"
                      color="info"
                      size="x-small"
                      variant="flat"
                    >
                      {{ detalleCredito.total_expenses_with_iva }} gastos con
                      IVA
                    </VChip>
                    <VChip
                      v-else
                      color="info"
                      size="x-small"
                      variant="outlined"
                      style="visibility: hidden;"
                    >
                      0 gastos
                    </VChip>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- IVA a Pagar -->
            <VCol cols="12" md="4">
              <VCard
                variant="tonal"
                :color="getIvaStatus.color"
                class="position-relative h-100"
              >
                <VCardText
                  class="text-center d-flex flex-column"
                  style="min-block-size: 180px;"
                >
                  <div class="d-flex align-center justify-center mb-2">
                    <VIcon :icon="getIvaStatus.icon" size="28" class="me-2" />
                    <span class="text-h6 font-weight-bold">IVA a Pagar</span>
                  </div>
                  <div
                    class="text-h4 font-weight-bold"
                    :class="{
                      'text-error-darken-2': ivaAPagar > 0,
                      'text-success-darken-2': ivaAPagar < 0,
                      'text-info-darken-2': ivaAPagar === 0,
                    }"
                  >
                    {{ formatCurrency(Math.abs(ivaAPagar)) }}
                  </div>
                  <div class="text-caption text-medium-emphasis mt-1">
                    {{ getIvaStatus.message }}
                  </div>

                  <!-- Espaciador para mantener altura consistente -->
                  <div class="mt-auto pt-2">
                    <VChip
                      v-if="ivaAPagar < 0"
                      color="success"
                      size="x-small"
                      variant="flat"
                    >
                      Saldo a favor
                    </VChip>
                    <VChip
                      v-else
                      color="transparent"
                      size="x-small"
                      variant="outlined"
                      style="visibility: hidden;"
                    >
                      Placeholder
                    </VChip>
                  </div>
                </VCardText>

                <!-- Badge para la esquina superior -->
                <div
                  v-if="ivaAPagar < 0"
                  class="position-absolute"
                  style="inset-block-start: 10px; inset-inline-end: 10px;"
                >
                  <VChip color="success" size="x-small" variant="flat">
                    Saldo a favor
                  </VChip>
                </div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Información adicional en caso de no tener datos -->
          <VRow v-if="creditoFiscal === 0 && debitoFiscal === 0" class="mt-4">
            <VCol cols="12">
              <VAlert type="info" variant="tonal">
                <template #title>
                  <div class="d-flex align-center">
                    <VIcon icon="tabler-info-circle" class="me-2" />
                    Sin datos para el período seleccionado
                  </div>
                </template>

                <div class="mt-2">
                  No se encontraron gastos con IVA ni ventas gravadas para el
                  período
                  <strong>{{ formatDate(periodo.start_date) }}</strong> -
                  <strong>{{ formatDate(periodo.end_date) }}</strong>
                </div>
              </VAlert>
            </VCol>
          </VRow>
        </template>
      </VCardText>
    </VCard>

    <!-- Tabla de Débito Fiscal -->
    <DebitoFiscalTable
      :fiscal-data="fiscalData"
      :loading="tableLoading"
      :total-records="totalRecords"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="handleTableOptionsUpdate"
      class="mb-6"
    />

    <!-- Tabla de Crédito Fiscal -->
    <CreditoFiscalTable
      :expenses-data="expensesData"
      :loading="expensesTableLoading"
      :total-records="totalExpensesRecords"
      :items-per-page="expensesItemsPerPage"
      :page="expensesPage"
      @update:options="handleExpensesTableOptionsUpdate"
    />
  </div>
</template>
