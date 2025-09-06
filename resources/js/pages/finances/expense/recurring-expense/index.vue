<script setup lang="js">
import ExpenseFormDialoge from '@/components/dialogs/ExpenseFormDialoge.vue';
import FiltrosGastoRecurrente from '@/components/FiltrosGastoRecurrente.vue';
import LoaderComponent from '@/components/LoaderComponent.vue';
import RecurringExpenseTable from '@/components/RecurringExpenseTable.vue';

import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfGastos from '@/utils/pdfGastos';
// import Swal from 'sweetalert2';
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

const formulario= reactive({
  id:null,
  category_id:"",
  amount:"",
  amount_usd:"",
  currency:"",
  has_invoice:false,
  is_deductible:false,
  expense_date:"",
  user_id:"",
  count:"",
})

const formularioError= reactive({
  id:"",
  category_id:"",
  amount:"",
  amount_usd:"",
  currency:"",
  has_invoice:"",
  is_deductible:"",
  expense_date:"",
  user_id:"",
  count:"",
})

const buscardor_filtro= ref("");// nombre, id
const category_id_filtro= ref("");
const currency= ref("");
const fechaDesde_filtro= ref("");
const fechaHasta_filtro= ref("");
const status= "Pending";

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()

function insertarDatosAlFormulario(datos){
  formulario.id=datos.id
  formulario.category_id=datos.category_id
  formulario.amount=datos.amount
  formulario.amount_usd=datos.amount_usd
  formulario.currency=datos.currency
  formulario.has_invoice=datos.has_invoice
  formulario.is_deductible=datos.is_deductible
  formulario.expense_date=datos.expense_date
  formulario.user_id=datos.user_id
  formulario.count=datos.count
}

function limpiarDatosFormulario(){
  formulario.id=null
  formulario.category_id=""
  formulario.amount=""
  formulario.amount_usd=""
  formulario.currency=""
  formulario.has_invoice=false
  formulario.is_deductible=false
  formulario.expense_date=""
  formulario.user_id=""
  formulario.count=""
}

function limpiarErroresFormulario(){
  formularioError.id=""
  formularioError.category_id=""
  formularioError.amount=""
  formularioError.amount_usd=""
  formularioError.currency=""
  formularioError.has_invoice=false
  formularioError.is_deductible=false
  formularioError.expense_date=""
  formularioError.user_id=""
  formularioError.count=""
}

function cargarErrores(errores){
  formularioError.id=(errores.id)?errores.id.join(", "):""
  formularioError.category_id=(errores.identification)?errores.identification.join(", "):""
  formularioError.amount=(errores.identification)?errores.identification.join(", "):""
  formularioError.amount_usd=(errores.identification)?errores.identification.join(", "):""
  formularioError.currency=(errores.identification)?errores.identification.join(", "):""
  formularioError.has_invoice=(errores.identification)?errores.identification.join(", "):""
  formularioError.is_deductible=(errores.identification)?errores.identification.join(", "):""
  formularioError.expense_date=(errores.identification)?errores.identification.join(", "):""
  formularioError.user_id=(errores.identification)?errores.identification.join(", "):""
  formularioError.count=(errores.identification)?errores.identification.join(", "):""
}

function mostarModal(){
  modal.statu=true
  modal.titulo="Nuevo Gasto"
}

function cerrarModal(payload){
  modal.statu=payload
  limpiarDatosFormulario()
  limpiarErroresFormulario()
}



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
      orderBy
  ],
  async () =>{
    actualizarTabla()
  }
)

function enviar(){
  alert("hola")
}

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
}


async function generaPdf(){
  statuModule.loadingApp=true
  const DATA ={
      status,
      buscardor_filtro:buscardor_filtro.value,
      currency:currency.value,
      category_id_filtro:category_id_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value
  }
  let respuestaApi=await axios.post(`/finances/expenses`,DATA)
  if(respuestaApi.status!=200){
     statuModule.loadingApp=false
    toast.error("Error al cargar los gastos")
    return
  }
  console.log("respuesta => ",respuestaApi)
   statuModule.loadingApp=false
  pdfGastos([...respuestaApi.data.data])
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
        fechaHasta_filtro:fechaHasta_filtro.value
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
    let fileName = `gastos-pendientes.${formato}`;
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

onMounted(async () => {
  statuModule.loadingApp=true
  formulario.user_id=1
  let categorias=await consultarCategorias()
  await actualizarTabla()
  statuModule.categorias=categorias
  statuModule.loadingApp=false
})
</script>
<template>
  <LoaderComponent :loadingApp="statuModule.loadingApp" />
  <div>
    <FiltrosGastoRecurrente
      v-model:currency="currency"
      v-model:buscardor_filtro="buscardor_filtro"
      v-model:category_id_filtro="category_id_filtro"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      :categorias="statuModule.categorias"
      @export-excel="exportarExcel"
      @export-pdf="generaPdf"
      @clear="limpliarFiltros"
      @add="mostarModal"
    />
    <ExpenseFormDialoge
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      @modal-close="cerrarModal"
      @clear-error-form="limpiarErroresFormulario"
      @save="enviar"
    />
    <VCard title="Gastos">
      <VDivider />
      <RecurringExpenseTable
        :items="statuModule.items"
        :total="statuModule.total"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @update:options="updateTableOptions"
      />
    </VCard>
  </div>
</template>
