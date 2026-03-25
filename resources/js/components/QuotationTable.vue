<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { ref, watch } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const inputQuantities = ref(new Map()); 
const emit = defineEmits(['update:options', 'add-product', 'view-group-products', 'failures-products']);

const headers = [
  { title: "ID", key: "id", sortable: true, width: "70px" },
  { title: "Stock", key: "valid_stock_sum", sortable: true, width: "80px", align: "center" },
  { title: "Producto", key: "name", sortable: true },
  { title: "Laboratorio", key: "laboratory_name", sortable: true },
  { title: "USD", key: "sale_price", sortable: true, align: "end" },
  { title: "Bs", key: "price_bs", sortable: true, align: "end" },
  { title: "COP", key: "price_cop", sortable: true, align: "end" },
  {
    title: "Añadir",
    key: "add_action_with_quantity",
    sortable: false,
    width: "140px",
    align: "center"
  },
  { title: "Acción", key: "actions", sortable: false, width: "100px", align: "center" },
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
  if (isNaN(cleanVal) || cleanVal < 0) cleanVal = 0;
  if (maxQty === 0) {
    cleanVal = 0;
  } else if (cleanVal > maxQty) {
    cleanVal = maxQty;
  }
  inputQuantities.value.set(productId, cleanVal);
};

const handleAddProduct = (productId) => {
  const quantityToAdd = inputQuantities.value.get(productId);
  if (quantityToAdd === null || quantityToAdd === undefined || quantityToAdd <= 0) return;
  emit('add-product', { productId, quantity: quantityToAdd });
};

const calculatePriceWithIVA = (basePrice, product) => {
  const price = parseFloat(basePrice) || 0;
  let taxRate = product.iva == 1 ? 0.16 : 0;
  return taxRate > 0 ? price * (1 + taxRate) : price;
};

const calculateAndFormatCopPriceWithIVA = (basePrice, product) => {
  const priceWithIVA = calculatePriceWithIVA(basePrice, product);
  return formatCurrency(roundUpToNearestHundred(priceWithIVA), 'COP');
};

const handleViewGroupProducts = (product) => {
  emit('view-group-products', product.group_id);
};

const handleFailures = (product) => {
  emit('failures-products', product.id);
};

const handleMobilePageChange = (newPage) => {
  emit('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
};
</script>

<template>
  <VCard variant="flat" border class="rounded-lg overflow-hidden elevation-1">
    <!-- Vista de Escritorio -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.products"
        :items-length="props.totalProduct"
        :loading="props.loading"
        class="text-no-wrap quotation-table-premium"
        @update:options="options => emit('update:options', options)"
        hover
      >
        <template #item.id="{ item }">
          <span class="font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <template #item.valid_stock_sum="{ item }">
          <VChip
            :color="item.valid_stock_sum > 0 ? 'success' : 'error'"
            size="x-small"
            variant="flat"
            class="font-weight-black px-2 shadow-sm"
          >
            {{ item.valid_stock_sum }}
          </VChip>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex flex-column py-2">
            <div class="d-flex align-center gap-1 mb-0 pb-0">
              <span class="text-primary font-weight-black text-xs">#{{ item.id }}</span>
              <span class="text-subtitle-2 font-weight-black text-uppercase leading-tight" style="font-size: 0.85rem !important;">{{ item.name.toUpperCase() }}</span>
              <VChip v-if="item.iva == 1" size="x-small" color="primary" variant="tonal" class="ms-1 font-weight-bold">IVA</VChip>
              <VChip v-if="item.is_colombian_origin == 1" size="x-small" color="info" variant="tonal" class="ms-1 font-weight-bold">COL</VChip>
            </div>
            <div class="text-caption leading-tight d-flex align-center gap-1 mt-0 pt-0">
              <span class="text-disabled" style="font-size: 0.75rem !important;">{{ item.active_ingredient || '—' }}</span>
              <span class="text-disabled" style="font-size: 0.75rem !important;">|</span>
              <span class="text-primary font-weight-bold uppercase" style="font-size: 0.75rem !important;">{{ item.laboratory?.name || 'S/L' }}</span>
            </div>
          </div>
        </template>

        <template #item.laboratory_name="{ item }">
          <span class="text-caption font-weight-bold text-medium-emphasis">{{ item.laboratory?.name || 'GENÉRICO' }}</span>
        </template>

        <template #item.sale_price="{ item }">
          <span class="font-weight-black text-primary">{{ formatCurrency(calculatePriceWithIVA(item.sale_price, item), 'USD') }}</span>
        </template>

        <template #item.price_bs="{ item }">
          <span class="font-weight-bold text-medium-emphasis">{{ formatCurrency(calculatePriceWithIVA(item.price_bs, item), 'BS') }}</span>
        </template>

        <template #item.price_cop="{ item }">
          <span class="font-weight-bold text-medium-emphasis">{{ calculateAndFormatCopPriceWithIVA(item.price_cop, item) }}</span>
        </template>

        <template #item.add_action_with_quantity="{ item }">
          <div class="d-flex align-center gap-2 justify-center">
            <VTextField
              :model-value="inputQuantities.get(item.id) ?? 0"
              @update:model-value="(val) => handleInputQuantityChange(item.id, val)"
              type="number"
              min="0"
              :max="item.valid_stock_sum"
              density="compact"
              variant="outlined"
              hide-details
              single-line
              class="quantity-input-field font-weight-black"
              :disabled="item.valid_stock_sum === 0"
            />
            <VBtn
              icon="tabler-plus"
              size="32"
              color="primary"
              variant="flat"
              class="rounded-lg shadow-sm"
              @click="handleAddProduct(item.id)"
              :disabled="(inputQuantities.get(item.id) ?? 0) <= 0 || item.valid_stock_sum === 0"
            />
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-center">
            <VBtn icon="tabler-eye" size="30" variant="tonal" color="info" class="rounded-lg" @click="handleViewGroupProducts(item)" />
            <VBtn icon="tabler-alert-triangle" size="30" variant="tonal" color="error" class="rounded-lg" @click="handleFailures(item)" />
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Cards) -->
    <div class="d-block d-md-none pa-3">
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-3" />
      
      <div v-if="props.products.length === 0 && !props.loading" class="text-center py-10 text-disabled">
        <VIcon icon="tabler-search-off" size="48" class="mb-2" />
        <div class="font-weight-bold">No se encontraron productos</div>
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          variant="flat"
          class="border mb-2 overflow-hidden premium-mobile-card elevation-1"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-2">
              <span class="text-primary font-weight-black text-xs">#{{ item.id }}</span>
              <VChip
                :color="item.valid_stock_sum > 0 ? 'success' : 'error'"
                size="x-small"
                variant="flat"
                class="font-weight-black"
              >
                STOCK: {{ item.valid_stock_sum }}
              </VChip>
            </div>

            <div class="d-flex align-center gap-1 mb-1">
              <span class="text-primary font-weight-black text-xs">#{{ item.id }}</span>
              <h3 class="text-subtitle-2 font-weight-950 text-high-emphasis text-uppercase leading-tight mb-0">
                {{ item.name.toUpperCase() }}
              </h3>
            </div>
            
            <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs mb-3">
              <span class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">{{ item.active_ingredient || '—' }}</span>
              <span class="text-disabled">|</span>
              <span class="text-primary font-weight-bold text-truncate" style="max-inline-size: 120px;">{{ item.laboratory?.name || 'S/L' }}</span>
            </div>
            
            <div class="d-flex gap-1 mb-3">
              <VChip v-if="item.iva == 1" size="x-small" color="primary" variant="flat" class="font-weight-black px-2 shadow-sm">IVA</VChip>
              <VChip v-if="item.is_colombian_origin == 1" size="x-small" color="info" variant="flat" class="font-weight-black px-2 shadow-sm">COL</VChip>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-grid mobile-price-grid gap-2 mb-4">
              <div class="price-box">
                <span class="label">USD</span>
                <span class="value text-primary font-weight-black">{{ formatCurrency(calculatePriceWithIVA(item.sale_price, item), 'USD') }}</span>
              </div>
              <div class="price-box">
                <span class="label">Bs</span>
                <span class="value text-medium-emphasis">{{ formatCurrency(calculatePriceWithIVA(item.price_bs, item), 'BS') }}</span>
              </div>
              <div class="price-box">
                <span class="label">COP</span>
                <span class="value text-medium-emphasis">{{ calculateAndFormatCopPriceWithIVA(item.price_cop, item) }}</span>
              </div>
            </div>

            <div class="d-flex gap-2 mb-3">
              <VBtn variant="tonal" color="info" size="small" class="rounded-lg flex-grow-1 font-weight-black" @click="handleViewGroupProducts(item)">
                <VIcon start icon="tabler-eye" size="16" /> GRUPO
              </VBtn>
              <VBtn variant="tonal" color="error" size="small" class="rounded-lg flex-grow-1 font-weight-black" @click="handleFailures(item)">
                <VIcon start icon="tabler-alert-triangle" size="16" /> FALLA
              </VBtn>
            </div>

            <div class="d-flex align-center gap-2 mt-2">
              <VTextField
                :model-value="inputQuantities.get(item.id) ?? 0"
                @update:model-value="(val) => handleInputQuantityChange(item.id, val)"
                type="number"
                label="CANT."
                density="compact"
                variant="outlined"
                hide-details
                class="flex-grow-1 font-weight-black"
                :disabled="item.valid_stock_sum === 0"
              />
              <VBtn
                color="primary"
                height="40"
                class="rounded-lg font-weight-black px-4"
                @click="handleAddProduct(item.id)"
                :disabled="(inputQuantities.get(item.id) ?? 0) <= 0 || item.valid_stock_sum === 0"
              >
                <VIcon icon="tabler-shopping-cart-plus" />
              </VBtn>
            </div>
          </div>
        </VCard>
      </div>

      <div class="d-flex justify-center mt-6 pb-2">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalProduct / props.itemsPerPage)"
          :total-visible="3"
          density="compact"
          size="small"
          active-color="primary"
          @update:model-value="handleMobilePageChange"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.bg-light {
  background-color: #f8fafc !important;
}

.premium-mobile-card {
  border-radius: 8px !important;
  background: white;
  transition: all 0.2s ease;
}

.mobile-price-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
}

.price-box {
  display: flex;
  flex-direction: column;
}

.price-box .label {
  color: rgba(var(--v-theme-on-surface), 0.4);
  font-size: 0.6rem;
  font-weight: 900;
  margin-block-end: 2px;
  text-transform: uppercase;
}

.price-box .value {
  font-size: 0.75rem;
}

.quantity-input-field {
  max-inline-size: 80px;
}

.quotation-table-premium :deep(thead th) {
  background-color: white !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
