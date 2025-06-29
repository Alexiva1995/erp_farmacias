<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

import ProductLotEditDialog from "@/components/dialogs/ProductLotEditDialog.vue";
import ProductLotCreateDialog from "@/components/dialogs/ProductWithoutLotCreateDialog.vue";
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

const isCreateDialogVisible = ref(false);
const availableProducts = ref([]);
const availableSuppliers = ref([]);
const isLoadingDialogData = ref(false);

const isEditDialogVisible = ref(false);
const lotsForEditing = ref([]);
const productNameToEdit = ref("");
const productIdToEdit = ref(null);

const fetchProductLots = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  try {
    const response = await axios.get("/product-lots", { params });
    productLots.value = response.data?.data.data || [];
    totalProductLots.value = response.data?.total || 0;
  } catch (error) {
    console.error("Error al obtener los lotes:", error);
    toast.error("No se pudieron cargar los lotes.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProductLots(), 300);
  },
  { deep: true, immediate: true }
);

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key || "id";
  orderBy.value = options.sortBy[0]?.order || "desc";
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
    fetchProductLots(); // Recargar tabla
  } catch (error) {
    console.error("Error al crear el lote:", error);
    const errorMessage =
      error.response?.data?.message || "No se pudo crear el lote.";
    toast.error(errorMessage);
  }
};

const handleEditLot = (lotToEdit) => {
  const product = lotToEdit.product;

  productNameToEdit.value = product.name;
  productIdToEdit.value = product.id;

  lotsForEditing.value = productLots.value.filter(
    (lot) => lot.product.id === product.id
  );

  isEditDialogVisible.value = true;
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
      toast.error(
        error.response.data.message ||
          "Por favor, revisa los datos de los lotes."
      );
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
      @add-lot="handleAddLot"
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
      :lots="lotsForEditing"
      @save="handleUpdateLot"
    />
  </div>
</template>
