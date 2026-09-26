<script setup>
import ProductFilters from "@/components/ProductFilters.vue";
import ProductTable from "@/components/ProductTable.vue";
import BarcodeSearchModal from "@/components/dialogs/BarcodeSearchModal.vue";
import ProductEditDialog from "@/components/dialogs/ProductEditDialog.vue";
import { ref } from "vue";

const props = defineProps({
  isProductSearchVisible: {
    type: Boolean,
    default: false,
  },
  products: {
    type: Array,
    default: () => [],
  },
  totalProducts: {
    type: Number,
    default: 0,
  },
  loadingProducts: {
    type: Boolean,
    default: false,
  },
  laboratories: {
    type: Array,
    default: () => [],
  },
  origins: {
    type: Array,
    default: () => [],
  },
  categories: {
    type: Array,
    default: () => [],
  },
  isLoadingFilters: {
    type: Boolean,
    default: false,
  },
  productPage: {
    type: Number,
    default: 1,
  },
  productItemsPerPage: {
    type: Number,
    default: 10,
  },
});

const emit = defineEmits([
  "update:isProductSearchVisible",
  "update:searchQuery",
  "update:page",
  "update-table-options",
  "add-product-to-invoice",
  "save-product",
  "laboratory-created",
  "search-barcode",
]);

const searchQueryModel = defineModel("searchQuery", { type: String, default: "" });

const isBarcodeModalVisible = ref(false);
const isEditDialogVisible = ref(false);
const searchingBarcode = ref(false);
const currentProduct = ref({});
const productFormErrors = ref({});
const barcodeModalRef = ref(null);

const handleAddNewProduct = () => {
  currentProduct.value = {};
  productFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleSaveProductInternal = async (formData) => {
  emit("save-product", formData, {
    onSuccess: () => {
      isEditDialogVisible.value = false;
    },
    onError: (errors) => {
      productFormErrors.value = errors;
    },
  });
};

const openBarcodeModal = () => {
  isBarcodeModalVisible.value = true;
};

defineExpose({
  openBarcodeModal,
  handleProductFound: (product) => barcodeModalRef.value?.handleProductFound(product),
  handleProductNotFound: () => barcodeModalRef.value?.handleProductNotFound(),
  setSearchingBarcode: (val) => { searchingBarcode.value = val; },
});
</script>

<template>
  <div>
    <div
      v-if="isProductSearchVisible"
      class="product-search-section mt-6"
    >
      <div class="d-flex align-center justify-space-between mb-4">
        <h4 class="text-h4">Buscar Productos en Catálogo</h4>
        <VBtn
          variant="text"
          color="error"
          @click="emit('update:isProductSearchVisible', false)"
        >
          <VIcon icon="tabler-x" class="me-2" />Cerrar Búsqueda
        </VBtn>
      </div>
      <ProductFilters
        v-model:search-query="searchQueryModel"
        :laboratories="laboratories"
        :origins="origins"
        :loading="isLoadingFilters"
        mode="minimal"
        @clear="searchQueryModel = ''"
        @add-product="handleAddNewProduct"
      />
      <ProductTable
        :products="products"
        :loading="loadingProducts"
        :total-product="totalProducts"
        :items-per-page="productItemsPerPage"
        :page="productPage"
        mode="add-to-invoice"
        @update:options="emit('update-table-options', $event)"
        @add-product-to-invoice="emit('add-product-to-invoice', $event)"
      />
    </div>

    <!-- Modal de Búsqueda por Código de Barras -->
    <BarcodeSearchModal
      ref="barcodeModalRef"
      v-model="isBarcodeModalVisible"
      :loading="searchingBarcode"
      @search-barcode="emit('search-barcode', $event)"
      @show-product-search="emit('update:isProductSearchVisible', true)"
      @add-new-product="handleAddNewProduct"
      @add-product-to-invoice="emit('add-product-to-invoice', $event)"
    />

    <!-- Diálogo de Creación / Edición de Producto -->
    <ProductEditDialog
      v-model="isEditDialogVisible"
      :product="currentProduct"
      :laboratories="laboratories"
      :origins="origins"
      :categories="categories"
      :errors="productFormErrors"
      @save="handleSaveProductInternal"
      @clear-errors="productFormErrors = {}"
      @laboratory-created="emit('laboratory-created')"
    />
  </div>
</template>
