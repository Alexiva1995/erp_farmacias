<script setup>
import CashCloseTable from "@/components/CashCloseTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, onMounted, reactive, ref, watch } from "vue";

const counts = ref([]);
const loading = ref(false);
const isClosing = ref(false);
const filters = reactive({
  searchQuery: "",
  startDate: null,
  endDate: null,
});

const hasActiveCycle = ref(false);
const activeCycle = ref(null);
const isCreatingCycle = ref(false);

const fetchData = async () => {
  loading.value = true;
  const params = {
    searchQuery: filters.searchQuery,
    startDate: filters.startDate,
    endDate: filters.endDate,
  };
  try {
    const response = await axios.get("/inventory/cash-close-items", { params });
    counts.value = response.data.data.map((item) => ({
      id: item.id,
      discrepancy: item.discrepancy,
      product: { name: item.product_name, sale_price: item.product_sale_price },
      user: { name: item.user_name || item.user_email },
    }));
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
  filters,
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchData, 300);
  },
  { deep: true }
);

const totals = computed(() => {
  const result = counts.value.reduce(
    (acc, item) => {
      const amount = (item.product.sale_price || 0) * item.discrepancy;
      if (amount > 0) acc.surplus += amount;
      else acc.shortage += Math.abs(amount);
      return acc;
    },
    { surplus: 0, shortage: 0 }
  );
  result.netTotal = result.surplus - result.shortage;
  return result;
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
  }).format(value || 0);
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
    month: "long",
    day: "numeric",
  });
};
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <VCard class="position-relative">
          <VCardTitle>Resumen del Cierre de Inventario</VCardTitle>
          <VDivider />
          <VCardText class="pb-12">
            <VRow>
              <VCol cols="12" md="4">
                <div class="d-flex align-center">
                  <VAvatar color="success" rounded variant="tonal" class="me-4">
                    <VIcon icon="tabler-trending-up" />
                  </VAvatar>
                  <div>
                    <span class="text-caption">Total Sobrantes</span>
                    <p class="text-h6 font-weight-medium text-success mb-0">
                      {{ formatCurrency(totals.surplus) }}
                    </p>
                  </div>
                </div>
              </VCol>
              <VCol cols="12" md="4">
                <div class="d-flex align-center">
                  <VAvatar color="error" rounded variant="tonal" class="me-4">
                    <VIcon icon="tabler-trending-down" />
                  </VAvatar>
                  <div>
                    <span class="text-caption">Total Faltantes</span>
                    <p class="text-h6 font-weight-medium text-error mb-0">
                      - {{ formatCurrency(totals.shortage) }}
                    </p>
                  </div>
                </div>
              </VCol>
              <VCol cols="12" md="4">
                <div class="d-flex align-center">
                  <VAvatar color="primary" rounded variant="tonal" class="me-4">
                    <VIcon icon="tabler-sum" />
                  </VAvatar>
                  <div>
                    <span class="text-caption">Total Cierre</span>
                    <p
                      class="text-h6 font-weight-bold mb-0"
                      :class="
                        totals.netTotal >= 0 ? 'text-primary' : 'text-warning'
                      "
                    >
                      {{ formatCurrency(totals.netTotal) }}
                    </p>
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>

          <div
            class="position-absolute"
            style="bottom: 16px; right: 16px; z-index: 2"
          >
            <VChip
              v-if="hasActiveCycle && activeCycle"
              color="info"
              variant="elevated"
              size="default"
              label
              class="me-2"
              style="padding: 19px 19px 19px 19px"
            >
              <VIcon icon="tabler-refresh-dot" start />
              Ciclo Activo: {{ formatDate(activeCycle.start_date) }}
            </VChip>

            <VBtn
              v-else
              color="success"
              prepend-icon="tabler-plus"
              :loading="isCreatingCycle"
              @click="handleCreateCycle"
              class="me-2"
            >
              Crear Nuevo Ciclo
            </VBtn>
            <VBtn
              color="primary"
              :disabled="loading || isClosing || !hasActiveCycle"
              :loading="isClosing"
              @click="handleCashClose"
            >
              <VIcon icon="tabler-lock" start />
              Generar cierre
            </VBtn>
          </div>

          <!-- Sección separada para el botón de cierre -->
          <VCardActions class="justify-end pa-4 pt-0"> </VCardActions>

          <VCardText>
            <VRow>
              <VCol cols="12" sm="3" md="2">
                <AppTextField
                  v-model="filters.searchQuery"
                  placeholder="Buscar por Producto, Usuario..."
                  clearable
                />
              </VCol>

              <VCol cols="12" sm="2" md="3">
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
              <VCol cols="12" sm="2" md="3">
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
          <VCardActions class="pa-4 px-6">
            <VBtn
              color="secondary"
              variant="outlined"
              @click="handleClearFilters"
            >
              Limpiar Filtros
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>

      <VCol cols="12">
        <CashCloseTable :items="counts" :loading="loading" />
      </VCol>
    </VRow>
  </div>
</template>
