<script setup lang="js">
import SupplierAssistantReportTable from '@/components/SupplierAssistantReportTable.vue';
import SupplierIaOrderAssistantReportFilter from '@/components/SupplierIaOrderAssistantReportFilter.vue';
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";
import { onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from "vue-router";
const router= useRouter()

const statuModule= reactive({
  data:{},
  items:[],
  total:0,
})

const laboratories = ref([]);
const productos = ref([]);
const productosSelect = ref([]);

const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const selectedLaboratory = ref();
const selectProducts= ref();
const checkColombia= ref(false);

const tipo_de_filtracion= ref("sales");// promedio o ventas
const lapso_de_tiempo= ref("3 month");// tiempo
const stock= ref("all");// Fallas , Execeso o All

// KPIs globales
const loadingStats = ref(false);
const kpiGlobal = reactive({ necesitan: 0, exceso: 0, ok: 0 });

// Obtiene KPIs de todos los productos (sin paginar)
async function consultarKpisGlobales() {
  loadingStats.value = true;
  try {
    const data = {
      itemsPerPage: 99999, // traer todos para contar
      page: 1,
      orderBy: orderBy.value,
      sortBy: sortBy.value,
      product: selectProducts.value,
      laboratoryId: selectedLaboratory.value,
      is_colombia: checkColombia.value,
      lapso_de_tiempo: lapso_de_tiempo.value,
      tipo_filtracion: tipo_de_filtracion.value,
      stock: stock.value,
    };
    const resp = await axios.post('/suppliers-ia-assistant-report/filtrar-paginate?page=1', data);
    const items = resp.data?.data?.paginate?.data || [];
    kpiGlobal.necesitan = items.filter(p => roundIaAnalysis(p.solicitar) > 0).length;
    kpiGlobal.exceso    = items.filter(p => roundIaAnalysis(p.solicitar) < 0).length;
    kpiGlobal.ok        = items.filter(p => roundIaAnalysis(p.solicitar) == 0).length;
  } catch (e) {
    console.error('Error al cargar KPIs globales:', e);
  } finally {
    loadingStats.value = false;
  }
}

async function consultarDataReport(){
  let data={
    itemsPerPage:itemsPerPage.value,
    page:page.value,
    orderBy:orderBy.value,
    sortBy:sortBy.value,
    product:selectProducts.value,
    laboratoryId:selectedLaboratory.value,
    is_colombia:checkColombia.value,
    lapso_de_tiempo:lapso_de_tiempo.value,
    tipo_filtracion:tipo_de_filtracion.value,
    stock:stock.value,
  }

  let respuestaApi= await axios.post(`suppliers-ia-assistant-report/filtrar-paginate?page=${page.value}`,data)
  if(respuestaApi.status!=200){
    toast.error("error al filtrar")
  }
  console.log("data filtro => ",respuestaApi)
  return {...respuestaApi.data}
}

async function consultarProductos(){
  let respuestaApi= await axios.get("suppliers-ia-assistant-report/consult-products")
  if(respuestaApi.status!=200){
    toast.error("Error al consultar los productos")
  }
  console.log("list products => ",respuestaApi)
  return [...respuestaApi.data.data]
}

async function consultarLaboratorios(){
  let respuesta=await axios.get("/laboratories")
  laboratories.value = respuesta.data;
}

const handleClearFilters = () => {
  tipo_de_filtracion.value = "sales";
  lapso_de_tiempo.value = "3 month";
  selectedLaboratory.value = [];
  selectProducts.value = [];
};

const updateTableOptionsTable = options => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

// Watchers con debounce para filtros
let filterTimeout = null;
watch([
  checkColombia,
  selectProducts,
  selectedLaboratory,
  tipo_de_filtracion,
  lapso_de_tiempo,
  stock,
], async () => {
  // Solo cargar si hay laboratorios seleccionados
  if (!selectedLaboratory.value || selectedLaboratory.value.length === 0) {
    statuModule.items = [];
    statuModule.total = 0;
    kpiGlobal.necesitan = 0;
    kpiGlobal.exceso = 0;
    kpiGlobal.ok = 0;
    return;
  }

  clearTimeout(filterTimeout);
  filterTimeout = setTimeout(async () => {
    loading.value = true;
    page.value = 1; // reset a la primera página si cambian filtros
    
    await Promise.all([
      consultarKpisGlobales(),
      (async () => {
        statuModule.data = await consultarDataReport();
        statuModule.total = statuModule.data.data.total;
        statuModule.items = [...statuModule.data.data.data];
      })()
    ]);
    
    loading.value = false;
  }, 400);
});

// Watch para paginación y ordenamiento
watch([page, itemsPerPage, orderBy, sortBy], async () => {
  if (!selectedLaboratory.value || selectedLaboratory.value.length === 0) return;

  loading.value = true;
  statuModule.data = await consultarDataReport();
  statuModule.total = statuModule.data.data.total;
  statuModule.items = [...statuModule.data.data.data];
  loading.value = false;
});


async function filtrarSinPaginar(dataFiltro){
  let respuestaApi = await axios.post(`/suppliers-ia-assistant-report/filtrar-without-paginate`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  console.log("respues api => ",respuestaApi)

  return [...respuestaApi.data.data]
}

async function generarPdf(){
//  alert("desuwa")
  let filtros={
    orderBy:orderBy.value,
    sortBy:sortBy.value,
    product:selectProducts.value,
    laboratoryId:selectedLaboratory.value,
    is_colombia:checkColombia.value,
    lapso_de_tiempo:lapso_de_tiempo.value,
    tipo_filtracion:tipo_de_filtracion.value,
    stock:stock.value,
  }

  let respuestaApi=await filtrarSinPaginar(filtros)

    if(respuestaApi.length==0){
    toast.info("No hay data para generar un reporte")
    return null;
  }

  console.log("data pdf => ",respuestaApi)

  pdfProductsAssistantReportGenerator(respuestaApi)
}

async function exportarExcel(formato){

  try{
      let params={
        orderBy:orderBy.value,
        sortBy:sortBy.value,
        product:selectProducts.value,
        laboratoryId:selectedLaboratory.value,
        is_colombia:checkColombia.value,
        lapso_de_tiempo:lapso_de_tiempo.value,
        tipo_filtracion:tipo_de_filtracion.value,
        stock:stock.value,
        formato
    }

    let respuestaApi = await axios.post(
      '/suppliers-ia-assistant-report/exportar/excel',
      params,  // Tus parámetros como objeto
      {
        responseType: 'blob',
        headers: {
          'Content-Type': 'application/json',  // Asegura el envío correcto de los parámetros
        }
      }
    );

    console.log("res => ",respuestaApi)

    if(respuestaApi.status!=200){
      toast.success("Error al filtrar los datos")
    }
    const url = window.URL.createObjectURL(new Blob([respuestaApi.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = respuestaApi.headers["content-disposition"];
    let fileName = `assistant-report.${formato}`;
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


onMounted(async () => {
  loading.value = true;
  
  // Cargar catálogos iniciales
  await Promise.all([
    consultarProductos().then(res => {
      productos.value = res;
      productosSelect.value = res.map(p => ({
        name: `${p.id} - ${p.name}`,
        id: p.id,
      }));
    }),
    consultarLaboratorios()
  ]);

  loading.value = false;
});
</script>
<template>
  <VContainer fluid class="pa-6">
    <!-- Header Premium -->
    <div class="premium-header mb-8 rounded-xl overflow-hidden shadow-sm">
      <div class="header-gradient pa-8 relative-content">
        <div class="d-flex align-center gap-4">
          <VAvatar color="white" variant="flat" size="56" class="shadow-sm rounded-xl">
            <VIcon icon="tabler-robot" color="primary" size="32" />
          </VAvatar>
          <div>
            <div class="text-caption text-white opacity-80 font-weight-bold text-uppercase tracking-wider mb-1">
              Suministros • Inteligencia Artificial
            </div>
            <h1 class="text-h3 font-weight-black text-white leading-none">Asistente de Pedidos</h1>
          </div>
        </div>
      </div>
    </div>

    <!-- KPIs Globales Premium -->
    <VRow class="mb-8 overflow-hidden">
      <VCol v-for="kpi in [
        { title: 'Necesitan Reposición', value: kpiGlobal.necesitan, icon: 'tabler-alert-triangle', color: 'error', desc: 'Productos bajo stock' },
        { title: 'Exceso de Stock', value: kpiGlobal.exceso, icon: 'tabler-package', color: 'warning', desc: 'Optimización requerida' },
        { title: 'Stock Óptimo', value: kpiGlobal.ok, icon: 'tabler-circle-check', color: 'success', desc: 'Inventario saludable' }
      ]" :key="kpi.title" cols="12" sm="4">
        <VCard class="stats-card h-full rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
          <!-- Decoración de fondo -->
          <div class="card-bg-decoration">
            <VIcon :icon="kpi.icon" />
          </div>

          <VCardText class="pa-5 relative-content">
            <div class="d-flex align-center justify-space-between mb-4">
              <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="elevation-1">
                <VIcon :icon="kpi.icon" size="26" />
              </VAvatar>

              <div class="text-right">
                <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important;">{{ kpi.title }}</span>
                <h4 class="text-h4 font-weight-black mt-1" :class="`text-${kpi.color}`">
                  <template v-if="loadingStats">
                    <VProgressCircular size="24" width="3" indeterminate :color="kpi.color" />
                  </template>
                  <template v-else>{{ kpi.value }}</template>
                </h4>
              </div>
            </div>

            <VDivider class="mb-3 opacity-20" />

            <div class="d-flex align-center justify-space-between">
              <span class="text-caption font-weight-medium text-medium-emphasis">{{ kpi.desc }}</span>
              <VIcon icon="tabler-trending-up" size="16" :color="kpi.color" class="opacity-50" />
            </div>
          </VCardText>
          <div class="accent-border" :style="{ backgroundColor: `rgb(var(--v-theme-${kpi.color}))` }"></div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filtros -->
    <SupplierIaOrderAssistantReportFilter
      v-model:selectProducts="selectProducts"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:tipo_de_filtracion="tipo_de_filtracion"
      v-model:lapso_de_tiempo="lapso_de_tiempo"
      v-model:checkColombia="checkColombia"
      :checkColombia="checkColombia"
      :products="productosSelect"
      :laboratories="laboratories"
      :tipo_de_filtracion="tipo_de_filtracion"
      :lapso_de_tiempo="lapso_de_tiempo"
      @clear="handleClearFilters"
      @export-pdf="generarPdf"
      @export-excel="exportarExcel"
    />

    <!-- Tabla -->
    <SupplierAssistantReportTable
      :products="statuModule.items"
      :total-product="statuModule.total"
      :loading="loading"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptionsTable"
    />
  </VContainer>
</template>

<style scoped>
.header-gradient {
  position: relative;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #2c3e50 100%);
}

.premium-header {
  position: relative;
}

.stats-card {
  position: relative;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stats-card:hover {
  box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 15%) !important;
  transform: translateY(-4px);
}

.card-bg-decoration {
  position: absolute;
  inset-block-start: -20px;
  inset-inline-end: -20px;
  opacity: 0.03;
  pointer-events: none;
  transform: rotate(-15deg);
}

.card-bg-decoration .v-icon {
  font-size: 120px !important;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 4px;
  inset-block-end: 0;
  inset-inline: 0;
  opacity: 0.8;
}

.tracking-wider {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1;
}

.gap-4 {
  gap: 16px;
}
</style>
