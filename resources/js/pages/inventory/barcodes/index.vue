<script setup>
import PendingProductsFilters from "@/components/PendingProductsFilters.vue";
import PendingProductsTable from "@/components/PendingProductsTable.vue";
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

const productWithError = ref(null);
const searchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);

const laboratories = ref([]);
const origins = ref([]);
const suppliers = ref([]);
const categories = ref([]);

const isLoadingFilters = ref(false);

const handleUpdateProduct = async ({ id, barcode }) => {
  if (productWithError.value === id) {
    productWithError.value = null;
  }

  try {
    await axios.patch(`/products/pending/${id}`, { barcode });
    toast.success("Se registró el código de barra en el producto");
    await fetchProducts();
  } catch (err) {
    toast.error("Error al actualizar");

    if (err.response.status === 422) {
      productWithError.value = id;
    }
  }
};

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse, categoryResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/categories"),
    ]);
    console.log("laboratories response:", labResponse);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
    categories.value = categoryResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/products/pending", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
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
    selectedOrigin,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

watch([searchQuery, selectedLaboratory, selectedOrigin], () => {
  page.value = 1;
});

onMounted(async () => {
  fetchSelectOptions();
  fetchProducts();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
};
</script>

<template>
  <div>
    <PendingProductsFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
    />

    <PendingProductsTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      :product-with-error="productWithError"
      @update:options="updateTableOptions"
      @update-product="handleUpdateProduct"
    />
  </div>
</template>
