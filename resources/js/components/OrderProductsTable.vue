<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import axios from "@/plugins/axios";
import { computed, ref, watch, onMounted, defineProps } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  discountMinProducts: { type: Number, default: 0 },
  discountMaxProducts: { type: Number, default: 0 },
  currentDiscount: { type: Number, default: 0 },
  orderItems: { type: Array, default: () => [] },
});

const inputQuantities = ref(new Map());
const emit = defineEmits([
  "update:options",
  "update:page",
  "update:itemsPerPage",
  "sort",
  "add-product",
  "view-group-products",
  "failures-products",
  "view-pack-details",
  "add-pack",
]);

const isInitialLoad = ref(true);

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
});

const headers = [
  { title: "ID", key: "id", sortable: true, maxWidth: "50px" },
  { title: "Stock", key: "valid_stock_sum", sortable: true, width: "80px" },
  { title: "Producto", key: "name", sortable: true },
  { title: "Laboratorio", key: "laboratory_name", sortable: true },
  { title: "USD", key: "sale_price", sortable: true, align: "end" },
  { title: "Bs", key: "price_bs", sortable: true, align: "end" },
  { title: "COP", key: "price_cop", sortable: true, align: "end" },
  { title: "Añadir", key: "add_action_with_quantity", sortable: false, width: "150px" },
  { title: "Acción", key: "actions", sortable: false, width: "100px" },
];

const totalProductsInCart = computed(() => {
  let total = 0;
  total += props.orderItems.reduce((sum, item) => sum + (item.selectedQuantity || 0), 0);
  return total;
});

const shouldApplyDiscount = computed(() => {
  if (props.currentDiscount <= 0) return false;
  if (props.discountMinProducts === 0 && props.discountMaxProducts === 0) return true;
  return totalProductsInCart.value >= props.discountMinProducts && totalProductsInCart.value <= props.discountMaxProducts;
});

const calculatePriceWithDiscount = (basePrice, product = null) => {
  const price = parseFloat(basePrice) || 0;
  const discountPcts = [];
  if (product?.discount_percentage > 0 && (product?.discount_type === "individual" || product?.discount_type === "category" || product?.discount_type === "expiration")) {
    discountPcts.push(parseFloat(product.discount_percentage));
  }
  if (shouldApplyDiscount.value && props.currentDiscount > 0) {
    discountPcts.push(parseFloat(props.currentDiscount));
  }
  const bestPct = discountPcts.length > 0 ? Math.max(...discountPcts) : 0;
  return bestPct > 0 ? price * (1 - bestPct / 100) : price;
};

const calculatePriceWithIVAAndDiscount = (basePrice, product) => {
  let effectivePrice = calculatePriceWithDiscount(basePrice, product);
  let taxRate = product.iva == 1 ? 0.16 : 0;
  return taxRate > 0 ? effectivePrice * (1 + taxRate) : effectivePrice;
};

const calculateAndFormatCopPriceWithIVAAndDiscount = (basePrice, product) => {
  const priceWithIVA = calculatePriceWithIVAAndDiscount(basePrice, product);
  return formatCurrency(roundUpToNearestHundred(priceWithIVA), "COP");
};

const handleAddProduct = (productId) => {
  const quantityToAdd = inputQuantities.value.get(productId);
  if (quantityToAdd === null || quantityToAdd === undefined || quantityToAdd <= 0) return;
  emit("add-product", { productId, quantity: quantityToAdd });
  inputQuantities.value.set(productId, 1);
};

const handleAddPack = (packId) => {
  const quantityToAdd = inputQuantities.value.get(packId);
  if (quantityToAdd === null || quantityToAdd === undefined || quantityToAdd <= 0) return;
  const pack = props.products.find((p) => p.id === packId);
  if (pack) emit("add-pack", { pack, quantity: quantityToAdd });
  inputQuantities.value.set(packId, 1);
};

watch(() => props.products, (newProducts) => {
  const newOrderMap = new Map();
  newProducts.forEach((product) => {
    let currentQty = product.valid_stock_sum === 0 ? 0 : (inputQuantities.value.get(product.id) || 1);
    if (currentQty > product.valid_stock_sum && product.valid_stock_sum > 0) currentQty = product.valid_stock_sum;
    newOrderMap.set(product.id, currentQty);
  });
  inputQuantities.value = newOrderMap;
}, { immediate: true });

const handleInputOrderChange = (productId, val) => {
  let cleanVal = parseInt(val);
  const maxQty = props.products.find((p) => p.id === productId)?.valid_stock_sum ?? 0;
  if (isNaN(cleanVal) || cleanVal < 0) cleanVal = 0;
  if (maxQty === 0) cleanVal = 0;
  else if (cleanVal > maxQty) cleanVal = maxQty;
  inputQuantities.value.set(productId, cleanVal);
};

const handleViewGroupProducts = (product) => emit("view-group-products", product.group_id);
const handleFailures = (product) => emit("failures-products", product.id);
const handleViewPack = (pack) => emit("view-pack-details", pack);

const calculatePriceWithIVA = (basePrice, product) => {
  const price = parseFloat(basePrice) || 0;
  let taxRate = product.iva == 1 ? 0.16 : 0;
  return taxRate > 0 ? price * (1 + taxRate) : price;
};

const calculateAndFormatCopPriceWithIVA = (basePrice, product) => {
  const priceWithIVA = calculatePriceWithIVA(basePrice, product);
  return formatCurrency(roundUpToNearestHundred(priceWithIVA), "COP");
};

const roundUpToNearestHundred = (value) => Math.ceil(value / 100) * 100;

onMounted(async () => {
  try {
    const { data } = await axios.get('/user/config');
    const config = data.config; 
    if (config && config.sort_products_orders) {
      const [key, order] = config.sort_products_orders.split('|');
      options.value.sortBy = [{ key, order }];
    }
  } catch (error) {
    console.error("Error cargando config inicial");
  } finally {
    setTimeout(() => { isInitialLoad.value = false; }, 1000);
  }
});

const handleUpdateOptions = (newOptions) => {
  emit('update:page', newOptions.page);
  emit('update:itemsPerPage', newOptions.itemsPerPage);
  emit('update:options', newOptions);
};

const getPriceClass = (item) => {
  const hasDiscount = item.discount_percentage > 0 || (shouldApplyDiscount.value && props.currentDiscount > 0);
  if (!hasDiscount) return 'precio-normal';
  if (item.discount_type === 'expiration') return 'precio-expira';
  return 'precio-oferta';
};

const getRowClass = (item) => (item.valid_stock_sum ?? 0) <= 0 ? 'row-zero-stock' : '';
</script>

<template>
  <VCard variant="flat" border class="rounded-xl overflow-hidden shadow-sm">
    <!-- Vista Escritorio -->
    <VDataTableServer
      v-model:options="options"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProduct"
      :loading="props.loading"
      class="text-no-wrap premium-table d-none d-md-block"
      :row-props="(data) => ({ class: getRowClass(data.item) })"
      @update:options="handleUpdateOptions"
    >
      <template #item.id="{ item }">
        <span class="font-weight-black text-disabled">{{ item.id }}</span>
      </template>

      <template #item.valid_stock_sum="{ item }">
        <VChip
          :color="item.valid_stock_sum > 0 ? 'success' : 'error'"
          size="small"
          variant="tonal"
          class="font-weight-black"
        >
          {{ item.valid_stock_sum }}
        </VChip>
      </template>

      <template #item.name="{ item }">
        <div class="d-flex flex-column py-2" style="max-inline-size: 300px;">
          <span
            class="text-body-1 font-weight-black text-high-emphasis leading-tight"
            :class="{ 'text-primary': item.psychotropic == 1 }"
            style="white-space: normal;"
          >
            {{ item.name }}
            <span v-if="item.iva == 1" class="text-xs text-disabled">(G)</span>
            <VChip
              v-if="item.discount_type === 'expiration' && item.discount_percentage > 0"
              color="error"
              size="x-small"
              class="ms-1 font-weight-black uppercase"
              label
            >
              Expira (-{{ item.discount_percentage }}%)
            </VChip>
          </span>
          <span class="text-xs font-weight-bold text-disabled uppercase mt-1" style="white-space: normal;">
            {{ item.active_ingredient }}
            {{ item.origin?.name ? `• ${item.origin.name}` : '' }}
          </span>
        </div>
      </template>

      <template #item.laboratory_name="{ item }">
        <span class="text-caption font-weight-black uppercase text-disabled">{{ item.laboratory_name }}</span>
      </template>

      <template #item.sale_price="{ item }">
        <div class="d-flex flex-column align-end">
          <del v-if="calculatePriceWithIVA(item.sale_price, item) > calculatePriceWithIVAAndDiscount(item.sale_price, item)" class="precio-tachado">
            {{ formatCurrency(calculatePriceWithIVA(item.sale_price, item)) }}
          </del>
          <span :class="getPriceClass(item)" class="font-weight-black">
            {{ formatCurrency(calculatePriceWithIVAAndDiscount(item.sale_price, item)) }}
          </span>
        </div>
      </template>

      <template #item.price_bs="{ item }">
        <div class="d-flex flex-column align-end">
          <del v-if="calculatePriceWithIVA(item.price_bs, item) > calculatePriceWithIVAAndDiscount(item.price_bs, item)" class="precio-tachado">
            {{ formatCurrency(calculatePriceWithIVA(item.price_bs, item), "BS") }}
          </del>
          <span :class="getPriceClass(item)" class="font-weight-black">
            {{ formatCurrency(calculatePriceWithIVAAndDiscount(item.price_bs, item), "BS") }}
          </span>
        </div>
      </template>

      <template #item.price_cop="{ item }">
        <div class="d-flex flex-column align-end">
          <del v-if="calculatePriceWithIVA(item.price_cop, item) > calculatePriceWithIVAAndDiscount(item.price_cop, item)" class="precio-tachado">
            {{ calculateAndFormatCopPriceWithIVA(item.price_cop, item) }}
          </del>
          <span :class="getPriceClass(item)" class="font-weight-black">
            {{ calculateAndFormatCopPriceWithIVAAndDiscount(item.price_cop, item) }}
          </span>
        </div>
      </template>

      <template #item.add_action_with_quantity="{ item }">
        <div class="d-flex align-center gap-2">
          <VTextField
            :model-value="inputQuantities.get(item.id) ?? 0"
            @update:model-value="(val) => handleInputOrderChange(item.id, val)"
            type="number"
            min="0"
            :max="item.valid_stock_sum"
            density="compact"
            variant="outlined"
            hide-details
            class="rounded-lg font-weight-black quantity-input"
            style="inline-size: 70px;"
            :disabled="(item.valid_stock_sum ?? 0) <= 0"
          />
          <VBtn
            v-if="item.item_type === 'product'"
            color="primary"
            variant="tonal"
            icon="tabler-shopping-cart-plus"
            size="small"
            class="rounded-lg"
            :disabled="(inputQuantities.get(item.id) ?? 0) <= 0 || (item.valid_stock_sum ?? 0) <= 0"
            @click="handleAddProduct(item.id)"
          />
          <VBtn
            v-else
            color="primary"
            variant="flat"
            icon="tabler-package-import"
            size="small"
            class="rounded-lg"
            :disabled="(inputQuantities.get(item.id) ?? 0) <= 0"
            @click="handleAddPack(item.id)"
          />
        </div>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <VBtn
            icon="tabler-eye"
            variant="text"
            color="info"
            size="small"
            @click="item.item_type === 'product' ? handleViewGroupProducts(item) : handleViewPack(item)"
          />
          <VBtn
            icon="tabler-alert-triangle"
            variant="text"
            color="error"
            size="small"
            :disabled="item.item_type === 'pack'"
            @click="handleFailures(item)"
          />
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil (Cards) -->
    <div class="d-md-none pa-4 bg-grey-lighten-4">
      <div v-if="props.loading" class="text-center py-8">
        <VProgressCircular indeterminate color="primary" />
      </div>
      <div v-else-if="props.products.length === 0" class="text-center py-8 text-disabled font-weight-black uppercase">
        No se encontraron productos
      </div>
      <div v-else class="d-flex flex-column gap-4">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          variant="flat"
          border
          class="rounded-xl premium-mobile-card shadow-sm"
          :class="{ 'border-error border-opacity-50': (item.valid_stock_sum ?? 0) <= 0 }"
        >
          <VCardText class="pa-4">
            <div class="d-flex justify-space-between align-start mb-2">
              <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                <span 
                  class="text-body-1 font-weight-black leading-tight mb-1"
                  :class="{ 'text-primary': item.psychotropic == 1 }"
                >
                  {{ item.name }}
                  <span v-if="item.iva == 1" class="text-xs text-disabled">(G)</span>
                </span>
                <span class="text-super-xs font-weight-black text-disabled uppercase">
                  {{ item.active_ingredient }} • {{ item.laboratory_name }}
                </span>
              </div>
              <VChip
                :color="item.valid_stock_sum > 0 ? 'success' : 'error'"
                size="small"
                variant="flat"
                class="font-weight-black ms-2 px-3"
              >
                Stock: {{ item.valid_stock_sum }}
              </VChip>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="grid-prices mb-4">
              <div class="price-box">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">USD</span>
                <div class="d-flex flex-column">
                  <del v-if="calculatePriceWithIVA(item.sale_price, item) > calculatePriceWithIVAAndDiscount(item.sale_price, item)" class="text-xs text-disabled text-decoration-line-through">
                    {{ formatCurrency(calculatePriceWithIVA(item.sale_price, item)) }}
                  </del>
                  <span class="text-body-2 font-weight-black" :class="getPriceClass(item)">
                    {{ formatCurrency(calculatePriceWithIVAAndDiscount(item.sale_price, item)) }}
                  </span>
                </div>
              </div>
              <div class="price-box">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">BS</span>
                <div class="d-flex flex-column">
                  <del v-if="calculatePriceWithIVA(item.price_bs, item) > calculatePriceWithIVAAndDiscount(item.price_bs, item)" class="text-xs text-disabled text-decoration-line-through">
                    {{ formatCurrency(calculatePriceWithIVA(item.price_bs, item), "BS") }}
                  </del>
                  <span class="text-body-2 font-weight-black" :class="getPriceClass(item)">
                    {{ formatCurrency(calculatePriceWithIVAAndDiscount(item.price_bs, item), "BS") }}
                  </span>
                </div>
              </div>
              <div class="price-box">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">COP</span>
                <div class="d-flex flex-column">
                  <del v-if="calculatePriceWithIVA(item.price_cop, item) > calculatePriceWithIVAAndDiscount(item.price_cop, item)" class="text-xs text-disabled text-decoration-line-through">
                    {{ calculateAndFormatCopPriceWithIVA(item.price_cop, item) }}
                  </del>
                  <span class="text-body-2 font-weight-black" :class="getPriceClass(item)">
                    {{ calculateAndFormatCopPriceWithIVAAndDiscount(item.price_cop, item) }}
                  </span>
                </div>
              </div>
            </div>

            <div class="d-flex align-center gap-2">
              <VBtn
                icon="tabler-eye"
                variant="tonal"
                color="info"
                size="small"
                class="rounded-lg"
                @click="item.item_type === 'product' ? handleViewGroupProducts(item) : handleViewPack(item)"
              />
              <VBtn
                icon="tabler-alert-triangle"
                variant="tonal"
                color="error"
                size="small"
                class="rounded-lg"
                :disabled="item.item_type === 'pack'"
                @click="handleFailures(item)"
              />
              <VSpacer />
              <div class="d-flex align-center bg-grey-lighten-4 rounded-lg px-2 py-1 shadow-inner">
                <VTextField
                  :model-value="inputQuantities.get(item.id) ?? 0"
                  @update:model-value="(val) => handleInputOrderChange(item.id, val)"
                  type="number"
                  min="0"
                  density="compact"
                  variant="plain"
                  hide-details
                  class="font-weight-black text-center"
                  style="inline-size: 50px;"
                />
                <VBtn
                  color="primary"
                  variant="flat"
                  :icon="item.item_type === 'product' ? 'tabler-shopping-cart-plus' : 'tabler-package-import'"
                  size="small"
                  class="rounded-lg ms-2"
                  :disabled="(inputQuantities.get(item.id) ?? 0) <= 0 || (item.item_type === 'product' && (item.valid_stock_sum ?? 0) <= 0)"
                  @click="item.item_type === 'product' ? handleAddProduct(item.id) : handleAddPack(item.id)"
                />
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="mt-6 d-flex justify-center flex-wrap gap-2">
        <VPagination
          v-model="options.page"
          :length="Math.ceil(props.totalProduct / props.itemsPerPage)"
          :total-visible="3"
          density="compact"
          active-color="primary"
          class="premium-pagination"
          @update:model-value="(p) => handleUpdateOptions({ ...options, page: p })"
        />
      </div>
    </div>

    <!-- Información del descuento -->
    <VCardText v-if="currentDiscount > 0" class="pa-4 bg-primary-lighten-5 border-t">
      <div class="d-flex flex-column gap-1">
        <div class="d-flex justify-space-between align-center">
          <span class="text-xs font-weight-black uppercase text-disabled">Productos seleccionados:</span>
          <VChip size="x-small" color="primary" class="font-weight-black">{{ totalProductsInCart }}</VChip>
        </div>
        <div class="d-flex justify-space-between align-center">
          <span class="text-xs font-weight-black uppercase text-disabled">Estado de descuento:</span>
          <VChip 
            size="x-small" 
            :color="shouldApplyDiscount ? 'success' : 'secondary'" 
            variant="flat"
            class="font-weight-black"
          >
            {{ shouldApplyDiscount ? `ACTIVO (-${currentDiscount}%)` : "INACTIVO" }}
          </VChip>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: rgb(var(--v-theme-surface)) !important;
  text-transform: uppercase !important;
  font-size: 0.7rem !important;
  font-weight: 950 !important;
  letter-spacing: 1px !important;
  color: rgb(var(--v-theme-on-surface), 0.6) !important;
}

.premium-table :deep(tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

.row-zero-stock {
  background-color: rgba(var(--v-theme-error), 0.05) !important;
}

.precio-normal {
  color: rgb(var(--v-theme-on-surface));
}

.precio-tachado {
  color: #a0a0a0;
  font-size: 0.7rem;
  text-decoration: line-through;
  line-height: 1;
}

.precio-oferta {
  color: rgb(var(--v-theme-success));
}

.precio-expira {
  color: rgb(var(--v-theme-error));
}

.leading-tight {
  line-height: 1.25 !important;
}

.leading-none {
  line-height: 1 !important;
}

.text-super-xs {
  font-size: 10px !important;
  line-height: 1;
}

.premium-mobile-card {
  transition: transform 0.2s, box-shadow 0.2s;
  background: white !important;
}

.premium-mobile-card:active {
  transform: scale(0.98);
}

.grid-prices {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.price-box {
  display: flex;
  flex-direction: column;
}

.shadow-inner {
  box-shadow: inset 0 1px 4px rgba(0, 0, 0, 0.05);
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-4 { gap: 16px !important; }

.font-weight-950 { font-weight: 950 !important; }
</style>
