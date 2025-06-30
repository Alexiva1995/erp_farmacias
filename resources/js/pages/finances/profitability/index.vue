<script setup>
import ProfitabilityTable from '@/components/ProfitabilityTable.vue';
import buttonProfitability from '@/components/buttonProfitability.vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import { onMounted, ref } from 'vue';

// Constantes para ProfitabilityTable
const products = ref([]);
const totalProduct = ref(0)
const profitability = ref([]);
const page = ref(1)
const itemsPerPage = ref(10)
const loading = ref(false)

// Constantes para buttonProfitability
const percentage = ref()

const fetchProducts = async () => {
  // Si no usas params, elimina esta línea
  // Object.keys(params).forEach(key => (params[key] === null || params[key] === '') && delete params[key]);
  loading.value = true
  try {
    const response = await axios.get('/products');
    console.log(response.data.data)
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error('Hubo un error al obtener los productos:', error);
    toast.error('Error al obtener los productos.');
  }
  loading.value = false
}

const percentProfitability = async () => {
  loading.value = true
  try {
    const response = await axios.get('/finances/profitability');
    profitability.value = response.data[0];
  } catch (error) {
    console.error('Hubo un error al obtener la rentabilidad:', error);
    toast.error('Error al obtener la rentabilidad.');
  }
  loading.value = false
}

function reloadTable() {
  fetchProducts();
  percentProfitability();
}




onMounted(() => {
  reloadTable();
});
</script>

<template>
  <VCard class="flex justify-end">
  <div class="py-5 px-5">
    <buttonProfitability 
    :percentage="percentage" 
    @refresh="reloadTable"
    />
  </div>
  <VDivider />
  <div>
    <!-- Usa el componente y pásale los datos si es necesario -->
    <ProfitabilityTable 
    :products="products" 
    :totalProduct="totalProduct" 
    :profitability="profitability.default_profitability_percentage" 
    :page="page" 
    :itemsPerPage="itemsPerPage" 
    :loading="loading" 
    @refresh="reloadTable"
    />
  </div>
  </VCard>
</template>
