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
  { title: 'Unidades', key: 'lots_sum_quantity', sortable: true },
  { title: 'Código', key: 'id', sortable: true },
  { title: 'Código de Barra', key: 'barcode', sortable: true },
  { title: 'Producto', key: 'name' },
  { title: 'Precio en USD', key: 'sale_price', sortable: false },
  { title: 'Precio en Bs', key: 'price_bs' },
  { title: 'Precio en COP', key: 'price_cop' },
  { title: 'Añadir', key: 'add_action_with_quantity', sortable: false, width: '150px'  },
];

watch(() => props.products, (newProducts) => {
  const newQuantitiesMap = new Map();
  newProducts.forEach(product => {
    let currentQty = inputQuantities.value.has(product.id)
                       ? inputQuantities.value.get(product.id)
                       : (product.lots_sum_quantity === 0 ? 0 : 1);
    if (product.lots_sum_quantity === 0) {
      currentQty = 0;
    } else if (currentQty < 1) {
      currentQty = 1;
    } else if (currentQty > product.lots_sum_quantity) {
      currentQty = product.lots_sum_quantity;
    }

    newQuantitiesMap.set(product.id, currentQty);
  });
  inputQuantities.value = newQuantitiesMap;
}, { immediate: true });


const handleInputQuantityChange = (productId, val) => {
  let cleanVal = parseInt(val);
  const maxQty = props.products.find(p => p.id === productId)?.lots_sum_quantity ?? 0;
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
      <template #item.lots_sum_quantity="{ item }"><span class="font-weight-medium">{{ item.lots_sum_quantity }}</span></template>
      <template #item.id="{ item }"><span class="font-weight-medium">{{ item.id }}</span></template>
      <template #item.barcode="{ item }"><span class="font-weight-medium">{{ item.barcode }}</span></template>
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.name }}</span>
            <span class="text-sm text-disabled">{{ item.active_ingredient }}</span>
          </div>
        </div>
      </template>

      <template #item.sale_price="{ item }"><span class="font-weight-medium">{{ formatCurrency(item.sale_price,'USD') }}</span></template>
      <template #item.price_bs="{ item }"><span class="font-weight-medium">{{ formatCurrency(item.price_bs,'BS') }}</span></template>
      <template #item.price_cop="{ item }"><span class="font-weight-medium">{{ formatCurrency(item.price_cop,'COP') }}</span></template>

      <template #item.add_action_with_quantity="{ item }">
        <div class="d-flex align-center gap-2">
          <VTextField
            :model-value="inputQuantities.get(item.id) ?? 0"
            @update:model-value="val => handleInputQuantityChange(item.id, val)"
            type="number"
            min="0"
            :max="item.lots_sum_quantity"
            density="compact"
            variant="outlined"
            hide-details
            single-line
            style="max-width: 90px;"
            class="my-2 quantity-input-field"
            :disabled="item.lots_sum_quantity === 0"
          />
          <IconBtn
            @click="handleAddProduct(item.id)"
            :disabled="
                (inputQuantities.get(item.id) ?? 0) <= 0 || 
                (inputQuantities.get(item.id) ?? 0) > item.lots_sum_quantity || 
                item.lots_sum_quantity === 0
            "
          >
            <VIcon icon="tabler-plus" />
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
