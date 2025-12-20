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

  return data
    .map((e) => {
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
      };
    })
    .sort((a, b) => b.scores.total - a.scores.total); // Rank by total score
});
</script>

<template>
  <VContainer fluid>
    <div class="d-flex align-center justify-space-between mb-4">
      <h2 class="text-h4 font-weight-bold">Métricas de {{ monthTitle }}</h2>
      <div class="d-flex gap-4" style="max-width: 400px">
        <VSelect
          v-model="selectedMonth"
          :items="availableMonths"
          item-title="title"
          item-value="value"
          label="Mes"
          density="compact"
          hide-details
          class="me-2"
          style="width: 150px"
        />
        <VSelect
          v-model="selectedYear"
          :items="availableYears"
          label="Año"
          density="compact"
          hide-details
          style="width: 100px"
        />
      </div>
    </div>
    <EmployeeMonthTable :items="calculatedEmployees" />
  </VContainer>
</template>
