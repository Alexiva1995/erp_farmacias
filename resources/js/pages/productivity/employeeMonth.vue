<script setup>
import EmployeeMonthTable from "@/components/EmployeeMonthTable.vue";
import EmployeeMonthFilters from "@/components/EmployeeMonthFilters.vue";
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

const fetchEmployees = async () => {
  try {
    const response = await axios.get("/api/rrhh/employee-performance", {
      params: {
        month: selectedMonth.value,
        year: selectedYear.value,
      },
    });
    if (response.data && response.data.status) {
      employees.value = response.data.data;
      isLocked.value = response.data.data.some(e => e.is_locked);
    }
  } catch (error) {
    console.error("Error fetching employee performance:", error);
  }
};

const handleLockMonth = async () => {
  const monthTitle = availableMonths.value.find(m => m.value === selectedMonth.value)?.title;

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
        const response = await axios.post("/api/rrhh/employee-performance/lock", {
          month: selectedMonth.value,
          year: selectedYear.value,
        });
        if (!response.data.status) {
          throw new Error(response.data.message || "Error al procesar");
        }
        return response.data;
      } catch (error) {
        Swal.showValidationMessage(`Error: ${error.response?.data?.message || error.message}`);
      }
    },
    allowOutsideClick: () => !Swal.isLoading(),
  });

  if (isConfirmed) {
    await Swal.fire({
      title: "¡Mes Cerrado!",
      text: "Los datos han sido persistidos correctamente.",
      icon: "success",
      confirmButtonColor: "#1e5128",
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
      (e.identification && String(e.identification).toLowerCase().includes(search))
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
</script>

<template>
  <VContainer fluid class="productivity-employee-month-page pa-4">
    <!-- Header Summary / Leaderboard -->
    <VRow v-if="calculatedEmployees.length" class="mb-6" dense>
      <VCol cols="12" md="4">
        <VCard class="leader-card h-100 overflow-hidden border shadow-lg position-relative rounded-lg">
          <div class="premium-header pa-5 d-flex align-center gap-4">
            <VAvatar size="70" class="leader-avatar border-2 border-white shadow-lg">
              <VImg v-if="calculatedEmployees[0].photo" :src="calculatedEmployees[0].photo" />
              <div v-else class="text-h4 font-weight-black text-white">{{ calculatedEmployees[0].name.charAt(0) }}</div>
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-super-xs font-weight-black text-white opacity-80 uppercase mb-1">Líder del Mes</span>
              <span class="text-h5 font-weight-black text-white leading-tight mb-1">
                {{ calculatedEmployees[0].name }} {{ calculatedEmployees[0].last_name }}
              </span>
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-award" color="warning" size="20" />
                <span class="text-h4 font-weight-black text-white">
                  {{ formatNumber(calculatedEmployees[0].scores?.total || 0) }} 
                  <small class="text-h6 font-weight-bold">pts</small>
                </span>
              </div>
            </div>
            <VIcon icon="tabler-trophy" size="64" class="position-absolute opacity-10" style="inset-block-end: -10px; inset-inline-end: -10px;" />
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" md="8">
        <VCard class="h-100 border shadow-sm rounded-lg overflow-hidden">
          <VCardText class="d-flex align-center h-100 pa-6 bg-surface-variant-light">
            <VRow class="w-100 text-center align-center">
              <VCol cols="6" sm="3">
                <div class="d-flex flex-column align-center">
                  <VAvatar color="primary" variant="tonal" size="40" class="mb-2">
                    <VIcon icon="tabler-currency-dollar" size="20" />
                  </VAvatar>
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Ventas</span>
                  <span class="text-h6 font-weight-black">{{ formatCurrency(calculatedEmployees.reduce((acc, e) => acc + (e.sales || 0), 0)) }}</span>
                </div>
              </VCol>
              <VCol cols="6" sm="3">
                <div class="d-flex flex-column align-center">
                  <VAvatar color="success" variant="tonal" size="40" class="mb-2">
                    <VIcon icon="tabler-trending-up" size="20" />
                  </VAvatar>
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Crecimiento</span>
                  <span class="text-h6 font-weight-black text-success">{{ formatNumber(calculatedEmployees.reduce((acc, e) => acc + (e.growth || 0), 0) / (calculatedEmployees.length || 1)) }}%</span>
                </div>
              </VCol>
              <VCol cols="6" sm="3">
                <div class="d-flex flex-column align-center">
                  <VAvatar color="error" variant="tonal" size="40" class="mb-2">
                    <VIcon icon="tabler-calendar-off" size="20" />
                  </VAvatar>
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Vencimientos</span>
                  <span class="text-h6 font-weight-black text-error">{{ calculatedEmployees.reduce((acc, e) => acc + (e.expirations || 0), 0) }}</span>
                </div>
              </VCol>
              <VCol cols="6" sm="3">
                <div class="d-flex flex-column align-center">
                  <VAvatar color="info" variant="tonal" size="40" class="mb-2">
                    <VIcon icon="tabler-package" size="20" />
                  </VAvatar>
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Inventario</span>
                  <span class="text-h6 font-weight-black text-info">{{ calculatedEmployees.reduce((acc, e) => acc + (e.inventory_counted || 0), 0) }}</span>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <EmployeeMonthFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedMonth="selectedMonth"
      v-model:selectedYear="selectedYear"
      :selected-sort="selectedSort"
      :available-months="availableMonths"
      :available-years="availableYears"
      :sort-options="sortOptions"
      :is-locked="isLocked"
      @clear="handleClear"
      @lock-month="handleLockMonth"
      @sort="handleSortClick"
    />

    <EmployeeMonthTable :items="calculatedEmployees" />
  </VContainer>
</template>

<style scoped>
.productivity-employee-month-page {
  background-color: rgb(var(--v-theme-background));
  min-block-size: 100vh;
}

.premium-header {
  background: linear-gradient(135deg, rgb(var(--v-theme-warning)) 0%, #ff8c00 100%);
}

.leader-avatar {
  background-color: white !important;
  padding: 2px;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.h-38 {
  block-size: 38px !important;
}

.border-dashed {
  border-style: dashed !important;
}

:deep(.premium-input-compact) {
  .v-field__input {
    font-size: 0.8125rem !important;
    min-block-size: 38px !important;
    padding-block: 0 !important;
  }
}

:deep(.premium-select-compact) {
  .v-field__input {
    font-size: 0.8125rem !important;
    min-block-size: 38px !important;
    padding-block: 0 !important;
  }
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.03);
}

.leader-card {
  transition: transform 0.3s ease;
}

.leader-card:hover {
  transform: translateY(-4px);
}
</style>
