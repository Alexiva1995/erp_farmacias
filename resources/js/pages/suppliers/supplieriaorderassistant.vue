<script setup lang="js">
import SupplierIaOrderAssistantFilter from '@/components/SupplierIaOrderAssistantFilter.vue';
import SupplierIaOrderAssistantGrupoTable from '@/components/SupplierIaOrderAssistantGrupoTable.vue';
import SupplierIaOrderAssistantIndividualTable from '@/components/SupplierIaOrderAssistantIndividualTable.vue';
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";
import { onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from "vue-router";

const router = useRouter();


const statuModule = reactive({ total: 0, items: [] });
const groups = ref([]);
const laboratories = ref([]);
const loading = ref(false);
const loadingStats = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const selectedLaboratory = ref([]);
const selectedGroup = ref([]);

// Defaults mejorados: lapso 1 mes, cálculo combinado
const tipo_de_vista = ref(false);
const tipo_de_filtracion = ref("combinado");
const lapso_de_tiempo = ref("1 month");
const stock = ref("all");
const con_descuento = ref(true);
const isColombian = ref(false);
const searchQuery = ref("");

// KPIs globales (todos los productos, no paginados)
const kpiGlobal = reactive({ necesitan: 0, exceso: 0, ok: 0 });

const handleClearFilters = () => {
  con_descuento.value = true;
  tipo_de_vista.value = false;
  tipo_de_filtracion.value = "combinado";
  lapso_de_tiempo.value = "1 month";
  stock.value = "all";
  isColombian.value = false;
  selectedLaboratory.value = [];
  selectedGroup.value = [];
  searchQuery.value = "";
};

async function consultarLaboratorios() {
  const respuesta = await axios.get("/laboratories");
  laboratories.value = respuesta.data;
}

async function consultarGruposProductos() {
  const respuestaApi = await axios.get("/groups/consult-all");
  if (respuestaApi.status !== 200) { toast.error("Error al cargar grupos"); return; }
  groups.value = [...respuestaApi.data.data];
}

async function consultarProductosConPaginacion() {
  const data = {
    laboratoryId: selectedLaboratory.value,
    groups: selectedGroup.value,
    tipo_vista: tipo_de_vista.value,
    tipo_filtracion: tipo_de_filtracion.value,
    lapso_de_tiempo: lapso_de_tiempo.value,
    stock: stock.value,
    isColombian: isColombian.value,
    q: searchQuery.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  const resp = await axios.post(`/suppliers-ia-order-assistant/filtrar-paginate?page=${page.value}`, data);
  if (resp.status !== 200) toast.error("Error al filtrar los datos");
  return { ...resp.data };
}

async function actualizarTabla() {
  loading.value = true;
  try {
    const paginacion = await consultarProductosConPaginacion();
    statuModule.items = paginacion.data.paginate.data;
    statuModule.total = paginacion.data.paginate.total;
  } catch (e) {
    toast.error("Error al cargar los productos.");
  } finally {
    loading.value = false;
  }
}

const updateTableOptionsTable = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

let filterTimeout = null;
watch([selectedLaboratory, selectedGroup, tipo_de_vista, tipo_de_filtracion, lapso_de_tiempo, stock, isColombian, searchQuery], () => {
  clearTimeout(filterTimeout);
  filterTimeout = setTimeout(async () => {
    page.value = 1;
    await actualizarTabla();
  }, 400); // 400ms de retraso para evitar peticiones masivas
});

// Al paginar, solo recargar tabla
let paginationTimeout = null;
watch([page, itemsPerPage, orderBy, sortBy], () => {
  clearTimeout(paginationTimeout);
  paginationTimeout = setTimeout(async () => {
    await actualizarTabla();
  }, 200);
});

function generarPedido() {
  toast.info('Navegando a generar pedido...');
  console.log('[DEBUG] Iniciando generarPedido desde el asistente');
  router.push({
    path: "/suppliers/generar-pedido",
    query: {
      con_descuento: con_descuento.value,
      tipo_filtracion: tipo_de_filtracion.value,
      lapso_de_tiempo: lapso_de_tiempo.value,
      stock: stock.value,
      isColombian: isColombian.value,
      laboratoryId: JSON.stringify(selectedLaboratory.value),
      groups: JSON.stringify(selectedGroup.value),
    }
  });
}

onMounted(async () => {
  await Promise.all([consultarGruposProductos(), consultarLaboratorios()]);
  await actualizarTabla();
});
</script>

<template>
  <div class="assistant-ia-view px-6 mt-6 pb-12">
    <div class="d-flex flex-column gap-6">

      <!-- Filtros -->
      <SupplierIaOrderAssistantFilter
        v-model:selectConDescuento="con_descuento"
        v-model:selectedLaboratory="selectedLaboratory"
        v-model:selectedGroup="selectedGroup"
        v-model:tipo_de_vista="tipo_de_vista"
        v-model:tipo_de_filtracion="tipo_de_filtracion"
        v-model:lapso_de_tiempo="lapso_de_tiempo"
        v-model:stock="stock"
        v-model:isColombian="isColombian"
        v-model:searchQuery="searchQuery"
        :groups="groups"
        :laboratories="laboratories"
        :tipo_de_filtracion="tipo_de_filtracion"
        :tipo_de_vista="tipo_de_vista"
        :lapso_de_tiempo="lapso_de_tiempo"
        :stock="stock"
        :isColombian="isColombian"
        @clear="handleClearFilters"
        @generarPedido="generarPedido"
      />

      <!-- Tabla -->
      <div class="assistant-content">
        <SupplierIaOrderAssistantGrupoTable
          v-if="tipo_de_vista == true"
          :products="statuModule.items"
          :total-product="statuModule.total"
          :loading="loading"
          :items-per-page="itemsPerPage"
          :page="page"
          @update:options="updateTableOptionsTable"
        />
        <SupplierIaOrderAssistantIndividualTable
          v-else
          :products="statuModule.items"
          :total-product="statuModule.total"
          :loading="loading"
          :items-per-page="itemsPerPage"
          :page="page"
          @update:options="updateTableOptionsTable"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.assistant-ia-view {
  min-block-size: 100vh;
}
</style>
