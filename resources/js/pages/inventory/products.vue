<script setup>
import ProductEditDialog from '@/components/dialogs/ProductEditDialog.vue';
import ProductFilters from '@/components/ProductFilters.vue';
import ProductTable from '@/components/ProductTable.vue';
import axios from '@/plugins/axios';
import { onMounted, ref, watch } from 'vue';

import { toast } from '@/plugins/sweetalert';
import Swal from 'sweetalert2';

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

const productFormErrors = ref({})

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
    toast.error('No se pudieron cargar los filtros.');
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
    toast.error('Error al obtener los productos.');
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
  productFormErrors.value = {}; 
  isEditDialogVisible.value = true;
}

const handleDeleteProduct = async (id) => {
  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: "¡No podrás revertir la eliminación de este producto!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Sí, ¡eliminar!',
    cancelButtonText: 'Cancelar'
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/products/${id}`);
      toast.success('Producto eliminado con éxito.');
      fetchProducts(); 
    } catch (error) {
      console.error(`Error al borrar el producto ${id}:`, error);
      toast.error('No se pudo eliminar el producto.');
    }
  }
}

const handleSaveProduct = async (productFormData) => {
  const isNewProduct = !currentProduct.value.id;
  const url = isNewProduct ? '/products' : `/products/${currentProduct.value.id}`;

  try {
    if (!isNewProduct) {
      productFormData.append('_method', 'PUT');
    }

    await axios.post(url, productFormData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    toast.success(`Producto ${isNewProduct ? 'creado' : 'actualizado'} con éxito`);
    isEditDialogVisible.value = false;
    await fetchProducts(); 
  } catch (error) {
    if (error.response && error.response.status === 422) {
      productFormErrors.value = error.response.data.errors;
      toast.error('Por favor, corrige los errores en el formulario.');
    } else {
      console.error('Error al guardar/crear el producto:', error);
      toast.error('Hubo un error al guardar el producto.');
    }
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
  productFormErrors.value = {};
  isEditDialogVisible.value = true;
}

const clearFormErrors = () => {
  productFormErrors.value = {};
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
      :errors="productFormErrors"
      @save="handleSaveProduct"
      @clear-errors="clearFormErrors"
    />
  </div>
</template>
