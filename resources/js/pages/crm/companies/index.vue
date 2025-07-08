<script setup lang="js">

import CompanyTable from "@/components/CompanyTable.vue";
import ClientFormOfCompanyDialoge from "@/components/dialogs/ClientFormOfCompanyDialoge.vue";
import CompanyFilters from "@/components/dialogs/CompanyFilters.vue";
import CompanyFormDialoge from "@/components/dialogs/CompanyFormDialoge.vue";
import ListClientsOfCompanyDialoge from "@/components/dialogs/ListClientsOfCompanyDialoge.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfCompaniesGenerator from "@/utils/pdfCompaniesGenerator";
import Swal from 'sweetalert2';
import { onMounted, reactive, watch } from 'vue';
import { useRouter } from "vue-router";

const route= useRouter()

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const statuModalListClients= reactive({
  statu:false,
  titulo:"Nuevo",
  clients:[],
  totalclients:0,
  company:{},
})

const statuModalFormularioCliente= reactive({
  statu:false,
  titulo:"Nuevo",
  company:{},
})

const statuModule= reactive({
  items:[],
  total:0,
  comapanies:[],
})

const formulario= reactive({
  id:null,
  identification:"",
  type_company:"",
  name:"",
  address:"",
})

const formularioError= reactive({
  id:"",
  identification:"",
  type_company:"",
  name:"",
  address:"",
})


const formularioClient= reactive({
  id:null,
  identification:"",
  identification_type:"",
  name:"",
  last_name:"",
  email:"",
  phone:"",
  address:"",
  birthdate:"",
  company_id:"",
})

const formularioClientError= reactive({
  id:"",
  identification:"",
  identification_type:"",
  name:"",
  last_name:"",
  email:"",
  phone:"",
  address:"",
  birthdate:"",
  company_id:"",
})

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()


const buscardor_filtro= ref("");// nombre, identificación, direccion
const tipo_empresa_filtro= ref("");
const fechaDesde_filtro= ref("");
const fechaHasta_filtro= ref("");



const loadingTableCliente = ref(false)

const pageTableCliente  = ref(1)
const itemsPerPageTableCliente  = ref(10)
const sortByTableCliente  = ref()
const orderByTableCliente = ref()


function cerrarModal(payload){
  modal.statu=payload
  limpiarDatosFormulario()
  limpiarErroresFormulario()
}

function insertarDatosAlFormulario(datos){
  formulario.id=datos.id
  formulario.identification=datos.identification
  formulario.type_company=datos.type_company
  formulario.name=datos.name
  formulario.address=datos.address
}

function limpiarDatosFormulario(){
  formulario.id=null
  formulario.identification=""
  formulario.type_company=""
  formulario.name=""
  formulario.address=""
}

function limpiarErroresFormulario(){
  formularioError.id=""
  formularioError.identification=""
  formularioError.type_company=""
  formularioError.name=""
  formularioError.address=""
}

function cargarErrores(errores){
  formularioError.id=(errores.id)?errores.id.join(", "):""
  formularioError.identification=(errores.identification)?errores.identification.join(", "):""
  formularioError.type_company=(errores.type_company)?errores.type_company.join(", "):""
  formularioError.name=(errores.name)?errores.name.join(", "):""
  formularioError.address=(errores.address)?errores.address.join(", "):""
}

function insertarDatosAlFormularioClient(datos){
  formularioClient.id=datos.id
  formularioClient.identification=datos.identification
  formularioClient.identification_type=datos.identification_type
  formularioClient.name=datos.name
  formularioClient.last_name=datos.last_name
  formularioClient.email=datos.email
  formularioClient.phone=datos.phone
  formularioClient.address=datos.address
  formularioClient.birthdate=datos.birthdate
  formularioClient.company_id=datos.company_id
}

function limpiarDatosFormularioClient(){
  formularioClient.id=null
  formularioClient.identification=""
  formularioClient.identification_type=""
  formularioClient.name=""
  formularioClient.last_name=""
  formularioClient.email=""
  formularioClient.phone=""
  formularioClient.address=""
  formularioClient.birthdate=null
  formularioClient.company_id=""
}

function limpiarErroresFormularioClient(){
  formularioClientError.id=""
  formularioClientError.identification=""
  formularioClientError.identification_type=""
  formularioClientError.name=""
  formularioClientError.last_name=""
  formularioClientError.email=""
  formularioClientError.phone=""
  formularioClientError.address=""
  formularioClientError.birthdate=""
  formularioClientError.company_id=""
}

function cargarErroresClient(errores){
  formularioClientError.id=(errores.id)?errores.id.join(", "):""
  formularioClientError.identification=(errores.identification)?errores.identification.join(", "):""
  formularioClientError.identification_type=(errores.identification_type)?errores.identification_type.join(", "):""
  formularioClientError.name=(errores.name)?errores.name.join(", "):""
  formularioClientError.last_name=(errores.last_name)?errores.last_name.join(", "):""
  formularioClientError.email=(errores.email)?errores.email.join(", "):""
  formularioClientError.phone=(errores.phone)?errores.phone.join(", "):""
  formularioClientError.address=(errores.address)?errores.address.join(", "):""
  formularioClientError.birthdate=(errores.birthdate)?errores.birthdate.join(", "):""
  formularioClientError.company_id=(errores.company_id)?errores.company_id.join(", "):""
}



function mostarModal(){
  modal.statu=true
  modal.titulo="Nueva Empresa"
}

async function mostarModalListClients(payload){

  let registro= statuModule.items.find(registro => registro.id==payload)
  statuModalListClients.statu=true
  statuModalListClients.company={...registro}
  await actualizarTablaCliente()
  statuModalListClients.titulo=`Clientes de la ${registro.type_company} ${registro.name}`
}

function mostarModalFormularioCliente(payload){
  // let registro= statuModule.items.find(registro => registro.id==payload)
  statuModalFormularioCliente.company={...statuModalListClients.company}
  statuModalFormularioCliente.statu=true
  statuModalFormularioCliente.titulo=`${statuModalFormularioCliente.company.name}: Nuevo Cliente`
  formularioClient.company_id=statuModalFormularioCliente.company.id
}

function cerrarModalFormularioCliente(payload){
  statuModalListClients.statu=true
  limpiarDatosFormulario()
  limpiarErroresFormularioClient()
}

async function actualizarTabla(){
  loading.value = true;
  let filtros={
    page:page.value,
    itemsPerPage:itemsPerPage.value,
    orderBy:orderBy.value,
    sortBy:sortBy.value,
    buscardor_filtro:buscardor_filtro.value,
    tipo_empresa_filtro:tipo_empresa_filtro.value,
    fechaDesde_filtro:fechaDesde_filtro.value,
    fechaHasta_filtro:fechaHasta_filtro.value,
  }
  let responseApi= await filtraCompany(filtros)
  statuModule.items=responseApi.data
  statuModule.total=responseApi.total
  loading.value = false;
  return {...responseApi}
}


async function confirmarEliminar(payload){
  // alert(payload)

  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: "¡No podrás revertir la eliminación de esta Empresa!",
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
    // alert("ula")
    await eliminar(payload)
  }
}

async function eliminar(id){
  try {
    let respuesApi=await axios.delete(`/crm/companies/${id}`)
    if(respuesApi.status==200){
        toast.success("La registro a sido eliminado correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    toast.error("Error al eliminar el registro")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
}

function enviar(payload){
  console.log("data id => ",payload.get("id"))
  if(formulario.id==null){
    crear(payload)
  }
  else{
    actualizar(payload)
  }
}


async function crear(data){
  try {
    let respuesApi=await axios.post("/crm/companies",data)
    if(respuesApi.status==200){
        toast.success("El cliente se a guardado correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    toast.error("Error al crear la empresa")
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
    let respuesApi=await axios.post(`/crm/companies/edit/${data.get("id")}`,data,config)
    if(respuesApi.status==200){
        toast.success("Se guardaron los cambios correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    toast.error("Error al guardar los cambios de la empresa")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
}

function mostarModoEdit(payload){
  let registro= statuModule.items.find(registro => registro.id==payload)
  modal.statu=true
  modal.titulo=registro.name
  insertarDatosAlFormulario({...registro})
}


async function crearCliente(data){
  loadingTableCliente.value=true
  try {
    let respuesApi=await axios.post("/crm/clients",data)
    if(respuesApi.status==200){
      toast.success("El cliente se a guardado correctamente")
      statuModalFormularioCliente.statu=false
      statuModalFormularioCliente.company={}
      statuModalFormularioCliente.titulo=""
      statuModalListClients.statu=true
      await actualizarTablaCliente()
      await actualizarTabla()
      let registro= statuModule.items.find(registro => registro.id==statuModalListClients.company.id)
      statuModalListClients.company={...registro}
    }
  } catch (error) {
    toast.error("Error al crear el cliente")
    console.log("error en el servidor => ",error)
    loadingTableCliente.value=false
    let errores={...error.response.data.data.errors}
    cargarErroresClient(errores)
  }
}

async function actualizarTablaCliente(){
  loadingTableCliente.value=true
  // console.log("actualizar")

  let filtros={
    page:pageTableCliente.value,
    itemsPerPage:itemsPerPageTableCliente.value,
    orderBy:orderByTableCliente.value,
    sortBy:sortByTableCliente.value,
    company_id:statuModalListClients.company.id
  }

  let respuestaApi=await filtrarClientCompany(filtros)

  statuModalListClients.clients=respuestaApi.data
  statuModalListClients.totalclients=respuestaApi.total

  loadingTableCliente.value=false
}

async function filtrarClientCompany(dataFiltro){
  let datosFiltros={
    page:dataFiltro.page,
    itemsPerPage:dataFiltro.itemsPerPage,
    orderBy:dataFiltro.orderBy,
    sortBy:dataFiltro.sortBy,
    company_id:dataFiltro.company_id,
  }
  let respuestaApi = await axios.post(`/crm/clients/filtrar?page=${datosFiltros.page}`,datosFiltros)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return {...respuestaApi.data.data}
}

async function filtraCompany(dataFiltro){
  let respuestaApi = await axios.post(`/crm/companies/filtrar?page=${dataFiltro.page}`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return {...respuestaApi.data.data}
}

watch(
    [pageTableCliente,itemsPerPageTableCliente,orderByTableCliente,sortByTableCliente],
  async () =>{
    await actualizarTablaCliente()
  }
)

watch(
    [
      buscardor_filtro,
      tipo_empresa_filtro,
      fechaDesde_filtro,
      fechaHasta_filtro,
      page,
      itemsPerPage,
      orderBy,
      sortBy
  ],
  async () =>{
    console.log("uwu")
    await actualizarTabla()
  }
)

const updateTableOptionsTableCompany = options => {
  // console.log(options)
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const updateTableOptionsTableCliente = options => {
  // console.log(options)
  pageTableCliente.value = options.page
  itemsPerPageTableCliente.value = options.itemsPerPage
  sortByTableCliente.value = options.sortBy[0]?.key
  orderByTableCliente.value = options.sortBy[0]?.order
}


function limpiarFiltros(){
  buscardor_filtro.value=""
  tipo_empresa_filtro.value=""
  fechaDesde_filtro.value=""
  fechaHasta_filtro.value=""
}

async function filtrarSinPaginar(dataFiltro){
  let respuestaApi = await axios.post(`/crm/companies/filtrar-sin-paginar`,dataFiltro)
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
      tipo_empresa_filtro:tipo_empresa_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value,
  }
  let respuestaApi= await filtrarSinPaginar(filtros)
  console.log("respuesta => ",respuestaApi)

  if(respuestaApi.length==0){
    toast.info("No hay empresas para poder genera un reporte")
    return null;
  }

  pdfCompaniesGenerator(respuestaApi)

}

async function exportarExcel(formato){

  try{
      let params={
      buscardor_filtro:buscardor_filtro.value,
      tipo_empresa_filtro:tipo_empresa_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value,
      formato,
    }

    let respuestaApi = await axios.get(`/crm/companies/exportar/excel`,{
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
    let fileName = `companies.${formato}`;
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

function irHaVerClientesEmpresa(payload){
  // alert(payload)
  route.push(`/crm/companies/${payload}`)
}



onMounted(async () => {
  await actualizarTabla()
})
</script>
<template>
  <div>
    <CompanyFilters
      v-model:buscador="buscardor_filtro"
      v-model:tipo_empresa_filtro="tipo_empresa_filtro"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      @clear="limpiarFiltros"
      @add-client="mostarModal"
      @export-pdf="exportarPdf"
      @export-excel="exportarExcel"
    />
    <ListClientsOfCompanyDialoge
      :status="statuModalListClients"
      :titulo="statuModalListClients.titulo"
      :items="statuModalListClients.clients"
      :total="statuModalListClients.totalclients"
      :loading="loadingTableCliente"
      :items-per-page="itemsPerPageTableCliente"
      :page="pageTableCliente"
      @mostrar-formulario="mostarModalFormularioCliente"
      @update:options="updateTableOptionsTableCliente"
    />
    <ClientFormOfCompanyDialoge
      :status="statuModalFormularioCliente"
      :titulo="statuModalFormularioCliente.titulo"
      :form-data="formularioClient"
      :form-error="formularioClientError"
      @modal-close="cerrarModalFormularioCliente"
      @save="crearCliente"
    />
    <CompanyFormDialoge
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      @modal-close="cerrarModal"
      @clear-error-form="limpiarErroresFormulario"
      @save="enviar"
    />
    <VCard title="Empresas">
      <VDivider />
      <CompanyTable
        :items="statuModule.items"
        :total="statuModule.total"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @edit="mostarModoEdit"
        @delete="confirmarEliminar"
        @ver-clientes="irHaVerClientesEmpresa"
        @update:options="updateTableOptionsTableCompany"
      />
    </VCard>
  </div>
</template>
