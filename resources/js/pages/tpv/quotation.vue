<script setup>
import QuotationTable from "@/components/QuotationTable.vue";
import QuotationFilters from '@/components/QuotationFilters.vue';
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";

import { toast } from '@/plugins/sweetalert';
import Swal from 'sweetalert2';

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const searchQuery = ref('')
const selectedLaboratory = ref(null)
const selectedOrigin = ref(null)
const stockStatusFilter = ref(null)

const laboratories = ref([])
const origins = ref([])

const currentProduct = ref({})

const fetchSelectOptions = async () => {
  try {
    const [labResponse, originResponse, categoryResponse] = await Promise.all([
      axios.get('/laboratories'),
      axios.get('/origins'),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
  } catch (error) {
    console.error('Error al cargar opciones de los selects:', error);
    toast.error('No se pudieron cargar los filtros.');
  }
}

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};


const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && { hasStock: stockStatusFilter.value }),
    page: page.value, itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value, orderBy: orderBy.value,
  };
  Object.keys(params).forEach(key => (params[key] === null || params[key] === '') && delete params[key]);

  try {
    const response = await axios.get('/products', { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error('Hubo un error al obtener los productos:', error);
    toast.error('Error al obtener los productos.');
  } finally {
    loading.value = false;
  }
}

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter], 
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  }, 
  { deep: true }
);

watch([searchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter], () => {
  page.value = 1;
});

onMounted(() => {
  fetchProducts();
});


const handleAddProduct = (product) => {
  currentProduct.value = { ...product }; 
}
</script>
<template>
  <div>
    <VRow class='mb-4'>
      <VCol cols="12" sm="12" md="6">
        <QuotationCard />
      </VCol>
    <VCol cols="12" sm="12" md="6">
        <QuotationProducts />
      </VCol>
    </VRow>

    <QuotationFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      :laboratories="laboratories"
      :origins="origins"
      @clear="handleClearFilters"
    />

    <QuotationTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @add-product="handleAddProduct"
    />
  </div>
</template>
