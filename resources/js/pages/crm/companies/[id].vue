<script setup lang="js">
import CompaniesClientsFilters from "@/components/CompaniesClientsFilters.vue";
import CompaniesClientFormDialoge from "@/components/dialogs/CompaniesClientFormDialoge.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfClienstGenerator from "@/utils/pdfClienstGenerator";
import Swal from 'sweetalert2';
import { onMounted, reactive } from 'vue';
import { useRoute } from "vue-router";

const router = useRoute()

const statuModule= reactive({
  items:[],
  itemsClientes:[],
  totalClientes:0,
  company:{},
  comapanies:[],
})

const modal= reactive({
  statu:false,
  titulo:"Nuevo"
})

const formulario= reactive({
  id:null,
  identification:"",
  identification_type:"",
  name:"",
  last_name:"",
  email:"",
  phone:"",
  address:"",
  birthdate:"",
  company_id:router.params.id,
})
const formularioError= reactive({
  id:"",
  identification:"",
  identification_type:"",
  name:"",
  last_name:"",
  email:"",
  phone:"",
  address:"",
  birthdate:"",
})

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()

const buscardor_filtro= ref("");
const tipo_identificacion_filtro= ref(null);
const company_id_filtro= ref(router.params.id);
const fechaDesde_filtro= ref("");
const fechaHasta_filtro= ref("");


function mostarModal(){
  modal.statu=true
  modal.titulo="Nuevo Cliente de "+statuModule.company.name
}

function mostarModoEdit(payload){
  let cliente= statuModule.items.find(client => client.id==payload)
  modal.statu=true
  modal.titulo=`${cliente.name} ${cliente.last_name}`

  insertarDatosAlFormulario({...cliente})
}

function cerrarModal(payload){
  modal.statu=payload
  limpiarDatosFormulario()
  limpiarErroresFormulario()
}

function insertarDatosAlFormulario(datos){
  formulario.id=datos.id
  formulario.identification=datos.identification
  formulario.identification_type=datos.identification_type
  formulario.name=datos.name
  formulario.last_name=datos.last_name
  formulario.email=(datos.email==null)?"":datos.email
  formulario.phone=datos.phone
  formulario.address=datos.address
  formulario.birthdate=datos.birthdate
}

function limpiarDatosFormulario(){
  formulario.id=null
  formulario.identification=""
  formulario.identification_type=""
  formulario.name=""
  formulario.last_name=""
  formulario.email=""
  formulario.phone=""
  formulario.address=""
  formulario.birthdate=null
}

function limpiarErroresFormulario(){
  formularioError.id=""
  formularioError.identification=""
  formularioError.identification_type=""
  formularioError.name=""
  formularioError.last_name=""
  formularioError.email=""
  formularioError.phone=""
  formularioError.address=""
  formularioError.birthdate=""
}

async function consultAllcomapanies(){
  let res = await axios.get("/crm/companies")
  if(res.status!=200){
    console.error("error => ",res)
    return []
  }

  return [...res.data.data]

}

function enviar(payload){
  // console.log("data id => ",payload.get("id"))
  if(formulario.id==null){
    crear(payload)
  }
  else{
    actualizar(payload)
  }
}

async function crear(data){
  try {
    let respuesApi=await axios.post("/crm/clients",data)
    if(respuesApi.status==200){
        toast.success("El cliente se a guardado correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    toast.error("Error al crear el cliente")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
}

async function actualizar(data){
  try {
    let config={
        headers: {
          'Content-Type': 'multipart/form-data',
        },
    }
    let respuesApi=await axios.post(`/crm/clients/edit/${data.get("id")}`,data,config)
    if(respuesApi.status==200){
        toast.success("Se guardaron los cambios correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    toast.error("Error al guardar los cambios del cliente")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
}

function cargarErrores(errores){
  formularioError.id=(errores.id)?errores.id.join(", "):""
  formularioError.identification=(errores.identification)?errores.identification.join(", "):""
  formularioError.identification_type=(errores.identification_type)?errores.identification_type.join(", "):""
  formularioError.name=(errores.name)?errores.name.join(", "):""
  formularioError.last_name=(errores.last_name)?errores.last_name.join(", "):""
  formularioError.email=(errores.email)?errores.email.join(", "):""
  formularioError.phone=(errores.phone)?errores.phone.join(", "):""
  formularioError.address=(errores.address)?errores.address.join(", "):""
  formularioError.birthdate=(errores.birthdate)?errores.birthdate.join(", "):""
}

async function actualizarTabla(){
  loading.value = true;

  let filtroNaturales={
    page:page.value,
    itemsPerPage:itemsPerPage.value,
    orderBy:orderBy.value,
    sortBy:sortBy.value,
    // company_id:company_id_filtro.value,
  }
  let respuestaApiNaturles= await filtrar(filtroNaturales)
  statuModule.itemsClientes=respuestaApiNaturles.data
  statuModule.totalClientes=respuestaApiNaturles.total

  statuModule.items=[...respuestaApiNaturles.data]

  loading.value = false;
}

async function actualizarTablaTablaClientes(){
  loading.value = true;

  let filtroNaturales={
    page:page.value,
    itemsPerPage:itemsPerPage.value,
    orderBy:orderBy.value,
    sortBy:sortBy.value,
    // filtros
    buscardor_filtro:buscardor_filtro.value,
    tipo_identificacion_filtro:tipo_identificacion_filtro.value,
    // company_id:company_id_filtro.value,
    fechaDesde_filtro:fechaDesde_filtro.value,
    fechaHasta_filtro:fechaHasta_filtro.value,
  }
  let respuestaApiNaturles= await filtrar(filtroNaturales)
  statuModule.itemsClientes=respuestaApiNaturles.data
  statuModule.totalClientes=respuestaApiNaturles.total

  statuModule.items=[...respuestaApiNaturles.data]

  loading.value = false;
}

async function confirmarEliminarCliente(payload){
  // alert(payload)

  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: '¡No podrás revertir la eliminación de este cliente!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, ¡Eliminar!',
    cancelButtonText: 'No, ¡Cancelar!',
    buttonsStyling: false,
    customClass: {
      confirmButton: 'v-btn v-btn--elevated v-theme--light bg-error v-btn--density-default v-btn--size-default v-btn--variant-elevated',
      cancelButton: 'v-btn v-theme--light text-secondary v-btn--density-default v-btn--size-default v-btn--variant-outlined mx-2'
    },
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    await eliminarCliente(payload)
  }
}


async function eliminarCliente(id){
  try {
    let respuesApi=await axios.delete(`/crm/clients/${id}`)
    if(respuesApi.status==200){
        toast.success("El cliente se a eliminado correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    toast.error("Error al eliminar el cliente")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
}

const updateTableOptions = options => {
  // console.log(options)
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

watch(
    [
      buscardor_filtro,
      tipo_identificacion_filtro,
      fechaDesde_filtro,
      fechaHasta_filtro,
      company_id_filtro,
      page,
      itemsPerPage,
      orderBy,
      sortBy
  ],
  async () =>{
    await actualizarTablaTablaClientes()
  }
)

watch(
  () => formulario.identification_type,
  (value) => {
    if(value=="J-"){
      formulario.last_name=""
      formulario.company_id=""
    }
  }
)


function limpiarFiltros(){
  buscardor_filtro.value=""
  tipo_identificacion_filtro.value=""
  // company_id_filtro.value=""
  fechaDesde_filtro.value=""
  fechaHasta_filtro.value=""
}

async function filtrar(dataFiltro){
  let respuestaApi = await axios.post(`/crm/clients/filtrar?page=${dataFiltro.page}`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return {...respuestaApi.data.data}
}

async function filtrarSinPaginar(dataFiltro){
  let respuestaApi = await axios.post(`/crm/clients/filtrar-sin-paginar`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return [...respuestaApi.data.data]
}


async function exportarPdf(){
    let filtros={
      // filtros
      buscardor_filtro:buscardor_filtro.value,
      tipo_identificacion_filtro:tipo_identificacion_filtro.value,
      company_id:company_id_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value,
  }
  let respuestaApi= await filtrarSinPaginar(filtros)
  console.log("respuesta => ",respuestaApi)

  if(respuestaApi.length==0){
    toast.info("No hay clientes para poder genera un reporte")
    return null;
  }

  pdfClienstGenerator(respuestaApi)

}

async function exportarExcel(formato){

  try{
      let params={
      buscardor_filtro:buscardor_filtro.value,
      tipo_identificacion_filtro:tipo_identificacion_filtro.value,
      company_id:company_id_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value,
      formato,
    }

    let respuestaApi = await axios.get(`/crm/clients/exportar/excel`,{
      params,
      responseType: "blob",
    })

    console.log("res => ",respuestaApi)

    if(respuestaApi.status!=200){
      toast.success("Error al filtrar los datos")
    }
    const url = window.URL.createObjectURL(new Blob([respuestaApi.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = respuestaApi.headers["content-disposition"];
    let fileName = `clients.${formato}`;
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

async function consultarCompanyById(id){

  let respuestaApi = await axios.get(`/crm/companies/${id}`)
  if(respuestaApi.status!=200){
    toast.success("Error al consultar la información de la empresa")
  }
  // console.log("respues api => ",respuestaApi)

  return {...respuestaApi.data.data}
}


async function assignCompany(data) {
  console.log("data assign => ",data)
  let body={
    client_id:  parseInt(data.client_id),
    company_id: parseInt(data.company_id),
    status:     data.status,
  }

  let respuestaApi = await axios.post(`/crm/clients/${data.client_id}/update-company/${data.company_id}`,body)
  if(respuestaApi.status!=200){
    toast.error("Error al asignar la empresa al cliente")
  }
  await actualizarTabla()
}


onMounted(async () => {
  let responseComponies = await consultAllcomapanies()
  let companyData=await consultarCompanyById(router.params.id)
  limpiarFiltros()
  await actualizarTabla()

  statuModule.company=companyData
  statuModule.comapanies=[...responseComponies]
})
</script>
<template>
  <div>
    <VCard
      :title="'Clientes de la Empresa: ' + statuModule.company.name"
      class="mb-5"
    ></VCard>
    <CompaniesClientsFilters
      v-model:buscador="buscardor_filtro"
      v-model:tipo_identificacion_filtro="tipo_identificacion_filtro"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      @clear="limpiarFiltros"
      @add-client="mostarModal"
      @export-pdf="exportarPdf"
      @export-excel="exportarExcel"
    />
    <CompaniesClientFormDialoge
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      @modal-close="cerrarModal"
      @clear-error-form="limpiarErroresFormulario"
      @save="enviar"
    />
    <VCard title="Clientes">
      <VDivider />
      <ClientOfCompanyTable
        :companyId="router.params.id"
        :clients="statuModule.itemsClientes"
        :total-clients="statuModule.totalClientes"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @edit="mostarModoEdit"
        @delete="confirmarEliminarCliente"
        @update:options="updateTableOptions"
        @assign-company="assignCompany"
      />
    </VCard>
  </div>
</template>
