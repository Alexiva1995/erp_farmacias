<script setup lang="js">
import ClientFormDialoge from "@/components/dialogs/ClientFormDialoge.vue";
import axios from "@/plugins/axios";
import { onMounted, reactive } from 'vue';

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

function consultAll(){
  loading.value = true;
  axios.get("/crm/clients")
  .then(res => {
    if(res.status==200){
      statuModule.items=[...res.data.data]
      totalClients.value=statuModule.items.length
    }
    // console.log("res => ",res)
    loading.value = false;
  })
  .catch(error => {
    loading.value = false;
    console.error("error => ",error)

  })
}


onMounted(() => {
  consultAll()
})
</script>
<template>
  <div>
    <ClientFormDialoge
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      @modal-close="cerrarModal"
    />
    <VCard title="Clientes">
      <!-- <VTextField
          v-model="search"
          label="Search"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          hide-details
          single-line
        /> -->
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
