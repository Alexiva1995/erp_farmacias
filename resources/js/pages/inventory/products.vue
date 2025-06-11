<script setup>
import axios from '@/plugins/axios';
import { onMounted, ref, watch } from 'vue';

const products = ref([])
const totalProduct = ref(0)
const loading = ref(false)
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()
const selectedRows = ref([])
const searchQuery = ref('')
const selectedLaboratory = ref(null)
const selectedOrigin = ref(null)
const stockStatusFilter = ref(null)
const startDate = ref(null)
const endDate = ref(null)

const laboratories = ref([])
const origins = ref([])

const fetchSelectOptions = async () => {
  try {
    const [labResponse, originResponse] = await Promise.all([
      axios.get('/laboratories'),
      axios.get('/origins')
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
  } catch (error) {
    console.error('Error al cargar opciones de los filtros:', error);
  }
}

const fetchProducts = async () => {
  loading.value = true;

  const params = {
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && { hasStock: stockStatusFilter.value }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
  };

  Object.keys(params).forEach(key => {
    if (params[key] === null || params[key] === '') {
      delete params[key];
    }
  });

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

watch([searchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter, startDate, endDate], () => {
  page.value = 1;
});

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter, startDate, endDate], 
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      fetchProducts();
    }, 300);
  }, 
  { deep: true }
);

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
});

const headers = [
  { title: 'Producto', key: 'name' },
  { title: 'Stock Válido', key: 'valid_stock', sortable: false },
  { title: 'Próximo Vencimiento', key: 'next_expiration', sortable: false },
  { title: 'Laboratorio', key: 'laboratory.name' },
  { title: 'Origen', key: 'origin.name' },
  { title: 'Precio Venta', key: 'sale_price' },
  { title: 'Acciones', key: 'actions', sortable: false },
]
const stockOptions = [ { title: 'Con Stock', value: true }, { title: 'Sin Stock', value: false } ]

const updateOptions = options => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const clearFilters = () => {
  searchQuery.value = ''
  selectedLaboratory.value = null
  selectedOrigin.value = null
  stockStatusFilter.value = null
  startDate.value = null
  endDate.value = null
}

const deleteProduct = async id => {
  try {
    await axios.delete(`/products/${id}`);
    fetchProducts(); 
  } catch (error) {
    console.error(`Error al borrar el producto ${id}:`, error);
  }
}

const exportData = async (format) => {
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
    let fileName = `productos.${format}`; // Nombre por defecto
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


const calculateValidStock = product => {
  if (!product.lots || !Array.isArray(product.lots)) return 0;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return product.lots
    .filter(lot => lot.expiration_date && new Date(lot.expiration_date) >= today)
    .reduce((sum, lot) => sum + Number(lot.quantity || 0), 0);
}

const nextExpirationDate = product => {
  if (!product.lots || !Array.isArray(product.lots) || product.lots.length === 0) return 'N/A';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validLots = product.lots.filter(lot => {
    if (!lot.expiration_date) return false;
    const expirationDate = new Date(lot.expiration_date);
    return !isNaN(expirationDate.getTime()) && expirationDate >= today;
  });
  if (validLots.length === 0) return 'Todos expiraron';
  validLots.sort((a, b) => new Date(a.expiration_date) - new Date(b.expiration_date));
  const closestDate = new Date(validLots[0].expiration_date);
  const year = closestDate.getFullYear();
  const month = String(closestDate.getMonth() + 1).padStart(2, '0');
  const day = String(closestDate.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};
</script>

<template>
  <div>
    <VCard title="Filtros" class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" sm="6" md="3">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar Producto, C. Activo, Código..."
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VSelect
              v-model="selectedLaboratory"
              label="Laboratorio"
              :items="laboratories"
              item-title="name"
              item-value="id"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VSelect
              v-model="selectedOrigin"
              label="Origen"
              :items="origins"
              item-title="name"
              item-value="id"
              clearable
            />
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VSelect
              v-model="stockStatusFilter"
              label="Estado de Stock"
              :items="stockOptions"
              clearable
            />
          </VCol>

          <VCol cols="12" sm="6" md="6">
            <AppDateTimePicker
              v-model="startDate"
              label="Vencimiento Desde"
              clearable
              :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>

          <VCol cols="12" sm="6" md="6">
            <AppDateTimePicker
              v-model="endDate"
              label="Vencimiento Hasta"
              clearable
              :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>
        </VRow>
      </VCardText>
      
      <VDivider />

      <VCardActions class="pa-4 d-flex flex-wrap gap-4">
        <VBtn
          color="primary"
          @click="clearFilters"
        >
          Limpiar Filtros
        </VBtn>
        <VSpacer />
        <VMenu>
          <template #activator="{ props }">
            <VBtn
              color="success"
              variant="flat"
              prepend-icon="tabler-upload"
              v-bind="props"
            >
              Exportar
            </VBtn>
          </template>
          <VList>
            <VListItem @click="exportData('xlsx')">
              <template #prepend>
                <VIcon icon="tabler-file-type-csv" class="me-2" color="success" />
              </template>
              <VListItemTitle class="text-success">Excel</VListItemTitle>
            </VListItem>
            <VListItem @click="exportData('pdf')">
              <template #prepend>
                <VIcon icon="tabler-file-type-pdf" class="me-2" />
              </template>
              <VListItemTitle>PDF</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="$router.push('/apps/ecommerce/product/add')"
        >
          Añadir Producto
        </VBtn>
      </VCardActions>
    </VCard>

    <VCard>
      <VDataTableServer
        v-model="selectedRows"
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :headers="headers"
        :items="products"
        :items-length="totalProduct"
        :loading="loading"
        show-select
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              v-if="item.photo_url"
              size="38"
              variant="tonal"
              rounded
              :image="item.photo_url"
            />
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.name }}</span>
              <span class="text-sm text-disabled">{{ item.active_ingredient }}</span>
            </div>
          </div>
        </template>

        <template #item.valid_stock="{ item }">
          <span class="font-weight-medium">{{ calculateValidStock(item) }}</span>
        </template>
        
        <template #item.next_expiration="{ item }">
          <span>{{ nextExpirationDate(item) }}</span>
        </template>

        <template #item.sale_price="{ item }">
          <span class="font-weight-medium">${{ item.sale_price }}</span>
        </template>

        <template #item.actions="{ item }">
          <IconBtn>
            <VIcon icon="tabler-edit" />
          </IconBtn>
          <IconBtn @click="deleteProduct(item.id)">
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </template>

        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalProduct"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
