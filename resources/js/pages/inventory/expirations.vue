<!-- /src/views/pages/ExpirationsList.vue -->
<script setup>
import ExpirationsFilters from '@/components/ExpirationsFilters.vue';
import ExpirationsTable from '@/components/ExpirationsTable.vue';
import axios from '@/plugins/axios';
import { onMounted, ref, watch } from 'vue';

const lots = ref([])
const totalLots = ref(0)
const loading = ref(false)
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref('expiration_date')
const orderBy = ref('asc')
const searchQuery = ref('')

const fetchLots = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    page: page.value, 
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value, 
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(key => (params[key] === null || params[key] === '') && delete params[key]);

  try {
    const response = await axios.get('/products/expirations', { params });
    lots.value = response.data.data;
    totalLots.value = response.data.total;
  } catch (error) {
    console.error('Hubo un error al obtener los lotes por vencer:', error);
  } finally {
    loading.value = false;
  }
}

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchLots(), 300);
  }, 
  { deep: true }
);

watch(searchQuery, () => {
  page.value = 1;
});

onMounted(() => {
  fetchLots();
});

const updateTableOptions = options => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const handleExpireLot = async (lotToExpire) => {
  const confirmed = window.confirm(`¿Estás seguro de querer caducar el lote Nº ${lotToExpire.lot_number} del producto "${lotToExpire.product.name}"?`);

  if (!confirmed) {
    return;
  }

  try {
    loading.value = true;
    await axios.put(`/lots/${lotToExpire.id}/expire`);

    console.log('Lote caducado con éxito.');
    await fetchLots(); 
  } catch (error) {
    console.error('Error al caducar el lote:', error);
    alert('No se pudo caducar el lote. Inténtalo de nuevo.');
  } finally {
    loading.value = false;
  }
}

</script>

<template>
  <div>
    <ExpirationsFilters v-model:searchQuery="searchQuery" />

    <ExpirationsTable
      :lots="lots"
      :loading="loading"
      :total-lots="totalLots"
      :items-per-page="itemsPerPage"
      @update:options="updateTableOptions"
      @apply-discount="lot => console.log('Futura acción: Aplicar descuento a:', lot)"
      @expire-lot="handleExpireLot"
    />
  </div>
</template>
