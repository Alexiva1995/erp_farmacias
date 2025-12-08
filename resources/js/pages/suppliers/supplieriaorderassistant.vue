<script setup lang="js">
import SupplierIaOrderAssistantFilter from '@/components/SupplierIaOrderAssistantFilter.vue';
import SupplierIaOrderAssistantGrupoTable from '@/components/SupplierIaOrderAssistantGrupoTable.vue';
import SupplierIaOrderAssistantIndividualTable from '@/components/SupplierIaOrderAssistantIndividualTable.vue';
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfProductsWithoutSuppliersGenerator from "@/utils/pdfProductsWithoutSuppliersGenerator";
import { onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from "vue-router";

const route = useRouter();

const modal = reactive({
  statu: false,
  titulo: "Nuevo",
});

const statuModule = reactive({
  total: 0,
  items: [],
});

const groups = ref([]);
const laboratories = ref([]);
const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const selectedLaboratory = ref();
const selectedGroup = ref();

const tipo_de_vista = ref(false);
const tipo_de_filtracion = ref("sales");
const lapso_de_tiempo = ref("3 month");
const stock = ref("all");
const con_descuento = ref(true);

const handleClearFilters = () => {
  con_descuento.value = true;
  tipo_de_vista.value = false;
  tipo_de_filtracion.value = "sales";
  lapso_de_tiempo.value = "3 month";
  stock.value = "all";
  selectedLaboratory.value = [];
  selectedGroup.value = [];
};

async function consultarProductosConPaginacion() {
  let data = {
    "laboratoryId": selectedLaboratory.value,
    "groups": selectedGroup.value,
    "tipo_vista": tipo_de_vista.value,
    "tipo_filtracion": tipo_de_filtracion.value,
    "lapso_de_tiempo": lapso_de_tiempo.value,
    "stock": stock.value,
    "page": page.value,
    "itemsPerPage": itemsPerPage.value,
    "sortBy": sortBy.value,
    "orderBy": orderBy.value,
  };

  let respuestaApi = await axios.post(`/suppliers-ia-order-assistant/filtrar-paginate?page=${page.value}`, data);

  if (respuestaApi.status != 200) {
    toast.error("Error al filtrar los datos");
  }

  return { ...respuestaApi.data };
}

async function actualizarTabla() {
  loading.value = true;
  let paginacion = await consultarProductosConPaginacion();

  if (paginacion.data && paginacion.data.paginate) {
    statuModule.items = paginacion.data.paginate.data;
    statuModule.total = paginacion.data.paginate.total;
  }

  loading.value = false;
}

const updateTableOptionsTable = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

watch([
  selectedLaboratory,
  selectedGroup,
  tipo_de_vista,
  tipo_de_filtracion,
  lapso_de_tiempo,
  stock,
  orderBy,
  sortBy,
  page,
  itemsPerPage,
], async () => {
  await actualizarTabla();
});

function generarPedido() {
  route.push({
    path: "/suppliers/generar-pedido",
    query: {
      "con_descuento": con_descuento.value,
      "tipo_filtracion": tipo_de_filtracion.value,
      "lapso_de_tiempo": lapso_de_tiempo.value,
      "laboratoryId": JSON.stringify(selectedLaboratory.value),
      "groups": JSON.stringify(selectedGroup.value),
      "stock": stock.value,
    }
  });
}

async function consultarLaboratorios() {
  let respuesta = await axios.get("/laboratories");
  laboratories.value = respuesta.data;
}

async function consultarGruposProductos() {
  let respuestaApi = await axios.get("/groups/consult-all");
  if (respuestaApi.status != 200) {
    toast.error("Error al obtener grupos");
  }
  groups.value = [...respuestaApi.data.data];
}

onMounted(async () => {
  await consultarGruposProductos();
  await consultarLaboratorios();
  await actualizarTabla();
});

async function fetchAllDataForPdf() {
  try {
    loading.value = true;

    let data = {
      "laboratoryId": selectedLaboratory.value,
      "groups": selectedGroup.value,
      "tipo_vista": tipo_de_vista.value,
      "tipo_filtracion": tipo_de_filtracion.value,
      "lapso_de_tiempo": lapso_de_tiempo.value,
      "stock": stock.value,
      "page": 1,
      "itemsPerPage": 10000,
      "sortBy": sortBy.value,
      "orderBy": orderBy.value,
    };

    let respuestaApi = await axios.post(`/suppliers-ia-order-assistant/filtrar-paginate`, data);

    if (respuestaApi.status === 200) {
      const items = respuestaApi.data.data?.paginate?.data || [];
      return items;
    } else {
      toast.error("Error al obtener datos del servidor");
      return [];
    }
  } catch (error) {
    console.error(error);
    toast.error("Error en la descarga");
    return [];
  } finally {
    loading.value = false;
  }
}

const handleDownloadPdf = async () => {
  const allItems = await fetchAllDataForPdf();

  if (allItems && allItems.length > 0) {
    // Usamos el generador externo
    pdfProductsWithoutSuppliersGenerator(allItems);
    toast.success("PDF generado correctamente");
  } else {
    toast.info("No hay datos para generar el PDF");
  }
};
</script>

<template>
  <div>
    <SupplierIaOrderAssistantFilter
      v-model:selectConDescuento="con_descuento"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedGroup="selectedGroup"
      v-model:tipo_de_vista="tipo_de_vista"
      v-model:tipo_de_filtracion="tipo_de_filtracion"
      v-model:lapso_de_tiempo="lapso_de_tiempo"
      v-model:stock="stock"
      :groups="groups"
      :laboratories="laboratories"
      :tipo_de_filtracion="tipo_de_filtracion"
      :tipo_de_vista="tipo_de_vista"
      :lapso_de_tiempo="lapso_de_tiempo"
      :stock="stock"
      @clear="handleClearFilters"
      @generarPedido="generarPedido"
      @downloadPdf="handleDownloadPdf"
    />
  </div>

  <div v-if="tipo_de_vista == true">
    <SupplierIaOrderAssistantGrupoTable
      :products="statuModule.items"
      :total-product="statuModule.total"
      :loading="loading"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptionsTable"
    />
  </div>

  <div v-if="tipo_de_vista == false">
    <SupplierIaOrderAssistantIndividualTable
      :products="statuModule.items"
      :total-product="statuModule.total"
      :loading="loading"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptionsTable"
    />
  </div>
</template>
