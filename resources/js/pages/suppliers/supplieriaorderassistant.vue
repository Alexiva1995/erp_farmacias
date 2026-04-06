<script setup lang="js">
import SupplierIaOrderAssistantFilter from "@/components/SupplierIaOrderAssistantFilter.vue";
import SupplierIaOrderAssistantGrupoTable from "@/components/SupplierIaOrderAssistantGrupoTable.vue";
import SupplierIaOrderAssistantIndividualTable from "@/components/SupplierIaOrderAssistantIndividualTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();

const statuModule = reactive({ total: 0, items: [] });
// Para vista grupal: grupos con sus productos anidados
const gruposData = reactive({ grupos: [], total_grupos: 0, per_page: 25, current_page: 1, last_page: 1 });

const groups = ref([]);
const laboratories = ref([]);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(25);
const sortBy = ref();
const orderBy = ref();

const selectedLaboratory = ref([]);
const selectedGroup = ref([]);

const tipo_de_vista = ref(false);
const tipo_de_filtracion = ref("combinado");
const lapso_de_tiempo = ref("1 month");
const stock = ref("all");
const con_descuento = ref(true);
const isColombian = ref(false);
const searchQuery = ref("");

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
  if (respuestaApi.status !== 200) {
    toast.error("Error al cargar grupos");
    return;
  }
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
  const resp = await axios.post(
    `/suppliers-ia-order-assistant/filtrar-paginate?page=${page.value}`,
    data,
  );
  if (resp.status !== 200) toast.error("Error al filtrar los datos");
  return { ...resp.data };
}

async function actualizarTabla() {
  loading.value = true;
  try {
    const respuesta = await consultarProductosConPaginacion();
    const paginacion = respuesta.data.paginate;

    if (tipo_de_vista.value) {
      // Vista grupal: el servidor devuelve { grupos, total_grupos, per_page, current_page, last_page }
      gruposData.grupos = paginacion.grupos ?? [];
      gruposData.total_grupos = paginacion.total_grupos ?? 0;
      gruposData.per_page = paginacion.per_page ?? 25;
      gruposData.current_page = paginacion.current_page ?? 1;
      gruposData.last_page = paginacion.last_page ?? 1;
      // Limpiar vista individual
      statuModule.items = [];
      statuModule.total = 0;
    } else {
      // Vista individual: paginator estándar de Laravel
      statuModule.items = paginacion.data ?? [];
      statuModule.total = paginacion.total ?? 0;
      // Limpiar vista grupal
      gruposData.grupos = [];
      gruposData.total_grupos = 0;
    }
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

const onGrupalPageChange = (newPage) => {
  page.value = newPage;
  actualizarTabla();
};

const handleProductScarceToggled = (productId) => {
  if (tipo_de_vista.value) {
    // 1. Encontrar el grupo que contiene el producto
    gruposData.grupos = gruposData.grupos.map(g => {
      if (!g.productos.some(p => p.id === productId)) return g;

      // 2. Remover el producto
      const nuevosProductos = g.productos.filter(p => p.id !== productId);

      // 3. Recalcular suma de promedios para la "preferencia"
      const nuevaSumaPromedio = nuevosProductos.reduce((acc, p) => acc + (parseFloat(p.sales_average) || 0), 0);

      // 4. Actualizar preferencia_product para los productos restantes
      const productosRecalculados = nuevosProductos.map(p => {
        const avg = parseFloat(p.sales_average) || 0;
        return {
          ...p,
          preferencia_product: nuevaSumaPromedio > 0 ? (avg / nuevaSumaPromedio) * 100 : 0
        };
      });

      return {
        ...g,
        productos: productosRecalculados,
      };
    }).filter(g => g.productos.length > 0);
  } else {
    statuModule.items = statuModule.items.filter(item => item.id !== productId);
    statuModule.total -= 1;
  }
};

let filterTimeout = null;
watch(
  [
    selectedLaboratory,
    selectedGroup,
    tipo_de_vista,
    tipo_de_filtracion,
    lapso_de_tiempo,
    stock,
    isColombian,
    searchQuery,
  ],
  () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(async () => {
      page.value = 1;
      await actualizarTabla();
    }, 400); // 400ms de retraso para evitar peticiones masivas
  },
);

let paginationTimeout = null;
watch([page, itemsPerPage, orderBy, sortBy], () => {
  clearTimeout(paginationTimeout);
  paginationTimeout = setTimeout(async () => {
    await actualizarTabla();
  }, 200);
});

function generarPedido() {
  toast.info("Navegando a generar pedido...");
  console.log("[DEBUG] Iniciando generarPedido desde el asistente");
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
      q: searchQuery.value,
    },
  });
}

onMounted(async () => {
  await Promise.all([consultarGruposProductos(), consultarLaboratorios()]);
  await actualizarTabla();
});
</script>

<template>
  <div class="assistant-ia-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Filtros -->
      <SupplierIaOrderAssistantFilter
        class="mb-6"
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
        <!-- Vista Grupal: acordeón con grupos paginados por el servidor -->
        <SupplierIaOrderAssistantGrupoTable
          v-if="tipo_de_vista == true"
          :grupos="gruposData.grupos"
          :total-grupos="gruposData.total_grupos"
          :per-page="gruposData.per_page"
          :current-page="gruposData.current_page"
          :last-page="gruposData.last_page"
          :loading="loading"
          @page-change="onGrupalPageChange"
          @product-scarce-toggled="handleProductScarceToggled"
        />
        <!-- Vista Individual: tabla estándar paginada -->
        <SupplierIaOrderAssistantIndividualTable
          v-else
          :products="statuModule.items"
          :total-product="statuModule.total"
          :loading="loading"
          :items-per-page="itemsPerPage"
          :page="page"
          @update:options="updateTableOptionsTable"
          @refresh="actualizarTabla"
          @product-scarce-toggled="handleProductScarceToggled"
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
