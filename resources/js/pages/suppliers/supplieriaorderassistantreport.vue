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
  <div>
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
  </div>
</template>

<style scoped>
/* Estilos originales */
</style>
