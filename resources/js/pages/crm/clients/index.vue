<script setup lang="js">
import ClientFormDialoge from "@/components/dialogs/ClientFormDialoge.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from 'sweetalert2';
import { onMounted, reactive } from 'vue';

const statuModule= reactive({
  items:[],
  itemsClientesNaturales:[],
  itemsClientesJuridicos:[],
  comapaies:[],
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
const pageTablaClientesJuridicos = ref(1)
const itemsPerPage = ref(10)
// const sortBy = ref()
// const orderBy = ref()
// const search = ref()


function mostarModal(){
  modal.statu=true
  modal.titulo="Nuevo Cliente"
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
  formulario.email=datos.email
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

async function consultAllComapaies(){
  let res = await axios.get("/crm/companies")
  if(res.status!=200){
    console.error("error => ",res)
    return []
  }

  return [...res.data.data]

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
  let responseCliest= await consultAll()
  statuModule.items=[...responseCliest]
  statuModule.itemsClientesNaturales=filtrarPorTipoDeIdentificacion([...responseCliest],["V-","E-","G-"])
  statuModule.itemsClientesJuridicos=filtrarPorTipoDeIdentificacion([...responseCliest],["J-"])
  loading.value = false;
}

function filtrarPorTipoDeIdentificacion(clients,isFiltro){
  return clients.filter(item => isFiltro.includes(item.identification_type));
}

async function confirmarEliminarCliente(payload){
  // alert(payload)

  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: "¡No podrás revertir la eliminación de este cliente!",
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


onMounted(async () => {
  await actualizarTabla()

  let responseComponies = await consultAllComapaies()
  statuModule.comapaies=[...responseComponies]
})
</script>
<template>
  <div>
    <ClientFormDialoge
      :companies="statuModule.comapaies"
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
      <div class="d-flex flex-wrap justify-end gap-4 ma-6">
        <VBtn color="primary" @click="mostarModal()">
          <VIcon icon="tabler-plus" class="mr-2" />
          Agregar
        </VBtn>
      </div>
      <VDivider />
      <ClientTable
        :clients="statuModule.itemsClientesNaturales"
        :total-clients="statuModule.itemsClientesNaturales.length"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @edit="mostarModoEdit"
        @delete="confirmarEliminarCliente"
      />
    </VCard>
    <div class="mb-5"></div>
    <VCard>
      <ClientTable
        :clients="statuModule.itemsClientesJuridicos"
        :total-clients="statuModule.itemsClientesJuridicos.length"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="pageTablaClientesJuridicos"
        @edit="mostarModoEdit"
        @delete="confirmarEliminarCliente"
      />
    </VCard>
  </div>
</template>
