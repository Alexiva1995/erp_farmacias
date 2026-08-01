<script setup>
import ProductEditDialog from "@/components/dialogs/ProductEditDialog.vue";
import ProductFilters from "@/components/ProductFilters.vue";
import ProductStatsDialog from "@/components/dialogs/ProductStatsDialog.vue";
import ProductTable from "@/components/ProductTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import Swal from "sweetalert2";
import { onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute } from "vue-router";

const authStore = useAuthStore();
const route = useRoute();

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("name");
const orderBy = ref("asc");

const searchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const selectedGroup = ref(null);
const selectedSupplier = ref(null);
const selectedCategory = ref(null);
const stockStatusFilter = ref(null);
const productTypeFilter = ref(null);
const selectedRestaurantType = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const isStrictSearch = ref(false);
const isScarce = ref(false);
const onlyDeleted = ref(false);

const laboratories = ref([]);
const origins = ref([]);
const suppliers = ref([]);
const categories = ref([]);
const groups = ref([]);

const isEditDialogVisible = ref(false);
const currentProduct = ref({});
const productFormErrors = ref({});
const isStatsDialogVisible = ref(false);
const selectedProductForStats = ref(null);

const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse, categoryResponse, groupsResponse, suppliersResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/categories"),
      axios.get("/groups/consult-all"),
      axios.get("/suppliers"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
    categories.value = categoryResponse.data;
    groups.value = groupsResponse.data?.data || groupsResponse.data || [];
    suppliers.value = suppliersResponse.data?.data || suppliersResponse.data || [];
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
    groupId: selectedGroup.value || undefined,
    supplierId: selectedSupplier.value || undefined,
    categoryId: selectedCategory.value || undefined,
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
    ...(selectedRestaurantType.value && { restaurantType: selectedRestaurantType.value }),
  };

  try {
    const response = await axios.get("/products", { params });
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

// Watcher unificado para filtros: reinicia página a 1 y hace debounce
watch(
  [
    searchQuery,
    selectedLaboratory,
    selectedOrigin,
    selectedGroup,
    selectedSupplier,
    selectedCategory,
    stockStatusFilter,
    productTypeFilter,
    selectedRestaurantType,
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

// Watcher directo para paginación y ordenamiento
watch([page, itemsPerPage, sortBy, orderBy], () => {
  fetchProducts();
});

onMounted(() => {
  if (route.query.laboratoryId) {
    selectedLaboratory.value = Number(route.query.laboratoryId);
  }
  fetchSelectOptions();
  fetchProducts();
});

onUnmounted(() => clearTimeout(debounceTimer));

const updateTableOptions = options => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;

  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  }
};

const handleEditProduct = product => {
  currentProduct.value = { ...product };
  productFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleViewStats = product => {
  selectedProductForStats.value = product;
  isStatsDialogVisible.value = true;
};

const handleDeleteProduct = async id => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de este producto!",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/products/${id}`);
      toast.success("Producto eliminado con éxito.");
      fetchProducts();
    } catch (error) {
      console.error(`Error al borrar el producto ${id}:`, error);
      toast.error("No se pudo eliminar el producto.");
    }
  }
};

const handleRestoreProduct = async id => {
  const result = await Swal.fire({
    title: "¿Restaurar producto?",
    text: "Este producto volverá a estar activo en el inventario.",
    icon: "info",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Restaurar",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      await axios.post(`/products/${id}/restore`);
      toast.success("Producto restaurado con éxito.");
      fetchProducts();
    } catch (error) {
      console.error(`Error al restaurar el producto ${id}:`, error);
      toast.error("No se pudo restaurar el producto.");
    }
  }
};

const handleSaveProduct = async productFormData => {
  const isNewProduct = !currentProduct.value.id;
  const url = isNewProduct ? "/products" : `/products/${currentProduct.value.id}`;

  try {
    if (!isNewProduct)
      productFormData.append("_method", "PUT");

    await axios.post(url, productFormData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    toast.success(`Producto ${isNewProduct ? "creado" : "actualizado"} con éxito`);
    isEditDialogVisible.value = false;
    await fetchProducts();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      productFormErrors.value = error.response.data.errors;
      const errorMessages = error.response.data.errors;
      if (errorMessages && Object.keys(errorMessages).length > 0) {
        const firstErrorKey = Object.keys(errorMessages)[0];
        const firstError = Array.isArray(errorMessages[firstErrorKey])
          ? errorMessages[firstErrorKey][0]
          : errorMessages[firstErrorKey];
        toast.error(`Error: ${firstError}`);
      } else {
        toast.error("Por favor, corrige los errores en el formulario.");
      }
    } else {
      console.error("Error al guardar/crear el producto:", error);
      const errorMessage = error.response?.data?.message || "Hubo un error al guardar el producto.";
      toast.error(errorMessage);
    }
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  selectedGroup.value = null;
  selectedSupplier.value = null;
  selectedCategory.value = null;
  stockStatusFilter.value = null;
  productTypeFilter.value = null;
  selectedRestaurantType.value = null;
  startDate.value = null;
  endDate.value = null;
  isStrictSearch.value = false;
  isScarce.value = false;
  onlyDeleted.value = false;
};

const productTableRef = ref(null);

const handleBulkToggleActive = async () => {
  const selectedIds = productTableRef.value?.selectedProducts || [];
  if (!selectedIds.length) {
    toast.error("Por favor, selecciona al menos un producto de la lista.");
    return;
  }

  const result = await Swal.fire({
    title: "¿Inhabilitar/Habilitar seleccionados?",
    text: `Se alternará el estado de activación de ${selectedIds.length} productos seleccionados.`,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, cambiar estado",
    cancelButtonText: "Cancelar",
    reverseButtons: true
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.post("/products/bulk-actions", {
        ids: selectedIds,
        action: "toggle-active"
      });
      toast.success(response.data.message || "Estado de productos actualizado.");
      if (productTableRef.value) {
        productTableRef.value.selectedProducts = [];
      }
      fetchProducts();
    } catch (e) {
      console.error("Error en cambio masivo de estado:", e);
      toast.error("Ocurrió un error al cambiar el estado de los productos.");
    }
  }
};

const handleBulkDelete = async () => {
  const selectedIds = productTableRef.value?.selectedProducts || [];
  if (!selectedIds.length) {
    toast.error("Por favor, selecciona al menos un producto de la lista.");
    return;
  }

  const result = await Swal.fire({
    title: "¿Eliminar seleccionados?",
    text: `Se enviarán a la papelera (eliminado suave) ${selectedIds.length} productos seleccionados.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Eliminar",
    cancelButtonText: "Cancelar",
    reverseButtons: true
  });

  if (result.isConfirmed) {
    try {
      await axios.post("/products/bulk-actions", {
        ids: selectedIds,
        action: "delete"
      });
      toast.success("Productos eliminados con éxito.");
      if (productTableRef.value) {
        productTableRef.value.selectedProducts = [];
      }
      fetchProducts();
    } catch (e) {
      console.error("Error en eliminación masiva de productos:", e);
      toast.error("Ocurrió un error al eliminar los productos.");
    }
  }
};

const handleAddProduct = () => {
  currentProduct.value = {};
  productFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const clearFormErrors = () => {
  productFormErrors.value = {};
};

const handleExport = async format => {
  const params = {
    q: searchQuery.value || undefined,
    laboratoryId: selectedLaboratory.value || undefined,
    originId: selectedOrigin.value || undefined,
    groupId: selectedGroup.value || undefined,
    categoryId: selectedCategory.value || undefined,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    startDate: startDate.value || undefined,
    endDate: endDate.value || undefined,
    format,
  };

  try {
    const response = await axios.get("/products/export", {
      params,
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = response.headers["content-disposition"];
    let fileName = `productos.${format}`;
    if (contentDisposition) {
      const fileNameMatch = contentDisposition.match(/filename="(.+)"/);
      if (fileNameMatch && fileNameMatch.length === 2)
        fileName = fileNameMatch[1];
    }

    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();

    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    console.error("Error al exportar los datos:", error);
    toast.error("Error al generar el archivo de exportación.");
  }
};

const handleSort = sortOptions => {
  if (sortOptions.key === undefined && sortOptions.order === undefined) {
    sortBy.value = undefined;
    orderBy.value = undefined;
  } else {
    sortBy.value = sortOptions.key;
    orderBy.value = sortOptions.order;
  }
};
</script>

<template>
  <div>
    <ProductFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:selectedGroup="selectedGroup"
      v-model:selectedSupplier="selectedSupplier"
      v-model:selectedCategory="selectedCategory"
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:productTypeFilter="productTypeFilter"
      v-model:selectedRestaurantType="selectedRestaurantType"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      v-model:isStrictSearch="isStrictSearch"
      :laboratories="laboratories"
      :origins="origins"
      :groups="groups"
      :suppliers="suppliers"
      :categories="categories"
      :loading="isLoadingFilters"
      :showAddButton="authStore.isAdmin"
      @clear="handleClearFilters"
      @export="handleExport"
      @add-product="handleAddProduct"
      @sort="handleSort"
      @bulk-toggle-active="handleBulkToggleActive"
      @bulk-delete="handleBulkDelete"
    />

    <ProductTable
      ref="productTableRef"
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      :sort-by="sortBy"
      :order-by="orderBy"
      :only-deleted="productTypeFilter === 'eliminados'"
      :categories="categories"
      @update:options="updateTableOptions"
      @edit-product="handleEditProduct"
      @delete-product="handleDeleteProduct"
      @restore-product="handleRestoreProduct"
      @product-merged="fetchProducts"
      @view-stats="handleViewStats"
    />

    <ProductEditDialog
      v-model="isEditDialogVisible"
      :product="currentProduct"
      :laboratories="laboratories"
      :origins="origins"
      :suppliers="suppliers"
      :categories="categories"
      :all-products="products"
      :errors="productFormErrors"
      :groups="groups"
      @save="handleSaveProduct"
      @clear-errors="clearFormErrors"
      @laboratory-created="fetchSelectOptions"
    />

    <ProductStatsDialog
      v-model="isStatsDialogVisible"
      :product="selectedProductForStats"
    />
  </div>
</template>
