<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

import ProductLotCreateDialog from "@/components/dialogs/ProductLotDialog.vue";
import ProductLotEditDialog from "@/components/dialogs/ProductLotEditDialog.vue";
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

const isCreateDialogVisible = ref(false);
const availableProducts = ref([]);
const availableSuppliers = ref([]);
const isLoadingDialogData = ref(false);

const isEditDialogVisible = ref(false);
const lotsForEditing = ref([]);
const productNameToEdit = ref("");
const productIdToEdit = ref(null);
const productStockToEdit = ref(0);

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
    isCreateDialogVisible.value = true;
  } catch (error) {
    console.error("Error al obtener datos para el modal:", error);
    toast.error("No se pudieron cargar los datos para crear el lote.");
  } finally {
    isLoadingDialogData.value = false;
  }
};

const handleCreateLot = async (lotData) => {
  try {
    await axios.post("/product-lots", lotData);
    toast.success("Lote creado con éxito.");
    isCreateDialogVisible.value = false;
    fetchProductLots();
  } catch (error) {
    console.error("Error al crear el lote:", error);

    if (error.response && error.response.status === 422) {
      const errors = error.response.data.errors;
      if (errors && errors.quantity && errors.quantity[0]) {
        toast.error(errors.quantity[0]);
      } else {
        toast.error(
          error.response.data.message || "Error de validación al crear el lote."
        );
      }
    } else {
      const errorMessage =
        error.response?.data?.message || "No se pudo crear el lote.";
      toast.error(errorMessage);
    }
  }
};

const handleEditLot = async (lotToEdit) => {
  try {
    const product = lotToEdit.product;

    productNameToEdit.value = product.name;
    productIdToEdit.value = product.id;
    productStockToEdit.value = product.stock;

    lotsForEditing.value = productLots.value.filter(
      (lot) => lot.product.id === product.id
    );

    try {
      const stockResponse = await axios.get(
        `/lots/available-stock/${product.id}`
      );
      const stockInfo = stockResponse.data.data;

      if (stockInfo.product_stock !== productStockToEdit.value) {
        productStockToEdit.value = stockInfo.product_stock;
      }

      if (stockInfo.has_discrepancy && stockInfo.available_stock > 0) {
        toast.info(
          `Este producto tiene ${stockInfo.available_stock} unidades disponibles para asignar en lotes.`
        );
      }
    } catch (stockError) {
      console.warn(
        "No se pudo obtener información actualizada de stock:",
        stockError
      );
    }

    isEditDialogVisible.value = true;
  } catch (error) {
    console.error("Error al preparar la edición del lote:", error);
    toast.error("No se pudo abrir el editor de lotes.");
  }
};

const handleUpdateLot = async (lotsToSave) => {
  loading.value = true;
  try {
    const payload = {
      product_id: productIdToEdit.value,
      lots: lotsToSave,
    };

    await axios.post("/product-lots/batch-update", payload);

    toast.success("Cambios guardados con éxito.");
    isEditDialogVisible.value = false;
    fetchProductLots();
  } catch (error) {
    console.error("Error al guardar los cambios de los lotes:", error);

    if (error.response && error.response.status === 422) {
      const errorData = error.response.data;

      if (errorData.errors) {
        const firstError = Object.values(errorData.errors)[0];
        if (Array.isArray(firstError) && firstError.length > 0) {
          toast.error(firstError[0]);
        } else {
          toast.error(
            errorData.message || "Por favor, revisa los datos de los lotes."
          );
        }
      } else {
        toast.error(
          errorData.message || "Por favor, revisa los datos de los lotes."
        );
      }
    } else {
      const errorMessage =
        error.response?.data?.message || "No se pudieron guardar los cambios.";
      toast.error(errorMessage);
    }
  } finally {
    loading.value = false;
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

    <ProductLotCreateDialog
      v-model="isCreateDialogVisible"
      :loading="isLoadingDialogData"
      :products="availableProducts"
      :suppliers="availableSuppliers"
      @save="handleCreateLot"
    />

    <ProductLotEditDialog
      v-model="isEditDialogVisible"
      :product-name="productNameToEdit"
      :product-id="productIdToEdit"
      :product-stock="productStockToEdit"
      :lots="lotsForEditing"
      @save="handleUpdateLot"
    />
  </div>
</template>
