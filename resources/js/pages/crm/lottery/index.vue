<script setup lang="js">
// import DoctorFilters from "@/components/dialogs/DoctorFilters.vue";
// import DoctorFormDialoge from "@/components/dialogs/DoctorFormDialoge.vue";
// import DoctorTable from "@/components/DoctorTable.vue";
import axios from "@/plugins/axios";
// import { toast } from "@/plugins/sweetalert";
// import pdfDoctorsGenerator from "@/utils/pdfDoctorsGenerator";
// import Swal from 'sweetalert2';
import LotteryFiltrers from '@/components/LotteryFiltrers.vue';
import LotteryTable from "@/components/LotteryTable.vue";
import { onMounted, reactive } from 'vue';
// import { useRouter } from "vue-router";

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const statuModule= reactive({
  items:[],
  total:0,
  ordenesParaLaLoteria:[],
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
  async  () =>{
    await filtrar()
  }
)

async function consultAllLaboratories(){
  let res = await axios.get("/laboratories")
  if(res.status!=200){
    console.error("error => ",res)
    return []
  }

  return [...res.data.data]

}

async function filtrarSinPaginar(filtros){
  let respuestaApi = await axios.post(`/crm/lottery/filtrar-ordenes-sin-paginar`,filtros)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return [...respuestaApi.data.data]
}


async function filtrarConPaginacion(filtros){
  let respuestaApi = await axios.post(`/crm/lottery/filtrar-ordenes?page=${filtros.page}`,filtros)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return {...respuestaApi.data.data}
}

async function filtrar(){
  loading.value=true
  let filtros={
    laboratory_id:laboratory_id.value,
    fechaDesde_filtro:fechaDesde_filtro.value,
    fechaHasta_filtro:fechaHasta_filtro.value,
    minimo:`${monto_minimo.value}`,
    itemsPerPage:itemsPerPage.value,
    page:page.value,
    orderBy:orderBy.value,
    sortBy:sortBy.value,
  }
  let ordenesSinPaginar=await filtrarSinPaginar(filtros)
  let ordenesConPaginar=await filtrarConPaginacion(filtros)
  statuModule.ordenesParaLaLoteria=ordenesSinPaginar
  statuModule.items=ordenesConPaginar.data
  statuModule.total=ordenesConPaginar.total
  loading.value=false

}

const updateTableOptionsTable = options => {
  console.log(options)
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}


onMounted(async () => {
  let laboratorios=await consultAllLaboratories()
  console.log("data laboratorio => ",laboratorios)
  statuModule.laboratories=[...laboratorios]
  await filtrar()

  // await actualizarTabla()
})
</script>
<template>
  <div>
    <LotteryFiltrers
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      v-model:laboratory_id="laboratory_id"
      v-model:monto_minimo="monto_minimo"
      :laboratories="statuModule.laboratories"
      @clear="limpiarFiltros"
      @add-sortiar="sortiar"
    />
    <VCard title="Ordenes">
      <VDivider />
      <LotteryTable
        :items="statuModule.items"
        :total="statuModule.total"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @update:options="updateTableOptionsTable"
      />
    </VCard>
  </div>
</template>
