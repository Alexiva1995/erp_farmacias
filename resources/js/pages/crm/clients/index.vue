<script setup lang="js">
import ClientFormDialoge from "@/components/dialogs/ClientFormDialoge.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, reactive } from 'vue';
// TODO: la tabla actual usarla para consultar a todos los clientes naturales
// TODO: crear una tabla donde se pueda lista los clientes juridicos

const statuModule= reactive({
  items:[],
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

const totalClients = ref(0)
const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
// const sortBy = ref()
// const orderBy = ref()
// const search = ref()


function mostarModal(){
  modal.statu=true
  modal.titulo="Nuevo Cliente"
}

function cerrarModal(payload){
  // console.log("payload => ",payload)
  modal.statu=payload
  limpiarDatosFormulario()
  limpiarErroresFormulario()
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
  formulario.birthdate=""
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
    let respuesApi=await axios.put(`/crm/clients/${data.get("id")}`,data)
    if(respuesApi.status==200){
        toast.success("Se guardaron los cambios correctamente")
        cerrarModal(false)
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



onMounted(async () => {
  loading.value = true;
  let responseCliest= await consultAll()
  let responseComponies = await consultAllComapaies()
  // console.log("companies => ",responseComponies)
  statuModule.items=[...responseCliest]
  statuModule.comapaies=[...responseComponies]
  loading.value = false;
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
        :clients="statuModule.items"
        :total-clients="totalClients"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
      />
    </VCard>
  </div>
</template>
