<script setup>
import CashCloseTable from "@/components/CashCloseTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatDateSimple, formatPrice } from "@/utils/formatters";
import Swal from "sweetalert2";
import { onMounted, onUnmounted, reactive, ref, watch, computed } from "vue";

let debounceTimer;
const counts = ref([]);
const totalCounts = ref(0);
const loading = ref(true);
const isClosing = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("product.name");
const orderBy = ref("asc");
const isAdvancedFiltersVisible = ref(false);

const filters = reactive({
  searchQuery: "",
  startDate: null,
  endDate: null,
});

const hasActiveCycle = ref(false);
const activeCycle = ref(null);
const isCreatingCycle = ref(false);
const globalTotals = ref({
  surplus: 0,
  shortage: 0,
  netTotal: 0
});

const fetchData = async () => {
  loading.value = true;
  const params = {
    searchQuery: filters.searchQuery,
    startDate: filters.startDate,
    endDate: filters.endDate,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "" || params[key] === undefined) && delete params[key]
  );
  
  try {
    const response = await axios.get("/inventory/cash-close-items", { params });
    counts.value = response.data.data.map((item) => ({
      id: item.id,
      productId: item.product_id,
      discrepancy: item.discrepancy,
      status: item.status,
      product: { 
        id: item.product_id,
        name: item.product_name, 
        activeIngredient: item.active_ingredient,
        sale_price: item.product_sale_price,
        unit_cost: item.product_unit_cost,
        laboratory: { name: item.laboratory_name }
      },
      user: { 
        name: item.user_name || item.user_email,
        employee_name: item.user_employee_name,
        employee_last_name: item.user_employee_last_name
      },
      supervisor: { 
        name: item.supervisor_name || item.supervisor_email || null,
        employee_name: item.supervisor_employee_name,
        employee_last_name: item.supervisor_employee_last_name
      },
      sourceType: item.source_type,
      hasTraceability: Number(item.has_traceability) === 1,
    }));
    totalCounts.value = response.data.total || 0;
    
    if (response.data.totals) {
      globalTotals.value = {
        surplus: response.data.totals.surplus || 0,
        shortage: response.data.totals.shortage || 0,
        netTotal: response.data.totals.netTotal || 0
      };
    }
  } catch (error) {
    console.error("Error al obtener datos para el cierre de caja:", error);
    toast.error("No se pudieron cargar los datos para el cierre.");
  } finally {
    loading.value = false;
  }
};

const fetchCycleStatus = async () => {
  try {
    const response = await axios.get("/inventory/cycle/active");
    hasActiveCycle.value = !!response.data.has_active_cycle;
    activeCycle.value = response.data.data || null;
  } catch (error) {
    console.error("Error al obtener estado del ciclo:", error);
    hasActiveCycle.value = false;
    activeCycle.value = null;
  }
};

onMounted(async () => {
  loading.value = true;
  await fetchCycleStatus();
  await fetchData();
});

onUnmounted(() => clearTimeout(debounceTimer));

watch(
  [filters, page, itemsPerPage, sortBy, orderBy],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchData, 300);
  },
  { deep: true }
);

watch(
  [filters.searchQuery, filters.startDate, filters.endDate],
  () => {
    page.value = 1;
  }
);

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  } else {
    sortBy.value = "product.name";
    orderBy.value = "asc";
  }
};

const handleCashClose = async (rejectPending = false) => {
  if (!rejectPending) {
    const result = await Swal.fire({
      title: "¿Estás seguro?",
      text: "Esta acción cerrará el ciclo de inventario activo y creará automáticamente un nuevo ciclo. ¿Deseas continuar?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, cerrar ciclo",
      cancelButtonText: "Cancelar",
      reverseButtons: true,
    });

    if (!result.isConfirmed) return;
  }

  isClosing.value = true;

  try {
    const closeResponse = await axios.post("/inventory/cycle/close", {
      reject_pending: rejectPending,
    });
    toast.success(closeResponse.data.message);

    try {
      const createResponse = await axios.post("/inventory/cycle/create");
      toast.success(`Nuevo ciclo creado automáticamente: ${createResponse.data.message}`);
    } catch (createError) {
      console.error("Error al crear el nuevo ciclo:", createError);
      toast.error(
        createError.response?.data?.message ||
          "El ciclo se cerró correctamente, pero hubo un error al crear el nuevo ciclo. Por favor, créelo manualmente."
      );
    }

    await Promise.all([fetchData(), fetchCycleStatus()]);
  } catch (closeError) {
    console.error("Error al cerrar el ciclo:", closeError);
    const errorData = closeError.response?.data;
    if (errorData?.has_pending) {
      const pendingResult = await Swal.fire({
        title: "Conteos pendientes detectados",
        text: "Existen conteos pendientes de aprobación o rechazo. ¿Deseas rechazar automáticamente los conteos pendientes y cerrar el ciclo?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, rechazar pendientes y cerrar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#d33",
        reverseButtons: true,
      });

      if (pendingResult.isConfirmed) {
        await handleCashClose(true);
        return;
      }
    } else {
      toast.error(errorData?.message || "Error al cerrar el ciclo.");
    }
  } finally {
    isClosing.value = false;
  }
};

const handleCreateCycle = async () => {
  const result = await Swal.fire({
    title: "¿Crear un nuevo ciclo de inventario?",
    text: "Se creará un nuevo ciclo y se establecerá como activo.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, crear ciclo",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
  });

  if (!result.isConfirmed) return;

  isCreatingCycle.value = true;
  try {
    const response = await axios.post("/inventory/cycle/create");
    toast.success(response.data.message);
    await fetchCycleStatus();
  } catch (error) {
    toast.error(error.response?.data?.message || "No se pudo crear el ciclo.");
  } finally {
    isCreatingCycle.value = false;
  }
};

const handleClearFilters = () => {
  filters.searchQuery = "";
  filters.startDate = null;
  filters.endDate = null;
};

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return filters.startDate || filters.endDate;
});

const handleDeleteItem = async (item) => {
  const result = await Swal.fire({
    title: "¿Eliminar este registro?",
    text: "Este registro parece ser un error y no tiene movimientos de trazabilidad asociados. ¿Deseas eliminarlo de la lista de cierre?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#d33",
  });

  if (!result.isConfirmed) return;

  try {
    loading.value = true;
    await axios.delete(`/inventory/count/${item.sourceType}/${item.id}`);
    toast.success("Registro eliminado y stock revertido exitosamente.");
    await fetchData();
  } catch (error) {
    console.error("Error al eliminar el registro:", error);
    toast.error(error.response?.data?.message || "No se pudo eliminar el registro.");
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <VContainer fluid class="pa-0">
    <!-- Dashboard de Control de Cierre Consolidado -->
    <VCard v-if="hasActiveCycle && activeCycle" class="mb-6 elevation-1 overflow-hidden">
      <VCardText class="pa-4">
        <!-- Fila de Control: Info + Búsqueda + Acciones -->
        <VRow dense align="center" class="mb-1">
          <!-- Información del Ciclo -->
          <VCol cols="12" md="4" class="d-flex align-center">
            <VChip
              color="primary"
              variant="tonal"
              size="default"
              class="font-weight-black text-uppercase px-3 w-100 justify-start"
            >
              <VIcon start icon="tabler-refresh" size="18" />
              Ciclo Activo
              <span class="text-disabled font-weight-medium ms-1">({{ formatDateSimple(activeCycle.start_date) }})</span>
            </VChip>
          </VCol>

          <!-- Buscador Central -->
          <VCol cols="12" sm="7" md="5">
            <AppTextField
              v-model="filters.searchQuery"
              placeholder="Buscar producto, usuario..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              variant="outlined"
            />
          </VCol>

          <!-- Botones de Acción Redondos sin texto -->
          <VCol cols="12" sm="5" md="3" class="d-flex align-center justify-end gap-1">
            <IconBtn
              variant="tonal"
              :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              class="rounded-circle"
              @click="toggleAdvancedFilters"
            >
              <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
              <VTooltip activator="parent">Filtros Avanzados</VTooltip>
              <VBadge v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible" color="error" dot offset-x="3" offset-y="-3" />
            </IconBtn>

            <IconBtn
              variant="tonal"
              color="success"
              size="38"
              class="rounded-circle"
              :disabled="loading || isClosing"
              :loading="isClosing"
              @click="handleCashClose"
            >
              <VIcon icon="tabler-lock-check" />
              <VTooltip activator="parent">Generar Cierre de Ciclo</VTooltip>
            </IconBtn>

            <VDivider vertical class="mx-1 my-2" />

            <IconBtn
              variant="text"
              color="secondary"
              size="38"
              class="rounded-circle"
              @click="handleClearFilters"
            >
              <VIcon icon="tabler-eraser" size="18" />
              <VTooltip activator="parent">Limpiar Filtros</VTooltip>
            </IconBtn>
          </VCol>
        </VRow>

        <!-- Filtros Avanzados (Expandibles) -->
        <VExpandTransition>
          <div v-show="isAdvancedFiltersVisible" class="py-3">
            <VDivider class="my-2 border-opacity-10" />
            <VRow dense>
              <VCol cols="12" sm="6">
                <AppDateTimePicker
                  v-model="filters.startDate"
                  placeholder="Fecha Inicio"
                  clearable
                  density="compact"
                  hide-details
                  prepend-inner-icon="tabler-calendar-plus"
                  :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <AppDateTimePicker
                  v-model="filters.endDate"
                  placeholder="Fecha Fin"
                  clearable
                  density="compact"
                  hide-details
                  prepend-inner-icon="tabler-calendar-check"
                  :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>

        <!-- Métricas Financieras (KPI Cards Modernos) -->
        <div v-if="globalTotals" class="pt-3 mt-2 border-t">
          <VRow dense>
            <!-- Sobrante -->
            <VCol cols="12" sm="4">
              <div class="kpi-metric-card d-flex align-center justify-space-between pa-2 px-3 rounded-lg border bg-surface">
                <div class="d-flex align-center gap-2">
                  <VAvatar size="28" color="success" variant="tonal" class="rounded">
                    <VIcon icon="tabler-trending-up" size="16" />
                  </VAvatar>
                  <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase">Sobrante</span>
                </div>
                <span class="text-sm font-weight-black text-success">{{ formatPrice(globalTotals.surplus) }}</span>
              </div>
            </VCol>

            <!-- Faltante -->
            <VCol cols="12" sm="4">
              <div class="kpi-metric-card d-flex align-center justify-space-between pa-2 px-3 rounded-lg border bg-surface">
                <div class="d-flex align-center gap-2">
                  <VAvatar size="28" color="error" variant="tonal" class="rounded">
                    <VIcon icon="tabler-trending-down" size="16" />
                  </VAvatar>
                  <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase">Faltante</span>
                </div>
                <span class="text-sm font-weight-black text-error">{{ formatPrice(globalTotals.shortage) }}</span>
              </div>
            </VCol>

            <!-- Balance Neto -->
            <VCol cols="12" sm="4">
              <div class="kpi-metric-card d-flex align-center justify-space-between pa-2 px-3 rounded-lg border bg-surface">
                <div class="d-flex align-center gap-2">
                  <VAvatar size="28" :color="globalTotals.netTotal >= 0 ? 'primary' : 'warning'" variant="tonal" class="rounded">
                    <VIcon icon="tabler-scale" size="16" />
                  </VAvatar>
                  <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase">Balance Neto</span>
                </div>
                <span 
                  class="text-sm font-weight-black"
                  :class="globalTotals.netTotal >= 0 ? 'text-primary' : 'text-warning'"
                >
                  {{ formatPrice(globalTotals.netTotal) }}
                </span>
              </div>
            </VCol>
          </VRow>
        </div>
      </VCardText>
    </VCard>

    <div v-else-if="!loading" class="mb-6">
      <VBtn
        block
        color="primary"
        variant="tonal"
        class="py-6 rounded-lg border-dashed"
        prepend-icon="tabler-plus"
        :loading="isCreatingCycle"
        @click="handleCreateCycle"
      >
        CREAR NUEVO CICLO DE INVENTARIO
      </VBtn>
    </div>

    <!-- Tabla / Vista de Cierre -->
    <CashCloseTable 
      :items="counts" 
      :loading="loading"
      :total-items="totalCounts"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @delete="handleDeleteItem"
      @refresh="fetchData"
    />
  </VContainer>
</template>

<style scoped>
.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.5) !important;
}

.kpi-metric-card {
  transition: all 0.2s ease;
  border-color: rgba(var(--v-border-color), 0.12) !important;
}

.kpi-metric-card:hover {
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.gap-x-6 {
  column-gap: 24px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.line-height-1 {
  line-height: 1 !important;
}
</style>
