<script setup>
import QuotationTable from "@/components/QuotationTable.vue";
import QuotationFilters from '@/components/QuotationFilters.vue';
import QuotationProducts from '@/components/cards/QuotationProducts.vue';
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";

import { toast } from '@/plugins/sweetalert'; // Asegúrate de que esto esté configurado
import Swal from 'sweetalert2';

// ... (tus otras definiciones de ref y fetchSelectOptions) ...

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

const isLoadingFilters = ref(false);
const quotationItems = ref([]); // La lista de productos en la cotización actual

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
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
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && { hasStock: stockStatusFilter.value }),
    page: page.value, itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value, orderBy: orderBy.value,
  };
  Object.keys(params).forEach(key => (params[key] === null || params[key] === '') && delete params[key]);
  try {
    const response = await axios.get("/quotation", { params }); // Asegúrate de que esta ruta devuelve los productos con lots_sum_quantity, price_bs, price_cop
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
}

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
    stockStatusFilter
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
  ],
  () => {
    page.value = 1;
  }
);

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};


// **Función addProductToQuotation ahora recibe un objeto { productId, quantity }**
const addProductToQuotation = async ({ productId, quantity }) => {

  console.log(`Intentando agregar producto con ID: ${productId} y cantidad: ${quantity} a la cotización.`);

  if (quantity <= 0) {
    toast.error('La cantidad a agregar debe ser mayor que cero.');
    return;
  }

  try {
    const response = await axios.get(`/quotation/${productId}`); // Ruta para obtener detalles de un producto específico
    const productDetails = response.data;

    const availableQuantity = productDetails.lots_sum_quantity;

    if (quantity > availableQuantity) {
      toast.error(`No hay suficiente stock para "${productDetails.name}". Disponible: ${availableQuantity}. Solicitado: ${quantity}.`);
      return;
    }

    const existingItemIndex = quotationItems.value.findIndex(item => item.id === productId);

    if (existingItemIndex !== -1) {
      const currentSelectedQuantity = quotationItems.value[existingItemIndex].selectedQuantity;
      const newTotalSelectedQuantity = currentSelectedQuantity + quantity;

      if (newTotalSelectedQuantity > availableQuantity) {
        toast.warning(`Ya se agrego la cantidad maxima disponible de "${productDetails.name}"`);
        quotationItems.value[existingItemIndex].selectedQuantity = availableQuantity;
      } else {
        quotationItems.value[existingItemIndex].selectedQuantity = newTotalSelectedQuantity;
        toast.success(`Cantidad de "${productDetails.name}" incrementada a ${newTotalSelectedQuantity}.`);
      }
    } else {
      const itemToAdd = {
        id: productDetails.id,
        title: productDetails.name,
        active_ingredient: productDetails.active_ingredient,
        itemCode: productDetails.barcode,
        price: productDetails.sale_price, // Precio de venta en USD
        price_bs: productDetails.price_bs,
        price_cop: productDetails.price_cop,
        availableQuantity: availableQuantity,
        selectedQuantity: quantity, // La cantidad que el usuario especificó
        laboratory: productDetails.laboratory ? productDetails.laboratory.name : 'N/A',
      };
      quotationItems.value.push(itemToAdd);
      toast.success(`"${itemToAdd.title}" agregado a la cotización.`);
    }

  } catch (error) {
    console.error('Error al obtener o agregar el producto a la cotización:', error.response ? error.response.data : error.message);
    toast.error('Error al agregar el producto a la cotización. Inténtalo de nuevo.');
  }
};


// Método para remover un producto de la lista de cotización
const removeQuotationItem = (productId) => {
  quotationItems.value = quotationItems.value.filter(item => item.id !== productId);
  console.log(`Producto ${productId} removido de la cotización.`);
  toast.success('Producto eliminado exitosamente');
};

const removeQuotation = () => {
  quotationItems.value = [];
  toast.success('Cotización cancelada');
};

// Formato de moneda (puedes moverlo a un utility file si lo usas en varios lugares)
// NOTA: Esta función en el padre no es estrictamente necesaria para la tabla si ya tienes las funciones de formato en QuotationTable.vue
// Pero la puedes usar si quieres mostrar totales o detalles en el padre.
const formatCurrency = (value, currencyCode = 'USD') => {
  if (typeof value !== 'number' || isNaN(value)) {
    return 'N/A';
  }
  let locale = 'en-US';
  if (currencyCode === 'BS') {
    locale = 'es-VE';
  } else if (currencyCode === 'COP') {
    locale = 'es-CO';
  }
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currencyCode,
  }).format(value);
};


const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
};
</script>

<template>
  <div>
    <VRow class='mb-4'>
      <VCol cols="12" sm="12" md="6">
        <QuotationCard />
      </VCol>
      <VCol cols="12" sm="12" md="6">
        <QuotationProducts
          :quotation-products="quotationItems"
          @remove-quotation-product="removeQuotationItem"
          @remove="removeQuotation"
        />
      </VCol>
    </VRow>

    <QuotationFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
    />

    <QuotationTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @add-product="addProductToQuotation" />
  </div>
</template>
