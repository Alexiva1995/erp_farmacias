<script setup>
import EmployeeMonthFilters from "@/components/EmployeeMonthFilters.vue";
import EmployeeMonthTable from "@/components/EmployeeMonthTable.vue";
import axios from "axios";
import Swal from "sweetalert2";
import { computed, onMounted, ref, watch } from "vue";

const employees = ref([]);
const date = new Date();
const currentMonth = date.getMonth() + 1; // 1-12
const currentYear = date.getFullYear();

const selectedMonth = ref(currentMonth);
const selectedYear = ref(currentYear);

const searchQuery = ref("");
const selectedSort = ref({ key: "scores.total", order: "desc" });
const isLocked = ref(false);

const sortOptions = [
  {
    title: "Puntaje Total",
    icon: "tabler-trophy",
    key: "scores.total",
    order: "desc",
  },
  {
    title: "Ventas",
    icon: "tabler-currency-dollar",
    key: "sales",
    order: "desc",
  },
  {
    title: "Crecimiento",
    icon: "tabler-trending-up",
    key: "growth",
    order: "desc",
  },
  {
    title: "Inventario",
    icon: "tabler-package",
    key: "inventory_counted",
    order: "desc",
  },
  {
    title: "Vencimientos",
    icon: "tabler-calendar-off",
    key: "expirations",
    order: "asc",
  },
];

const handleSortClick = (option) => {
  selectedSort.value = { key: option.key, order: option.order };
};

const handleClear = () => {
  searchQuery.value = "";
  selectedMonth.value = currentMonth;
  selectedYear.value = currentYear;
  selectedSort.value = { key: "scores.total", order: "desc" };
};

// Generar lista de meses
const availableMonths = computed(() => {
  return [
    { value: 1, title: "Enero" },
    { value: 2, title: "Febrero" },
    { value: 3, title: "Marzo" },
    { value: 4, title: "Abril" },
    { value: 5, title: "Mayo" },
    { value: 6, title: "Junio" },
    { value: 7, title: "Julio" },
    { value: 8, title: "Agosto" },
    { value: 9, title: "Septiembre" },
    { value: 10, title: "Octubre" },
    { value: 11, title: "Noviembre" },
    { value: 12, title: "Diciembre" },
  ];
});

// Años: Actual y Anterior
const availableYears = computed(() => {
  return [currentYear, currentYear - 1];
});

const loading = ref(false);
const errorMessage = ref("");
const showErrorSnackbar = ref(false);

const fetchEmployees = async () => {
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await axios.get("/api/rrhh/employee-performance", {
      params: {
        month: selectedMonth.value,
        year: selectedYear.value,
      },
    });
    if (response.data && response.data.status) {
      employees.value = response.data.data;
      isLocked.value = response.data.data.some((e) => e.is_locked);
    } else {
      throw new Error(response.data?.message || "Error al obtener rendimiento de empleados");
    }
  } catch (error) {
    console.error("Error fetching employee performance:", error);
    errorMessage.value = error.response?.data?.message || error.message || "Error de conexión con el servidor";
    showErrorSnackbar.value = true;
  } finally {
    loading.value = false;
  }
};

const handleLockMonth = async () => {
  const monthTitle = availableMonths.value.find(
    (m) => m.value === selectedMonth.value,
  )?.title;

  const { isConfirmed } = await Swal.fire({
    title: "¿Cerrar este mes?",
    text: `Se bloquearán los puntajes de ${monthTitle} ${selectedYear.value}. Esta acción no se puede deshacer.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Sí, cerrar mes",
    cancelButtonText: "Cancelar",
    background: "#fff",
    showLoaderOnConfirm: true,
    preConfirm: async () => {
      try {
        const response = await axios.post(
          "/api/rrhh/employee-performance/lock",
          {
            month: selectedMonth.value,
            year: selectedYear.value,
          },
        );
        if (!response.data.status) {
          throw new Error(response.data.message || "Error al procesar");
        }
        return response.data;
      } catch (error) {
        Swal.showValidationMessage(
          `Error: ${error.response?.data?.message || error.message}`,
        );
      }
    },
    allowOutsideClick: () => !Swal.isLoading(),
  });

  if (isConfirmed) {
    await Swal.fire({
      title: "¡Mes Cerrado!",
      text: "Los datos han sido persistidos correctamente.",
      icon: "success",
      confirmButtonColor: "rgb(var(--v-theme-gradient-end))",
    });
    await fetchEmployees();
  }
};

watch([selectedMonth, selectedYear], () => {
  fetchEmployees();
});

onMounted(() => {
  fetchEmployees();
});

const calculatedEmployees = computed(() => {
  const filtered = employees.value.filter((e) => {
    if (!searchQuery.value) return true;
    const search = searchQuery.value.toLowerCase();
    return (
      e.name.toLowerCase().includes(search) ||
      e.last_name.toLowerCase().includes(search) ||
      (e.identification &&
        String(e.identification).toLowerCase().includes(search))
    );
  });

  return [...filtered].sort((a, b) => {
    const key = selectedSort.value.key;
    const order = selectedSort.value.order === "asc" ? 1 : -1;

    let valA, valB;

    if (key.includes(".")) {
      const keys = key.split(".");
      valA = a[keys[0]]?.[keys[1]] ?? 0;
      valB = b[keys[0]]?.[keys[1]] ?? 0;
    } else {
      valA = a[key] ?? 0;
      valB = b[key] ?? 0;
    }

    if (valA < valB) return -1 * order;
    if (valA > valB) return 1 * order;
    return 0;
  });
});

const formatNumber = (num) =>
  new Intl.NumberFormat("es-VE", { maximumFractionDigits: 2 }).format(num);

const formatCurrency = (amount) =>
  new Intl.NumberFormat("es-US", {
    style: "currency",
    currency: "USD",
  }).format(amount);
const statistics = computed(() => [
  {
    title: "Ventas",
    value: formatCurrency(
      calculatedEmployees.value.reduce((acc, e) => acc + (e.sales || 0), 0),
    ),
    icon: "tabler-currency-dollar",
    color: "primary",
    description: "Total facturado mes",
  },
  {
    title: "Crecimiento",
    value: `${formatNumber(calculatedEmployees.value.reduce((acc, e) => acc + (e.growth || 0), 0) / (calculatedEmployees.value.length || 1))}%`,
    icon: "tabler-trending-up",
    color: "success",
    description: "Promedio mensual",
  },
  {
    title: "Vencimientos",
    value: calculatedEmployees.value.reduce(
      (acc, e) => acc + (e.expirations || 0),
      0,
    ),
    icon: "tabler-calendar-off",
    color: "error",
    description: "Unidades detectadas",
  },
  {
    title: "Inventario",
    value: calculatedEmployees.value.reduce(
      (acc, e) => acc + (e.inventory_counted || 0),
      0,
    ),
    icon: "tabler-package",
    color: "info",
    description: "Conteos realizados",
  },
]);
</script>

<template>
  <div class="productivity-employee-month-page pb-12">
    <div class="d-flex flex-column gap-2 mt-2">
      <EmployeeMonthFilters
        v-model:searchQuery="searchQuery"
        v-model:selectedMonth="selectedMonth"
        v-model:selectedYear="selectedYear"
        :selected-sort="selectedSort"
        :available-months="availableMonths"
        :available-years="availableYears"
        :sort-options="sortOptions"
        :is-locked="isLocked"
        :loading="loading"
        @clear="handleClear"
        @lock-month="handleLockMonth"
        @sort="handleSortClick"
        class="ma-0 mb-0"
      />

      <!-- Skeletons durante Carga -->
      <VRow v-if="loading" class="ma-0 mt-4 mb-4 mx-n2">
        <VCol cols="12" md="4" class="pa-2">
          <VSkeletonLoader type="card" height="130" class="rounded-lg" />
        </VCol>
        <VCol v-for="n in 4" :key="n" cols="6" sm="6" md="2" class="pa-2">
          <VSkeletonLoader type="card" height="130" class="rounded-lg" />
        </VCol>
      </VRow>

      <!-- Header Summary / Leaderboard -->
      <VRow v-else-if="calculatedEmployees.length" class="ma-0 mt-4 mb-4 mx-n2">
        <!-- Card Lider -->
        <VCol cols="12" md="4" class="pa-2">
          <VCard
            class="stats-card leader-card h-100 overflow-hidden border shadow-sm position-relative ma-0"
          >
            <div
              class="card-bg-decoration"
              style="
                background: linear-gradient(
                  45deg,
                  rgba(var(--v-theme-warning), 0.15),
                  transparent
                );
              "
            ></div>
            <div
              class="premium-header-new pa-5 d-flex align-center gap-4 relative-content"
            >
              <VAvatar
                size="70"
                class="leader-avatar border-2 border-white shadow-lg"
              >
                <VImg
                  v-if="calculatedEmployees[0].photo"
                  :src="calculatedEmployees[0].photo"
                />
                <div v-else class="text-h4 font-weight-black text-white">
                  {{ calculatedEmployees[0].name.charAt(0) }}
                </div>
              </VAvatar>
              <div class="d-flex flex-column">
                <span
                  class="text-super-xs font-weight-black text-warning-darken-2 opacity-80 uppercase mb-1"
                  >Líder del Mes 🏆</span
                >
                <span
                  class="text-h5 font-weight-black text-high-emphasis leading-tight mb-1"
                >
                  {{ calculatedEmployees[0].name }}
                  {{ calculatedEmployees[0].last_name }}
                </span>
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-award" color="warning" size="20" />
                  <span class="text-h4 font-weight-black text-primary">
                    {{
                      formatNumber(calculatedEmployees[0].scores?.total || 0)
                    }}
                    <small class="text-h6 font-weight-bold">pts</small>
                  </span>
                </div>
              </div>
            </div>
            <div
              class="accent-border"
              style="background-color: rgb(var(--v-theme-warning)); opacity: 1"
            ></div>
          </VCard>
        </VCol>

        <!-- Cards de Estadísticas Individuales -->
        <VCol
          v-for="item in statistics"
          :key="item.title"
          cols="6"
          sm="6"
          md="2"
          class="pa-2"
        >
          <VCard class="stats-card h-100 border-0 overflow-hidden ma-0">
            <div
              class="card-bg-decoration"
              :style="{
                background: `linear-gradient(45deg, rgba(var(--v-theme-${item.color}), 0.1), transparent)`,
              }"
            ></div>
            <VCardText class="pa-5 relative-content d-flex flex-column h-100">
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar
                  :color="item.color"
                  variant="tonal"
                  size="44"
                  rounded="lg"
                >
                  <VIcon :icon="item.icon" size="24" />
                </VAvatar>
                <div class="text-right">
                  <span
                    class="text-overline font-weight-bold text-disabled leading-none d-block mb-1"
                    style="
                      letter-spacing: 0.5px !important;
                      font-size: 0.65rem !important;
                    "
                    >{{ item.title }}</span
                  >
                  <h4 class="text-h6 font-weight-black leading-none">
                    {{ item.value }}
                  </h4>
                </div>
              </div>
              <VSpacer />
              <VDivider class="mb-3 opacity-10" />
              <div class="d-flex align-center justify-space-between">
                <span
                  class="text-super-xs font-weight-medium text-medium-emphasis uppercase"
                  >{{ item.description }}</span
                >
                <VIcon
                  icon="tabler-trending-up"
                  size="14"
                  :color="item.color"
                  class="opacity-50"
                />
              </div>
            </VCardText>
            <div
              class="accent-border"
              :style="{ backgroundColor: `rgb(var(--v-theme-${item.color}))` }"
            ></div>
          </VCard>
        </VCol>
      </VRow>

      <EmployeeMonthTable :items="calculatedEmployees" :loading="loading" />

      <!-- Toast de Error -->
      <VSnackbar
        v-model="showErrorSnackbar"
        color="error"
        location="top right"
        timeout="5000"
      >
        {{ errorMessage }}
        <template #actions>
          <VBtn color="white" variant="text" @click="showErrorSnackbar = false">
            Cerrar
          </VBtn>
        </template>
      </VSnackbar>
    </div>
  </div>
</template>

<style scoped>
.productivity-employee-month-page {
  background-color: rgb(var(--v-theme-background));
  min-block-size: 100vh;
}

.stats-card {
  border-radius: 12px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 0.9) !important;
  box-shadow: 0 4px 12px rgba(var(--v-shadow-key-umbra-color), 0.05) !important;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stats-card:hover {
  box-shadow: 0 8px 24px rgba(var(--v-shadow-key-umbra-color), 0.1) !important;
  transform: translateY(-4px);
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 120px;
  filter: blur(45px);
  inline-size: 120px;
  inset-block-start: -30px;
  inset-inline-end: -30px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 60%;
  border-end-end-radius: 4px;
  border-start-end-radius: 4px;
  inline-size: 4px;
  inset-block-start: 20%;
  inset-inline-start: 0;
  opacity: 0.8;
}

.leader-avatar {
  background-color: white !important;
  padding: 2px;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: normal;
}
</style>
