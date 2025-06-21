<!-- cyclic.vue -->
<script setup>
import ProductTable from '@/components/ProductCyclicTable.vue';
import InventoryCountModal from '@/components/dialogs/InventoryCountModal.vue';
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from 'vue';

const products = ref([])
const totalProduct = ref(0)
const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref('name')
const orderBy = ref('asc')
const search = ref('');

const showCountModal = ref(false);
const selectedProduct = ref(null);

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    page: page.value, itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value, orderBy: orderBy.value,
  };
  Object.keys(params).forEach(key => (params[key] === null || params[key] === '') && delete params[key]);

  try {
    const response = await axios.get('/cyclic', { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error('Hubo un error al obtener los productos:', error);
  } finally {
    loading.value = false;
  }
}

let debounceTimer;

watch(
  [page, itemsPerPage, sortBy, orderBy], 
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  }, 
  { deep: true }
);

onMounted(() => {
  fetchProducts();
});

const updateTableOptions = (options) => {
    page.value = options.page;
    itemsPerPage.value = options.itemsPerPage;
    if (options.sortBy && options.sortBy.length > 0) {
        sortBy.value = options.sortBy[0]?.key;
        orderBy.value = options.sortBy[0]?.order;
    } else {
        sortBy.value = 'name';
        orderBy.value = 'asc';
    }
}

const openCountModal = (product) => {
    selectedProduct.value = product;
     console.log( selectedProduct.value)
    if (selectedProduct.value) {
        showCountModal.value = true;
    }
};

const handleCountProcessed = async (event) => {
  console.log('Actualizando la tabla despues del modal')
  fetchProducts();
};

</script>
<template>
  <div>
    <ProductCyclicTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @product-click="openCountModal"
    />

    <InventoryCountModal
      v-if="showCountModal && selectedProduct"
            v-model="showCountModal"
            :product="selectedProduct"
            @count-processed="handleCountProcessed"
        />

  </div>
</template>
