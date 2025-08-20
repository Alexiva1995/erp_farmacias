<script setup lang="js">
import SupplierAssistantReportTable from '@/components/SupplierAssistantReportTable.vue';
import SupplierIaOrderAssistantReportFilter from '@/components/SupplierIaOrderAssistantReportFilter.vue';
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, reactive } from 'vue';
import { useRouter } from "vue-router";
const router= useRouter()

const statuModule= reactive({
  data:{},
  items:[],
  total:0,
})

const laboratories = ref([]);
const productos = ref([]);
const productosSelect = ref([]);

const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const selectedLaboratory = ref();
const selectProducts= ref();
const checkColombia= ref(false);

const tipo_de_filtracion= ref("sales");// promedio o ventas
const lapso_de_tiempo= ref("3 month");// tiempo
const stock= ref("all");// Fallas , Execeso o All

async function consultarDataReport(){
  let data={
    itemsPerPage:itemsPerPage.value,
    page:page.value,
    orderBy:orderBy.value,
    sortBy:sortBy.value,
    product:selectProducts.value,
    laboratoryId:selectedLaboratory.value,
    is_colombia:checkColombia.value,
    lapso_de_tiempo:lapso_de_tiempo.value,
    tipo_filtracion:tipo_de_filtracion.value,
    stock:stock.value,
  }

  let respuestaApi= await axios.post(`suppliers-ia-assistant-report/filtrar-paginate?page=${page.value}`,data)
  if(respuestaApi.status!=200){
    toast.error("error al filtrar")
  }
  console.log("data filtro => ",respuestaApi)
  return {...respuestaApi.data}
}

async function consultarProductos(){
  let respuestaApi= await axios.get("suppliers-ia-assistant-report/consult-products")
  if(respuestaApi.status!=200){
    toast.error("Error al consultar los productos")
  }
  console.log("list products => ",respuestaApi)
  return [...respuestaApi.data.data]
}

async function consultarLaboratorios(){
  let respuesta=await axios.get("/laboratories")
  laboratories.value = respuesta.data;
}

const handleClearFilters = () => {
  tipo_de_filtracion.value = "sales";
  lapso_de_tiempo.value = "3 month";
  selectedLaboratory.value = [];
  selectProducts.value = [];
};

watch([
  checkColombia,
  selectProducts,
  selectedLaboratory,
  tipo_de_filtracion,
  lapso_de_tiempo,
  orderBy,
  sortBy,
  page,
  itemsPerPage,
],
async () => {
  loading.value=true

  statuModule.data=await consultarDataReport()
  statuModule.total=statuModule.data.data.total
  statuModule.items=[...statuModule.data.data.data]

  loading.value=false
})

const updateTableOptionsTable = options => {
  // console.log(options)
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}


onMounted(async () => {
  loading.value=true
  productos.value=await consultarProductos()

  statuModule.data=await consultarDataReport()
  statuModule.total=statuModule.data.data.total
  statuModule.items=[...statuModule.data.data.data]

  await consultarLaboratorios()
  productosSelect.value=productos.value.map(p => {
    return {
      name:`${p.id} - ${p.name}`,
      id:p.id,
    }
  })
  loading.value=false
})
</script>
<template>
  <div>
    <SupplierIaOrderAssistantReportFilter
      v-model:selectProducts="selectProducts"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:tipo_de_filtracion="tipo_de_filtracion"
      v-model:lapso_de_tiempo="lapso_de_tiempo"
      v-model:checkColombia="checkColombia"
      :checkColombia="checkColombia"
      :products="productosSelect"
      :laboratories="laboratories"
      :tipo_de_filtracion="tipo_de_filtracion"
      :lapso_de_tiempo="lapso_de_tiempo"
      @clear="handleClearFilters"
    />
    <SupplierAssistantReportTable
      :products="statuModule.items"
      :total-product="statuModule.total"
      :loading="loading"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptionsTable"
    />
  </div>
</template>
