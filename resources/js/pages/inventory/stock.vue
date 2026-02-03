<script setup lang="js">
import InventoryStockFilters from "@/components/InventoryStockFilters.vue";
import InventoryStockTable from "@/components/InventoryStockTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfStockProductsGenerator from "@/utils/pdfStockProductsGenerator";
import { onMounted, reactive, watch } from 'vue';
import { useRouter } from "vue-router";
const route= useRouter()

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const modulo= reactive({
  items:[],
  totalItems:0,
})

const searchQuery = ref("");
const selectedLaboratory = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const days = ref(30);
const stock = ref("all");
const expProd = ref(false);
const isStrictSearch = ref(false);
const tipoFiltracion = ref("average");
const isColombian = ref(false);

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()

const laboratories = ref([]);

const fetchSelectOptions = async () => {
  loading.value = true;
  try {
    const labResponse= await axios.get("/laboratories")
    laboratories.value = labResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    loading.value = false;
  }
};

const fetchProducts = async () => {

  const data = {
    q: searchQuery.value,
    hasStock: stockStatusFilter.value,
    laboratoryId: selectedLaboratory.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
    days: days.value,
    stock: stock.value,
    expProd: expProd.value,
    isStrictSearch: isStrictSearch.value,
    tipo_filtracion: tipoFiltracion.value,
    isColombian: isColombian.value,
  };
  loading.value = true;
  let respuesApi=await axios.post("/inventory/stock/filter",data)
  if(respuesApi.status==200){
    console.log("productos consultados correctamente")
  }
  else{
    toast.error("error al consultar")
    console.log("error en el servidor => ",error)
  }
  loading.value=false
  console.log(respuesApi)
  return {...respuesApi.data.data}
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  stockStatusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
  stock.value = "all";
  days.value = 30;
  expProd.value = false;
  isStrictSearch.value = false;
  tipoFiltracion.value = "average";
  isColombian.value = false;
  // sortBy.value = undefined;
  // orderBy.value = undefined;
};

const handleSort = (sortOptions) => {
  if (sortOptions.key === undefined && sortOptions.order === undefined) {
    sortBy.value = undefined;
    orderBy.value = undefined;
  } else {
    sortBy.value = sortOptions.key;
    orderBy.value = sortOptions.order;
  }
};

watch(
    [
      expProd,
      stock,
      days,
      searchQuery,
      selectedLaboratory,
      stockStatusFilter,
      startDate,
      endDate,
      page,
      itemsPerPage,
      sortBy,
      orderBy,
      isStrictSearch,
      tipoFiltracion,
      isColombian
  ],
  async () =>{
    actualizarTabla()
  }
)

async function actualizarTabla(){
  let dataTabla=await fetchProducts();
  console.log("=> ",dataTabla)
  modulo.items=dataTabla.data
  modulo.totalItems=dataTabla.total
}

const updateTableOptions = options => {
  // console.log(options)
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

onMounted(async () => {
  await fetchSelectOptions();
  let dataTabla=await fetchProducts();
  console.log("=> ",dataTabla)
  modulo.items=dataTabla.data
  modulo.totalItems=dataTabla.total
  // fetchSales();
})

async function filtrarSinPaginar(dataFiltro){
  let respuestaApi = await axios.post(`/inventory/stock/filter-without-paginate`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return [...respuestaApi.data.data]
}

async function exportarPdf(){
    let filtros={
      // filtros
      q: searchQuery.value,
      hasStock: stockStatusFilter.value,
      laboratoryId: selectedLaboratory.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
      startDate: startDate.value,
      endDate: endDate.value,
      days: days.value,
      stock: stock.value,
      expProd: expProd.value,
      isStrictSearch: isStrictSearch.value,
      tipo_filtracion: tipoFiltracion.value,
      isColombian: isColombian.value,
  }
  let respuestaApi= await filtrarSinPaginar(filtros)
  console.log("respuesta => ",respuestaApi)

  if(respuestaApi.length==0){
    toast.info("No hay clientes para poder genera un reporte")
    return null;
  }

  pdfStockProductsGenerator(respuestaApi)

}

async function exportarExcel(formato){

  try{
      let params={
        q: searchQuery.value,
        hasStock: stockStatusFilter.value,
        laboratoryId: selectedLaboratory.value,
        sortBy: sortBy.value,
        orderBy: orderBy.value,
        startDate: startDate.value,
        endDate: endDate.value,
        days: days.value,
        stock: stock.value,
        expProd: expProd.value,
        isStrictSearch: isStrictSearch.value,
        tipo_filtracion: tipoFiltracion.value,
        isColombian: isColombian.value,
        formato
    }

    // let respuestaApi = await axios.get(`/inventory/stock/exportar/excel`,{
    //   params,
    //   responseType: "blob",
    // })

    let respuestaApi = await axios.post(
      '/inventory/stock/exportar/excel',
      params,  // Tus parámetros como objeto
      {
        responseType: 'blob',
        headers: {
          'Content-Type': 'application/json',  // Asegura el envío correcto de los parámetros
        }
      }
    );

    console.log("res => ",respuestaApi)

    if(respuestaApi.status!=200){
      toast.success("Error al filtrar los datos")
    }
    const url = window.URL.createObjectURL(new Blob([respuestaApi.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = respuestaApi.headers["content-disposition"];
    let fileName = `stock-products.${formato}`;
    if (contentDisposition) {
      const fileNameMatch = contentDisposition.match(/filename="(.+)"/);
      if (fileNameMatch && fileNameMatch.length === 2)
        fileName = fileNameMatch[1];
    }

    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();

    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    console.error("Error al exportar los datos:", error);
  }

}
</script>
<template>
  <div>
    <InventoryStockFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      v-model:days="days"
      v-model:stock="stock"
      v-model:expProd="expProd"
      v-model:isStrictSearch="isStrictSearch"
      v-model:tipoFiltracion="tipoFiltracion"
      v-model:isColombian="isColombian"
      :laboratories="laboratories"
      :loading="loading"
      @clear="handleClearFilters"
      @sort="handleSort"
      @export-pdf="exportarPdf"
      @export-excel="exportarExcel"
    />
    <InventoryStockTable
      :products="modulo.items"
      :loading="loading"
      :total-product="modulo.totalItems"
      :items-per-page="itemsPerPage"
      :page="page"
      :sort-by="sortBy"
      :order-by="orderBy"
      @update:options="updateTableOptions"
    />
  </div>
</template>
