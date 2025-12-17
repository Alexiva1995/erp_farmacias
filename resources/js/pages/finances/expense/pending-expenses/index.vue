<script setup lang="js">
import FiltrosGastos from '@/components/FiltrosGastos.vue';
import LoaderComponent from '@/components/LoaderComponent.vue';
import PendingExpenseTable from '@/components/PendingExpenseTable.vue';
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfGastos from '@/utils/pdfGastos';
import Swal from "sweetalert2";
import { onMounted, reactive, watch } from 'vue';
// import { useRouter } from "vue-router";
// const route= useRouter()

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const statuModule= reactive({
  items:[],
  total:0,
  categorias:[],
  loadingApp:false
})

const buscardor_filtro= ref("");// nombre, id
const category_id_filtro= ref("");
const currency= ref("");
const fechaDesde_filtro= ref("");
const fechaHasta_filtro= ref("");
const type_of_expense = ["Normal","Recurrente"];
const status= ["Pending"];

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()

const isDeductible = ref(false);
const hasInvoice = ref(false);

watch(
    [
      buscardor_filtro,
      category_id_filtro,
      currency,
      fechaDesde_filtro,
      fechaHasta_filtro,
      page,
      itemsPerPage,
      sortBy,
      orderBy,
      isDeductible,
      hasInvoice
  ],
  async () =>{
    actualizarTabla()
  }
)

async function consultarCategorias(){
  let respuestaApi=await axios.get("/finances/expenses/category")
  if(respuestaApi.status!=200){
    toast.error("Error al cargar las categorias de los gastos")
  }
  return [...respuestaApi.data.data]
}

async function consultarGastos(){
  const DATA ={
    status,
    buscardor_filtro:buscardor_filtro.value,
    currency:currency.value,
    category_id_filtro:category_id_filtro.value,
    fechaDesde_filtro:fechaDesde_filtro.value,
    fechaHasta_filtro:fechaHasta_filtro.value,
    page:page.value,
    itemsPerPage:itemsPerPage.value,
    sortBy:sortBy.value,
    orderBy:orderBy.value,
    type_of_expense:type_of_expense,
    isDeductible: isDeductible.value,
    hasInvoice: hasInvoice.value,
  }
  let respuestaApi=await axios.post(`/finances/expenses/filter-paginate?page=${page.value}`,DATA)
  if(respuestaApi.status!=200){
    toast.error("Error al cargar los gastos")
  }
  console.log("respuesta => ",respuestaApi)
  return {...respuestaApi.data.data}
}

async function actualizarTabla(){
  loading.value=true
  let gastosPaginate=await consultarGastos()
  statuModule.items=gastosPaginate.data
  statuModule.total=gastosPaginate.total
  loading.value=false
}

const updateTableOptions = options => {
  // console.log(options)
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

function limpliarFiltros(){
  buscardor_filtro.value=""
  currency.value=""
  category_id_filtro.value=""
  fechaDesde_filtro.value=""
  fechaHasta_filtro.value=""
  isDeductible.value=false
  hasInvoice.value=false
}

async function generaPdf(){
  statuModule.loadingApp=true
  const DATA ={
      status,
      buscardor_filtro:buscardor_filtro.value,
      currency:currency.value,
      category_id_filtro:category_id_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value,
      type_of_expense:type_of_expense,
  }
  let respuestaApi=await axios.post(`/finances/expenses`,DATA)
  if(respuestaApi.status!=200){
     statuModule.loadingApp=false
    toast.error("Error al cargar los gastos")
    return
  }
  console.log("respuesta => ",respuestaApi)
  statuModule.loadingApp=false
  pdfGastos([...respuestaApi.data.data],"Gastos Pendientes")
}

async function exportarExcel(formato){
  try{
    statuModule.loadingApp=true
    let params={
        formato,
        status,
        buscardor_filtro:buscardor_filtro.value,
        currency:currency.value,
        category_id_filtro:category_id_filtro.value,
        fechaDesde_filtro:fechaDesde_filtro.value,
        fechaHasta_filtro:fechaHasta_filtro.value,
        type_of_expense:type_of_expense,
    }

    let respuestaApi = await axios.post(
      '/finances/expenses/exportar/excel',
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
    let fileName = `gastos_pendientes.${formato}`;
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
    statuModule.loadingApp=false
  } catch (error) {
    console.error("Error al exportar los datos:", error);
    statuModule.loadingApp=false
  }

}

function confirmarEstado(payload){
 const statusText = payload.status == 'Cancelled' ? 'Cancelar': 'Aprobar'
  Swal.fire({
    title: "¿Estás seguro?",
    text: `¡Desea ${statusText} el estado del producto!`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Continuar",
    cancelButtonText: "Cancelar",
  }).then(async (result) => {
    if (result.isConfirmed) {
      cambiarEstado(payload)
    }
  });
}

async function cambiarEstado(payload){
const statusText = payload.status == 'Cancelled' ? 'Cancelado': 'Aprobado'
  let respuestaApi=await axios.post("/finances/expenses/change-status",payload)
  if(respuestaApi.status!=200){
    toast.error("Error al cambiar el estado del gasto")
    return
  }

  statuModule.loadingApp=true
  await actualizarTabla()
  statuModule.loadingApp=false
  toast.success(`El gasto fue ${statusText}`)
}

onMounted(async () => {
  statuModule.loadingApp=true
  let categorias=await consultarCategorias()
  await actualizarTabla()
  statuModule.categorias=categorias
  statuModule.loadingApp=false
})
</script>
<template>
  <LoaderComponent :loadingApp="statuModule.loadingApp" />
  <div>
    <FiltrosGastos
      v-model:currency="currency"
      v-model:buscardor_filtro="buscardor_filtro"
      v-model:category_id_filtro="category_id_filtro"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      v-model:isDeductible="isDeductible"
      v-model:hasInvoice="hasInvoice"
      :categorias="statuModule.categorias"
      :show-add-button="false" 
      @export-excel="exportarExcel"
      @export-pdf="generaPdf"
      @clear="limpliarFiltros"
    />
    <VCard title="Gastos Pendientes">
      <VDivider />
      <PendingExpenseTable
        :items="statuModule.items"
        :total="statuModule.total"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @update:options="updateTableOptions"
        @cambiarEstado="confirmarEstado"
      />
    </VCard>
  </div>
</template>
