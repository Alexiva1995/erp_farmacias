<script setup lang="js">
// import DoctorFilters from "@/components/dialogs/DoctorFilters.vue";
// import DoctorFormDialoge from "@/components/dialogs/DoctorFormDialoge.vue";
// import DoctorTable from "@/components/DoctorTable.vue";
// import axios from "@/plugins/axios";
// import { toast } from "@/plugins/sweetalert";
// import pdfDoctorsGenerator from "@/utils/pdfDoctorsGenerator";
// import Swal from 'sweetalert2';
import LotteryFiltrers from '@/components/LotteryFiltrers.vue';
import { onMounted, reactive } from 'vue';
// import { useRouter } from "vue-router";

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const statuModule= reactive({
  items:[],
  total:0,
  laboratories:[],
})


const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()


const laboratory_id= ref(null);
const fechaDesde_filtro= ref("");
const fechaHasta_filtro= ref("");
const monto_minimo= ref(0);

function limpiarFiltros(){
  laboratory_id.value=null
  fechaDesde_filtro.value=""
  fechaHasta_filtro.value=""
  monto_minimo.value=0
}

function sortiar(payload){
  alert(payload)
}

watch(
    [
      laboratory_id,
      fechaDesde_filtro,
      fechaHasta_filtro,
      monto_minimo,
      page,
      itemsPerPage,
      orderBy,
      sortBy
  ],
  async () =>{
    console.log("uwu")
  }
)

async function consultAllLaboratories(){
  let res = await axios.get("/crm/companies")
  if(res.status!=200){
    console.error("error => ",res)
    return []
  }

  return [...res.data.data]

}

onMounted(async () => {
  // await actualizarTabla()
})
</script>
<template>
  <div>
    <LotteryFiltrers
      v-model:laboratory_id="laboratory_id"
      v-model:monto_minimo="monto_minimo"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      :laboratories:="[]"
      @clear="limpiarFiltros"
      @add-sortiar="sortiar"
    />
  </div>
</template>
