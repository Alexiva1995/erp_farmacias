<script setup lang="js">
import SupplierIaOrderAssistantFilter from '@/components/SupplierIaOrderAssistantFilter.vue';
import SupplierIaOrderAssistantGrupoTable from '@/components/SupplierIaOrderAssistantGrupoTable.vue';
import SupplierIaOrderAssistantIndividualTable from '@/components/SupplierIaOrderAssistantIndividualTable.vue';
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from "vue-router";

const route = useRouter();

const modal = reactive({ statu: false, titulo: "Nuevo" });

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

const selectedLaboratory = ref([]);
const selectedGroup = ref([]);

const tipo_de_vista = ref(false);      // false=individual, true=grupal
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
  const data = {
    laboratoryId: selectedLaboratory.value,
    groups: selectedGroup.value,
    tipo_vista: tipo_de_vista.value,
    tipo_filtracion: tipo_de_filtracion.value,
    lapso_de_tiempo: lapso_de_tiempo.value,
    stock: stock.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  const respuestaApi = await axios.post(`/suppliers-ia-order-assistant/filtrar-paginate?page=${page.value}`, data);
  if (respuestaApi.status !== 200) {
    toast.error("Error al filtrar los datos");
  }
  return { ...respuestaApi.data };
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

watch(
  [selectedLaboratory, selectedGroup, tipo_de_vista, tipo_de_filtracion, lapso_de_tiempo, stock, orderBy, sortBy, page, itemsPerPage],
  async () => { await actualizarTabla(); }
);

function generarPedido() {
  route.push({
    path: "/suppliers/generar-pedido",
    query: {
      con_descuento: con_descuento.value,
      tipo_filtracion: tipo_de_filtracion.value,
      lapso_de_tiempo: lapso_de_tiempo.value,
      laboratoryId: JSON.stringify(selectedLaboratory.value),
      groups: JSON.stringify(selectedGroup.value),
    }
  });
}

async function consultarLaboratorios() {
  const respuesta = await axios.get("/laboratories");
  laboratories.value = respuesta.data;
}

async function consultarGruposProductos() {
  const respuestaApi = await axios.get("/groups/consult-all");
  if (respuestaApi.status !== 200) {
    toast.error("Error al filtrar los datos");
    return;
  }
  groups.value = [...respuestaApi.data.data];
}

// KPIs calculados desde los datos actuales de la página
const kpiNecesitan = computed(() => statuModule.items.filter(p => parseFloat(p.solicitar) > 0).length);
const kpiExceso = computed(() => statuModule.items.filter(p => parseFloat(p.solicitar) < 0).length);
const kpiOk = computed(() => statuModule.items.filter(p => parseFloat(p.solicitar) == 0).length);

onMounted(async () => {
  await consultarGruposProductos();
  await consultarLaboratorios();
  await actualizarTabla();
});
</script>

<template>
  <VContainer fluid class="pa-6">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h4 class="text-h5 font-weight-bold">Asistente Inteligente de Pedidos</h4>
        <p class="text-body-2 text-disabled mb-0">Analiza el inventario y genera órdenes de compra optimizadas con IA</p>
      </div>
      <VChip color="primary" variant="tonal" prepend-icon="tabler-brain">
        IA Activa
      </VChip>
    </div>

    <!-- KPIs resumen rápido -->
    <VRow class="mb-5" no-gutters>
      <VCol cols="12" sm="4" class="pa-2">
        <VCard class="kpi-ia-card" elevation="0">
          <VCardText class="pa-4 d-flex align-center gap-4">
            <VAvatar color="error" variant="tonal" size="44" rounded>
              <VIcon icon="tabler-alert-circle" size="22" />
            </VAvatar>
            <div>
              <div class="text-caption text-disabled text-uppercase font-weight-bold">Necesitan Reposición</div>
              <div class="text-h5 font-weight-black text-error">{{ kpiNecesitan }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4" class="pa-2">
        <VCard class="kpi-ia-card" elevation="0">
          <VCardText class="pa-4 d-flex align-center gap-4">
            <VAvatar color="warning" variant="tonal" size="44" rounded>
              <VIcon icon="tabler-package" size="22" />
            </VAvatar>
            <div>
              <div class="text-caption text-disabled text-uppercase font-weight-bold">Exceso de Stock</div>
              <div class="text-h5 font-weight-black text-warning">{{ kpiExceso }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4" class="pa-2">
        <VCard class="kpi-ia-card" elevation="0">
          <VCardText class="pa-4 d-flex align-center gap-4">
            <VAvatar color="success" variant="tonal" size="44" rounded>
              <VIcon icon="tabler-circle-check" size="22" />
            </VAvatar>
            <div>
              <div class="text-caption text-disabled text-uppercase font-weight-bold">Stock Óptimo</div>
              <div class="text-h5 font-weight-black text-success">{{ kpiOk }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filtros -->
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
    />

    <!-- Tabla -->
    <div class="mt-4">
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
  </VContainer>
</template>

<style scoped>
.kpi-ia-card {
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 8px !important;
  transition: all 0.2s ease;
}

.kpi-ia-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 6%) !important;
  transform: translateY(-2px);
}
</style>
