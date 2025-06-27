<script setup lang="js">

import CompanyTable from "@/components/CompanyTable.vue";
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
  name:"",
  address:"",
})

const formularioError= reactive({
  id:"",
  identification:"",
  name:"",
  address:"",
})

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

onMounted(async () => {
  await actualizarTabla()
})
</script>
<template>
  <div>
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
