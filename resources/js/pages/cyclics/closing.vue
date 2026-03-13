<script setup>
import CashCloseTable from "@/components/CashCloseTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatDate, formatPrice } from "@/utils/formatters";
import Swal from "sweetalert2";
import { onMounted, reactive, ref, watch, computed } from "vue";

const counts = ref([]);
const totalCounts = ref(0);
const loading = ref(false);
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
      product: { 
        id: item.product_id,
        name: item.product_name, 
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
    hasActiveCycle.value = response.data.has_active_cycle;
    activeCycle.value = response.data.data;
  } catch (error) {
    console.error("Error al obtener estado del ciclo:", error);
  }
};

onMounted(() => {
  fetchData();
  fetchCycleStatus();
});

let debounceTimer;
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

const handleCashClose = async () => {
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

  isClosing.value = true;

  try {
    const closeResponse = await axios.post("/inventory/cycle/close");
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
    toast.error(closeError.response?.data?.message || "Error al cerrar el ciclo.");
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
  <div>
    <!-- Resumen de Ciclo Superior -->
    <VCard v-if="hasActiveCycle && activeCycle" class="mb-4 border-dashed border-primary overflow-hidden">
      <VCardText class="pa-3">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <!-- Info Ciclo -->
          <div class="d-flex align-center gap-2">
            <VAvatar color="primary" variant="tonal" size="32">
              <VIcon icon="tabler-refresh-dot" size="18" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-xs text-disabled text-uppercase font-weight-black line-height-1">Ciclo Activo</span>
              <span class="text-sm font-weight-bold">Desde: {{ formatDate(activeCycle.start_date) }}</span>
            </div>
          </div>

          <!-- Botón Generar Cierre (Móvil/Desktop) -->
          <div class="d-flex d-md-none">
            <VBtn
              color="success"
              variant="elevated"
              icon
              size="small"
              :disabled="loading || isClosing"
              :loading="isClosing"
              @click="handleCashClose"
            >
              <VIcon icon="tabler-lock-check" size="20" />
              <VTooltip activator="parent">GENERAR CIERRE</VTooltip>
            </VBtn>
          </div>

          <VBtn
            class="d-none d-md-flex"
            color="success"
            variant="elevated"
            :disabled="loading || isClosing"
            :loading="isClosing"
            prepend-icon="tabler-lock-check"
            @click="handleCashClose"
          >
            GENERAR CIERRE
          </VBtn>
        </div>

        <VDivider class="my-3 border-opacity-10" />

        <!-- Totales -->
        <div class="d-flex align-center justify-space-between flex-wrap gap-x-6 gap-y-2">
          <div class="d-flex flex-column">
            <span class="text-super-xs text-disabled text-uppercase font-weight-black">Sobrante</span>
            <span class="text-sm font-weight-bold text-success">{{ formatPrice(globalTotals.surplus) }}</span>
          </div>
          <VDivider vertical class="mx-1 d-none d-sm-block" />
          <div class="d-flex flex-column">
            <span class="text-super-xs text-disabled text-uppercase font-weight-black">Faltante</span>
            <span class="text-sm font-weight-bold text-error">{{ formatPrice(globalTotals.shortage) }}</span>
          </div>
          <VDivider vertical class="mx-1 d-none d-sm-block" />
          <div class="d-flex flex-column flex-grow-1 text-right text-sm-left">
            <span class="text-super-xs text-disabled text-uppercase font-weight-black">Balance Neto</span>
            <span 
              class="text-sm font-weight-black"
              :class="globalTotals.netTotal >= 0 ? 'text-primary' : 'text-warning'"
            >
              {{ formatPrice(globalTotals.netTotal) }}
            </span>
          </div>
        </div>
      </VCardText>
    </VCard>

    <VBtn
      v-else-if="!loading"
      block
      color="primary"
      variant="tonal"
      class="mb-4 py-6"
      prepend-icon="tabler-plus"
      :loading="isCreatingCycle"
      @click="handleCreateCycle"
    >
      CREAR NUEVO CICLO DE INVENTARIO
    </VBtn>

    <!-- Filtros Colapsables -->
    <VCard class="mb-6">
      <VCardText class="pa-3">
        <VRow align="center" no-gutters class="gap-2">
          <VCol cols="12" md="4">
            <AppTextField
              v-model="filters.searchQuery"
              placeholder="Buscar producto, usuario..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
            />
          </VCol>

          <VSpacer />

          <div class="d-flex align-center gap-1">
            <VBtn
              icon
              variant="tonal"
              :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              @click="toggleAdvancedFilters"
            >
              <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
              <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
              <VBadge
                v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
                color="error"
                dot
                offset-x="3"
                offset-y="-3"
              />
            </VBtn>

            <VDivider vertical class="mx-1 my-2" />

            <VBtn
              icon
              variant="text"
              color="secondary"
              size="38"
              @click="handleClearFilters"
            >
              <VIcon icon="tabler-eraser" />
              <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
            </VBtn>
          </div>
        </VRow>

        <VExpandTransition>
          <div v-show="isAdvancedFiltersVisible">
            <VDivider class="my-3 border-opacity-10" />
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
      </VCardText>
    </VCard>

    <!-- Tabla / Vista de Cierre -->
    <CashCloseTable 
      :items="counts" 
      :loading="loading"
      :total-items="totalCounts"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @delete="handleDeleteItem"
    />
  </div>
</template>

<style scoped>
.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.5) !important;
}

.gap-x-6 {
  column-gap: 24px !important;
}
</style>
