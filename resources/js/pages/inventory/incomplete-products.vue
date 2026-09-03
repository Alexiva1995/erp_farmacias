<script setup>
import IncompleteProductsTable from "@/components/IncompleteProductsTable.vue";
import ProductFilters from "@/components/ProductFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, onUnmounted, ref, watch } from "vue";

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
const productTypeFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const isStrictSearch = ref(false);

const laboratories = ref([]);
const origins = ref([]);
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
    return "Datos faltantes asignados con éxito";
  } else if (updatedFields.length === 1) {
    return `Se asignó el ${updatedFields[0]} al producto`;
  } else if (updatedFields.length === 2) {
    return `Se asignaron ${updatedFields.join(" y ")}`;
  }
  return "Producto actualizado correctamente";
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
    if (err.response?.status === 422) {
      productWithError.value = payload.id;
      const errors = err.response.data?.errors;
      const errorMsg =
        errors?.barcode?.[0] ||
        errors?.laboratory_id?.[0] ||
        errors?.origin_id?.[0] ||
        Object.values(errors || {})?.[0]?.[0] ||
        err.response.data?.message ||
        "Error de validación al actualizar el producto";

      errorMessage.value = errorMsg;
      toast.error(errorMsg);
    } else {
      toast.error(err.response?.data?.message || "Error al actualizar los datos del producto");
    }
  }
};

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value || undefined,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value || undefined,
    orderBy: orderBy.value || undefined,
    laboratoryId: selectedLaboratory.value || undefined,
    originId: selectedOrigin.value || undefined,
    startDate: startDate.value || undefined,
    endDate: endDate.value || undefined,
    isStrictSearch: isStrictSearch.value || undefined,
    hasStock: stockStatusFilter.value !== null ? stockStatusFilter.value : undefined,
    ...(productTypeFilter.value && { productType: productTypeFilter.value }),
  };

  try {
    const response = await axios.get("/products/incomplete", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos incompletos.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;

// Watcher unificado para filtros con debounce y reseteo a página 1
watch(
  [
    searchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    productTypeFilter,
    startDate,
    endDate,
    isStrictSearch,
  ],
  () => {
    page.value = 1;
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  }
);

// Watcher para paginación y ordenamiento directo
watch([page, itemsPerPage, sortBy, orderBy], () => {
  fetchProducts();
});

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
});

onUnmounted(() => clearTimeout(debounceTimer));

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
  productTypeFilter.value = null;
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
      v-model:productTypeFilter="productTypeFilter"
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
      :sort-by="sortBy"
      :order-by="orderBy"
      @update:options="updateTableOptions"
      @update-product="handleUpdateProduct"
      @laboratory-created="handleLaboratoryCreated"
      @origin-created="handleOriginCreated"
    />
  </div>
</template>
