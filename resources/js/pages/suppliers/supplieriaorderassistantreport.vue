<script setup lang="js">
import SupplierAssistantReportTable from '@/components/SupplierAssistantReportTable.vue';
import SupplierIaOrderAssistantReportFilter from '@/components/SupplierIaOrderAssistantReportFilter.vue';
import pdfProductsAssistantReportGenerator from '@/utils/pdfProductsAssistantReportGenerator';
import pdfSupplierOrderReportGenerator from '@/utils/pdfSupplierOrderReportGenerator';
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from 'sweetalert2';
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";
import { onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from "vue-router";
const router = useRouter()

const reportState = reactive({
  data: {},
  items: [],
  total: 0,
})

const laboratories = ref([]);
const productos = ref([]);
const productosSelect = ref([]);

const loading = ref(false);
const generatingPdf = ref(false);
const exportingExcel = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("solicitar");
const orderBy = ref("desc");

const selectedLaboratory = ref([]);
const selectProducts = ref([]);
const checkColombia = ref(false);

const tipo_de_filtracion = ref("combinado"); // promedio o ventas o combinado
const lapso_de_tiempo = ref("3 month");// tiempo
const stock = ref("all");// Fallas , Exceso o All
const showIgnored = ref(false);
const showGraphs = ref(false);

const suppliers = ref([]);
const selectedSupplierId = ref(null);
const globalDiscountPercent = ref(0);

// KPIs globales
const loadingStats = ref(false);
const kpiGlobal = reactive({ necesitan: 0, exceso: 0, ok: 0 });

// Obtiene KPIs de todos los productos (sin paginar)
async function consultarKpisGlobales() {
  loadingStats.value = true;
  try {
    const data = {
      product: selectProducts.value,
      laboratoryId: selectedLaboratory.value,
      is_colombia: checkColombia.value,
      lapso_de_tiempo: lapso_de_tiempo.value,
      tipo_filtracion: tipo_de_filtracion.value,
      stock: stock.value,
      show_ignored: showIgnored.value,
    };
    
    const resp = await axios.post('/suppliers-ia-assistant-report/stats', data);
    const stats = resp.data?.data || { necesitan: 0, exceso: 0, ok: 0 };
    
    kpiGlobal.necesitan = stats.necesitan;
    kpiGlobal.exceso    = stats.exceso;
    kpiGlobal.ok        = stats.ok;
  } catch (e) {
    console.error('Error al cargar KPIs globales:', e);
  } finally {
    loadingStats.value = false;
  }
}

async function consultarDataReport(){
  let data = {
    itemsPerPage: itemsPerPage.value,
    page: page.value,
    orderBy: orderBy.value,
    sortBy: sortBy.value,
    product: selectProducts.value,
    laboratoryId: selectedLaboratory.value,
    is_colombia: checkColombia.value,
    lapso_de_tiempo: lapso_de_tiempo.value,
    tipo_filtracion: tipo_de_filtracion.value,
    stock: stock.value,
    show_ignored: showIgnored.value,
    with_trend: showGraphs.value,
  }

  let respuestaApi = await axios.post(`suppliers-ia-assistant-report/filtrar-paginate?page=${page.value}`, data)
  if(respuestaApi.status != 200){
    toast.error("Error al filtrar")
  }
  return { ...respuestaApi.data }
}

async function consultarProductos(){
  let respuestaApi = await axios.get("suppliers-ia-assistant-report/consult-products")
  if(respuestaApi.status != 200){
    toast.error("Error al consultar los productos")
  }
  return [...respuestaApi.data.data]
}

async function consultarLaboratorios(){
  let respuesta = await axios.get("/laboratories")
  laboratories.value = respuesta.data;
}

async function consultarProveedores() {
  try {
    const respuesta = await axios.get("/suppliers");
    suppliers.value = respuesta.data.data || respuesta.data || [];
  } catch (error) {
    console.error("Error al cargar proveedores:", error);
  }
}

const handleClearFilters = () => {
  tipo_de_filtracion.value = "sales";
  lapso_de_tiempo.value = "3 month";
  selectedLaboratory.value = [];
  selectProducts.value = [];
  showIgnored.value = false;
  showGraphs.value = false;
};

const handleClearIgnore = async () => {
  const confirmResult = await Swal.fire({
    title: '¿Estás seguro?',
    text: "Esto restaurará todos los productos que fueron marcados para ignorar hasta una fecha específica.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Sí, restaurar!',
    cancelButtonText: 'Cancelar'
  });

  if (confirmResult.isConfirmed) {
    loading.value = true;
    try {
      await axios.post('/suppliers-ia-assistant-report/clear-ignore-until');
      toast.success("Todos los productos han sido restaurados.");
      await Promise.all([
        consultarKpisGlobales(),
        (async () => {
          reportState.data = await consultarDataReport();
          reportState.total = reportState.data.data.total;
          reportState.items = [...reportState.data.data.data];
        })()
      ]);
    } catch (error) {
      console.error("Error al restaurar:", error);
      toast.error("Ocurrió un error al restaurar los productos.");
    } finally {
      loading.value = false;
    }
  }
};

const updateTableOptionsTable = options => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key
    orderBy.value = options.sortBy[0].order
  } else {
    sortBy.value = "solicitar"
    orderBy.value = "desc"
  }
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
  showIgnored,
  showGraphs,
], async () => {
  if (!selectedLaboratory.value || selectedLaboratory.value.length === 0) {
    reportState.items = [];
    reportState.total = 0;
    kpiGlobal.necesitan = 0;
    kpiGlobal.exceso = 0;
    kpiGlobal.ok = 0;
    return;
  }

  clearTimeout(filterTimeout);
  filterTimeout = setTimeout(async () => {
    loading.value = true;
    page.value = 1;
    
    await Promise.all([
      consultarKpisGlobales(),
      (async () => {
        reportState.data = await consultarDataReport();
        reportState.total = reportState.data.data.total;
        reportState.items = [...reportState.data.data.data];
      })()
    ]);
    
    loading.value = false;
  }, 400);
});

// Watch para paginación y ordenamiento
watch([page, itemsPerPage, orderBy, sortBy], async () => {
  if (!selectedLaboratory.value || selectedLaboratory.value.length === 0) return;

  loading.value = true;
  reportState.data = await consultarDataReport();
  reportState.total = reportState.data.data.total;
  reportState.items = [...reportState.data.data.data];
  loading.value = false;
});

async function filtrarSinPaginar(dataFiltro){
  let respuestaApi = await axios.post(`/suppliers-ia-assistant-report/filtrar-without-paginate`, dataFiltro)
  if(respuestaApi.status != 200){
    toast.error("Error al filtrar los datos")
  }
  return [...respuestaApi.data.data]
}

async function generarPdf(){
  generatingPdf.value = true;
  try {
    let filtros = {
      orderBy: orderBy.value,
      sortBy: sortBy.value,
      product: selectProducts.value,
      laboratoryId: selectedLaboratory.value,
      is_colombia: checkColombia.value,
      lapso_de_tiempo: lapso_de_tiempo.value,
      tipo_filtracion: tipo_de_filtracion.value,
      stock: stock.value,
      show_ignored: showIgnored.value,
      with_trend: showGraphs.value,
    }

    let respuestaApi = await filtrarSinPaginar(filtros)

    if(respuestaApi.length == 0){
      toast.info("No hay data para generar un reporte")
      return null;
    }

    pdfProductsAssistantReportGenerator(respuestaApi)
    pdfSupplierOrderReportGenerator(respuestaApi)
    toast.success("PDFs generados correctamente.")
  } catch (error) {
    console.error("Error al generar PDF:", error);
    toast.error("Error al generar los archivos PDF.");
  } finally {
    generatingPdf.value = false;
  }
}

async function exportarExcel(formato){
  exportingExcel.value = true;
  try{
    let params = {
      orderBy: orderBy.value,
      sortBy: sortBy.value,
      product: selectProducts.value,
      laboratoryId: selectedLaboratory.value,
      is_colombia: checkColombia.value,
      lapso_de_tiempo: lapso_de_tiempo.value,
      tipo_filtracion: tipo_de_filtracion.value,
      stock: stock.value,
      show_ignored: showIgnored.value,
      with_trend: showGraphs.value,
      formato
    }

    let respuestaApi = await axios.post(
      '/suppliers-ia-assistant-report/exportar/excel',
      params,
      {
        responseType: 'blob',
        headers: {
          'Content-Type': 'application/json',
        }
      }
    );

    if(respuestaApi.status != 200){
      toast.error("Error al exportar los datos")
      return;
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
    toast.success("Excel exportado correctamente.");
  } catch (error) {
    console.error("Error al exportar los datos:", error);
    toast.error("Error al exportar el archivo Excel.");
  } finally {
    exportingExcel.value = false;
  }
}

onMounted(async () => {
  loading.value = true;
  
  await Promise.all([
    consultarProductos().then(res => {
      productos.value = res;
      productosSelect.value = res.map(p => ({
        name: `${p.id} - ${p.name}`,
        id: p.id,
      }));
    }),
    consultarLaboratorios(),
    consultarProveedores()
  ]);

  loading.value = false;
});
</script>

<template>
  <div>
    <!-- Filtros -->
    <SupplierIaOrderAssistantReportFilter
      v-model:selectProducts="selectProducts"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:tipo_de_filtracion="tipo_de_filtracion"
      v-model:lapso_de_tiempo="lapso_de_tiempo"
      v-model:checkColombia="checkColombia"
      v-model:showIgnored="showIgnored"
      v-model:showGraphs="showGraphs"
      v-model:stock="stock"
      :checkColombia="checkColombia"
      :products="productosSelect"
      :laboratories="laboratories"
      :tipo_de_filtracion="tipo_de_filtracion"
      :lapso_de_tiempo="lapso_de_tiempo"
      :generating-pdf="generatingPdf"
      :exporting-excel="exportingExcel"
      @clear="handleClearFilters"
      @clear-ignore="handleClearIgnore"
      @export-pdf="generarPdf"
      @export-excel="exportarExcel"
      v-model:selectedSupplierId="selectedSupplierId"
      v-model:globalDiscountPercent="globalDiscountPercent"
      :suppliers="suppliers"
    />

    <!-- KPIs Globales -->
    <VRow class="mb-6">
      <VCol cols="12" sm="4">
        <VCard class="border shadow-sm overflow-hidden" elevation="0">
          <VCardText class="pa-4 d-flex align-center">
            <div class="d-flex align-center justify-center rounded-lg bg-light-error pa-3 me-4">
              <VIcon icon="tabler-alert-triangle" color="error" size="24" />
            </div>
            <div>
              <span class="text-xs text-disabled text-uppercase font-weight-bold">Fallas Detectadas</span>
              <VSkeletonLoader v-if="loadingStats" type="text" width="60" height="24" class="mt-1" />
              <h3 v-else class="text-h4 font-weight-black text-error mt-1">{{ kpiGlobal.necesitan }}</h3>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard class="border shadow-sm overflow-hidden" elevation="0">
          <VCardText class="pa-4 d-flex align-center">
            <div class="d-flex align-center justify-center rounded-lg bg-light-warning pa-3 me-4">
              <VIcon icon="tabler-trending-up" color="warning" size="24" />
            </div>
            <div>
              <span class="text-xs text-disabled text-uppercase font-weight-bold">Stock Excedente</span>
              <VSkeletonLoader v-if="loadingStats" type="text" width="60" height="24" class="mt-1" />
              <h3 v-else class="text-h4 font-weight-black text-warning mt-1">{{ kpiGlobal.exceso }}</h3>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard class="border shadow-sm overflow-hidden" elevation="0">
          <VCardText class="pa-4 d-flex align-center">
            <div class="d-flex align-center justify-center rounded-lg bg-light-success pa-3 me-4">
              <VIcon icon="tabler-circle-check" color="success" size="24" />
            </div>
            <div>
              <span class="text-xs text-disabled text-uppercase font-weight-bold">Productos al Día</span>
              <VSkeletonLoader v-if="loadingStats" type="text" width="60" height="24" class="mt-1" />
              <h3 v-else class="text-h4 font-weight-black text-success mt-1">{{ kpiGlobal.ok }}</h3>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla -->
    <SupplierAssistantReportTable
      :products="reportState.items"
      :total-product="reportState.total"
      :loading="loading"
      :items-per-page="itemsPerPage"
      :page="page"
      :showGraphs="showGraphs"
      :sort-by="[{ key: sortBy, order: orderBy }]"
      :selectedSupplierId="selectedSupplierId"
      :globalDiscountPercent="globalDiscountPercent"
      @update:options="updateTableOptionsTable"
    />
  </div>
</template>

<style scoped>
.bg-light-error {
  background-color: rgba(var(--v-theme-error), 0.1);
}
.bg-light-warning {
  background-color: rgba(var(--v-theme-warning), 0.1);
}
.bg-light-success {
  background-color: rgba(var(--v-theme-success), 0.1);
}
</style>

