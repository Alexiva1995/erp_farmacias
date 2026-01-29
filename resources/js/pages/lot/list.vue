<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";
import ProductLotDialog from "@/components/dialogs/ProductLotDialog.vue";
import ProductLotsFilters from "@/components/ProductsLotsFilters.vue";
import ProductLotsTable from "@/components/ProductsLotsTable.vue";
import { useAuthStore } from "@/stores/auth";

const productLots = ref([]);
const totalProductLots = ref(0);
const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("product.name");
const orderBy = ref("asc");

const searchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);

const laboratories = ref([]);
const origins = ref([]);
const isLoadingFilters = ref(false);

const isLotDialogVisible = ref(false);
const availableProducts = ref([]);
const availableSuppliers = ref([]);
const availableOrigins = ref([]);
const isLoadingDialogData = ref(false);

const isEditingMode = ref(false);
const currentLotToEdit = ref(null);

const isStrictSearch = ref(false);

const { isAdmin } = useAuthStore();

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
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
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    startDate: startDate.value,
    endDate: endDate.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    isStrictSearch: isStrictSearch.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/product-lots", { params });
    // La respuesta tiene estructura: { data: { data: [...], total: ... } }
    if (response.data?.data) {
      productLots.value = response.data.data.data || response.data.data || [];
      totalProductLots.value = response.data.data.total || 0;
    } else {
      productLots.value = [];
      totalProductLots.value = 0;
    }
  } catch (error) {
    console.error("Error al obtener los lotes:", error);
    const errorMessage = error.response?.data?.message || error.message || "No se pudieron cargar los lotes.";
    toast.error(errorMessage);
    productLots.value = [];
    totalProductLots.value = 0;
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
    debounceTimer = setTimeout(() => fetchProductLots(), 300);
  },
  { deep: true }
);

watch(
  [searchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter, startDate, endDate],
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
  sortBy.value = options.sortBy[0]?.key || "product.name";
  orderBy.value = options.sortBy[0]?.order || "asc";
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value=null;
  stockStatusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
  isStrictSearch.value = false;
  // sortBy.value = "id";
  // orderBy.value = "desc";
};

const handleAddLot = async () => {
  isLoadingDialogData.value = true;
  try {
    const [productsResponse, suppliersResponse, originsResponse] = await Promise.all([
      axios.get("/products/all"),
      axios.get("/available-suppliers"),
      axios.get("/origins"),
    ]);

    availableProducts.value = productsResponse.data;
    availableSuppliers.value = suppliersResponse.data.data;
    availableOrigins.value = originsResponse.data;

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
    const originsResponse = await axios.get("/origins");
    availableSuppliers.value = suppliersResponse.data.data;
    availableOrigins.value = originsResponse.data;

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

const handleCleanZeroQuantity = async () => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "Se eliminarán todos los lotes que tienen cantidad 0. Esta acción no se puede deshacer.",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
    reverseButtons: true,
    confirmButtonColor: "#d33",
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete("/product-lots/clean-zero-quantity");
      toast.success(response.data.message || "Lotes eliminados con éxito.");
      fetchProductLots();
    } catch (error) {
      console.error("Error al eliminar lotes:", error);
      const errorMessage =
        error.response?.data?.message || "No se pudieron eliminar los lotes.";
      toast.error(errorMessage);
    }
  }
};
</script>

<template>
  <div>
    <ProductLotsFilters
      v-model:searchQuery="searchQuery"
      v-model:itemsPerPage="itemsPerPage"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      v-model:isStrictSearch="isStrictSearch"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      :add-lot-loading="isLoadingDialogData"
      :is-admin="isAdmin"
      @clear="handleClearFilters"
      @add-lot="handleAddLot"
      @sort="handleSort"
      @clean-zero-quantity="handleCleanZeroQuantity"
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
      :origins="availableOrigins"
      :is-editing="isEditingMode"
      :lot-to-edit="currentLotToEdit"
      @save="handleSaveLot"
    />
  </div>
</template>
