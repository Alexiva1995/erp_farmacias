<script setup lang="js">

import CompanyTable from "@/components/CompanyTable.vue";
import CompanyFormDialoge from "@/components/dialogs/CompanyFormDialoge.vue";
import axios from "@/plugins/axios";
// import { toast } from "@/plugins/sweetalert";
// import Swal from 'sweetalert2';
import { onMounted, reactive } from 'vue';

// TODO: moda crear
// TODO: moda editar
// TODO: eliminar
// TODO: modal ver lista de clientes de la empresa
// TODO: modal crear cliente que se desparara desde la modal clientes de la empresa desde la tabla

const modal= reactive({
  statu:false,
  titulo:"Nuevo"
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

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
// const sortBy = ref()
// const orderBy = ref()
// const search = ref()

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


function mostarModal(){
  modal.statu=true
  modal.titulo="Nueva Empresa"
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
}

function mostarModoEdit(payload){
  alert("modal editar")

}
function confirmarEliminar(payload){
  alert("modal confirmar")
}

function enviar(payload){

}

onMounted(async () => {
  await actualizarTabla()
})
</script>
<template>
  <div>
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
      />
    </VCard>
  </div>
</template>
