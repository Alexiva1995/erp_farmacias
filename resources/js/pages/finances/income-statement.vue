<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref } from "vue";

// Estado reactivo
const loading = ref(false);
const loadingSummary = ref(false);
const loadingDetails = ref(false);

// Datos
const summary = ref({});
const transactions = ref([]);
const totalItems = ref(0);
const itemsPerPage = ref(50);
const page = ref(1);

// Filtros
const startDate = ref(null);
const endDate = ref(null);
const selectedQuickFilter = ref(null);

const quickFilterOptions = [
  { title: "Todo el tiempo", value: "all" },
  { title: "Últimos 15 días", value: 15 },
  { title: "Últimos 30 días", value: 30 },
  { title: "Últimos 60 días", value: 60 },
  { title: "Últimos 90 días", value: 90 },
  { title: "Mes actual", value: "current_month" },
  { title: "Mes anterior", value: "last_month" },
];

// Headers de la tabla
const headers = [
  { title: "Fecha", key: "date", sortable: true },
  { title: "Tipo", key: "type", sortable: false },
  { title: "Descripción", key: "description", sortable: true },
  { title: "Cliente/Categoría", key: "client", sortable: true },
  { title: "Monto", key: "amount", sortable: true },
  { title: "Costos", key: "costs", sortable: true },
  { title: "Utilidad", key: "profit", sortable: true },
];

// Cargar resumen (remains mostly same, but renamed data access if needed)
const loadSummary = async () => {
  loadingSummary.value = true;
  try {
    const params = new URLSearchParams();
    if (startDate.value) params.append("start_date", startDate.value);
    if (endDate.value) params.append("end_date", endDate.value);

    const response = await axios.get(
      `/finances/income-statement/summary?${params}`,
    );

    if (response.data && response.data.success) {
      summary.value = response.data.data;
    } else {
      toast.error(
        "Error al cargar el resumen: " +
          (response.data?.message || "Error desconocido"),
      );
    }
  } catch (error) {
    console.error("Error al cargar resumen:", error);
    toast.error("Error al cargar el resumen del estado de resultados");
  } finally {
    loadingSummary.value = false;
  }
};

// Cargar detalles - PAGINADO
const loadDetails = async () => {
  loadingDetails.value = true;
  try {
    const params = new URLSearchParams();
    if (startDate.value) params.append("start_date", startDate.value);
    if (endDate.value) params.append("end_date", endDate.value);
    params.append("page", page.value);
    params.append("per_page", itemsPerPage.value);

    const response = await axios.get(
      `/finances/income-statement/details?${params}`,
    );

    if (response.data && response.data.success) {
      transactions.value = response.data.data.transactions || [];
      totalItems.value = response.data.data.pagination?.total || 0;
    } else {
      toast.error(
        "Error al cargar los detalles: " +
          (response.data?.message || "Error desconocido"),
      );
    }
  } catch (error) {
    console.error("Error al cargar detalles:", error);
    toast.error("Error al cargar los detalles del estado de resultados");
  } finally {
    loadingDetails.value = false;
  }
};

// Cargar todos los datos
const loadData = async () => {
  page.value = 1; // Reset to page 1 on filter change
  await Promise.all([loadSummary(), loadDetails()]);
};

// Manejar cambios de paginación
const updateOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  loadDetails(); // Solo cargar detalles al cambiar página
};

// Aplicar filtros
const applyFilters = () => {
  loadData();
};

// Filtros rápidos
const setQuickFilter = (days) => {
  const today = new Date();
  const start = new Date(today);

  if (days === "all") {
    startDate.value = null;
    endDate.value = null;
  } else if (days === "current_month") {
    start.setDate(1);
    startDate.value = start.toISOString().split("T")[0];
    endDate.value = today.toISOString().split("T")[0];
  } else if (days === "last_month") {
    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
    startDate.value = lastMonth.toISOString().split("T")[0];
    endDate.value = lastDay.toISOString().split("T")[0];
  } else {
    start.setDate(today.getDate() - days);
    startDate.value = start.toISOString().split("T")[0];
    endDate.value = today.toISOString().split("T")[0];
  }

  applyFilters();
};

// Limpiar filtros
const clearFilters = () => {
  startDate.value = null;
  endDate.value = null;
  selectedQuickFilter.value = null;
  applyFilters();
};

// Formatear moneda
const formatCurrency = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
  }).format(amount || 0);
};

// Formatear fecha
const formatDate = (date) => {
  return new Date(date).toLocaleDateString("es-VE");
};

// Verificar autenticación
const getCsrfCookie = async () => {
  try {
    await axios.get("/sanctum/csrf-cookie");
    console.log("CSRF cookie obtenida correctamente");
    return true;
  } catch (error) {
    console.error("Error al obtener CSRF cookie:", error);
    return false;
  }
};

const checkAuth = async () => {
  try {
    console.log("=== CHECKING AUTH ===");

    // Primero obtener CSRF cookie
    await getCsrfCookie();

    // Verificar usuario autenticado
    const response = await axios.get("/user");
    console.log("Usuario autenticado:", response.data);
    return true;
  } catch (error) {
    console.error("Usuario no autenticado:", {
      status: error.response?.status,
      data: error.response?.data,
      message: error.message,
    });

    if (error.response?.status === 401) {
      toast.error("No estás autenticado. Por favor, inicia sesión.");
    } else if (error.response?.status === 419) {
      toast.error("Sesión expirada. Por favor, inicia sesión nuevamente.");
    } else {
      toast.error("Error de autenticación. Por favor, inicia sesión.");
    }
    return false;
  }
};

// Lifecycle
onMounted(() => {
  loadSummary();
});

// Watch para filtros automáticos
import { watch } from "vue";
watch([startDate, endDate], () => {
  // Debounce opcional si fuera necesario, pero por ahora directo
  applyFilters();
});
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <!-- Filtros en una fila -->
        <VRow>
          <VCol cols="12" sm="3" md="4">
            <VSelect
              v-model="selectedQuickFilter"
              :items="quickFilterOptions"
              label="Filtro Rápido"
              placeholder="Seleccionar periodo"
              item-title="title"
              item-value="value"
              variant="outlined"
              hide-details
              @update:model-value="setQuickFilter"
            />
          </VCol>
          <VCol cols="12" sm="3" md="4">
            <AppDateTimePicker
              v-model="startDate"
              placeholder="Desde"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="3" md="4">
            <AppDateTimePicker
              v-model="endDate"
              placeholder="Hasta"
              clearable
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
        <VBtn color="secondary" variant="outlined" @click="clearFilters">
          Limpiar Filtros
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Resumen (4 cuadritos) -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VIcon
                icon="tabler-currency-dollar"
                color="success"
                size="40"
                class="me-4"
              />
              <div>
                <h3 class="text-h6 text-success">Ingresos Totales</h3>
                <p class="text-h4 font-weight-bold">
                  {{ formatCurrency(summary.income?.amount) }}
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VIcon
                icon="tabler-package"
                color="warning"
                size="40"
                class="me-4"
              />
              <div>
                <h3 class="text-h6 text-warning">Costos Totales</h3>
                <p class="text-h4 font-weight-bold">
                  {{ formatCurrency(summary.costs?.amount) }}
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VIcon
                icon="tabler-chart-line"
                color="error"
                size="40"
                class="me-4"
              />
              <div>
                <h3 class="text-h6 text-error">Gastos Operativos</h3>
                <p class="text-h4 font-weight-bold">
                  {{ formatCurrency(summary.expenses?.amount) }}
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VIcon
                :icon="
                  summary.net_profit?.amount >= 0
                    ? 'tabler-trending-up'
                    : 'tabler-trending-down'
                "
                :color="summary.net_profit?.amount >= 0 ? 'success' : 'error'"
                size="40"
                class="me-4"
              />
              <div>
                <h3
                  class="text-h6"
                  :class="
                    summary.net_profit?.amount >= 0
                      ? 'text-success'
                      : 'text-error'
                  "
                >
                  Utilidad Neta
                </h3>
                <p
                  class="text-h4 font-weight-bold"
                  :class="
                    summary.net_profit?.amount >= 0
                      ? 'text-success'
                      : 'text-error'
                  "
                >
                  {{ formatCurrency(summary.net_profit?.amount) }}
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de Detalles -->
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center">
          <h2 class="text-h5">Detalles de Transacciones</h2>
          <VChip color="primary" variant="tonal">
            {{ totalItems }} registros en total
          </VChip>
        </div>
      </VCardTitle>

      <VCardText>
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="transactions"
          :items-length="totalItems"
          :loading="loadingDetails"
          item-key="id"
          class="elevation-1"
          @update:options="updateOptions"
        >
          <template #item.date="{ item }">
            {{ formatDate(item.date) }}
          </template>

          <template #item.type="{ item }">
            <VChip
              :color="item.type === 'sale' ? 'success' : 'error'"
              size="small"
              variant="tonal"
            >
              {{ item.type === "sale" ? "Venta" : "Gasto" }}
            </VChip>
          </template>

          <template #item.client="{ item }">
            {{ item.client || item.category || "N/A" }}
          </template>

          <template #item.amount="{ item }">
            <span :class="item.type === 'sale' ? 'text-success' : 'text-error'">
              {{
                item.monto_display ||
                (item.type === "sale" ? "+" : "-") + formatCurrency(item.amount)
              }}
            </span>
          </template>

          <template #item.costs="{ item }">
            {{ item.costos_display || formatCurrency(item.costs) }}
          </template>

          <template #item.profit="{ item }">
            <span :class="item.profit >= 0 ? 'text-success' : 'text-error'">
              {{
                item.utilidad_display ||
                (item.profit >= 0 ? "+" : "") + formatCurrency(item.profit)
              }}
            </span>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
/* Estilos específicos del componente si es necesario */
</style>
