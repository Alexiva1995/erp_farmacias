<script setup>
import EmployeeMonthTable from "@/components/EmployeeMonthTable.vue";
import axios from "axios";
import { computed, onMounted, ref } from "vue";

const employees = ref([]);

const fetchEmployees = async () => {
  try {
    const response = await axios.get("/api/rrhh/employee-performance");
    if (response.data && response.data.status) {
      employees.value = response.data.data;
    }
  } catch (error) {
    console.error("Error fetching employee performance:", error);
  }
};

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
    <EmployeeMonthTable :items="calculatedEmployees" />
  </VContainer>
</template>
