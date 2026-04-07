<script setup>
import ProductEditDialog from "@/components/dialogs/ProductEditDialog.vue";
import ProductFilters from "@/components/ProductFilters.vue";
import ProductTable from "@/components/ProductTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const authStore = useAuthStore();

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
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const isStrictSearch = ref(false);
const isScarce = ref(false);

const laboratories = ref([]);
const origins = ref([]);
const suppliers = ref([]);
const categories = ref([]);
const groups = ref([]);

const isEditDialogVisible = ref(false);
const currentProduct = ref({});

const productFormErrors = ref({});

const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse, categoryResponse, groupsResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/categories"),
      axios.get("/groups/consult-all"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
    categories.value = categoryResponse.data;
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
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    groupId: selectedGroup.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
    isStrictSearch: isStrictSearch.value,
    ...(isScarce.value && { isScarce: true }),
  };
  Object.keys(params).forEach(
    key => (params[key] === null || params[key] === "") && delete params[key],
  );

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
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    searchQuery,
    selectedLaboratory,
    selectedOrigin,
    selectedGroup,
    stockStatusFilter,
    startDate,
    endDate,
    isStrictSearch,
    isScarce,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true },
);

watch(
  [searchQuery, selectedLaboratory, selectedOrigin, selectedGroup, stockStatusFilter, startDate, endDate],
  () => {
    page.value = 1;
  },
);

onMounted(async () => {
  fetchSelectOptions();
  fetchProducts();
});

const updateTableOptions = options => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;

  // Solo actualizar sortBy si hay una intención clara de ordenar desde la tabla
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  } else if (!options.sortBy || options.sortBy.length === 0) {
    // Si la tabla no envía sortBy pero ya teníamos uno (ej: vía filtros), lo preservamos
    // a menos que estemos en un flujo donde realmente queramos limpiar.
    // En Vuetify, al paginar, si no se clickeó cabecera, sortBy puede venir vacío.
  }
};

const handleEditProduct = product => {
  currentProduct.value = { ...product };
  productFormErrors.value = {};
  isEditDialogVisible.value = true;
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
      await axios.delete(`/products/${id}`);
      toast.success("Producto eliminado con éxito.");
      fetchProducts();
    } catch (error) {
      console.error(`Error al borrar el producto ${id}:`, error);
      toast.error("No se pudo eliminar el producto.");
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
  stockStatusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
  isStrictSearch.value = false;
  isScarce.value = false;
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
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    groupId: selectedGroup.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    startDate: startDate.value,
    endDate: endDate.value,
    format,
  };

  Object.keys(params).forEach(key => {
    if (params[key] === null || params[key] === "")
      delete params[key];
  });

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
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      v-model:isStrictSearch="isStrictSearch"
      v-model:isScarce="isScarce"
      :laboratories="laboratories"
      :origins="origins"
      :groups="groups"
      :loading="isLoadingFilters"
      :showAddButton="authStore.isAdmin"
      @clear="handleClearFilters"
      @export="handleExport"
      @add-product="handleAddProduct"
      @sort="handleSort"
    />

    <ProductTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      :sort-by="sortBy"
      :order-by="orderBy"
      @update:options="updateTableOptions"
      @edit-product="handleEditProduct"
      @delete-product="handleDeleteProduct"
      @product-merged="fetchProducts"
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
  </div>
</template>
