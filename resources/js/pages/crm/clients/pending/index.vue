<script setup lang="js">
import ClientsPendingFilters from "@/components/ClientsPendingFilters.vue";
import PendingClientFormDialog from "@/components/dialogs/PendingClientFormDialog.vue";
import PendingClientsTable from "@/components/PendingClientsTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, reactive, ref } from 'vue';

const statuModule= reactive({
  items:[],
  itemsClientes:[],
  totalClientes:0,
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
  is_spe: false,
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
  is_spe: "",
})

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()

const status = ref(null)

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

function insertarDatosAlFormulario(datos) {
  formulario.id = datos.id
  formulario.identification = datos.identification
  formulario.identification_type = datos.identification_type
  formulario.name = datos.name
  formulario.last_name = datos.last_name
  formulario.email = (datos.email == null) ? "" : datos.email
  formulario.phone = datos.phone
  formulario.address = datos.address
  formulario.birthdate = datos.birthdate
  formulario.company_id = datos.company_id
  formulario.is_spe = Boolean(datos.is_spe) || datos.is_spe === 1 || datos.is_spe === '1'
}

function limpiarDatosFormulario() {
  formulario.id = null
  formulario.identification = ""
  formulario.identification_type = ""
  formulario.name = ""
  formulario.last_name = ""
  formulario.email = ""
  formulario.phone = ""
  formulario.address = ""
  formulario.birthdate = null
  formulario.company_id = ""
  formulario.is_spe = false
}

function limpiarErroresFormulario() {
  formularioError.id = ""
  formularioError.identification = ""
  formularioError.identification_type = ""
  formularioError.name = ""
  formularioError.last_name = ""
  formularioError.email = ""
  formularioError.phone = ""
  formularioError.address = ""
  formularioError.birthdate = ""
  formularioError.company_id = ""
  formularioError.is_spe = ""
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
  if(formulario.id==null){
    crear(payload)
  }
  else{
    actualizar(payload)
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

function cargarErrores(errores) {
  formularioError.id = (errores.id) ? errores.id.join(", ") : ""
  formularioError.identification = (errores.identification) ? errores.identification.join(", ") : ""
  formularioError.identification_type = (errores.identification_type) ? errores.identification_type.join(", ") : ""
  formularioError.name = (errores.name) ? errores.name.join(", ") : ""
  formularioError.last_name = (errores.last_name) ? errores.last_name.join(", ") : ""
  formularioError.email = (errores.email) ? errores.email.join(", ") : ""
  formularioError.phone = (errores.phone) ? errores.phone.join(", ") : ""
  formularioError.address = (errores.address) ? errores.address.join(", ") : ""
  formularioError.birthdate = (errores.birthdate) ? errores.birthdate.join(", ") : ""
  formularioError.company_id = (errores.company_id) ? errores.company_id.join(", ") : ""
  formularioError.is_spe = (errores.is_spe) ? errores.is_spe.join(", ") : ""
}

async function actualizarTabla(){
  loading.value = true;

  let filtroNaturales={
    page:page.value,
    itemsPerPage:itemsPerPage.value,
    orderBy:orderBy.value,
    sortBy:sortBy.value,
    status: status.value
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
    status: status.value
  }
  let respuestaApiNaturles= await filtrar(filtroNaturales)
  statuModule.itemsClientes=respuestaApiNaturles.data
  statuModule.totalClientes=respuestaApiNaturles.total

  statuModule.items=[...respuestaApiNaturles.data]

  loading.value = false;
}

const updateTableOptions = options => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

watch(
    [
      status,
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
  company_id_filtro.value=""
  fechaDesde_filtro.value=""
  fechaHasta_filtro.value=""
}

async function filtrar(dataFiltro){
  let respuestaApi = await axios.post(`/crm/clients/pending?page=${dataFiltro.page}`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }

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
    <ClientsPendingFilters v-model:status="status" @clear="limpiarFiltros" />
    <PendingClientFormDialog
      :companies="statuModule.comapanies"
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
      <PendingClientsTable
        :clients="statuModule.itemsClientes"
        :total-clients="statuModule.totalClientes"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @edit="mostarModoEdit"
        @update:options="updateTableOptions"
      />
    </VCard>
  </div>
</template>
