<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { ref, watch } from "vue";

import ProductLotCreateDialog from "@/components/dialogs/ProductWithoutLotCreateDialog.vue";
import ProductLotEditDialog from "@/components/dialogs/ProductWithoutLotEditDialog.vue";
import ProductLotsFilters from "@/components/ProductWithoutLotsFilters.vue";
import ProductLotsTable from "@/components/ProductWithoutLotsTable.vue";

const productLots = ref([]);
const totalProductLots = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("id");
const orderBy = ref("desc");
const searchQuery = ref("");

const isCreateDialogVisible = ref(false);
const isEditDialogVisible = ref(false);
const currentLot = ref({});

const availableProducts = ref([]);
const availableSuppliers = ref([]);
const isLoadingDialogData = ref(false);

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
    const response = await axios.get("/product-without-lots", { params });
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
    debounceTimer = setTimeout(fetchProductLots, 300);
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
  // 1. Muestra un indicador de carga, pero todavía no el modal.
  isLoadingDialogData.value = true;

  try {
    // 2. Espera a que TODOS los datos necesarios se carguen.
    const [productsResponse, suppliersResponse] = await Promise.all([
      axios.get("/products-without-lots"),
      axios.get("/available-suppliers"),
    ]);

    availableProducts.value = productsResponse.data.data;
    availableSuppliers.value = suppliersResponse.data.data;

    // 3. SOLO AHORA, con los datos listos, muestra el modal.
    isCreateDialogVisible.value = true;
  } catch (error) {
    console.error("Error al obtener datos para el modal:", error);
    toast.error("No se pudieron cargar los datos para crear el lote.");
    // No es necesario cambiar 'isCreateDialogVisible', ya que nunca se puso en 'true'.
  } finally {
    // 4. Quita el indicador de carga.
    isLoadingDialogData.value = false;
  }
};

const handleEditLot = (lot) => {
  currentLot.value = { ...lot };
  isEditDialogVisible.value = true;
};

const handleDeleteLot = async (lot) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `¡No podrás revertir la eliminación del lote para "${lot.product.name}"!`,
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/product-lots/${lot.id}`);
      toast.success("Lote eliminado con éxito.");
      fetchProductLots();
    } catch (error) {
      console.error(`Error al borrar el lote ${lot.id}:`, error);
      toast.error("No se pudo eliminar el lote.");
    }
  }
};

const handleCreateLot = async (lotData) => {
  try {
    await axios.post("/product-lots", lotData);
    toast.success("Lote creado con éxito.");
    isCreateDialogVisible.value = false; // Cierra el diálogo de creación
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
    // Asumiendo que la API usa PUT o PATCH para actualizar
    await axios.put(`/product-lots/${lotData.id}`, lotData);
    toast.success("Lote actualizado con éxito.");
    isEditDialogVisible.value = false; // Cierra el diálogo de edición
    fetchProductLots(); // Refresca la tabla
  } catch (error) {
    console.error(`Error al actualizar el lote ${lotData.id}:`, error);
    const errorMessage =
      error.response?.data?.message || "No se pudo actualizar el lote.";
    toast.error(errorMessage);
  }
};
</script>

<template>
  <div>
    <ProductLotsFilters
      v-model:searchQuery="searchQuery"
      @add-lot="handleAddLot"
    />

    <ProductLotsTable
      :lots="productLots"
      :total-lots="totalProductLots"
      :loading="loading"
      @update:options="updateTableOptions"
      @edit-lot="handleEditLot"
      @delete-lot="handleDeleteLot"
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
      :lot="currentLot"
      @save="handleUpdateLot"
    />
  </div>
</template>
