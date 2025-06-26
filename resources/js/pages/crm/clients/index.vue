<script setup lang="js">
import ClientFormDialoge from "@/components/dialogs/ClientFormDialoge.vue";
import axios from "@/plugins/axios";
import { onMounted, reactive } from 'vue';
// TODO: la tabla actual usarla para consultar a todos los clientes naturales
// TODO: crear una tabla donde se pueda lista los clientes juridicos

const statuModule= reactive({
  items:[],
  comapaies:[],
})

const modal= reactive({
  statu:false,
  titulo:"Nuevo Cliente"
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
  formulario.company_id=""
}

function limpiarErroresFormulario(){
  formularioError.identification=""
  formularioError.identification_type=""
  formularioError.name=""
  formularioError.last_name=""
  formularioError.email=""
  formularioError.phone=""
  formularioError.address=""
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
