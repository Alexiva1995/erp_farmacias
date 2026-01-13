<script setup lang="js">
import { onMounted, ref, watch, reactive } from 'vue';
import { useRoute } from 'vue-router';
import ProductComparisionProductsTable from '@/components/ProductComparisionProductsTable.vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import Swal from 'sweetalert2';

const route = useRoute();
const allProducts = ref([]); // Todos los productos sin proveedor
const selectedProduct = ref(null); // Producto seleccionado en el select
const loading = ref(true);
const loadingProducts = ref(false);

// Productos de proveedores para la tabla de comparación
const supplierProducts = ref([]);
const productsPage = ref(1);
const productsItemPerPage = ref(10);
const productsTotal = ref(0);
const quantityErrors = reactive({});
const filterSearchQuery = ref('');
const isStrictSearch = ref(false);
const enableUsdAmountCol = ref(false);
const enableDiscountCol = ref(false);
const enableDiscounts = ref(false);

// Obtener productos disponibles del proveedor para el producto seleccionado
const fetchSupplierProducts = async () => {
  if (!selectedProduct.value) {
    supplierProducts.value = [];
    productsTotal.value = 0;
    return;
  }

  loadingProducts.value = true;
  const params = {
    page: productsPage.value,
    perPage: productsItemPerPage.value,
    q: selectedProduct.value.name, // Buscar por nombre del producto
    isStrictSearch: false, // Usar búsqueda no estricta para encontrar variaciones
  };

  try {
    const { data } = await axios.get('/suppliers/available-products', { params });
    supplierProducts.value = data.data;
    productsTotal.value = data.total;
  } catch (error) {
    console.error('Error al obtener productos de proveedores:', error);
    toast.error('Error al obtener productos de proveedores.');
  } finally {
    loadingProducts.value = false;
  }
};

// Agregar producto a auto orden
const handleAddItemToAutoOrder = async (product) => {
  quantityErrors[product.id] = null;
  const form = new FormData();
  form.append('productId', product.id);
  form.append('quantity', product.quantity);
  form.append('discount', enableDiscounts.value);

  try {
    await axios.post('/suppliers/add-product-to-order', form);
    toast.success(`Se añadieron ${product.quantity} productos al pedido del día`);
    
    // Remover el producto del select
    removeProductFromList();
  } catch (error) {
    if (error.response?.status === 422) {
      quantityErrors[product.id] = error.response.data.errors.quantity?.[0];
    }

    console.error('Error al enviar la petición:', error);

    if (error.response?.status === 400) {
      toast.error(error.response.data.message);
    } else {
      toast.error('Error al añadir productos al pedido del día.');
    }
  }
};

// Marcar producto como no conseguido
const markAsNotAvailable = () => {
  if (!selectedProduct.value) return;
  
  Swal.fire({
    title: '¿Marcar como no conseguido?',
    text: `El producto "${selectedProduct.value.name}" será removido de la lista.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, marcar',
    cancelButtonText: 'Cancelar',
  }).then((result) => {
    if (result.isConfirmed) {
      removeProductFromList();
      toast.info('Producto marcado como no conseguido');
    }
  });
};

// Remover producto de la lista
const removeProductFromList = () => {
  if (!selectedProduct.value) return;
  
  const index = allProducts.value.findIndex(p => p.id === selectedProduct.value.id);
  if (index !== -1) {
    allProducts.value.splice(index, 1);
  }
  
  // Seleccionar el siguiente producto o el primero
  if (allProducts.value.length > 0) {
    selectedProduct.value = allProducts.value[0];
  } else {
    selectedProduct.value = null;
    supplierProducts.value = [];
  }
};

// Actualizar opciones de la tabla
const updateProductsTableOptions = (options) => {
  productsPage.value = options.page;
  productsItemPerPage.value = options.itemsPerPage;
  if (options.sortBy) {
    // Manejar ordenamiento si es necesario
  }
};

// Watch para recargar productos cuando cambia el producto seleccionado
watch(selectedProduct, () => {
  if (selectedProduct.value) {
    filterSearchQuery.value = selectedProduct.value.name;
    productsPage.value = 1;
    fetchSupplierProducts();
  }
});

onMounted(() => {
  try {
    const productosParam = route.query.productos;
    if (productosParam) {
      allProducts.value = JSON.parse(productosParam);
      
      // Seleccionar automáticamente el primer producto
      if (allProducts.value.length > 0) {
        selectedProduct.value = allProducts.value[0];
      }
    }
  } catch (error) {
    console.error('Error al parsear productos:', error);
    toast.error('Error al cargar los productos');
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="pa-6">
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center justify-space-between">
        <div>
          <h2 class="text-h5">Productos Sin Proveedores</h2>
          <span class="text-caption text-medium-emphasis">
            Generado el: {{ new Date().toLocaleDateString('es-ES') }}
          </span>
        </div>
        <VChip color="primary" variant="tonal" size="large">
          Pendientes: {{ allProducts.length }}
        </VChip>
      </VCardTitle>

      <VDivider />

      <VCardText>
        <VRow>
          <VCol cols="12">
            <VSelect
              v-model="selectedProduct"
              :items="allProducts"
              item-title="name"
              item-value="id"
              return-object
              label="Seleccionar Producto"
              :loading="loading"
              clearable
            >
              <template #item="{ item, props: itemProps }">
                <VListItem v-bind="itemProps">
                  <template #prepend>
                    <VAvatar
                      v-if="item.raw.photo_url"
                      size="40"
                      rounded
                      variant="tonal"
                      :image="item.raw.photo_url"
                      class="me-3"
                    />
                  </template>
                  <VListItemTitle>
                    {{ item.raw.name }}
                  </VListItemTitle>
                  <VListItemSubtitle>
                    ID: {{ item.raw.id }} | Lab: {{ item.raw.laboratory?.name || 'Sin laboratorio' }}
                  </VListItemSubtitle>
                </VListItem>
              </template>
            </VSelect>
          </VCol>
          
          <VCol cols="12" v-if="selectedProduct">
            <div class="d-flex align-center gap-2">
              <VChip color="info" variant="tonal">
                Producto seleccionado: {{ selectedProduct.name }}
              </VChip>
              <VSpacer />
              <VBtn
                color="error"
                variant="outlined"
                icon="tabler-x"
                @click="markAsNotAvailable"
              >
                No se consiguió
              </VBtn>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard v-if="selectedProduct">
      <VCardTitle>
        Productos de Proveedores Disponibles
      </VCardTitle>
      <VDivider />
      <ProductComparisionProductsTable
        :products="supplierProducts"
        :loading="loadingProducts"
        :total-products="productsTotal"
        :items-per-page="productsItemPerPage"
        :page="productsPage"
        :quantity-errors="quantityErrors"
        :enable-usd-amount-col="enableUsdAmountCol"
        :enable-discount-col="enableDiscountCol"
        :search-query="filterSearchQuery"
        @update:search-query="filterSearchQuery = $event"
        :is-strict-search="isStrictSearch"
        @update:is-strict-search="isStrictSearch = $event"
        @update:options="updateProductsTableOptions"
        @send-product="handleAddItemToAutoOrder"
      />
    </VCard>

    <VCard v-else class="text-center pa-8">
      <VIcon icon="tabler-inbox" size="64" class="text-disabled mb-4" />
      <p class="text-h6 text-medium-emphasis">No hay productos seleccionados</p>
      <p class="text-body-2 text-disabled">
        Selecciona un producto de la lista para ver los proveedores disponibles
      </p>
    </VCard>
  </div>
</template>

