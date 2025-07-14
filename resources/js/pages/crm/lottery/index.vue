<script setup lang="js">
// import DoctorFilters from "@/components/dialogs/DoctorFilters.vue";
// import DoctorFormDialoge from "@/components/dialogs/DoctorFormDialoge.vue";
// import DoctorTable from "@/components/DoctorTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
// import pdfDoctorsGenerator from "@/utils/pdfDoctorsGenerator";
// import Swal from 'sweetalert2';
import LotteryDialoge from "@/components/dialogs/LotteryDialoge.vue";
import LotteryFiltrers from '@/components/LotteryFiltrers.vue';
import LotteryTable from "@/components/LotteryTable.vue";
import { onMounted, reactive } from 'vue';
// import { useRouter } from "vue-router";

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
  lista:[]
})

const statuModule= reactive({
  items:[],
  total:0,
  ordenesParaLaLoteria:[],
  laboratories:[],
})

const numero_de_premios = ref(3)


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
  numero_de_premios.value=0
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
  let ordenesConPaginar=await filtrarConPaginacion(filtros)
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

async function sortiar(payload){
  // alert(payload)
  if(numero_de_premios.value!=""){
    if(numero_de_premios.value>0){
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
      statuModule.ordenesParaLaLoteria=ordenesSinPaginar
      let ganadores=seleccionarGanadores([...ordenesSinPaginar],numero_de_premios.value)
      if(ganadores.length>0){
      console.log("datos => ",ganadores)
        modal.statu=true
        modal.lista=ganadores.map( gan  => {
          return `${gan.client.identification_type}${gan.client.identification} ${gan.client.name} ${gan.client.last_name}`
        })
      }
      else{
        modal.statu=false
        modal.lista=[]
        toast.error("Error no hay ganadores suficientes para poder hacer el sorteo")
      }
    }
    else{
      toast.error("Porfavor para poder hacer el sorteo tienes que indicar un minimo de de ganadores")
    }
  }
  else{
    toast.error("Porfavor para poder hacer el sorteo no puede dejar el campo de numero de ganadores vacio")
  }


}

function cerrarModalSorteo(payload){
  // alert(payload)
  modal.statu=false
}


function seleccionarGanadores(lista, totalGanadores, permitirRepetidos = false) {
  // Validaciones
  if (!Array.isArray(lista)) throw new Error('El primer parámetro debe ser un array');
  if (typeof totalGanadores !== 'number' || totalGanadores < 0) {
    throw new Error('El segundo parámetro debe ser un número positivo');
  }

  // Si permitimos repetidos o no hay suficientes elementos
  if (permitirRepetidos || lista.length <= totalGanadores) {
    const ganadores = [];
    for (let i = 0; i < totalGanadores; i++) {
      const indice = permitirRepetidos
        ? Math.floor(Math.random() * lista.length)
        : i % lista.length;
      ganadores.push(lista[indice]);
    }
    return ganadores;
  }

  // Selección sin repetición
  const listaCopia = [...lista];
  const ganadores = [];
  while (ganadores.length < totalGanadores && listaCopia.length > 0) {
    const indice = Math.floor(Math.random() * listaCopia.length);
    ganadores.push(listaCopia.splice(indice, 1)[0]);
  }
  return ganadores;
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
    <LotteryDialoge
      :modalFormulario="modal.statu"
      :lista="modal.lista"
      @modal-close="cerrarModalSorteo"
    />
    <LotteryFiltrers
      v-model:numero_de_premios="numero_de_premios"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      v-model:laboratory_id="laboratory_id"
      v-model:monto_minimo="monto_minimo"
      :laboratories="statuModule.laboratories"
      @clear="limpiarFiltros"
      @action-sortiar="sortiar"
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
