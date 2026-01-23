<script setup>
import IncompleteProductsTable from "@/components/IncompleteProductsTable.vue";
import ProductFilters from "@/components/ProductFilters.vue";
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
const errorMessage = ref("");
const searchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const isStrictSearch = ref(false);

const laboratories = ref([]);
const origins = ref([]);
const suppliers = ref([]);
const categories = ref([]);

const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse, categoryResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/categories"),
    ]);
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

const getSuccessMessage = (payload) => {
  const updatedFields = [];
  if (payload.barcode) updatedFields.push("código de barras");
  if (payload.laboratory_id) updatedFields.push("laboratorio");
  if (payload.origin_id) updatedFields.push("origen");

  if (updatedFields.length === 3) {
    return "Datos faltantes asignados";
  } else if (updatedFields.length === 1) {
    return `Se asignó el ${updatedFields[0]} al producto`;
  } else if (updatedFields.length === 2) {
    return `Se asignaron ${updatedFields.join(" y ")}`;
  }
  return "Producto actualizado";
};

const handleUpdateProduct = async (payload) => {
  if (productWithError.value === payload.id) {
    productWithError.value = null;
    errorMessage.value = "";
  }

  try {
    await axios.patch(`/products/incomplete/${payload.id}`, payload);
    toast.success(getSuccessMessage(payload));
    await fetchProducts();
  } catch (err) {
    toast.error("Error al actualizar");

    if (err.response?.status === 422) {
      productWithError.value = payload.id;
      const errors = err.response.data?.errors;
      if (errors?.barcode && errors.barcode[0]) {
        errorMessage.value = errors.barcode[0];
      } else {
        errorMessage.value = "Error al actualizar el producto";
      }
    }
  }
};

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    startDate: startDate.value,
    endDate: endDate.value,
    isStrictSearch: isStrictSearch.value,
    hasStock: stockStatusFilter.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/products/incomplete", { params });
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
    stockStatusFilter,
    startDate,
    endDate,
    isStrictSearch,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true },
);

watch(
  [
    searchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    startDate,
    endDate,
  ],
  () => {
    page.value = 1;
  },
);

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
  stockStatusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
  isStrictSearch.value = false;
};

const handleSort = (sortOptions) => {
  if (sortOptions.key === undefined && sortOptions.order === undefined) {
    sortBy.value = undefined;
    orderBy.value = undefined;
  } else {
    sortBy.value = sortOptions.key;
    orderBy.value = sortOptions.order;
  }
};

const handleLaboratoryCreated = (newLaboratory) => {
  laboratories.value.push(newLaboratory);
  laboratories.value.sort((a, b) => a.name.localeCompare(b.name));
};

const handleOriginCreated = (newOrigin) => {
  origins.value.push(newOrigin);
  origins.value.sort((a, b) => a.name.localeCompare(b.name));
};
</script>

<template>
  <div>
    <ProductFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      v-model:isStrictSearch="isStrictSearch"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      mode="products"
      :show-add-button="false"
      @clear="handleClearFilters"
      @sort="handleSort"
    />

    <IncompleteProductsTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      :product-with-error="productWithError"
      :error-message="errorMessage"
      :laboratories="laboratories"
      :origins="origins"
      @update:options="updateTableOptions"
      @update-product="handleUpdateProduct"
      @laboratory-created="handleLaboratoryCreated"
      @origin-created="handleOriginCreated"
    />
  </div>
</template>
