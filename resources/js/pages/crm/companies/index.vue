<script setup lang="js">

import CompanyTable from "@/components/CompanyTable.vue";
import ClientFormOfCompanyDialoge from "@/components/dialogs/ClientFormOfCompanyDialoge.vue";
import CompanyFormDialoge from "@/components/dialogs/CompanyFormDialoge.vue";
import ListClientsOfCompanyDialoge from "@/components/dialogs/ListClientsOfCompanyDialoge.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from 'sweetalert2';
import { onMounted, reactive } from 'vue';

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const statuModalListClients= reactive({
  statu:false,
  titulo:"Nuevo",
  clients:[],
  company:{},
})

const statuModalFormularioCliente= reactive({
  statu:false,
  titulo:"Nuevo",
  company:{},
})

const statuModule= reactive({
  items:[],
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


const loadingTableCliente = ref(false)

const pageTableCliente  = ref(1)
const itemsPerPageTableCliente  = ref(10)

// const sortByTableCliente  = ref()
// const orderByTableCliente = ref()
// const searchTableCliente  = ref()


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

function mostarModalListClients(payload){

  let registro= statuModule.items.find(registro => registro.id==payload)
  statuModalListClients.statu=true
  statuModalListClients.company={...registro}
  statuModalListClients.clients=[...registro.clients]
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


async function consultAll(){
  let res = await axios.get("/crm/companies")
  if(res.status!=200){
    console.error("error => ",res)
    return []
  }
  return [...res.data.data]

}

async function actualizarTabla(){
  loading.value = true;
  let responseApi= await consultAll()
  statuModule.items=[...responseApi]
  loading.value = false;
  return [...responseApi]
}


async function confirmarEliminar(payload){
  // alert(payload)

  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: "¡No podrás revertir la eliminación de esta Empresa!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: '<span style="color: white;">Sí, ¡eliminar!</span>',
    cancelButtonText: '<span style="color: white;">Cancelar</span>',
    // confirmButtonText: 'Sí, ¡eliminar!',
    // cancelButtonText: 'Cancelar',
    // color: '#111',
    // confirmButtonColor: '#7367f0',
    // cancelButtonColor: '#d33',
    // background: '#2f3349',
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
      await actualizarTabla()
      loadingTableCliente.value=false
      let registro= statuModule.items.find(registro => registro.id==statuModalListClients.company.id)
      statuModalListClients.company={...registro}
    }
  } catch (error) {
    toast.error("Error al crear el cliente")
    console.log("error en el servidor => ",error)
    loadingTableCliente.value=false
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
}

// function filtrar(){
//   let datosFiltros={
//     loading,

//   }
// }

// const updateTableOptions = options => {
//   page.value = options.page
//   itemsPerPage.value = options.itemsPerPage
//   sortBy.value = options.sortBy[0]?.key
//   orderBy.value = options.sortBy[0]?.order
// }

// watch(
//     [page,itemsPerPage,orderBy,sortBy],
//   () =>{
//     console.log("ula uwu")
//   },
//   {deep:true}
// )

// watch([searchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter, startDate, endDate], () => {
//   page.value = 1;
// });


onMounted(async () => {
  await actualizarTabla()
})
</script>
<template>
  <div>
    <ListClientsOfCompanyDialoge
      :status="statuModalListClients"
      :titulo="statuModalListClients.titulo"
      :items="statuModalListClients.company.clients"
      :total="statuModalListClients.clients.length"
      :loading="loadingTableCliente"
      :items-per-page="itemsPerPageTableCliente"
      :page="pageTableCliente"
      @mostrar-formulario="mostarModalFormularioCliente"
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
      <div class="d-flex flex-wrap justify-end gap-4 ma-6">
        <VBtn color="primary" @click="mostarModal">
          <VIcon icon="tabler-plus" class="mr-2" />
          Agregar
        </VBtn>
      </div>
      <VDivider />
      <CompanyTable
        :items="statuModule.items"
        :total="statuModule.items.length"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @edit="mostarModoEdit"
        @delete="confirmarEliminar"
        @ver-clientes="mostarModalListClients"
      />
    </VCard>
  </div>
</template>
