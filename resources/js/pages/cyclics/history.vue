<!-- pages/InventoryHistory.vue -->
<script setup>
import InventoryCycleFilters from "@/components/InventoryCycleFilters.vue";
import ProductHistoryTable from "@/components/ProductHistoryTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

// --- Estado para la tabla y paginación (sin cambios) ---
const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

// --- Estado para los filtros (sin cambios) ---
const searchQuery = ref("");
const selectedLaboratory = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const laboratories = ref([]);
const isLoadingFilters = ref(false);

// --- Cargar datos para los selects de los filtros (sin cambios) ---
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

// --- Actualizar la llamada a la API para usar el mismo endpoint ---
const fetchProducts = async () => {
  loading.value = true;

  // --- CAMBIO CLAVE 1: Añadimos el parámetro 'history' ---
  const params = {
    history: true, // ¡Este parámetro le dice al backend que queremos el historial!
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    startDate: startDate.value,
    endDate: endDate.value,
  };

  // Limpiar parámetros nulos o vacíos
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    // --- CAMBIO CLAVE 2: Apuntamos al endpoint original de conteos ---
    const response = await axios.get("/products/count", {
      params, // El objeto params ahora contiene `history: true`
    });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
    console.log(products.value);
  } catch (error) {
    console.error("Hubo un error al obtener el historial de conteos:", error);
    toast.error("No se pudo cargar el historial.");
  } finally {
    loading.value = false;
  }
};

// --- Observadores para recargar datos (sin cambios) ---
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

// --- Lógica de la tabla (sin cambios) ---
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

// --- Manejadores para los eventos del componente de filtros (sin cambios) ---
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

// Como eliminamos el botón de exportación, ya no necesitamos este manejador.
// const handleExport = async (format) => { ... };
</script>

<template>
  <div>
    <!-- No se necesita ningún cambio en el template -->
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
