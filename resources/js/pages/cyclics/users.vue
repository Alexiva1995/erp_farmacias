<script setup>
import InventoryCountDialog from "@/components/dialogs/InventoryCountDialog.vue";
import ProductFilters from "@/components/ProductFilters.vue";
import ProductTable from "@/components/ProductTable.vue";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";

import { toast } from "@/plugins/sweetalert";

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const searchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);

const laboratories = ref([]);
const origins = ref([]);

const isCountDialogVisible = ref(false);
const currentProduct = ref({});
const activeCycle = ref(null);
const hasActiveCycle = ref(false);

const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse, cycleResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/inventory/cycle/active"),
    ]);

    laboratories.value = labResponse.data.data;
    origins.value = originResponse.data;

    // Verificar estado del ciclo activo
    if (cycleResponse.data.success) {
      hasActiveCycle.value = cycleResponse.data.has_active_cycle;
      activeCycle.value = cycleResponse.data.data;

      if (!hasActiveCycle.value) {
        toast.warning(
          "No existe un ciclo de inventario activo. Los conteos no podrán ser registrados."
        );
      }
    }
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
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/inventory/products", { params });
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
    stockStatusFilter,
    startDate,
    endDate,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

watch(
  [
    searchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    startDate,
    endDate,
  ],
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
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleCountProduct = (product) => {
  if (!hasActiveCycle.value) {
    toast.error(
      "No se puede realizar el conteo. No existe un ciclo de inventario activo."
    );
    return;
  }

  currentProduct.value = { ...product };
  isCountDialogVisible.value = true;
};

const handleSaveCount = async (countData) => {
  try {
    const response = await axios.post(
      `/inventory/count/${currentProduct.value.id}`,
      {
        barcode: countData.barcode,
        counted_quantity: countData.countedQuantity,
        system_quantity: countData.system_quantity, // <-- AÑADIDO
        discrepancy: countData.discrepancy,
      }
    );

    if (response.data.success) {
      toast.success(response.data.message || "Conteo registrado exitosamente");
      isCountDialogVisible.value = false;
      await fetchProducts(); // Refrescar la tabla
    } else {
      toast.error(response.data.message || "Error al registrar el conteo");
    }
  } catch (error) {
    console.error("Error al registrar el conteo:", error);

    if (error.response?.status === 422) {
      // Errores de validación
      const errors = error.response.data.errors;
      const errorMessages = Object.values(errors).flat().join(", ");
      toast.error(`Errores de validación: ${errorMessages}`);
    } else if (error.response?.status === 400) {
      // Error de negocio (ej: código de barras no coincide)
      toast.error(error.response.data.message);
    } else {
      toast.error("Hubo un error al registrar el conteo.");
    }
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
};

const handleExport = async (format) => {
  const params = {
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    startDate: startDate.value,
    endDate: endDate.value,
    format: format,
  };

  Object.keys(params).forEach((key) => {
    if (params[key] === null || params[key] === "") {
      delete params[key];
    }
  });

  try {
    const response = await axios.get("/inventory/export", {
      params,
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = response.headers["content-disposition"];
    let fileName = `inventario.${format}`;
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

const handleSort = (sortOptions) => {
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
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      mode="inventory"
      @clear="handleClearFilters"
      @sort="handleSort"
    />

    <ProductTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      mode="inventory"
      @update:options="updateTableOptions"
      @count-product="handleCountProduct"
    />

    <InventoryCountDialog
      v-model="isCountDialogVisible"
      :product="currentProduct"
      @save="handleSaveCount"
    />
  </div>
</template>
