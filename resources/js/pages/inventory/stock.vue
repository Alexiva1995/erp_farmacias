<script setup lang="js">
import InventoryStockFilters from "@/components/InventoryStockFilters.vue";
import InventoryStockTable from "@/components/InventoryStockTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
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
const expirationDays = ref(15);
const stock = ref("all");

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
    expirationDays: expirationDays.value,
    stock: stock.value,
  };
  loading.value = true;
  let respuesApi=await axios.post("/inventory/stock/filter",data)
  if(respuesApi.status==200){
    toast.success("El cliente se a guardado correctamente")
  }
  else{
    toast.error("Error al crear el cliente")
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
  expirationDays.value = 15;
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
      stock,
      expirationDays,
      searchQuery,
      selectedLaboratory,
      stockStatusFilter,
      startDate,
      endDate,
      page,
      itemsPerPage,
      sortBy,
      orderBy
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
</script>
<template>
  <div>
    <InventoryStockFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      v-model:expirationDays="expirationDays"
      v-model:stock="stock"
      :laboratories="laboratories"
      :loading="loading"
      @clear="handleClearFilters"
      @sort="handleSort"
    />
    <InventoryStockTable
      :products="modulo.items"
      :loading="loading"
      :total-product="modulo.totalItems"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
    />
  </div>
</template>
