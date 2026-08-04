<script setup>
import InventoryStockFilters from "@/components/InventoryStockFilters.vue";
import InventoryStockTable from "@/components/InventoryStockTable.vue";
import InventoryStockGrupoTable from "@/components/InventoryStockGrupoTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfStockProductsGenerator from "@/utils/pdfStockProductsGenerator";
import { onMounted, onUnmounted, reactive, watch, ref, computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

// Contador de solicitudes para evitar race conditions
let requestId = 0;
let debounceTimer = null;
let skipPaginationWatch = false;

// Estado global de marca
const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);

// Filtros encapsulados en un objeto reactivo estructurado
const filters = reactive({
  searchQuery: "",
  selectedLaboratory: null,
  stockStatusFilter: null,
  viewType: "individual",
  days: 30,
  stock: isRestaurant.value ? "fallas" : "all",
  expProd: false,
  isStrictSearch: false,
  tipoFiltracion: isRestaurant.value ? "sales" : "average",
  isColombian: false,
});

// Estado de la tabla y paginación
const modulo = reactive({
  items: [],
  totalItems: 0,
});

const loading = ref(false);
const isExportingPdf = ref(false);
const isExportingExcel = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref(undefined);
const orderBy = ref(undefined);

const laboratories = ref([]);

const fetchSelectOptions = async () => {
  loading.value = true;
  try {
    const labResponse = await axios.get("/laboratories");
    laboratories.value = labResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    loading.value = false;
  }
};

const fetchProducts = async () => {
  const data = {
    q: filters.searchQuery,
    hasStock: filters.stockStatusFilter,
    viewType: filters.viewType,
    laboratoryId: filters.selectedLaboratory,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    days: filters.days,
    stock: filters.stock,
    expProd: filters.expProd,
    isStrictSearch: filters.isStrictSearch,
    tipo_filtracion: filters.tipoFiltracion,
    isColombian: filters.isColombian,
  };
  loading.value = true;
  try {
    const apiResponse = await axios.post("/inventory/stock/filter", data);
    loading.value = false;
    return { ...apiResponse.data.data };
  } catch (error) {
    toast.error("Error al consultar el stock.");
    loading.value = false;
    return { data: [], total: 0 };
  }
};

const handleClearFilters = () => {
  filters.searchQuery = "";
  filters.selectedLaboratory = null;
  filters.stockStatusFilter = null;
  filters.viewType = "individual";
  filters.stock = isRestaurant.value ? "fallas" : "all";
  filters.days = 30;
  filters.expProd = false;
  filters.isStrictSearch = false;
  filters.tipoFiltracion = isRestaurant.value ? "sales" : "average";
  filters.isColombian = false;
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

// Watch con debounce para filtros que cambian frecuentemente
watch(
  () => [
    filters.expProd,
    filters.stock,
    filters.days,
    filters.searchQuery,
    filters.selectedLaboratory,
    filters.stockStatusFilter,
    filters.viewType,
    filters.isStrictSearch,
    filters.tipoFiltracion,
    filters.isColombian,
  ],
  () => {
    if (page.value !== 1) {
      skipPaginationWatch = true;
      page.value = 1;
    }
    actualizarTablaDebounced();
  },
  { deep: true }
);

// Watch sin debounce para paginación y ordenamiento
watch([page, itemsPerPage, sortBy, orderBy], () => {
  if (skipPaginationWatch) {
    skipPaginationWatch = false;
    return;
  }
  actualizarTabla();
});

function actualizarTablaDebounced() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    actualizarTabla();
  }, 300);
}

async function actualizarTabla() {
  const currentRequestId = ++requestId;
  const dataTabla = await fetchProducts();

  if (currentRequestId !== requestId) return;

  modulo.items = dataTabla.data;
  modulo.totalItems = dataTabla.total;
}

const updateTableOptions = (options) => {
  const newPage = options.page;
  const newItemsPerPage = options.itemsPerPage;

  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  }

  page.value = newPage;
  itemsPerPage.value = newItemsPerPage;
};

onMounted(async () => {
  await fetchSelectOptions();
  const dataTabla = await fetchProducts();
  modulo.items = dataTabla.data;
  modulo.totalItems = dataTabla.total;
});

onUnmounted(() => {
  clearTimeout(debounceTimer);
});

async function filtrarSinPaginar(dataFiltro) {
  const respuestaApi = await axios.post(
    `/inventory/stock/filter-without-paginate`,
    dataFiltro
  );
  if (respuestaApi.status !== 200) {
    toast.error("Error al filtrar los datos");
  }
  return [...respuestaApi.data.data];
}

async function exportarPdf() {
  if (isExportingPdf.value) return;
  isExportingPdf.value = true;
  try {
    const dataFiltros = {
      q: filters.searchQuery,
      hasStock: filters.stockStatusFilter,
      viewType: filters.viewType,
      laboratoryId: filters.selectedLaboratory,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
      days: filters.days,
      stock: filters.stock,
      expProd: filters.expProd,
      isStrictSearch: filters.isStrictSearch,
      tipo_filtracion: filters.tipoFiltracion,
      isColombian: filters.isColombian,
    };
    const respuestaApi = await filtrarSinPaginar(dataFiltros);

    if (respuestaApi.length === 0) {
      toast.info("No hay productos para poder generar un reporte");
      return;
    }

    pdfStockProductsGenerator(respuestaApi);
    toast.success("Reporte PDF generado exitosamente.");
  } catch (error) {
    console.error("Error al exportar PDF:", error);
    toast.error("Error al generar el archivo PDF.");
  } finally {
    isExportingPdf.value = false;
  }
}

async function exportarExcel(formato) {
  if (isExportingExcel.value) return;
  isExportingExcel.value = true;
  try {
    const params = {
      q: filters.searchQuery,
      hasStock: filters.stockStatusFilter,
      viewType: filters.viewType,
      laboratoryId: filters.selectedLaboratory,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
      days: filters.days,
      stock: filters.stock,
      expProd: filters.expProd,
      isStrictSearch: filters.isStrictSearch,
      tipo_filtracion: filters.tipoFiltracion,
      isColombian: filters.isColombian,
      formato,
    };

    const respuestaApi = await axios.post(
      "/inventory/stock/exportar/excel",
      params,
      {
        responseType: "blob",
        headers: { "Content-Type": "application/json" },
      }
    );

    if (respuestaApi.status !== 200) {
      toast.error("Error al exportar los datos");
      return;
    }
    const url = window.URL.createObjectURL(new Blob([respuestaApi.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = respuestaApi.headers["content-disposition"];
    let fileName = `stock-products.${formato}`;
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
    toast.success("Archivo Excel exportado exitosamente.");
  } catch (error) {
    console.error("Error al exportar los datos:", error);
    toast.error("Ocurrió un error al exportar el archivo Excel.");
  } finally {
    isExportingExcel.value = false;
  }
}
</script>

<template>
  <div>

    <InventoryStockFilters
      v-model:searchQuery="filters.searchQuery"
      v-model:selectedLaboratory="filters.selectedLaboratory"
      v-model:stockStatusFilter="filters.stockStatusFilter"
      v-model:viewType="filters.viewType"
      v-model:days="filters.days"
      v-model:stock="filters.stock"
      v-model:expProd="filters.expProd"
      v-model:isStrictSearch="filters.isStrictSearch"
      v-model:tipoFiltracion="filters.tipoFiltracion"
      v-model:isColombian="filters.isColombian"
      :laboratories="laboratories"
      :loading="loading"
      :is-exporting-pdf="isExportingPdf"
      :is-exporting-excel="isExportingExcel"
      @clear="handleClearFilters"
      @sort="handleSort"
      @export-pdf="exportarPdf"
      @export-excel="exportarExcel"
    />

    <InventoryStockTable
      v-if="filters.viewType === 'individual'"
      :products="modulo.items"
      :loading="loading"
      :total-product="modulo.totalItems"
      :items-per-page="itemsPerPage"
      :page="page"
      :sort-by="sortBy"
      :order-by="orderBy"
      :view-type="filters.viewType"
      @update:options="updateTableOptions"
    />
    <InventoryStockGrupoTable
      v-else
      :products="modulo.items"
      :loading="loading"
      :total-product="modulo.totalItems"
      :items-per-page="itemsPerPage"
      :page="page"
      :sort-by="sortBy"
      :order-by="orderBy"
      @update:options="updateTableOptions"
    />
  </div>
</template>
