<script setup>
import ReportAbcFilters from "@/components/ReportAbcFilters.vue";
import ReportAbcTable from "@/components/ReportAbcTable.vue";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";

// --- State ---
const loading = ref(false);
const products = ref([]);
const filters = ref({
  startDate: null,
  endDate: null,
  classification: null,
});

const abcOptions = [
  { title: "Clase A (Top 80%)", value: "A" },
  { title: "Clase B (Siguiente 15%)", value: "B" },
  { title: "Clase C (Último 5%)", value: "C" },
];

// --- API Data Fetching ---
const fetchAbcData = async () => {
  loading.value = true;
  try {
    const params = {
      start_date: filters.value.startDate,
      end_date: filters.value.endDate,
      classification: filters.value.classification,
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
  filters.value.startDate = null;
  filters.value.endDate = null;
  filters.value.classification = null;
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
      @update:filters="handleUpdateFilters"
      @clear="handleClearFilters"
    />

    <ReportAbcTable :items="products" :loading="loading" />
  </div>
</template>
