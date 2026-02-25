<script setup lang="js">
// import Swal from 'sweetalert2';
// import { useRouter } from "vue-router";

// const route= useRouter()

/*const modal= reactive({
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
  name:"",
  category_id:"",
  amount:"",
  amount_usd:"",
  amount_bs:"",
  currency:"USD",
  has_invoice:false,
  is_deductible:false,
  iva:false,
  // expense_date:"",
  user_id:"",
  count:"",
  // file_factura:null,
  recurrence:"Mensual",
})

const formularioError= reactive({
  id:"",
  name:"",
  category_id:"",
  amount:"",
  amount_usd:"",
  amount_bs:"",
  currency:"",
  has_invoice:"",
  is_deductible:"",
  iva:"",
  // expense_date:null,
  count:"",
  // file_factura:null,
  recurrence:"",
})

const buscardor_filtro= ref("");// nombre, id
const category_id_filtro= ref("");
const currency= ref("");
const fechaDesde_filtro= ref("");
const fechaHasta_filtro= ref("");
const status= ["Approved","Cancelled"];
const type_of_expense = ["Recurrente"];

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()
const isDeductible = ref(false);
const hasInvoice = ref(false);

function insertarDatosAlFormulario(datos){
  formulario.id=datos.id
  formulario.id=datos.name
  formulario.category_id=datos.category_id
  formulario.amount=datos.amount
  formulario.amount_usd=datos.amount_usd
  formulario.amount_bs=datos.amount_bs
  formulario.currency=datos.currency
  formulario.has_invoice=datos.has_invoice
  formulario.is_deductible=datos.is_deductible
  formulario.iva=datos.iva
  // formulario.expense_date=datos.expense_date
  formulario.count=datos.count
  formulario.recurrence=datos.recurrence

}

function limpiarDatosFormulario(){
  formulario.id=null
  formulario.name=""
  formulario.category_id=""
  formulario.amount=""
  formulario.amount_usd=""
  formulario.amount_bs=""
  formulario.currency="BS"
  formulario.has_invoice=false
  formulario.is_deductible=false
  formulario.iva=false
  // formulario.expense_date=""
  formulario.count=""
  // formulario.file_factura=null
  formulario.recurrence="Mensual"

}

function limpiarErroresFormulario(){
  formularioError.id=""
  formularioError.name=""
  formularioError.category_id=""
  formularioError.amount=""
  formularioError.amount_usd=""
  formularioError.amount_bs=""
  formularioError.currency=""
  formularioError.has_invoice=false
  formularioError.is_deductible=false
  formularioError.iva=false
  // formularioError.expense_date=""
  formularioError.count=""
  formularioError.recurrence=""
  // formularioError.file_factura=""
}

function cargarErrores(errores){
  formularioError.id=(errores.id)?errores.id.join(", "):""
  formularioError.name=(errores.name)?errores.name.join(", "):""
  formularioError.category_id=(errores.category_id)?errores.category_id.join(", "):""
  formularioError.amount=(errores.amount)?errores.amount.join(", "):""
  formularioError.amount_usd=(errores.amount_usd)?errores.amount_usd.join(", "):""
  formularioError.amount_bs=(errores.amount_bs)?errores.amount_bs.join(", "):""
  formularioError.currency=(errores.currency)?errores.currency.join(", "):""
  formularioError.has_invoice=(errores.has_invoice)?errores.has_invoice.join(", "):""
  formularioError.is_deductible=(errores.is_deductible)?errores.is_deductible.join(", "):""
  formularioError.iva=(errores.iva)?errores.iva.join(", "):""
  // formularioError.expense_date=(errores.expense_date)?errores.expense_date.join(", "):""
  formularioError.count=(errores.count)?errores.count.join(", "):""
  formularioError.recurrence=(errores.recurrence)?errores.recurrence.join(", "):""
  // formularioError.file_factura=(errores.file_factura)?errores.file_factura.join(", "):""
}

function mostarModal(){
  modal.statu=true
  modal.titulo="Añadir Nuevo Gasto Recurrente"
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
      orderBy,
      isDeductible,
      hasInvoice
  ],
  async () =>{
    actualizarTabla()
  }
)

async function enviar(payload){
  try {
    statuModule.loadingApp=true
    let respuesApi=await axios.post("/finances/expenses/create-recurrence",payload)
    if(respuesApi.status==200 && payload.has_invoice==false){
      toast.success("El gasto se ha guardado correctamente")
      cerrarModal(false)
      await actualizarTabla()
      statuModule.loadingApp=false
    }
    console.log("respuesta api gasto => ",respuesApi.data.data)
    let gasto=respuesApi.data.data

    // if(payload.has_invoice==true){
    //   let data=new FormData()
    //   data.append("id",gasto.id)
    //   // data.append("file_invoice",payload.file_factura)

    //   let config= {
    //     headers: {
    //       'Content-Type': 'multipart/form-data',
    //     },
    //   }

    //   let respuesApiFileUploaa=await axios.post("/finances/expenses/upload-file-invoice",data,config)
    //   if(respuesApiFileUploaa.status==200){
    //     toast.success("El archivo de la factura a sido guardado correctamente")
    //     cerrarModal(false)
    //     await actualizarTabla()
    //     statuModule.loadingApp=false
    //   }
    // }

  } catch (error) {
    statuModule.loadingApp=false
    toast.error("Error al crear el gasto")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
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
})*/
</script>

<template>
  <!-- 
<LoaderComponent :loadingApp="statuModule?.loadingApp" />
  <div>
    <FiltrosGastoRecurrente
      v-model:currency="currency"
      v-model:buscardor_filtro="buscardor_filtro"
      v-model:category_id_filtro="category_id_filtro"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      v-model:isDeductible="isDeductible"
      v-model:hasInvoice="hasInvoice"
      :categorias="statuModule?.categorias"
      @export-excel="exportarExcel"
      @export-pdf="generaPdf"
      @clear="limpliarFiltros"
      @add="mostarModal"
    />
    <ExpenseFormDialoge
      type_of_expense="recurrente"
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      :categorias="statuModule.categorias"
      @modal-close="cerrarModal"
      @clear-error-form="limpiarErroresFormulario"
      @save="enviar"
    />
    <VCard>
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
  </div>-->
</template>
