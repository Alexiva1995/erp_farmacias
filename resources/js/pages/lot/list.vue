<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

import ProductLotDialog from "@/components/dialogs/ProductLotDialog.vue";
import ProductLotsFilters from "@/components/ProductsLotsFilters.vue";
import ProductLotsTable from "@/components/ProductsLotsTable.vue";

const productLots = ref([]);
const totalProductLots = ref(0);
const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("id");
const orderBy = ref("desc");

const searchQuery = ref("");
const selectedLaboratory = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);

const laboratories = ref([]);
const isLoadingFilters = ref(false);

const isLotDialogVisible = ref(false);
const availableProducts = ref([]);
const availableSuppliers = ref([]);
const isLoadingDialogData = ref(false);

const isEditingMode = ref(false);
const currentLotToEdit = ref(null);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const labResponse = await axios.get("/laboratories");
    laboratories.value = labResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchProductLots = async () => {
  loading.value = true;
  const params = {
    search: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    startDate: startDate.value,
    endDate: endDate.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/product-lots", { params });
    productLots.value = response.data?.data.data || [];
    totalProductLots.value = response.data?.data.total || 0;
  } catch (error) {
    console.error("Error al obtener los lotes:", error);
    toast.error("No se pudieron cargar los lotes.");
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
    stockStatusFilter,
    startDate,
    endDate,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProductLots(), 300);
  },
  { deep: true }
);

watch(
  [searchQuery, selectedLaboratory, stockStatusFilter, startDate, endDate],
  () => {
    page.value = 1;
  }
);

onMounted(() => {
  fetchSelectOptions();
  fetchProductLots();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key || "id";
  orderBy.value = options.sortBy[0]?.order || "desc";
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  stockStatusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
  sortBy.value = "id";
  orderBy.value = "desc";
};

const handleAddLot = async () => {
  isLoadingDialogData.value = true;
  try {
    const [productsResponse, suppliersResponse] = await Promise.all([
      axios.get("/products-without-lots"),
      axios.get("/available-suppliers"),
    ]);

    availableProducts.value = productsResponse.data.data;
    availableSuppliers.value = suppliersResponse.data.data;

    isEditingMode.value = false;
    currentLotToEdit.value = null;
    isLotDialogVisible.value = true;
  } catch (error) {
    console.error("Error al obtener datos para el modal:", error);
    toast.error("No se pudieron cargar los datos para crear el lote.");
  } finally {
    isLoadingDialogData.value = false;
  }
};

const handleEditLot = async (lotToEdit) => {
  isLoadingDialogData.value = true;
  try {
    const suppliersResponse = await axios.get("/available-suppliers");
    availableSuppliers.value = suppliersResponse.data.data;

    isEditingMode.value = true;
    currentLotToEdit.value = lotToEdit;
    isLotDialogVisible.value = true;
  } catch (error) {
    console.error("Error al obtener datos para el modal:", error);
    toast.error("No se pudieron cargar los datos para editar el lote.");
  } finally {
    isLoadingDialogData.value = false;
  }
};

const handleCreateLot = async (lotData) => {
  try {
    await axios.post("/product-lots", lotData);
    toast.success("Lote creado con éxito.");
    isLotDialogVisible.value = false;
    fetchProductLots();
  } catch (error) {
    console.error("Error al crear el lote:", error);
    const errorMessage =
      error.response?.data?.message || "No se pudo crear el lote.";
    toast.error(errorMessage);
  }
};

const handleUpdateLot = async (lotData) => {
  try {
    await axios.put(`/product-lots/${lotData.id}`, lotData);
    toast.success("Lote actualizado con éxito.");
    isLotDialogVisible.value = false;
    fetchProductLots();
  } catch (error) {
    console.error("Error al actualizar el lote:", error);
    const errorMessage =
      error.response?.data?.message || "No se pudo actualizar el lote.";
    toast.error(errorMessage);
  }
};

const handleSaveLot = (lotData) => {
  if (isEditingMode.value) {
    handleUpdateLot(lotData);
  } else {
    handleCreateLot(lotData);
  }
};
</script>

<template>
  <div>
    <ProductLotsFilters
      v-model:searchQuery="searchQuery"
      v-model:itemsPerPage="itemsPerPage"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      :laboratories="laboratories"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @add-lot="handleAddLot"
      @sort="handleSort"
    />

    <ProductLotsTable
      :lots="productLots"
      :total-lots="totalProductLots"
      :loading="loading"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @edit-lot="handleEditLot"
    />

    <ProductLotDialog
      v-model="isLotDialogVisible"
      :loading="isLoadingDialogData"
      :products="availableProducts"
      :suppliers="availableSuppliers"
      :is-editing="isEditingMode"
      :lot-to-edit="currentLotToEdit"
      @save="handleSaveLot"
    />
  </div>
</template>
