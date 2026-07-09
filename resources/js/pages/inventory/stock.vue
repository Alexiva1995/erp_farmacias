<script setup lang="js">
import InventoryStockFilters from "@/components/InventoryStockFilters.vue";
import InventoryStockTable from "@/components/InventoryStockTable.vue";
import InventoryStockGrupoTable from "@/components/InventoryStockGrupoTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfStockProductsGenerator from "@/utils/pdfStockProductsGenerator";
import { onMounted, reactive, watch, ref, computed } from 'vue';
import { useRouter } from "vue-router";
import { useBrandingStore } from "@/stores/useBrandingStore";

const route = useRouter();

const modal = reactive({
  statu: false,
  titulo: "Nuevo",
});

const modulo = reactive({
  items: [],
  totalItems: 0,
});

// Contador de solicitudes para evitar race conditions
let requestId = 0;
let debounceTimer = null;
let skipPaginationWatch = false;

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => (brandingStore.settings.business_type === 'restaurant' || brandingStore.settings.business_type === 'minimarket'));

const searchQuery = ref("");
const selectedLaboratory = ref(null);
const stockStatusFilter = ref(null);
const viewType = ref("individual");
const days = ref(30);
const stock = ref(isRestaurant.value ? "fallas" : "all");
const expProd = ref(false);
const isStrictSearch = ref(false);
const tipoFiltracion = ref(isRestaurant.value ? "sales" : "average");
const isColombian = ref(false);

const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

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
    q: searchQuery.value,
    hasStock: stockStatusFilter.value,
    viewType: viewType.value,
    laboratoryId: selectedLaboratory.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    days: days.value,
    stock: stock.value,
    expProd: expProd.value,
    isStrictSearch: isStrictSearch.value,
    tipo_filtracion: tipoFiltracion.value,
    isColombian: isColombian.value,
  };
  loading.value = true;
  try {
    const respuesApi = await axios.post("/inventory/stock/filter", data);
    if (respuesApi.status == 200) {
      console.log("productos consultados correctamente");
    } else {
      toast.error("error al consultar");
      console.log("error en el servidor => ", respuesApi);
    }
    loading.value = false;
    return { ...respuesApi.data.data };
  } catch (error) {
    toast.error("error al consultar");
    console.log("error en el servidor => ", error);
    loading.value = false;
    return { data: [], total: 0 };
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  stockStatusFilter.value = null;
  viewType.value = "individual";
  stock.value = isRestaurant.value ? "fallas" : "all";
  days.value = 30;
  expProd.value = false;
  isStrictSearch.value = false;
  tipoFiltracion.value = isRestaurant.value ? "sales" : "average";
  isColombian.value = false;
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

// Watch con debounce para filtros que cambian frecuentemente (ej: escribir en búsqueda)
watch(
  [
    expProd,
    stock,
    days,
    searchQuery,
    selectedLaboratory,
    stockStatusFilter,
    viewType,
    isStrictSearch,
    tipoFiltracion,
    isColombian,
  ],
  () => {
    // Cuando cambia un filtro, volver a la página 1
    if (page.value !== 1) {
      skipPaginationWatch = true;
      page.value = 1;
    }
    actualizarTablaDebounced();
  },
);

// Watch sin debounce para paginación y ordenamiento (respuesta inmediata)
watch(
  [page, itemsPerPage, sortBy, orderBy],
  () => {
    // Si el cambio de página viene del watch de filtros, no hacer doble llamada
    if (skipPaginationWatch) {
      skipPaginationWatch = false;
      return;
    }
    actualizarTabla();
  },
);

// Versión con debounce para cambios de filtros
function actualizarTablaDebounced() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    actualizarTabla();
  }, 300);
}

// Versión principal con protección contra race conditions
async function actualizarTabla() {
  const currentRequestId = ++requestId;
  const dataTabla = await fetchProducts();

  // Si hubo otra solicitud más reciente mientras esta estaba en curso,
  // descartamos esta respuesta obsoleta para evitar sobrescribir datos correctos
  if (currentRequestId !== requestId) {
    console.log("Respuesta descartada (solicitud obsoleta)");
    return;
  }

  console.log("=> ", dataTabla);
  modulo.items = dataTabla.data;
  modulo.totalItems = dataTabla.total;
}

const updateTableOptions = (options) => {
  const newPage = options.page;
  const newItemsPerPage = options.itemsPerPage;

  // Capturar ordenamiento solo si viene uno nuevo
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
  console.log("=> ", dataTabla);
  modulo.items = dataTabla.data;
  modulo.totalItems = dataTabla.total;
});

async function filtrarSinPaginar(dataFiltro) {
  const respuestaApi = await axios.post(
    `/inventory/stock/filter-without-paginate`,
    dataFiltro,
  );
  if (respuestaApi.status != 200) {
    toast.success("Error al filtrar los datos");
  }

  return [...respuestaApi.data.data];
}

async function exportarPdf() {
  const filtros = {
    q: searchQuery.value,
    hasStock: stockStatusFilter.value,
    viewType: viewType.value,
    laboratoryId: selectedLaboratory.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    days: days.value,
    stock: stock.value,
    expProd: expProd.value,
    isStrictSearch: isStrictSearch.value,
    tipo_filtracion: tipoFiltracion.value,
    isColombian: isColombian.value,
  };
  const respuestaApi = await filtrarSinPaginar(filtros);
  console.log("respuesta => ", respuestaApi);

  if (respuestaApi.length == 0) {
    toast.info("No hay productos para poder generar un reporte");
    return null;
  }

  pdfStockProductsGenerator(respuestaApi);
}

async function exportarExcel(formato) {
  try {
    const params = {
      q: searchQuery.value,
      hasStock: stockStatusFilter.value,
      viewType: viewType.value,
      laboratoryId: selectedLaboratory.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
      days: days.value,
      stock: stock.value,
      expProd: expProd.value,
      isStrictSearch: isStrictSearch.value,
      tipo_filtracion: tipoFiltracion.value,
      isColombian: isColombian.value,
      formato,
    };

    const respuestaApi = await axios.post(
      "/inventory/stock/exportar/excel",
      params,
      {
        responseType: "blob",
        headers: {
          "Content-Type": "application/json",
        },
      },
    );

    console.log("res => ", respuestaApi);

    if (respuestaApi.status != 200) {
      toast.success("Error al filtrar los datos");
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
  } catch (error) {
    console.error("Error al exportar los datos:", error);
  }
}
</script>

<template>
  <div>
    <InventoryStockFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:viewType="viewType"
      v-model:days="days"
      v-model:stock="stock"
      v-model:expProd="expProd"
      v-model:isStrictSearch="isStrictSearch"
      v-model:tipoFiltracion="tipoFiltracion"
      v-model:isColombian="isColombian"
      :laboratories="laboratories"
      :loading="loading"
      @clear="handleClearFilters"
      @sort="handleSort"
      @export-pdf="exportarPdf"
      @export-excel="exportarExcel"
    />
    <InventoryStockTable
      v-if="viewType === 'individual'"
      :products="modulo.items"
      :loading="loading"
      :total-product="modulo.totalItems"
      :items-per-page="itemsPerPage"
      :page="page"
      :sort-by="sortBy"
      :order-by="orderBy"
      :view-type="viewType"
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
