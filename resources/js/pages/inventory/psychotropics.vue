<script setup lang="js">
// import DoctorFormDialoge from "@/components/dialogs/DoctorFormDialoge.vue";
// import DoctorFilters from "@/components/DoctorFilters.vue";
// import DoctorTable from "@/components/DoctorTable.vue";
// import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
// import pdfDoctorsGenerator from "@/utils/pdfDoctorsGenerator";
// import Swal from 'sweetalert2';
import PsychotropicsRecipeDialoge from "@/components/dialogs/PsychotropicsRecipeDialoge.vue";
import OrderWithPsychotropicsTable from "@/components/OrderWithPsychotropicsTable.vue";
import PsychotropicsFilters from '@/components/PsychotropicsFilters.vue';
import PsychotropicsTable from "@/components/PsychotropicsTable.vue";
import axios from "@/plugins/axios";
import { onMounted, reactive, watch } from 'vue';
import { useRouter } from "vue-router";
const route= useRouter()

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const modalRecipe= reactive({
  statu:false,
  titulo:"Nuevo",
  data:{},
})

const sales = ref([]);
const totalSales = ref(0);

const pageOrder = ref(1)
const itemsPerPageOrder = ref(10)
const sortByOrder  = ref()
const orderByOrder  = ref()

const products = ref([]);
const totalProduct = ref(0);

const searchQuery = ref("");
const selectedLaboratory = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);


const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()

const laboratories = ref([]);
// const suppliers = ref([]);
const categories = ref([]);

// const isEditDialogVisible = ref(false);
// const currentProduct = ref({});

// const productFormErrors = ref({});

const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, categoryResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/categories"),
    ]);
    laboratories.value = labResponse.data;
    categories.value = categoryResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    hasStock: stockStatusFilter.value,
    laboratoryId: selectedLaboratory.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
    is_psychotropic: 1,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/products", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
};

const fetchSales = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    hasStock: stockStatusFilter.value,
    laboratoryId: selectedLaboratory.value,
    startDate: startDate.value,
    endDate: endDate.value,
    page: pageOrder.value,
    itemsPerPage: itemsPerPageOrder.value,
    sortBy: sortByOrder.value,
    orderBy: orderByOrder.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/sales/report/filterByPsychotropics", { params });
    sales.value = response.data.data;
    totalSales.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener el reporte de ventas:", error);
    toast.error("Error al obtener el reporte.");
  } finally {
    loading.value = false;
  }
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
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

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  stockStatusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
  // sortBy.value = undefined;
  // orderBy.value = undefined;
};

const updateTableOptionsProductos = (options) => {
  pageOrder.value = options.page;
  itemsPerPageOrder.value = options.itemsPerPage;
  sortByOrder.value = options.sortBy[0]?.key;
  orderByOrder.value = options.sortBy[0]?.order;
};



watch(
    [
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
    fetchProducts();
  }
)

watch(
    [
      searchQuery,
      selectedLaboratory,
      stockStatusFilter,
      startDate,
      endDate,
      pageOrder,
      pageOrder,
      sortByOrder,
      orderByOrder
  ],
  async () =>{
    fetchSales();
  }
)


function verProducto(paylod){
  console.log(paylod)
}

function verRecipe(paylod){
  console.log(paylod)
  if(paylod.order.url_recipe!=null){
    modalRecipe.statu=true
    modalRecipe.titulo=`Recipe: ${paylod.order.id}`
    modalRecipe.data={...paylod}
  }
}

function cerrarModalVerRecipe(paylod){
  modalRecipe.statu=false
}

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
  fetchSales();
})
</script>

<template>
  <div>
    <PsychotropicsRecipeDialoge
      :modal-formulario="modalRecipe.statu"
      :titulo="modalRecipe.titulo"
      :data="modalRecipe.data"
      @modal-close="cerrarModalVerRecipe"
    />
    <PsychotropicsFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      :laboratories="laboratories"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @sort="handleSort"
    />
    <PsychotropicsTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @action-ver="verProducto"
    />
    <div class="mb-5"></div>
    <OrderWithPsychotropicsTable
      :sales="sales"
      :loading="loading"
      :total-sales="totalSales"
      :items-per-page="itemsPerPageOrder"
      :page="pageOrder"
      @action-ver="verRecipe"
      @update:options="updateTableOptionsProductos"
    />
  </div>
</template>
