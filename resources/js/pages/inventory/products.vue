<!-- ProductList.vue -->
<script setup>
import ProductEditDialog from '@/components/dialogs/ProductEditDialog.vue';
import ProductFilters from '@/components/ProductFilters.vue';
import ProductTable from '@/components/ProductTable.vue';
import axios from '@/plugins/axios';
import { onMounted, ref, watch } from 'vue';

const products = ref([])
const totalProduct = ref(0)
const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()

const searchQuery = ref('')
const selectedLaboratory = ref(null)
const selectedOrigin = ref(null)
const stockStatusFilter = ref(null)
const startDate = ref(null)
const endDate = ref(null)

const laboratories = ref([])
const origins = ref([])
const suppliers = ref([])
const categories = ref([]);

const isEditDialogVisible = ref(false)
const currentProduct = ref({})


const fetchSelectOptions = async () => {
  try {
    const [labResponse, originResponse, categoryResponse] = await Promise.all([
      axios.get('/laboratories'),
      axios.get('/origins'),
      axios.get('/categories'),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
    categories.value = categoryResponse.data;
  } catch (error) {
    console.error('Error al cargar opciones de los selects:', error);
  }
}

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && { hasStock: stockStatusFilter.value }),
    page: page.value, itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value, orderBy: orderBy.value,
    startDate: startDate.value, endDate: endDate.value,
  };
  Object.keys(params).forEach(key => (params[key] === null || params[key] === '') && delete params[key]);

  try {
    const response = await axios.get('/products', { params });
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
  [page, itemsPerPage, sortBy, orderBy, searchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter, startDate, endDate], 
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  }, 
  { deep: true }
);

watch([searchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter, startDate, endDate], () => {
  page.value = 1;
});

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
});

const updateTableOptions = options => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const handleEditProduct = (product) => {
  currentProduct.value = { ...product }; 
  isEditDialogVisible.value = true;
}

const handleDeleteProduct = async (id) => {
  if (confirm('¿Estás seguro de que quieres eliminar este producto?')) {
    try {
      await axios.delete(`/products/${id}`);
      fetchProducts(); 
    } catch (error) {
      console.error(`Error al borrar el producto ${id}:`, error);
    }
  }
}

const handleSaveProduct = async (productData) => {
  try {
    if (productData.id) {
      await axios.put(`/products/${productData.id}`, productData);
      console.log('Producto actualizado con éxito:', productData);
    } else {
      await axios.post('/products', productData);
      console.log('Producto creado con éxito:', productData);
    }
    isEditDialogVisible.value = false;
    await fetchProducts(); 
  } catch (error) {
    console.error('Error al guardar/crear el producto:', error);
    alert('Hubo un error al guardar el producto. Por favor, revisa los datos e inténtalo de nuevo.');
  }
}

const handleClearFilters = () => {
  searchQuery.value = ''
  selectedLaboratory.value = null
  selectedOrigin.value = null
  stockStatusFilter.value = null
  startDate.value = null
  endDate.value = null
}

const handleAddProduct = () => {
  currentProduct.value = {}; 
  isEditDialogVisible.value = true;
}

const handleExport = async (format) => {
  const params = {
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && { hasStock: stockStatusFilter.value }),
    startDate: startDate.value,
    endDate: endDate.value,
    format: format
  };

  Object.keys(params).forEach(key => {
    if (params[key] === null || params[key] === '') {
      delete params[key];
    }
  });

  try {
    const response = await axios.get('/products/export', { 
        params,
        responseType: 'blob'
    });
    
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    
    const contentDisposition = response.headers['content-disposition'];
    let fileName = `productos.${format}`;
    if (contentDisposition) {
        const fileNameMatch = contentDisposition.match(/filename="(.+)"/);
        if (fileNameMatch && fileNameMatch.length === 2)
            fileName = fileNameMatch[1];
    }

    link.setAttribute('download', fileName);
    document.body.appendChild(link);
    link.click();
    
    link.remove();
    window.URL.revokeObjectURL(url);

  } catch(error) {
      console.error('Error al exportar los datos:', error);
  }
}
</script>

<template>
  <div>
    <ProductFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      :laboratories="laboratories"
      :origins="origins"
      @clear="handleClearFilters"
      @export="handleExport"
      @add-product="handleAddProduct"
    />

    <ProductTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @edit-product="handleEditProduct"
      @delete-product="handleDeleteProduct"
    />

    <ProductEditDialog
      v-model="isEditDialogVisible"
      :product="currentProduct"
      :laboratories="laboratories"
      :origins="origins"
      :suppliers="suppliers"
      :categories="categories"
      :all-products="products"  
      @save="handleSaveProduct"
    />
  </div>
</template>
