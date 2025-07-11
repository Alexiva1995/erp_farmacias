<script setup>
import { ref, watch, nextTick } from "vue"; 
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const inputQuantities = ref(new Map()); 
const emit = defineEmits(['update:options', 'add-product']);

const headers = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Stock', key: 'valid_stock_sum',sortable: true},
  //{ title: 'Código de Barra', key: 'barcode', sortable: false },
  { title: 'Producto', key: 'name',  sortable: true},
  { title: 'Precio en USD', key: 'sale_price', sortable: true },
  { title: 'Precio en Bs', key: 'price_bs', sortable: true},
  { title: 'Precio en COP', key: 'price_cop', sortable: true },
  { title: 'Añadir', key: 'add_action_with_quantity', sortable: false, width: '150px'},
];

watch(() => props.products, (newProducts) => {
  const newQuantitiesMap = new Map();
  newProducts.forEach(product => {
    let currentQty;
    if (product.valid_stock_sum === 0) {
      currentQty = 0;
    } else {
      let previousQty = inputQuantities.value.get(product.id);
      if (previousQty === undefined || previousQty === null || previousQty < 1) {
        currentQty = 1;
      } else {
        currentQty = previousQty;
      }
      if (currentQty > product.valid_stock_sum) {
        currentQty = product.valid_stock_sum;
      }
    }
    newQuantitiesMap.set(product.id, currentQty);
  });
  inputQuantities.value = newQuantitiesMap;
}, { immediate: true });


const handleInputQuantityChange = (productId, val) => {
  let cleanVal = parseInt(val);
  const maxQty = props.products.find(p => p.id === productId)?.valid_stock_sum ?? 0;
  if (isNaN(cleanVal) || cleanVal < 0) {
    cleanVal = 0;
  }
  if (maxQty === 0) {
    cleanVal = 0;
  } else if (cleanVal > maxQty) {
    cleanVal = maxQty;
  }
  inputQuantities.value.set(productId, cleanVal);
};


const handleAddProduct = (productId) => {
  const quantityToAdd = inputQuantities.value.get(productId);
  if (quantityToAdd === null || quantityToAdd === undefined || quantityToAdd <= 0) {
    console.error(`La cantidad para el producto ${productId} no es válida (${quantityToAdd}). Debe ser un número positivo para añadir.`);
    return;
  }
  emit('add-product', { productId, quantity: quantityToAdd });
};

const calculatePriceWithIVA = (basePrice, product) => {
  const price = parseFloat(basePrice) || 0;
  let taxRate = product.iva == 1 ? 0.16 : 0;
  if (taxRate > 0) {
    return price * (1 + taxRate);
  }
  return price;
};

const calculateAndFormatCopPriceWithIVA = (basePrice, product) => {
  const priceWithIVA = calculatePriceWithIVA(basePrice, product);
  return formatCurrency(roundUpToNearestHundred(priceWithIVA), 'COP');
};

</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProduct"
      :loading="props.loading"
      class="text-no-wrap"
      fixed-header height="auto"
      @update:options="options => emit('update:options', options)"
    >
      <template #item.id="{ item }"><span class="font-weight-medium">{{ item.id }}</span></template>
      <template #item.valid_stock_sum="{ item }"><span class="font-weight-medium d-block">{{ item.valid_stock_sum }}</span></template>
      <template #item.barcode="{ item }"><span class="font-weight-medium">{{ item.barcode }}</span></template>
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.name }}</span>
            <span class="text-sm text-disabled">{{ item.active_ingredient }}</span>
          </div>
        </div>
      </template>

      <template #item.sale_price="{ item }"><span class="font-weight-medium">{{ formatCurrency(calculatePriceWithIVA(item.sale_price, item), 'USD')}}</span></template>
      <template #item.price_bs="{ item }"><span class="font-weight-medium">{{ formatCurrency(calculatePriceWithIVA(item.price_bs, item), 'BS') }}</span></template>
      <template #item.price_cop="{ item }"><span class="font-weight-medium">{{calculateAndFormatCopPriceWithIVA(item.price_cop, item) }}</span></template>

      <template #item.add_action_with_quantity="{ item }">
        <div class="d-flex align-center gap-2">
          <VTextField
            :model-value="inputQuantities.get(item.id) ?? 0"
            @update:model-value="val => handleInputQuantityChange(item.id, val)"
            type="number"
            min="0"
            :max="item.valid_stock_sum"
            density="compact"
            variant="outlined"
            hide-details
            single-line
            style="max-width: 90px;min-width: 90px;"
            class="my-2 quantity-input-field"
            :disabled="item.valid_stock_sum === 0"
          />
          <IconBtn
            @click="handleAddProduct(item.id)"
            :disabled="
                (inputQuantities.get(item.id) ?? 0) <= 0 || 
                (inputQuantities.get(item.id) ?? 0) > item.valid_stock_sum || 
                item.valid_stock_sum === 0
            "
          >
            <VIcon icon="tabler-plus" />
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
<style scoped>
:deep(.v-data-table__thead .v-data-table__th:nth-child(2)),
:deep(.v-data-table__tbody .v-data-table__td:nth-child(2)){
  width: 50px !important;
  min-width: 60px !important;
  max-width: 70px !important;
  padding-left: 8px !important;
  padding-right: 8px !important;
  text-align: center !important;
}
</style>
