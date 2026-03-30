<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";
import ProductLotsFilters from "@/components/ProductsLotsFilters.vue";
import ProductLotsTable from "@/components/ProductsLotsTable.vue";
import ProductLotCreateDialog from "@/components/dialogs/ProductLotDialog.vue";
import LotDistributionModal from "@/components/dialogs/LotDistributionModal.vue";
import { useAuthStore } from "@/stores/auth";

const products = ref([]);
const totalProducts = ref(0);
const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("name");
const orderBy = ref("asc");

const searchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);

const laboratories = ref([]);
const origins = ref([]);
const locations = ref([]);
const isLoadingFilters = ref(false);

const isDistributionModalVisible = ref(false);
const isCreateDialogVisible = ref(false);
const availableProducts = ref([]);
const availableSuppliers = ref([]);
const currentProductForDistribution = ref(null);
const isLoadingDialogData = ref(false);

const isStrictSearch = ref(false);

const { isAdmin } = useAuthStore();

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse, locationResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/locations"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
    locations.value = locationResponse.data.data || locationResponse.data;
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
    laboratory_id: selectedLaboratory.value,
    origin_id: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && {
      has_stock: stockStatusFilter.value,
    }),
    startDate: startDate.value,
    endDate: endDate.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    isStrictSearch: isStrictSearch.value,
  };

  try {
    const response = await axios.get("/products", { params });
    if (response.data) {
      products.value = response.data.data || [];
      totalProducts.value = response.data.total || 0;
    }
  } catch (error) {
    console.error("Error al obtener los productos:", error);
    toast.error("No se pudieron cargar los productos.");
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
  fetchProducts();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key || "name";
  orderBy.value = options.sortBy[0]?.order || "asc";
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};
const handleAddLot = async () => {
  isLoadingDialogData.value = true;
  try {
    const [productsResponse, suppliersResponse] = await Promise.all([
      axios.get("/products"),
      axios.get("/available-suppliers"),
    ]);
    availableProducts.value = productsResponse.data.data;
    availableSuppliers.value = suppliersResponse.data.data;
    isCreateDialogVisible.value = true;
  } catch (error) {
    console.error("Error al cargar datos para nuevo lote:", error);
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
    fetchProducts();
  } catch (error) {
    console.error("Error al crear el lote:", error);
    const errorMessage = error.response?.data?.message || "No se pudo crear el lote.";
    toast.error(errorMessage);
  }
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

const handleAdjustLots = (product) => {
  currentProductForDistribution.value = product;
  isDistributionModalVisible.value = true;
};

const handleSaveLots = async (distributionData) => {
  if (!currentProductForDistribution.value) return;

  try {
    const payload = {
      product_id: currentProductForDistribution.value.id,
      lots: [
        ...distributionData.updatedLots.map(l => ({
          ...l,
          id: l.id,
          lot_number: l.lot_number,
          expiration_date: l.expiration_date,
          quantity: l.quantity,
          location: l.location,
        })),
        ...distributionData.newLots.map(l => ({
          ...l,
          lot_number: l.lot_number,
          expiration_date: l.expiration_date,
          quantity: l.quantity,
          location: l.location,
        }))
      ]
    };

    await axios.post("/product-lots/batch-update", payload);
    toast.success("Lotes actualizados con éxito.");
    isDistributionModalVisible.value = false;
    fetchProducts();
  } catch (error) {
    console.error("Error al actualizar lotes:", error);
    const errorMessage = error.response?.data?.message || "Error al actualizar los lotes.";
    toast.error(errorMessage);
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
      fetchProducts();
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
      :products="products"
      :total-products="totalProducts"
      :loading="loading"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @adjust-lots="handleAdjustLots"
    />

    <ProductLotCreateDialog
      v-model="isCreateDialogVisible"
      :loading="isLoadingDialogData"
      :products="availableProducts"
      :suppliers="availableSuppliers"
      :origins="origins"
      :locations="locations"
      @save="handleCreateLot"
    />

    <LotDistributionModal
      v-model="isDistributionModalVisible"
      :product-name="currentProductForDistribution?.name || ''"
      :lots="currentProductForDistribution?.lots || []"
      :target-quantity="currentProductForDistribution?.stock_calculado || 0"
      :locations="locations"
      mode="adjustment"
      @save="handleSaveLots"
    />
  </div>
</template>
