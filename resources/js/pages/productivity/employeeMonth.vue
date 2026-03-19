<script setup>
import EmployeeMonthTable from "@/components/EmployeeMonthTable.vue";
import axios from "axios";
import { computed, onMounted, ref, watch } from "vue";

const employees = ref([]);
const date = new Date();
const currentMonth = date.getMonth() + 1; // 1-12
const currentYear = date.getFullYear();

const selectedMonth = ref(currentMonth);
const selectedYear = ref(currentYear);

const searchQuery = ref("");
const selectedSort = ref({ key: "total", order: "desc" });
const isLocked = ref(false);
const isFiltersVisible = ref(false);

const sortOptions = [
  {
    title: "Puntaje Total",
    icon: "tabler-trophy",
    key: "total",
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
    key: "inventory_score",
    order: "desc",
  },
  {
    title: "Vencimientos",
    icon: "tabler-calendar-off",
    key: "scores.expiration", // Sort by score to reward fewer expirations
    order: "desc",
  },
];

const handleSortClick = (option) => {
  selectedSort.value = { key: option.key, order: option.order };
};

const handleClear = () => {
  searchQuery.value = "";
  selectedMonth.value = currentMonth;
  selectedYear.value = currentYear;
  selectedSort.value = { key: "total", order: "desc" };
  isFiltersVisible.value = false;
};

// Generate list of months (e.g., current year + previous year)
const availableMonths = computed(() => {
  const months = [
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
  return months;
});

// Years: Current and Previous
const availableYears = computed(() => {
  return [currentYear, currentYear - 1];
});

const monthTitle = computed(() => {
  const m = availableMonths.value.find((m) => m.value === selectedMonth.value);
  return m ? `${m.title} ${selectedYear.value}` : "";
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
  try {
    const response = await axios.post("/api/rrhh/employee-performance/lock", {
      month: selectedMonth.value,
      year: selectedYear.value,
    });
    if (response.data && response.data.status) {
      await fetchEmployees();
    }
  } catch (error) {
    console.error("Error locking month:", error);
  }
};

watch([selectedMonth, selectedYear], () => {
  fetchEmployees();
});

onMounted(() => {
  fetchEmployees();
});

const calculatedEmployees = computed(() => {
// Filter and Dynamic Sorting
  const filtered = processed.filter((e) => {
    if (!searchQuery.value) return true;
    const search = searchQuery.value.toLowerCase();
    return (
      e.name.toLowerCase().includes(search) ||
      e.last_name.toLowerCase().includes(search) ||
      (e.identification && e.identification.toLowerCase().includes(search))
    );
  });

  return filtered.sort((a, b) => {
    const key = selectedSort.value.key;
    const order = selectedSort.value.order === "asc" ? 1 : -1;

    let valA, valB;

    if (key.includes(".")) {
      const keys = key.split(".");
      valA = a[keys[0]][keys[1]];
      valB = b[keys[0]][keys[1]];
    } else {
      valA = a[key];
      valB = b[key];
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
        <VCard class="leader-card h-100 overflow-hidden border-0 shadow-lg position-relative">
          <div class="premium-header pa-5 d-flex align-center gap-4">
            <VAvatar size="70" class="leader-avatar border-2 border-white shadow-lg">
              <VImg v-if="calculatedEmployees[0].photo" :src="calculatedEmployees[0].photo" />
              <div v-else class="text-h4 font-weight-black">{{ calculatedEmployees[0].name.charAt(0) }}</div>
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-super-xs font-weight-black text-white opacity-80 uppercase mb-1">Líder del Mes</span>
              <span class="text-h5 font-weight-black text-white leading-tight mb-1">
                {{ calculatedEmployees[0].name }} {{ calculatedEmployees[0].last_name }}
              </span>
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-award" color="warning" size="20" />
                <span class="text-h4 font-weight-black text-white">{{ formatNumber(calculatedEmployees[0].scores.total) }} <small class="text-h6 font-weight-bold">pts</small></span>
              </div>
            </div>
            <VIcon icon="tabler-trophy" size="64" class="position-absolute opacity-10" style="inset-block-end: -10px; inset-inline-end: -10px;" />
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" md="8">
        <VCard class="h-100 border-0 shadow-sm rounded-xl overflow-hidden">
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

    <!-- Filtros Colapsables -->
    <VCard class="rounded-xl border-0 shadow-sm mb-6 overflow-hidden">
      <VCardText class="pa-4">
        <div class="d-flex flex-wrap align-center gap-3">
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar empleado..."
            prepend-inner-icon="tabler-search"
            class="flex-grow-1 premium-input-compact"
            density="compact"
            hide-details
            clearable
            style="min-inline-size: 200px;"
          />

          <div class="d-flex gap-2 flex-grow-1 flex-sm-grow-0 justify-sm-end">
            <VBtn
              :color="isFiltersVisible ? 'primary' : 'secondary'"
              variant="tonal"
              class="rounded-lg px-4 font-weight-black flex-grow-1 flex-sm-grow-0"
              @click="isFiltersVisible = !isFiltersVisible"
            >
              <VIcon start icon="tabler-filter" size="18" />
              <span class="d-none d-sm-inline">FILTROS</span>
              <VIcon end :icon="isFiltersVisible ? 'tabler-chevron-up' : 'tabler-chevron-down'" size="16" />
            </VBtn>

            <VBtn
              v-if="!isLocked"
              color="error"
              variant="flat"
              class="rounded-lg px-4 font-weight-black shadow-sm flex-grow-1 flex-sm-grow-0"
              @click="handleLockMonth"
            >
              <VIcon start icon="tabler-lock" size="18" />
              <span class="d-none d-sm-inline">CERRAR MES</span>
              <VIcon icon="tabler-lock" size="18" class="d-sm-none" />
            </VBtn>
            <VChip v-else color="success" variant="elevated" class="rounded-lg px-4 h-38 font-weight-black" prepend-icon="tabler-lock-check">
              HISTÓRICO
            </VChip>
          </div>
        </div>

        <VExpandTransition>
          <div v-show="isFiltersVisible">
            <VDivider class="my-4 border-dashed opacity-30" />
            <VRow>
              <VCol cols="12" sm="4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Periodo (Mes)</span>
                <AppSelect
                  v-model="selectedMonth"
                  :items="availableMonths"
                  item-title="title"
                  item-value="value"
                  placeholder="Mes"
                  prepend-inner-icon="tabler-calendar"
                  density="compact"
                  hide-details
                  class="premium-input-compact"
                />
              </VCol>
              
              <VCol cols="12" sm="4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Periodo (Año)</span>
                <AppSelect
                  v-model="selectedYear"
                  :items="availableYears"
                  placeholder="Año"
                  prepend-inner-icon="tabler-calendar-event"
                  density="compact"
                  hide-details
                  class="premium-input-compact"
                />
              </VCol>

              <VCol cols="12" sm="4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Criterio de Ordenamiento</span>
                <div class="d-flex align-center gap-2">
                  <VMenu>
                    <template #activator="{ props: menuProps }">
                      <VBtn v-bind="menuProps" variant="outlined" color="primary" density="compact" class="rounded-lg flex-grow-1 h-38 font-weight-black">
                        {{ sortOptions.find(o => o.key === selectedSort.key)?.title }}
                        <VIcon end icon="tabler-chevron-down" size="16" />
                      </VBtn>
                    </template>
                    <VList density="compact" class="rounded-lg py-1 border shadow-lg">
                      <VListItem
                        v-for="(option, index) in sortOptions"
                        :key="index"
                        :class="{ 'bg-primary-lighten-5': selectedSort.key === option.key }"
                        @click="handleSortClick(option)"
                      >
                        <template #prepend>
                          <VIcon :icon="option.icon" size="18" class="me-2" />
                        </template>
                        <VListItemTitle class="text-xs font-weight-bold">{{ option.title }}</VListItemTitle>
                        <template #append>
                          <VIcon v-if="selectedSort.key === option.key" icon="tabler-check" size="14" color="primary" />
                        </template>
                      </VListItem>
                    </VList>
                  </VMenu>
                </div>
              </VCol>
            </VRow>

            <div class="d-flex justify-end mt-4">
              <VBtn
                variant="text"
                color="secondary"
                size="small"
                class="font-weight-black"
                @click="handleClear"
              >
                LIMPIAR FILTROS
              </VBtn>
            </div>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

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
