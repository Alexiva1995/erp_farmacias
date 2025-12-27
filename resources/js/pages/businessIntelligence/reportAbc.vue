<script setup>
import ReportAbcFilters from "@/components/ReportAbcFilters.vue";
import ReportAbcTable from "@/components/ReportAbcTable.vue";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";

// --- State ---
const loading = ref(false);
const products = ref([]);
const date = new Date();
const firstDay = new Date(date.getFullYear(), date.getMonth(), 1)
  .toISOString()
  .substr(0, 10);
const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0)
  .toISOString()
  .substr(0, 10);

const filters = ref({
  startDate: firstDay,
  endDate: lastDay,
  classification: null,
  coverage_range: null,
});

const abcOptions = [
  { title: "Clase A (Top 80%)", value: "A" },
  { title: "Clase B (Siguiente 15%)", value: "B" },
  { title: "Clase C (Último 5%)", value: "C" },
];

const coverageOptions = [
  { title: "Sin Movimiento (Stock Muerto)", value: "dead_stock" },
  { title: "Crítica (< 1 mes)", value: "critical" },
  { title: "Baja (1 - 2 meses)", value: "low" },
  { title: "Óptima (2 - 4 meses)", value: "optimal" },
  { title: "Exceso (> 4 meses)", value: "excess" },
];

// --- API Data Fetching ---
const fetchAbcData = async () => {
  loading.value = true;
  try {
    const params = {
      start_date: filters.value.startDate,
      end_date: filters.value.endDate,
      classification: filters.value.classification,
      coverage_range: filters.value.coverage_range,
    };

    // Clean params
    Object.keys(params).forEach(
      (key) => params[key] == null && delete params[key]
    );

    const response = await axios.get("finances/reports/abc-analysis", {
      params,
    });
    products.value = response.data.data;
  } catch (error) {
    console.error("Error fetching ABC report:", error);
  } finally {
    loading.value = false;
  }
};

// --- Watchers ---
let debounceTimer;
const debouncedFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchAbcData();
  }, 500);
};

// Watch for changes in filters
watch(
  filters,
  () => {
    debouncedFetch();
  },
  { deep: true }
);

// --- Lifecycle ---
onMounted(() => {
  fetchAbcData();
});

const handleClearFilters = () => {
  filters.value.startDate = firstDay;
  filters.value.endDate = lastDay;
  filters.value.classification = null;
  filters.value.coverage_range = null;
};

const handleUpdateFilters = (newFilters) => {
  filters.value = newFilters;
};
</script>

<template>
  <div>
    <ReportAbcFilters
      :filters="filters"
      :abc-options="abcOptions"
      :coverage-options="coverageOptions"
      @update:filters="handleUpdateFilters"
      @clear="handleClearFilters"
    />

    <ReportAbcTable :items="products" :loading="loading" />
  </div>
</template>
