<script setup lang="js">
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import LotteryDialoge from "@/components/dialogs/LotteryDialoge.vue";
import LotteryFiltrers from '@/components/LotteryFiltrers.vue';
import LotteryTable from "@/components/LotteryTable.vue";
import { onMounted, reactive, ref, watch } from 'vue';

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

const loadingApp = ref(false)
const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref('id')
const orderBy = ref('desc')

const laboratory_id= ref([]);
const fechaDesde_filtro= ref("");
const fechaHasta_filtro= ref("");
const monto_minimo= ref(null);

function limpiarFiltros(){
  laboratory_id.value=[]
  fechaDesde_filtro.value=""
  fechaHasta_filtro.value=""
  monto_minimo.value=null
  numero_de_premios.value=3
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
  try {
    let res = await axios.get("/laboratories")
    if(res.status!=200){
      console.error("error => ",res)
      return []
    }
    return [...res.data]
  } catch (error) {
    console.error("Error al consultar laboratorios:", error)
    return []
  }
}

async function filtrarSinPaginar(filtros){
  let respuestaApi = await axios.post(`/crm/lottery/filtrar-ordenes-sin-paginar`,filtros)
  if(respuestaApi.status!=200){
    toast.error("Error al filtrar los datos")
  }
  return [...respuestaApi.data.data]
}


async function filtrarConPaginacion(filtros){
  let respuestaApi = await axios.post(`/crm/lottery/filtrar-ordenes?page=${filtros.page}`,filtros)
  if(respuestaApi.status!=200){
    toast.error("Error al filtrar los datos")
  }
  return {...respuestaApi.data.data}
}

async function filtrar(){
  loading.value=true
  try {
    let filtros={
      itemsPerPage:itemsPerPage.value,
      page:page.value,
      orderBy:orderBy.value,
      sortBy:sortBy.value,
    }

    // Solo enviar filtros que tengan valor real
    if(laboratory_id.value && laboratory_id.value.length > 0){
      filtros.laboratory_id = laboratory_id.value
    }
    if(fechaDesde_filtro.value && fechaHasta_filtro.value){
      filtros.fechaDesde_filtro = fechaDesde_filtro.value
      filtros.fechaHasta_filtro = fechaHasta_filtro.value
    }
    if(monto_minimo.value != null && monto_minimo.value !== '' && Number(monto_minimo.value) > 0){
      filtros.minimo = `${monto_minimo.value}`
    }

    let ordenesConPaginar=await filtrarConPaginacion(filtros)
    statuModule.items=ordenesConPaginar.data
    statuModule.total=ordenesConPaginar.total
  } catch (error) {
    console.error("Error al filtrar órdenes:", error)
    statuModule.items=[]
    statuModule.total=0
  } finally {
    loading.value=false
  }
}

const updateTableOptionsTable = options => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

async function sortiar(){
  let numeroDePremiosSorteo=parseInt(numero_de_premios.value)
  if(isNaN(numeroDePremiosSorteo) || numeroDePremiosSorteo <= 0){
    toast.error("Por favor indica un número válido de ganadores para realizar el sorteo")
    return
  }

  loadingApp.value=true
  try {
    let filtros={
      itemsPerPage:itemsPerPage.value,
      page:page.value,
      orderBy:orderBy.value,
      sortBy:sortBy.value,
    }

    if(laboratory_id.value && laboratory_id.value.length > 0){
      filtros.laboratory_id = laboratory_id.value
    }
    if(fechaDesde_filtro.value && fechaHasta_filtro.value){
      filtros.fechaDesde_filtro = fechaDesde_filtro.value
      filtros.fechaHasta_filtro = fechaHasta_filtro.value
    }
    if(monto_minimo.value != null && monto_minimo.value !== '' && Number(monto_minimo.value) > 0){
      filtros.minimo = `${monto_minimo.value}`
    }

    let ordenesSinPaginar=await filtrarSinPaginar(filtros)
    statuModule.ordenesParaLaLoteria=ordenesSinPaginar

    if(ordenesSinPaginar.length === 0){
      toast.error("No hay órdenes disponibles para realizar el sorteo con los filtros aplicados")
      return
    }

    let ganadores=seleccionarGanadores([...ordenesSinPaginar],numeroDePremiosSorteo)
    if(ganadores.length>0){
      modal.statu=true
      modal.lista=ganadores.map( gan  => {
        return {
          client: `${gan.client.identification_type}${gan.client.identification} ${gan.client.name} ${gan.client.last_name || ''}`,
          order_id: gan.id
        }
      })
    }
    else{
      modal.statu=false
      modal.lista=[]
      toast.error("No hay ganadores suficientes para poder hacer el sorteo")
    }
  } catch (error) {
    console.error("Error al realizar el sorteo:", error)
    toast.error("Error al realizar el sorteo")
  } finally {
    loadingApp.value=false
  }
}

function cerrarModalSorteo(){
  modal.statu=false
}


function seleccionarGanadores(lista, totalGanadores, permitirRepetidos = false) {
  if (!Array.isArray(lista)) throw new Error('El primer parámetro debe ser un array');
  if (typeof totalGanadores !== 'number' || totalGanadores < 0) {
    throw new Error('El segundo parámetro debe ser un número positivo');
  }

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
  statuModule.laboratories=[...laboratorios]
  await filtrar()
})
</script>
<template>
  <LoaderComponent :loadingApp="loadingApp" />
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
    <LotteryTable
      :items="statuModule.items"
      :total="statuModule.total"
      :loading="loading"
      :items-per-page="itemsPerPage"
      :page="page"
      :sort-by="sortBy"
      :order-by="orderBy"
      @update:options="updateTableOptionsTable"
    />
  </div>
</template>
