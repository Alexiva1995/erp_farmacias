<script setup>
import CashCloseTable from "@/components/CashCloseTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, onMounted, reactive, ref, watch } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";

const counts = ref([]);
const totalCounts = ref(0);
const loading = ref(false);
const isClosing = ref(false);
const page = ref(1);
const itemsPerPage = ref(25);
const sortBy = ref("product.name");
const orderBy = ref("asc");
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
  
  // Remover parámetros null o vacíos
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
    }));
    totalCounts.value = response.data.total || 0;
    
    // Actualizar totales globales desde el backend
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

const totals = computed(() => {
  // Usar los totales globales del backend en lugar de calcular solo de la página actual
  return globalTotals.value;
});

/*const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
  }).format(value || 0);
};*/

const handleCashClose = async () => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "Esta acción cerrará el ciclo de inventario activo y creará automáticamente un nuevo ciclo. ¿Deseas continuar?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, cerrar ciclo",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
  });

  if (!result.isConfirmed) return;

  isClosing.value = true;

  try {
    // Paso 1: Cerrar el ciclo actual
    const closeResponse = await axios.post("/inventory/cycle/close");

    // Mostrar mensaje de éxito del cierre
    toast.success(closeResponse.data.message);

    // Paso 2: Crear automáticamente un nuevo ciclo
    try {
      const createResponse = await axios.post("/inventory/cycle/create");

      // Mostrar mensaje de éxito de la creación del nuevo ciclo
      toast.success(
        `Nuevo ciclo creado automáticamente: ${createResponse.data.message}`
      );
    } catch (createError) {
      // Si falla la creación del nuevo ciclo, mostrar error específico
      console.error("Error al crear el nuevo ciclo:", createError);
      toast.error(
        createError.response?.data?.message ||
          "El ciclo se cerró correctamente, pero hubo un error al crear el nuevo ciclo. Por favor, créelo manualmente."
      );
    }

    // Paso 3: Actualizar los datos y estado del ciclo
    await Promise.all([fetchData(), fetchCycleStatus()]);
  } catch (closeError) {
    // Si falla el cierre del ciclo
    console.error("Error al cerrar el ciclo:", closeError);
    toast.error(
      closeError.response?.data?.message || "Error al cerrar el ciclo."
    );
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
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
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

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  return new Date(dateString).toLocaleDateString("es-ES", {
    year: "numeric",
    month: "numeric",
    day: "numeric",
  });
};
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <VCard class="mb-6">
          <VCardText>
            <VRow>
              <VCol cols="12" sm="6" md="4">
                <AppTextField
                  v-model="filters.searchQuery"
                  placeholder="Buscar por Producto, Usuario..."
                  clearable
                />
              </VCol>

              <VCol cols="12" sm="6" md="4">
                <AppDateTimePicker
                  v-model="filters.startDate"
                  placeholder="Desde"
                  clearable
                  :config="{
                    altInput: true,
                    altFormat: 'Y-m-d',
                    dateFormat: 'Y-m-d',
                  }"
                />
              </VCol>
              <VCol cols="12" sm="6" md="4">
                <AppDateTimePicker
                  v-model="filters.endDate"
                  placeholder="Hasta"
                  clearable
                  :config="{
                    altInput: true,
                    altFormat: 'Y-m-d',
                    dateFormat: 'Y-m-d',
                  }"
                />
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
            <VBtn
              color="secondary"
              variant="outlined"
              @click="handleClearFilters"
            >
              Limpiar Filtros
            </VBtn>

            <VSpacer />

            <div class="d-flex align-center gap-2">
              <VChip
                v-if="hasActiveCycle && activeCycle"
                color="info"
                variant="tonal"
                size="default"
                label
              >
                <VIcon icon="tabler-refresh-dot" start />
                Ciclo Activo: {{ formatDate(activeCycle.start_date) }} →   
                
                <span class="text-h6 font-weight-medium text-success">
                  &nbsp+&nbsp{{ formatCurrency(totals.surplus) }} 
                </span>
                <span class="text-h6 font-weight-medium text-error">
                    &nbsp-&nbsp{{ formatCurrency(totals.shortage) }}
                </span>&nbsp=&nbsp
                <span
                        class="text-h6 font-weight-bold"
                        :class="
                          totals.netTotal >= 0 ? 'text-primary' : 'text-warning'
                        "
                      >
                        {{ formatCurrency(totals.netTotal) }}
                      </span>
              </VChip>

              <VBtn
                v-else
                color="success"
                prepend-icon="tabler-plus"
                :loading="isCreatingCycle"
                @click="handleCreateCycle"
              >
                Crear Nuevo Ciclo
              </VBtn>

              <VBtn
                color="success"
                :disabled="loading || isClosing || !hasActiveCycle"
                :loading="isClosing"
                prepend-icon="tabler-lock"
                @click="handleCashClose"
              >
                Generar cierre
              </VBtn>
            </div>
          </VCardActions>
        </VCard>
      </VCol>

      <VCol cols="12">
        <CashCloseTable 
          :items="counts" 
          :loading="loading"
          :total-items="totalCounts"
          :items-per-page="itemsPerPage"
          :page="page"
          @update:options="updateTableOptions"
        />
      </VCol>
    </VRow>
  </div>
</template>
