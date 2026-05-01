<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
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
  exchangeRates: { type: Object, default: () => ({}) },
  currency: { type: String, default: "USD" },
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

const getEffectiveRate = (fromCurrency, toCurrency) => {
  if (fromCurrency === toCurrency) return 1;

  const rates = props.exchangeRates?.[fromCurrency];
  if (!rates) return 0;

  // REGLA NEGOCIO: Si convertimos de USD a COP, usar COPC (Tasa Manual) si existe
  if (fromCurrency === "USD" && toCurrency === "COP" && rates["COPC"]) {
    return rates["COPC"];
  }

  return rates[toCurrency] || 0;
};

const getDynamicPrice = (item, basePrice, targetCurrency) => {
  return parseFloat(basePrice) || 0;
};

const headers = [
  { title: "ID", key: "id", sortable: true, width: "70px" },
  { title: "Stock", key: "valid_stock_sum", sortable: true, width: "80px", align: "center" },
  { title: "Producto", key: "name", sortable: true },
  { title: "USD", key: "sale_price", sortable: true, align: "end" },
  { title: "Bs", key: "price_bs", sortable: true, align: "end" },
  { title: "COP", key: "price_cop", sortable: true, align: "end" },
  { title: "Añadir", key: "add_action_with_quantity", sortable: false, width: "140px", align: "center" },
  { title: "Acción", key: "actions", sortable: false, width: "100px", align: "center" },
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
  <VCard variant="flat" border class="rounded-lg overflow-hidden elevation-1">
    <!-- Vista Escritorio -->
    <VDataTableServer
      v-model:options="options"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProduct"
      :loading="props.loading"
      class="premium-table d-none d-md-block"
      :row-props="(data) => ({ class: getRowClass(data.item) })"
      @update:options="handleUpdateOptions"
      hover
    >
      <template #item.id="{ item }">
        <a
          :href="'/inventory/traceability?q=' + item.id"
          target="_blank"
          class="text-decoration-none font-weight-black text-primary"
        >
          {{ item.id }}
        </a>
      </template>

      <template #item.valid_stock_sum="{ item }">
        <VChip
          :color="item.valid_stock_sum > 0 ? 'success' : 'error'"
          size="small"
          variant="flat"
          class="font-weight-black shadow-sm px-2"
        >
          {{ item.valid_stock_sum }}
        </VChip>
      </template>

      <template #item.name="{ item }">
        <div class="d-flex flex-column py-2" style="max-inline-size: 300px;">
          <span
            class="text-subtitle-2 font-weight-black text-high-emphasis leading-tight text-uppercase"
            :class="{ 'text-primary': item.psychotropic == 1 }"
            style="white-space: normal;"
          >
            {{ item.name }}
            <VChip v-if="item.iva == 1" size="x-small" color="primary" variant="tonal" class="ms-1 font-weight-bold">IVA</VChip>
            <VChip v-if="item.is_colombian_origin == 1" size="x-small" color="info" variant="tonal" class="ms-1 font-weight-bold">COL</VChip>
            <VChip
              v-if="item.discount_type === 'expiration' && item.discount_percentage > 0"
              color="error"
              size="x-small"
              class="ms-1 font-weight-black uppercase"
              label
            >
              Expira (-{{ item.discount_percentage }}%)
            </VChip>
            <VChip
              v-if="item.discount_type && ['individual', 'category'].includes(item.discount_type) && item.discount_percentage > 0"
              color="success"
              size="x-small"
              class="ms-1 font-weight-black uppercase"
              label
            >
              Oferta (-{{ item.discount_percentage }}%)
            </VChip>
          </span>
          <div
            class="text-super-xs mt-1"
            :class="item.item_type === 'pack' ? 'd-flex flex-column' : 'd-flex align-center'"
            style="white-space: pre-wrap;"
          >
            <span class="text-disabled font-weight-medium text-uppercase">{{ item.active_ingredient || '—' }}</span>
            <template v-if="item.item_type !== 'pack'">
              <span class="text-disabled mx-1">|</span>
              <span class="text-primary font-weight-black text-uppercase">{{ item.laboratory_name || 'Genérico' }}</span>
            </template>
          </div>
        </div>
      </template>


      <template #item.sale_price="{ item }">
        <div class="d-flex flex-column align-end">
          <del v-if="calculatePriceWithIVA(getDynamicPrice(item, item.sale_price, 'USD'), item) > calculatePriceWithIVAAndDiscount(getDynamicPrice(item, item.sale_price, 'USD'), item)" class="precio-tachado">
            {{ formatCurrency(calculatePriceWithIVA(getDynamicPrice(item, item.sale_price, 'USD'), item)) }}
          </del>
          <span :class="getPriceClass(item)" class="font-weight-black text-primary">
            {{ formatCurrency(calculatePriceWithIVAAndDiscount(getDynamicPrice(item, item.sale_price, 'USD'), item)) }}
          </span>
        </div>
      </template>

      <template #item.price_bs="{ item }">
        <div class="d-flex flex-column align-end">
          <del v-if="calculatePriceWithIVA(item.price_bs, item) > calculatePriceWithIVAAndDiscount(item.price_bs, item)" class="precio-tachado">
            {{ formatCurrency(calculatePriceWithIVA(item.price_bs, item), "BS") }}
          </del>
          <span :class="getPriceClass(item)" class="font-weight-bold text-medium-emphasis">
            {{ formatCurrency(calculatePriceWithIVAAndDiscount(item.price_bs, item), "BS") }}
          </span>
        </div>
      </template>

      <template #item.price_cop="{ item }">
        <div class="d-flex flex-column align-end">
          <del v-if="calculatePriceWithIVA(item.price_cop, item) > calculatePriceWithIVAAndDiscount(item.price_cop, item)" class="precio-tachado">
            {{ calculateAndFormatCopPriceWithIVA(item.price_cop, item) }}
          </del>
          <span :class="getPriceClass(item)" class="font-weight-bold text-medium-emphasis">
            {{ calculateAndFormatCopPriceWithIVAAndDiscount(item.price_cop, item) }}
          </span>
        </div>
      </template>

      <template #item.add_action_with_quantity="{ item }">
        <div class="d-flex align-center gap-2 justify-center">
          <VTextField
            :model-value="inputQuantities.get(item.id) ?? 0"
            @update:model-value="(val) => handleInputOrderChange(item.id, val)"
            type="number"
            min="0"
            :max="item.valid_stock_sum"
            density="compact"
            variant="outlined"
            hide-details
            class="rounded-lg font-weight-black quantity-input-field"
            style="inline-size: 80px;"
            :disabled="(item.valid_stock_sum ?? 0) <= 0"
          />
          <VBtn
            v-if="item.item_type === 'product'"
            color="primary"
            variant="flat"
            icon="tabler-plus"
            size="32"
            class="rounded-lg shadow-sm"
            :disabled="(inputQuantities.get(item.id) ?? 0) <= 0 || (item.valid_stock_sum ?? 0) <= 0"
            @click="handleAddProduct(item.id)"
          />
          <VBtn
            v-else
            color="primary"
            variant="flat"
            icon="tabler-package-import"
            size="32"
            class="rounded-lg shadow-sm"
            :disabled="(inputQuantities.get(item.id) ?? 0) <= 0"
            @click="handleAddPack(item.id)"
          />
        </div>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center">
          <VBtn
            icon="tabler-eye"
            variant="tonal"
            color="info"
            size="30"
            class="rounded-lg"
            @click="item.item_type === 'product' ? handleViewGroupProducts(item) : handleViewPack(item)"
          />
          <VBtn
            icon="tabler-alert-triangle"
            variant="tonal"
            color="error"
            size="30"
            class="rounded-lg"
            :disabled="item.item_type === 'pack'"
            @click="handleFailures(item)"
          />
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil (Cards) -->
    <div class="d-block d-md-none pa-3">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-3" />
      
      <div v-if="props.products.length === 0 && !props.loading" class="text-center py-10 text-disabled">
        <VIcon icon="tabler-search-off" size="48" class="mb-2" />
        <div class="font-weight-bold uppercase">No se encontraron productos</div>
      </div>
      
      <div v-else class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          variant="flat"
          class="border mb-2 overflow-hidden premium-mobile-card elevation-1"
          :class="{ 'border-error border-opacity-50': (item.valid_stock_sum ?? 0) <= 0 }"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-2">
              <a
                :href="'/inventory/traceability?q=' + item.id"
                target="_blank"
                class="text-decoration-none text-primary font-weight-black text-xs"
              >
                #{{ item.id }}
              </a>
              <VChip
                :color="item.valid_stock_sum > 0 ? 'success' : 'error'"
                size="x-small"
                variant="flat"
                class="font-weight-black"
              >
                STOCK: {{ item.valid_stock_sum }}
              </VChip>
            </div>

            <h3 class="text-subtitle-2 font-weight-950 text-high-emphasis text-uppercase leading-tight mb-1">
              {{ item.name }}
            </h3>
            
            <div 
              class="text-super-xs mt-1" 
              :class="item.item_type === 'pack' ? 'd-flex flex-column gap-1' : 'd-flex align-center'"
              style="white-space: pre-wrap;"
            >
              <span class="text-disabled text-uppercase">{{ item.active_ingredient || '—' }}</span>
              <template v-if="item.item_type !== 'pack'">
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                  {{ item.laboratory_name || 'Genérico' }}
                </span>
              </template>
            </div>
            
            <div class="d-flex gap-1 mb-3">
              <VChip v-if="item.iva == 1" size="x-small" color="primary" variant="flat" class="font-weight-black">IVA</VChip>
              <VChip v-if="item.is_colombian_origin == 1" size="x-small" color="info" variant="flat" class="font-weight-black">COL</VChip>
              <VChip
                v-if="item.discount_type === 'expiration' && item.discount_percentage > 0"
                color="error"
                size="x-small"
                variant="flat"
                class="font-weight-black"
              >
                EXPIRA (-{{ item.discount_percentage }}%)
              </VChip>
              <VChip
                v-if="item.discount_type && ['individual', 'category'].includes(item.discount_type) && item.discount_percentage > 0"
                color="success"
                size="x-small"
                variant="flat"
                class="font-weight-black"
              >
                OFERTA (-{{ item.discount_percentage }}%)
              </VChip>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-grid mobile-price-grid gap-2 mb-4">
              <div class="price-box">
                <span class="label">USD</span>
                <div class="d-flex flex-column">
                  <del v-if="calculatePriceWithIVA(getDynamicPrice(item, item.sale_price, 'USD'), item) > calculatePriceWithIVAAndDiscount(getDynamicPrice(item, item.sale_price, 'USD'), item)" class="text-super-xs text-disabled text-decoration-line-through">
                    {{ formatCurrency(calculatePriceWithIVA(getDynamicPrice(item, item.sale_price, 'USD'), item)) }}
                  </del>
                  <span class="value font-weight-black text-primary" :class="getPriceClass(item)">
                    {{ formatCurrency(calculatePriceWithIVAAndDiscount(getDynamicPrice(item, item.sale_price, 'USD'), item)) }}
                  </span>
                </div>
              </div>
              <div class="price-box">
                <span class="label">Bs</span>
                <div class="d-flex flex-column">
                  <del v-if="calculatePriceWithIVA(item.price_bs, item) > calculatePriceWithIVAAndDiscount(item.price_bs, item)" class="text-super-xs text-disabled text-decoration-line-through">
                    {{ formatCurrency(calculatePriceWithIVA(item.price_bs, item), "BS") }}
                  </del>
                  <span class="value font-weight-bold text-medium-emphasis" :class="getPriceClass(item)">
                    {{ formatCurrency(calculatePriceWithIVAAndDiscount(item.price_bs, item), "BS") }}
                  </span>
                </div>
              </div>
              <div class="price-box">
                <span class="label">COP</span>
                <div class="d-flex flex-column">
                  <del v-if="calculatePriceWithIVA(item.price_cop, item) > calculatePriceWithIVAAndDiscount(item.price_cop, item)" class="text-super-xs text-disabled text-decoration-line-through">
                    {{ calculateAndFormatCopPriceWithIVA(item.price_cop, item) }}
                  </del>
                  <span class="value font-weight-bold text-medium-emphasis" :class="getPriceClass(item)">
                    {{ calculateAndFormatCopPriceWithIVAAndDiscount(item.price_cop, item) }}
                  </span>
                </div>
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
                @update:model-value="(val) => handleInputOrderChange(item.id, val)"
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
                @click="item.item_type === 'product' ? handleAddProduct(item.id) : handleAddPack(item.id)"
                :disabled="(inputQuantities.get(item.id) ?? 0) <= 0 || (item.item_type === 'product' && item.valid_stock_sum === 0)"
              >
                <VIcon icon="tabler-shopping-cart-plus" />
              </VBtn>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-6 pb-2">
        <VPagination
          v-model="options.page"
          :length="Math.ceil(props.totalProduct / props.itemsPerPage)"
          :total-visible="3"
          density="compact"
          size="small"
          active-color="primary"
          @update:model-value="(p) => handleUpdateOptions({ ...options, page: p })"
        />
      </div>
    </div>

    <!-- Información del descuento -->
    <VCardText v-if="currentDiscount > 0" class="pa-4 bg-primary-lighten-5 border-t">
      <div class="d-flex flex-column gap-1">
        <div class="d-flex justify-space-between align-center">
          <span class="text-xs font-weight-black uppercase text-disabled">Productos seleccionados:</span>
          <VChip size="x-small" color="primary" class="font-weight-black px-3">{{ totalProductsInCart }}</VChip>
        </div>
        <div class="d-flex justify-space-between align-center">
          <span class="text-xs font-weight-black uppercase text-disabled">Estado de descuento:</span>
          <VChip 
            size="x-small" 
            :color="shouldApplyDiscount ? 'success' : 'secondary'" 
            variant="flat"
            class="font-weight-black px-3"
          >
            {{ shouldApplyDiscount ? `ACTIVO (-${currentDiscount}%)` : "INACTIVO" }}
          </VChip>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.premium-table :deep(thead th) {
  color: rgba(var(--v-theme-on-surface), 0.6) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}

.premium-table :deep(tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.01) !important;
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
  line-height: 1;
  text-decoration: line-through;
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

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.bg-light {
  background-color: #f8fafc !important;
}

.premium-mobile-card {
  border-radius: 8px !important;
  background: white !important;
  transition: all 0.2s ease;
}

.premium-mobile-card:active {
  transform: scale(0.98);
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

.font-weight-950 { font-weight: 950 !important; }

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
