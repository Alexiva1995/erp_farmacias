<script setup>
import ProfitabilityTable from '@/components/ProfitabilityTable.vue';
import ProductProfitabilityEditDialog from '@/components/dialogs/ProductProfitabilityEditDialog.vue';
import addProfitabilityDialog from '@/components/dialogs/addProfitabilityDialog.vue';
import profitabilityFilters from '@/components/profitabilityFilters.vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import { onMounted, ref } from 'vue';

// Constantes para ProfitabilityTable
const products = ref([]);
const totalProduct = ref(0)
const profitability = ref();
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref();
const orderBy = ref();
const loading = ref(false)

// Constantes para buttonProfitability/addProfitabilityDialog
const percentage = ref()
const dialog = ref(false)

// Datos para el filtro
const searchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const laboratories = ref([]);
const origins = ref([]);
const isLoadingFilters = ref(false);
const lockedValue = ref()

const editDialog = ref(false)
const productProfitability = ref({})

/*const fetchProducts = async () => {
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
}*/

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
    lockedValue: lockedValue.value
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/products", { params });
    products.value = response.data.data;
    console.log("productos")
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
};

const percentProfitability = async () => {
  loading.value = true
  try {
    const response = await axios.get('/finances/profitability');
   
    profitability.value = response.data.default_profitability_percentage;

    console.log(profitability.value)
  } catch (error) {
    console.error('Hubo un error al obtener la rentabilidad:', error);
    toast.error('Error al obtener la rentabilidad.');
  }
  loading.value = false
}

const addProfitability = () => {
  dialog.value = true
}

const closeModal = () => {
  dialog.value = false
}

const editProductProfitability = (profitability_id = null, percentage = 0, id_product, is_locked = 1) => {
  editDialog.value = true
  productProfitability.value = {
    "id"         : profitability_id,
    "percentage" : percentage, 
    "product_id" : id_product, 
    "is_locked"  : is_locked
  }
  console.log(productProfitability.value);
}

const closeEditModal = () => {
  editDialog.value = false

}

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    searchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    startDate,
    endDate,
    lockedValue,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

watch(
  [
    searchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    startDate,
    endDate,
    lockedValue,
  ],
  () => {
    page.value = 1;
  }
);

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
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
  lockedValue.value = null;
  // sortBy.value = undefined;
  // orderBy.value = undefined;
};

function reloadTable() {
  fetchProducts();
  percentProfitability();
}

onMounted(() => {
  reloadTable();
});
</script>

<template>

  <profitabilityFilters
    v-model:searchQuery="searchQuery"
    v-model:selectedLaboratory="selectedLaboratory"
    v-model:selectedOrigin="selectedOrigin"
    v-model:stockStatusFilter="stockStatusFilter"
    v-model:startDate="startDate"
    v-model:endDate="endDate"
    v-model:lockedValue="lockedValue"
    :laboratories="laboratories"
    :origins="origins"
    :loading="isLoadingFilters"
    @add-profitability="addProfitability" 
    @sort="handleSort"
    @clear="handleClearFilters"
  />

  <addProfitabilityDialog 
    :percentage="percentage"
    :dialog="dialog" 
    @close-modal="closeModal"
    @refresh="reloadTable"
  />

  <ProductProfitabilityEditDialog 
    :product="productProfitability"
    :dialog="editDialog" 
    @close-modal="closeEditModal"
    @refresh="reloadTable"
  />

  <VCard>
  <div>
    <ProfitabilityTable 
    :products="products" 
    :totalProduct="totalProduct" 
    :profitability="profitability"
    :page="page" 
    :itemsPerPage="itemsPerPage" 
    :loading="loading" 
    @refresh="reloadTable"
    @update:options="updateTableOptions"
    @editProduct="editProductProfitability"
    />
  </div>
  </VCard>
</template>
