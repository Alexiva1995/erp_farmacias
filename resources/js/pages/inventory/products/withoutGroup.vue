<script setup>
import ProductFilters from "@/components/ProductFilters.vue";
import ProductsWithoutGroupTable from "@/components/ProductsWithoutGroupTable.vue";
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
const groups = ref([]);

const isLoadingFilters = ref(false);

const handleUpdateProduct = async ({ id, group_id }) => {
  if (productWithError.value === id) {
    productWithError.value = null;
  }

  try {
    await axios.patch(`/products/without-group/${id}`, { group_id });
    toast.success("Se asignó el grupo al producto");
    await fetchProducts();
  } catch (err) {
    toast.error("Error al actualizar la asignación de grupo");

    if (err.response?.status === 422) {
      productWithError.value = id;
    }
  }
};

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse, groupsResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/groups/consult-all"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
    groups.value = groupsResponse.data?.data || groupsResponse.data || [];
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
    q: searchQuery.value || undefined,
    laboratoryId: selectedLaboratory.value || undefined,
    originId: selectedOrigin.value || undefined,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value || undefined,
    orderBy: orderBy.value || undefined,
    startDate: startDate.value || undefined,
    endDate: endDate.value || undefined,
    isStrictSearch: isStrictSearch.value || undefined,
    ...(productTypeFilter.value && { productType: productTypeFilter.value }),
  };

  try {
    const response = await axios.get("/products/without-group", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos sin grupo:", error);
    toast.error("Error al obtener los productos sin grupo.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;

// Watcher unificado para filtros: reinicia la página a 1 y dispara fetch con debounce
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
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
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

const handleGroupCreated = (newGroup) => {
  groups.value.push(newGroup);
  groups.value.sort((a, b) => a.name.localeCompare(b.name));
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

    <ProductsWithoutGroupTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      :product-with-error="productWithError"
      :groups="groups"
      @update:options="updateTableOptions"
      @update-product="handleUpdateProduct"
      @group-created="handleGroupCreated"
    />
  </div>
</template>
