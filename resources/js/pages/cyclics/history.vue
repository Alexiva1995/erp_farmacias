<script setup>
import InventoryCycleFilters from "@/components/InventoryCycleFilters.vue";
import ProductHistoryTable from "@/components/ProductHistoryTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const searchQuery = ref("");
const selectedLaboratory = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const laboratories = ref([]);
const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const labResponse = await axios.get("/laboratories");
    laboratories.value = labResponse.data.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
    laboratories.value = [];
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchProducts = async () => {
  loading.value = true;

  const params = {
    history: true,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    startDate: startDate.value,
    endDate: endDate.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/products/count", {
      params,
    });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener el historial de conteos:", error);
    toast.error("No se pudo cargar el historial.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    searchQuery,
    selectedLaboratory,
    startDate,
    endDate,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

watch([searchQuery, selectedLaboratory, startDate, endDate], () => {
  page.value = 1;
});

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  } else {
    sortBy.value = undefined;
    orderBy.value = undefined;
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  startDate.value = null;
  endDate.value = null;
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};
</script>

<template>
  <div>
    <InventoryCycleFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      :laboratories="laboratories"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @sort="handleSort"
    />

    <ProductHistoryTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
    />
  </div>
</template>
