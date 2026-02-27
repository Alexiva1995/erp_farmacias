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

// Sort State
const selectedSort = ref({ key: "total", order: "desc" });
const isLocked = ref(false);

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
  selectedMonth.value = currentMonth;
  selectedYear.value = currentYear;
  selectedSort.value = { key: "total", order: "desc" };
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
  const data = employees.value;
  if (!data.length) return [];

  const processed = data.map((e) => {
    return {
      ...e,
      // Helper for sorting
      inventory_score: e.scores?.inventory || 0,
    };
  });

// Dynamic Sorting
  return processed.sort((a, b) => {
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
  <VContainer fluid>
    <!-- Header Summary / Leaderboard -->
    <VRow v-if="calculatedEmployees.length" class="mb-6">
      <VCol cols="12" md="4">
        <VCard color="warning" class="text-white h-100 d-flex align-center shadow-lg">
          <VCardText class="d-flex align-center gap-4 w-100">
            <VAvatar size="64" class="leader-podium-avatar" border="2px solid white">
              <VImg v-if="calculatedEmployees[0].photo" :src="calculatedEmployees[0].photo" />
              <div v-else class="text-h4">{{ calculatedEmployees[0].name.charAt(0) }}</div>
            </VAvatar>
            <div>
              <div class="text-overline opacity-80">Líder del Mes</div>
              <div class="text-h5 font-weight-black">{{ calculatedEmployees[0].name }} {{ calculatedEmployees[0].last_name }}</div>
              <div class="text-h4 font-weight-bold">{{ formatNumber(calculatedEmployees[0].scores.total) }} pts</div>
            </div>
            <VSpacer />
            <VIcon icon="tabler-trophy" size="48" class="opacity-30" />
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="8">
        <VCard class="h-100">
          <VCardText class="d-flex align-center h-100">
            <VRow class="w-100 text-center">
              <VCol cols="3">
                <div class="text-overline mb-1">Total Ventas</div>
                <div class="text-h6 font-weight-bold">{{ formatCurrency(calculatedEmployees.reduce((acc, e) => acc + e.sales, 0)) }}</div>
              </VCol>
              <VCol cols="3">
                <div class="text-overline mb-1">Crecimiento Prom.</div>
                <div class="text-h6 font-weight-bold text-success">{{ formatNumber(calculatedEmployees.reduce((acc, e) => acc + e.growth, 0) / calculatedEmployees.length) }}%</div>
              </VCol>
              <VCol cols="3">
                <div class="text-overline mb-1">Vencimientos</div>
                <div class="text-h6 font-weight-bold text-error">{{ calculatedEmployees.reduce((acc, e) => acc + e.expirations, 0) }}</div>
              </VCol>
              <VCol cols="3">
                <div class="text-overline mb-1">Conteos Inv.</div>
                <div class="text-h6 font-weight-bold text-info">{{ calculatedEmployees.reduce((acc, e) => acc + e.inventory_counted, 0) }}</div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-6 border">
      <VCardText class="pa-4">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div class="d-flex align-center gap-4 flex-grow-1 flex-md-grow-0">
            <VIcon icon="tabler-filter" color="secondary" />
            <span class="text-h6 font-weight-bold">Filtros de Análisis</span>
          </div>

          <div class="d-flex align-center flex-wrap gap-3 flex-grow-1 flex-md-grow-0">
            <div style="inline-size: 180px;">
              <AppSelect
                v-model="selectedMonth"
                :items="availableMonths"
                item-title="title"
                item-value="value"
                placeholder="Mes"
                prepend-inner-icon="tabler-calendar"
              />
            </div>
            <div style="inline-size: 140px;">
              <AppSelect
                v-model="selectedYear"
                :items="availableYears"
                placeholder="Año"
                prepend-inner-icon="tabler-calendar-event"
              />
            </div>
            
            <VBtn color="secondary" variant="tonal" @click="handleClear" icon="tabler-refresh" />
          </div>

          <VSpacer />

          <div class="d-flex align-center gap-3">
            <VBtn 
              v-if="!isLocked"
              color="error" 
              variant="elevated" 
              prepend-icon="tabler-lock"
              @click="handleLockMonth"
            >
              Cerrar Mes
            </VBtn>
            <VChip v-else color="success" variant="elevated" prepend-icon="tabler-lock-check">
              Mes Cerrado (Histórico)
            </VChip>

            <VMenu>
              <template #activator="{ props }">
                <VBtn v-bind="props" color="primary" variant="outlined" prepend-icon="tabler-sort-descending">
                  Ordenar por: {{ sortOptions.find(o => o.key === selectedSort.key)?.title }}
                </VBtn>
              </template>
              <VList>
                <VListItem
                  v-for="(option, index) in sortOptions"
                  :key="index"
                  @click="handleSortClick(option)"
                >
                  <template #prepend>
                    <VIcon :icon="option.icon" size="20" class="me-2" />
                  </template>
                  <VListItemTitle>{{ option.title }}</VListItemTitle>
                  <template #append>
                    <VIcon
                      v-if="selectedSort.key === option.key"
                      icon="tabler-check"
                      size="16"
                      color="primary"
                    />
                  </template>
                </VListItem>
              </VList>
            </VMenu>
          </div>
        </div>
      </VCardText>
    </VCard>
    <EmployeeMonthTable :items="calculatedEmployees" />
  </VContainer>
</template>
