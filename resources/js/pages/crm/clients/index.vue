<script setup lang="js">
import ClientsFilters from "@/components/ClientsFilters.vue";
import ClientFormDialoge from "@/components/dialogs/ClientFormDialoge.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from 'sweetalert2';
import { onMounted, reactive } from 'vue';

const statuModule= reactive({
  items:[],
  itemsClientesNaturales:[],
  totalClientesNaturales:0,
  itemsClientesJuridicos:[],
  totalClientesJuridicos:0,
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
  company_id:"",
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
  company_id:"",
})

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()

const pageTablaClientesJuridicos = ref(1)
const itemsPerPageTablaClientesJuridicos = ref(10)
const sortByTablaClientesJuridicos = ref()
const orderByTablaClientesJuridicos = ref()

const buscardor_filtro= ref("");
const tipo_identificacion_filtro= ref("");
const company_id_filtro= ref("");
const fechaDesde_filtro= ref("");
const fechaHasta_filtro= ref("");


function mostarModal(){
  modal.statu=true
  modal.titulo="Nuevo Cliente"
}

function mostarModoEdit(payload){
  // console.log("items => ",statuModule.items)
  // console.log("payload => ",payload)
  let cliente= statuModule.items.find(client => client.id==payload)
  // console.log("DATA => ",cliente)
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
  formulario.company_id=datos.company_id
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
  formulario.company_id=""
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
  formularioError.company_id=""
}

async function consultAll(){
  let res = await axios.get("/crm/clients")
  if(res.status!=200){
    console.error("error => ",res)
    return []
  }
  return [...res.data.data]

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
  formularioError.company_id=(errores.company_id)?errores.company_id.join(", "):""
}

async function actualizarTabla(){
  loading.value = true;

  let filtroNaturales={
    page:page.value,
    itemsPerPage:itemsPerPage.value,
    orderBy:orderBy.value,
    sortBy:sortBy.value,
    tipo:["V-"]
  }
  let respuestaApiNaturles= await filtrar(filtroNaturales)
  statuModule.itemsClientesNaturales=respuestaApiNaturles.data
  statuModule.totalClientesNaturales=respuestaApiNaturles.total

  let filtroJuridica={
    page:pageTablaClientesJuridicos.value,
    itemsPerPage:itemsPerPageTablaClientesJuridicos.value,
    orderBy:orderByTablaClientesJuridicos.value,
    sortBy:sortByTablaClientesJuridicos.value,
    tipo:["J-"]
  }
  let respuestaApiJurudicas= await filtrar(filtroJuridica)
  statuModule.itemsClientesJuridicos=respuestaApiJurudicas.data
  statuModule.totalClientesJuridicos=respuestaApiJurudicas.total

  statuModule.items=[...respuestaApiJurudicas.data,...respuestaApiNaturles.data]

  loading.value = false;
}

async function actualizarTablaTablaNatural(){
  loading.value = true;

  let filtroNaturales={
    page:page.value,
    itemsPerPage:itemsPerPage.value,
    orderBy:orderBy.value,
    sortBy:sortBy.value,
    tipo:["V-","E-"],
    // filtros
    buscardor_filtro:buscardor_filtro.value,
    tipo_identificacion_filtro:tipo_identificacion_filtro.value,
    company_id:company_id_filtro.value,
    fechaDesde_filtro:fechaDesde_filtro.value,
    fechaHasta_filtro:fechaHasta_filtro.value,
  }
  let respuestaApiNaturles= await filtrar(filtroNaturales)
  statuModule.itemsClientesNaturales=respuestaApiNaturles.data
  statuModule.totalClientesNaturales=respuestaApiNaturles.total

  statuModule.items=[...statuModule.itemsClientesJuridicos,...respuestaApiNaturles.data]

  loading.value = false;
}

async function actualizarTablaTablJuriidica(){
  loading.value = true;

  let filtroJuridica={
    page:pageTablaClientesJuridicos.value,
    itemsPerPage:itemsPerPageTablaClientesJuridicos.value,
    orderBy:orderByTablaClientesJuridicos.value,
    sortBy:sortByTablaClientesJuridicos.value,
    tipo:["J-","G-"],
    // filtros
    buscardor_filtro:buscardor_filtro.value,
    tipo_identificacion_filtro:tipo_identificacion_filtro.value,
    company_id:company_id_filtro.value,
    fechaDesde_filtro:fechaDesde_filtro.value,
    fechaHasta_filtro:fechaHasta_filtro.value,
  }
  let respuestaApiJurudicas= await filtrar(filtroJuridica)
  statuModule.itemsClientesJuridicos=respuestaApiJurudicas.data
  statuModule.totalClientesJuridicos=respuestaApiJurudicas.total

  statuModule.items=[...respuestaApiJurudicas.data,...statuModule.itemsClientesNaturales]

  loading.value = false;
}

// function filtrarPorTipoDeIdentificacion(clients,isFiltro){
//   return clients.filter(item => isFiltro.includes(item.identification_type));
// }

async function confirmarEliminarCliente(payload){
  // alert(payload)

  const result = await Swal.fire({
      title: '¿Estás seguro?',
  text: '¡No podrás revertir esta acción!',
  icon: 'warning',
  showCancelButton: true,
  confirmButtonText: 'Sí, ¡Eliminar!',
  cancelButtonText: 'No, ¡Cancelar!',
  buttonsStyling: false,
  customClass: {
    confirmButton: 'v-btn v-btn--elevated v-theme--light bg-error v-btn--density-default v-btn--size-default v-btn--variant-elevated',
    cancelButton: 'v-btn v-theme--light text-secondary v-btn--density-default v-btn--size-default v-btn--variant-outlined'}

    // title: '¿Estás seguro?',
    // text: "¡No podrás revertir la eliminación de este cliente!",
    // icon: 'warning',
    // showCancelButton: true,
    // confirmButtonText: '<span style="color: white;">Sí, ¡eliminar!</span>',
    // cancelButtonText: '<span style="color: white;">Cancelar</span>',
    // confirmButtonColor: '#d33',

    //     title: "¿Estás seguro?",
    // text: "¡No podrás revertir la eliminación de este producto!",
    // icon: "warning",
    // showCancelButton: true,
    // cancelButtonText: "Cancelar",
    // confirmButtonText: "Eliminar",
    // reverseButtons: true,



    // customClass: {
    //   confirmButton: 'red-accent-3',  // Clase para el botón de confirmar
    //   cancelButton: 'btn-cancel',   // Clase para el botón de cancelar
    // },
    // confirmButtonText: 'Sí, ¡eliminar!',
    // cancelButtonText: 'Cancelar',
    // color: '#111',
    // confirmButtonColor: '#7367f0',
    // cancelButtonColor: '#d33',
    // background: '#2f3349',
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

const updateTableOptionsJuridico = options => {
  // console.log(options)
  pageTablaClientesJuridicos.value = options.page
  itemsPerPageTablaClientesJuridicos.value = options.itemsPerPage
  sortByTablaClientesJuridicos.value = options.sortBy[0]?.key
  orderByTablaClientesJuridicos.value = options.sortBy[0]?.order
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
    if(tipo_identificacion_filtro.value=="V-" || tipo_identificacion_filtro.value=="E-" || tipo_identificacion_filtro.value==""){
      console.log("natural")
      await actualizarTablaTablaNatural()
    }
  }
)

watch(
    [
      buscardor_filtro,
      tipo_identificacion_filtro,
      fechaDesde_filtro,
      fechaHasta_filtro,
      company_id_filtro,
      pageTablaClientesJuridicos,
      itemsPerPageTablaClientesJuridicos,
      orderByTablaClientesJuridicos,
      sortByTablaClientesJuridicos
  ],
  async () =>{
    if(tipo_identificacion_filtro.value=="J-" || tipo_identificacion_filtro.value=="G-" || tipo_identificacion_filtro.value==""){
      console.log("juridico")
      await actualizarTablaTablJuriidica()
    }
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
  company_id_filtro.value=""
  fechaDesde_filtro.value=""
  fechaHasta_filtro.value=""
}

async function filtrar(dataFiltro){
  // let datosFiltros={
  //   page:dataFiltro.page,
  //   itemsPerPage:dataFiltro.itemsPerPage,
  //   orderBy:dataFiltro.orderBy,
  //   sortBy:dataFiltro.sortBy,
  //   tipo:dataFiltro.tipo,
  // }
  let respuestaApi = await axios.post(`/crm/clients/filrar?page=${dataFiltro.page}`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return {...respuestaApi.data.data}
}



onMounted(async () => {
  await actualizarTabla()

  let responseComponies = await consultAllcomapanies()
  statuModule.comapanies=[...responseComponies]
})
</script>
<template>
  <div>
    <ClientsFilters
      v-model:buscador="buscardor_filtro"
      v-model:tipo_identificacion_filtro="tipo_identificacion_filtro"
      v-model:company_id_filtro="company_id_filtro"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      :companies="statuModule.comapanies"
      @clear="limpiarFiltros"
      @add-client="mostarModal"
    />
    <ClientFormDialoge
      :companies="statuModule.comapanies"
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      @modal-close="cerrarModal"
      @clear-error-form="limpiarErroresFormulario"
      @save="enviar"
    />
    <VCard title="Clientes Naturales">
      <VDivider />
      <!-- <div class="d-flex flex-wrap justify-end gap-4 ma-6">
        <VBtn color="primary" @click="mostarModal()">
          <VIcon icon="tabler-plus" class="mr-2" />
          Agregar
        </VBtn>
      </div> -->
      <VDivider />
      <ClientTable
        :clients="statuModule.itemsClientesNaturales"
        :total-clients="statuModule.totalClientesNaturales"
        :loading="loading"
        :items-per-page="itemsPerPageTablaClientesJuridicos"
        :page="pageTablaClientesJuridicos"
        @edit="mostarModoEdit"
        @delete="confirmarEliminarCliente"
        @update:options="updateTableOptions"
      />
    </VCard>
    <div class="mb-5"></div>
    <VCard title="Clientes Juridicos">
      <ClientTable
        :clients="statuModule.itemsClientesJuridicos"
        :total-clients="statuModule.totalClientesJuridicos"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="pageTablaClientesJuridicos"
        @edit="mostarModoEdit"
        @delete="confirmarEliminarCliente"
        @update:options="updateTableOptionsJuridico"
      />
    </VCard>
  </div>
</template>
