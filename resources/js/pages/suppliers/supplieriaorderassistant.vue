<script setup lang="js">
import SupplierIaOrderAssistantFilter from '@/components/SupplierIaOrderAssistantFilter.vue';

// import DoctorFormDialoge from "@/components/dialogs/DoctorFormDialoge.vue";
// import DoctorFilters from "@/components/DoctorFilters.vue";
// import DoctorTable from "@/components/DoctorTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
// import pdfDoctorsGenerator from "@/utils/pdfDoctorsGenerator";
// import Swal from 'sweetalert2';
import { onMounted, reactive, watch } from 'vue';
import { useRouter } from "vue-router";
const route= useRouter()

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const statuModule= reactive({
  total:0,
  items:[],
})


const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const tipo_de_vista= ref(false);// grupo o individual
const tipo_de_filtracion= ref("sales");// promedio o ventas
const lapso_de_tiempo= ref("3 month");// tiempo
const stock= ref("all");// Fallas , Execeso o All

const handleClearFilters = () => {
  tipo_de_vista.value = false;
  tipo_de_filtracion.value = "sales";
  lapso_de_tiempo.value = "3 month";
  stock.value = "all";
};


async function consultarProductosConPaginacion(){
  let data ={
    "tipo_vista":tipo_de_vista.value,
    "tipo_filtracion":tipo_de_filtracion.value,
    "lapso_de_tiempo":lapso_de_tiempo.value,
    "stock":stock.value,
    "page":page.value,
    "itemsPerPage":itemsPerPage.value,
    "sortBy":sortBy.value,
    "orderBy":orderBy.value,
  }
  let respuestaApi = await axios.post(`/suppliers-ia-order-assistant/filtrar-paginate?page=${page.value}`,data)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
    console.log("respues api => ",respuestaApi)

    return {...respuestaApi.data}
}

async function actualizarTabla(){
  loading.value=true
  let paginacion = await consultarProductosConPaginacion()

  statuModule.items=paginacion.data.paginate.data
  statuModule.total=paginacion.data.paginate.total

  loading.value=false

}


watch([
  tipo_de_vista,
  tipo_de_filtracion,
  lapso_de_tiempo,
  stock,
  orderBy,
  sortBy,
  page,
  itemsPerPage,
],
async () => {
  await actualizarTabla()
})

onMounted(async () => {
  await actualizarTabla()

})
</script>
<template>
  <div>
    <SupplierIaOrderAssistantFilter
      v-model:tipo_de_vista="tipo_de_vista"
      v-model:tipo_de_filtracion="tipo_de_filtracion"
      v-model:lapso_de_tiempo="lapso_de_tiempo"
      v-model:stock="stock"
      :tipo_de_filtracion="tipo_de_filtracion"
      :tipo_de_vista="tipo_de_vista"
      :lapso_de_tiempo="lapso_de_tiempo"
      :stock="stock"
      @clear="handleClearFilters"
    />
  </div>
  <div v-if="tipo_de_vista == true">vista grupal</div>
  <div v-if="tipo_de_vista == false">vista individual</div>
</template>
