<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref } from "vue";

// Estado reactivo
const loading = ref(false);
const loadingSummary = ref(false);
const loadingDetails = ref(false);

// Datos
const summary = ref({});
const details = ref({});
const sales = ref([]);
const expenses = ref([]);

// Filtros
const startDate = ref(null);
const endDate = ref(null);

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

// Cargar resumen
const loadSummary = async () => {
  loadingSummary.value = true;
  try {
    const params = new URLSearchParams();
    if (startDate.value) params.append("start_date", startDate.value);
    if (endDate.value) params.append("end_date", endDate.value);

    const response = await axios.get(
      `http://127.0.0.1:8000/api/finances/income-statement/summary?${params}`
    );

    if (response.data && response.data.success) {
      summary.value = response.data.data;
    } else {
      toast.error(
        "Error al cargar el resumen: " +
          (response.data?.message || "Error desconocido")
      );
    }
  } catch (error) {
    console.error("Error al cargar resumen:", error);
    if (error.response?.status === 401) {
      toast.error("No tienes autorización para acceder a esta información");
    } else if (error.response?.status === 500) {
      toast.error(
        "Error del servidor: " +
          (error.response.data?.message || "Error interno")
      );
    } else {
      toast.error("Error al cargar el resumen del estado de resultados");
    }
  } finally {
    loadingSummary.value = false;
  }
};

// Cargar detalles
const loadDetails = async () => {
  loadingDetails.value = true;
  try {
    const params = new URLSearchParams();
    if (startDate.value) params.append("start_date", startDate.value);
    if (endDate.value) params.append("end_date", endDate.value);

    const response = await axios.get(
      `http://127.0.0.1:8000/api/finances/income-statement/details?${params}`
    );

    if (response.data && response.data.success) {
      details.value = response.data.data;
      sales.value = response.data.data.sales || [];
      expenses.value = response.data.data.expenses || [];
    } else {
      toast.error(
        "Error al cargar los detalles: " +
          (response.data?.message || "Error desconocido")
      );
    }
  } catch (error) {
    console.error("Error al cargar detalles:", error);
    if (error.response?.status === 401) {
      toast.error("No tienes autorización para acceder a esta información");
    } else if (error.response?.status === 500) {
      toast.error(
        "Error del servidor: " +
          (error.response.data?.message || "Error interno")
      );
    } else {
      toast.error("Error al cargar los detalles del estado de resultados");
    }
  } finally {
    loadingDetails.value = false;
  }
};

// Cargar todos los datos
const loadData = async () => {
  await Promise.all([loadSummary(), loadDetails()]);
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

// Computed para datos combinados
const allTransactions = computed(() => {
  return [...sales.value, ...expenses.value].sort(
    (a, b) => new Date(b.date) - new Date(a.date)
  );
});

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
    const response = await axios.get("/api/user");
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
  loadData();
});
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold">Estado de Resultados</h1>
        <p class="text-body-1 text-medium-emphasis">
          Visualización completa de ingresos, costos, gastos y utilidad neta
        </p>
      </div>
    </div>

    <!-- Filtros -->
    <VCard class="mb-6">
      <VCardText>
        <h6 class="text-h6 mb-3 text-primary">Filtros</h6>

        <!-- Filtros rápidos en una línea horizontal -->
        <VRow class="mb-4">
          <VCol cols="12">
            <div class="d-flex gap-2 flex-wrap">
              <VBtn
                v-for="filter in [
                  { label: 'Todo el tiempo', value: 'all' },
                  { label: 'Últimos 15 días', value: 15 },
                  { label: 'Últimos 30 días', value: 30 },
                  { label: 'Últimos 60 días', value: 60 },
                  { label: 'Últimos 90 días', value: 90 },
                  { label: 'Mes actual', value: 'current_month' },
                  { label: 'Mes anterior', value: 'last_month' },
                ]"
                :key="filter.value"
                size="small"
                variant="outlined"
                @click="setQuickFilter(filter.value)"
              >
                {{ filter.label }}
              </VBtn>
            </div>
          </VCol>
        </VRow>

        <!-- Campos de fecha con botón aplicar -->
        <VRow>
          <VCol cols="12" sm="6" md="3">
            <AppDateTimePicker
              v-model="startDate"
              placeholder="Fecha Inicio"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <AppDateTimePicker
              v-model="endDate"
              placeholder="Fecha Fin"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VBtn
              color="primary"
              @click="applyFilters"
              :loading="loadingSummary || loadingDetails"
              block
            >
              Aplicar Filtros
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VBtn
              color="secondary"
              variant="outlined"
              @click="clearFilters"
              block
            >
              Limpiar Filtros
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Resumen (4 cuadritos) -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VIcon
                icon="mdi-currency-usd"
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

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VIcon
                icon="mdi-package-variant"
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

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VIcon
                icon="mdi-chart-line"
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
                    ? 'mdi-trending-up'
                    : 'mdi-trending-down'
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
            {{ allTransactions.length }} registros
          </VChip>
        </div>
      </VCardTitle>

      <VCardText>
        <VDataTable
          :headers="headers"
          :items="allTransactions"
          :loading="loadingDetails"
          item-key="id"
          class="elevation-1"
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
              {{ item.type === "sale" ? "+" : "-"
              }}{{ formatCurrency(item.amount) }}
            </span>
          </template>

          <template #item.costs="{ item }">
            {{ formatCurrency(item.costs) }}
          </template>

          <template #item.profit="{ item }">
            <span :class="item.profit >= 0 ? 'text-success' : 'text-error'">
              {{ item.profit >= 0 ? "+" : "" }}{{ formatCurrency(item.profit) }}
            </span>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
/* Estilos específicos del componente si es necesario */
</style>
