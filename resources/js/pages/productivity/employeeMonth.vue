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
    }
  } catch (error) {
    console.error("Error fetching employee performance:", error);
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

  /* Leaders (Max Values) */
  const maxSales = Math.max(...data.map((e) => e.sales)) || 1;
  const maxGrowth = Math.max(...data.map((e) => e.growth)) || 1;
  const maxExpirations = Math.max(...data.map((e) => e.expirations)) || 1;
  const maxInventoryCount =
    Math.max(...data.map((e) => e.inventory_counted)) || 1;
  const maxPremium = Math.max(...data.map((e) => e.premium_products)) || 1;
  const maxCleaningCompleted =
    Math.max(...data.map((e) => e.cleaning_completed)) || 1;
  const maxStrategy = Math.max(...data.map((e) => e.strategy_sales)) || 1;

  const processed = data.map((e) => {
    /* SCORING LOGIC */
    const salesScore = (e.sales / maxSales) * 25;
    const growthScore = (e.growth / maxGrowth) * 15;
    const expirationScore = (e.expirations / maxExpirations) * 15;

    // Inventory
    const inventoryBase = (e.inventory_counted / maxInventoryCount) * 10;
    const inventoryPenalty = e.inventory_errors * 0.01;
    const inventoryScore = Math.max(0, inventoryBase - inventoryPenalty);

    const premiumScore = (e.premium_products / maxPremium) * 10;
    const invoiceScore =
      (e.score_loaded || 0) +
      (e.score_registered || 0) +
      (e.score_ordered || 0);
    const cleaningScore = (e.cleaning_completed / maxCleaningCompleted) * 5;
    const strategyScore = (e.strategy_sales / maxStrategy) * 5;

    const totalScore =
      salesScore +
      growthScore +
      expirationScore +
      inventoryScore +
      premiumScore +
      invoiceScore +
      cleaningScore +
      strategyScore;

    return {
      ...e,
      scores: {
        sales: salesScore,
        growth: growthScore,
        expiration: expirationScore,
        inventory: inventoryScore,
        premium: premiumScore,
        invoice: invoiceScore,
        cleaning: cleaningScore,
        strategy: strategyScore,
        total: totalScore,
      },
      // Helper for sorting
      inventory_score: inventoryScore,
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
</script>

<template>
  <VContainer fluid>
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" sm="6" md="6">
            <AppSelect
              v-model="selectedMonth"
              :items="availableMonths"
              item-title="title"
              item-value="value"
              placeholder="Seleccionar Mes"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <AppSelect
              v-model="selectedYear"
              :items="availableYears"
              placeholder="Seleccionar Año"
              clearable
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
        <VBtn color="secondary" variant="outlined" @click="handleClear">
          Limpiar Filtros
        </VBtn>

        <VMenu>
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

        <VChip
          v-if="selectedSort.key !== 'total'"
          color="primary"
          variant="tonal"
          size="small"
          closable
          @click="selectedSort = { key: 'total', order: 'desc' }"
        >
          <VIcon icon="tabler-sort-descending" size="14" class="me-1" />
          Orden Personalizado
        </VChip>
      </VCardActions>
    </VCard>
    <EmployeeMonthTable :items="calculatedEmployees" />
  </VContainer>
</template>
