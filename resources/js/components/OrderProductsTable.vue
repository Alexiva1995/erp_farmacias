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
  { title: "id", key: "id", sortable: true },
  { title: "Stock", key: "valid_stock_sum", sortable: true, maxWidth: "55px" },
  { title: "Producto", key: "name", sortable: true },
  { title: "Laboratorio", key: "laboratory_name", sortable: true },
  { title: "USD", key: "sale_price", sortable: true },
  { title: "Bs", key: "price_bs", sortable: true },
  { title: "COP", key: "price_cop", sortable: true },
  {
    title: "Añadir",
    key: "add_action_with_quantity",
    sortable: false,
    maxWidth: "130px",
  },
  { title: "Acción", key: "actions", sortable: false, maxWidth: "95px" },
];

// Calcular total de productos en el carrito (sumando los productos en la tabla)
const totalProductsInCart = computed(() => {
  let total = 0;

  // Sumar productos que ya están en el carrito
  total += props.orderItems.reduce((sum, item) => {
    return sum + (item.selectedQuantity || 0);
  }, 0);

  // Sumar productos que están en los inputs de la tabla
  props.products.forEach((product) => {
    const quantityInInput = inputQuantities.value.get(product.id) || 0;
    total += quantityInInput;
  });

  return total;
});

// Determinar si aplicar descuento basado en el total de productos
const shouldApplyDiscount = computed(() => {
  if (props.currentDiscount <= 0) return false;

  // Si no hay límites configurados, aplicar siempre
  if (props.discountMinProducts === 0 && props.discountMaxProducts === 0) {
    return true;
  }

  // Verificar si el total de productos está dentro del rango
  return (
    totalProductsInCart.value >= props.discountMinProducts &&
    totalProductsInCart.value <= props.discountMaxProducts
  );
});

// Calcular precio con descuento si aplica
const calculatePriceWithDiscount = (basePrice) => {
  const price = parseFloat(basePrice) || 0;

  if (shouldApplyDiscount.value && props.currentDiscount > 0) {
    const discountAmount = price * (props.currentDiscount / 100);
    return price - discountAmount;
  }

  return price;
};

// Calcular precio con IVA y descuento (si aplica)
const calculatePriceWithIVAAndDiscount = (basePrice, product) => {
  let effectivePrice = calculatePriceWithDiscount(basePrice);
  let taxRate = product.iva == 1 ? 0.16 : 0;

  if (taxRate > 0) {
    return effectivePrice * (1 + taxRate);
  }

  return effectivePrice;
};

// Formatear precio COP con redondeo y descuento (si aplica)
const calculateAndFormatCopPriceWithIVAAndDiscount = (basePrice, product) => {
  const priceWithIVA = calculatePriceWithIVAAndDiscount(basePrice, product);
  return formatCurrency(roundUpToNearestHundred(priceWithIVA));
};

const handleAddProduct = (productId) => {
  const quantityToAdd = inputQuantities.value.get(productId);
  if (
    quantityToAdd === null ||
    quantityToAdd === undefined ||
    quantityToAdd <= 0
  ) {
    console.error(
      `La cantidad para el producto ${productId} no es válida (${quantityToAdd}). Debe ser un número positivo para añadir.`
    );
    return;
  }
  emit("add-product", { productId, quantity: quantityToAdd });

  // Resetear la cantidad del input después de agregar
  inputQuantities.value.set(productId, 1);
};

watch(
  () => props.products,
  (newProducts) => {
    const newOrderMap = new Map();
    newProducts.forEach((product) => {
      let currentQty;
      if (product.valid_stock_sum === 0) {
        currentQty = 0;
      } else {
        let previousQty = inputQuantities.value.get(product.id);
        if (
          previousQty === undefined ||
          previousQty === null ||
          previousQty < 1
        ) {
          currentQty = 1;
        } else {
          currentQty = previousQty;
        }
        if (currentQty > product.valid_stock_sum) {
          currentQty = product.valid_stock_sum;
        }
      }
      newOrderMap.set(product.id, currentQty);
    });
    inputQuantities.value = newOrderMap;
  },
  { immediate: true }
);

const handleInputOrderChange = (productId, val) => {
  let cleanVal = parseInt(val);
  const maxQty =
    props.products.find((p) => p.id === productId)?.valid_stock_sum ?? 0;
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

const handleViewGroupProducts = (product) => {
  emit("view-group-products", product.group_id);
};

const handleFailures = (product) => {
  emit("failures-products", product.id);
};

// Función para calcular precio con IVA (sin descuento)
const calculatePriceWithIVA = (basePrice, product) => {
  const price = parseFloat(basePrice) || 0;
  let taxRate = product.iva == 1 ? 0.16 : 0;
  if (taxRate > 0) {
    return price * (1 + taxRate);
  }
  return price;
};

// Función para formatear precio COP (sin descuento)
const calculateAndFormatCopPriceWithIVA = (basePrice, product) => {
  const priceWithIVA = calculatePriceWithIVA(basePrice, product);
  return formatCurrency(roundUpToNearestHundred(priceWithIVA));
};

// Función de redondeo para COP
const roundUpToNearestHundred = (value) => {
  return Math.ceil(value / 100) * 100;
};

// Calcular el total del descuento que se aplicaría
const totalDiscountPreview = computed(() => {
  if (!shouldApplyDiscount.value || props.currentDiscount <= 0) return 0;

  let subtotalWithoutDiscount = 0;

  // Sumar productos en el carrito
  props.orderItems.forEach((item) => {
    // Necesitarías obtener el precio aquí - puedes ajustar según tus datos
    const price = item.price || item.sale_price || 0;
    const quantity = item.selectedQuantity || 0;
    subtotalWithoutDiscount += price * quantity;
  });

  // Sumar productos en los inputs de la tabla
  props.products.forEach((product) => {
    const quantityInInput = inputQuantities.value.get(product.id) || 0;
    if (quantityInInput > 0) {
      const price = product.sale_price || 0;
      subtotalWithoutDiscount += price * quantityInInput;
    }
  });

  return subtotalWithoutDiscount * (props.currentDiscount / 100);
});

const handleViewPack = (pack) => {
  emit("view-pack-details", pack);
};

onMounted(async () => {
  try {
    const { data } = await axios.get('/user/config');
    // data suele ser el usuario, y el usuario tiene 'config'
    const config = data.config; 
    
    if (config && config.sort_products_orders) {
      const [key, order] = config.sort_products_orders.split('|');
      
      // IMPORTANTE: Esto dispara handleUpdateOptions automáticamente
      options.value.sortBy = [{ key, order }];
    }
  } catch (error) {
    console.error("Error cargando config inicial");
  } finally {
    // Damos un margen pequeño para que ignore el primer disparo automático
    setTimeout(() => { isInitialLoad.value = false; }, 1000);
  }
});

const handleUpdateOptions = (newOptions) => {
  // Sincronizar con las props del padre si es necesario
  emit('update:page', newOptions.page);
  emit('update:itemsPerPage', newOptions.itemsPerPage);
  // Emitir el evento completo al padre para el fetch de datos
  emit('update:options', newOptions);
};


const handleAddPack = (packId) => {
  const quantityToAdd = inputQuantities.value.get(packId);
  if (
    quantityToAdd === null ||
    quantityToAdd === undefined ||
    quantityToAdd <= 0
  ) {
    return;
  }
  const pack = props.products.find((p) => p.id === packId);
  if (pack) {
    emit("add-pack", { pack, quantity: quantityToAdd });
  }
  // Reset input
  inputQuantities.value.set(packId, 1);
};
</script>

<template>
  <VCard>
    <VDataTableServer
      v-model:options="options"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProduct"
      :loading="props.loading"
      class="text-no-wrap"
     @update:options="handleUpdateOptions" >

      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>
      <template #item.valid_stock_sum="{ item }">
        <span class="font-weight-medium text-no-wrap">{{
          item.valid_stock_sum
        }}</span>
      </template>
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{
              item.name
            }}</span>
            <span class="text-sm text-disabled">{{
              item.active_ingredient
            }}</span>
            <span class="text-sm text-disabled">{{ item.origin?.name }}</span>
          </div>
        </div>
      </template>
      <template #item.sale_price="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{
            formatCurrency(
              calculatePriceWithIVAAndDiscount(item.sale_price, item)
            )
          }}</span>
          <!-- Mostrar precio original tachado si hay descuento -->
          <span
            v-if="shouldApplyDiscount && currentDiscount > 0"
            class="text-xs text-disabled text-decoration-line-through"
          >
            {{ formatCurrency(calculatePriceWithIVA(item.sale_price, item)) }}
          </span>
        </div>
      </template>
      <template #item.price_bs="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{
            formatCurrency(
              calculatePriceWithIVAAndDiscount(item.price_bs, item)
            )
          }}</span>
          <!-- Mostrar precio original tachado si hay descuento -->
          <span
            v-if="shouldApplyDiscount && currentDiscount > 0"
            class="text-xs text-disabled text-decoration-line-through"
          >
            {{ formatCurrency(calculatePriceWithIVA(item.price_bs, item)) }}
          </span>
        </div>
      </template>
      <template #item.price_cop="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{
            calculateAndFormatCopPriceWithIVAAndDiscount(item.price_cop, item)
          }}</span>
          <!-- Mostrar precio original tachado si hay descuento -->
          <span
            v-if="shouldApplyDiscount && currentDiscount > 0"
            class="text-xs text-disabled text-decoration-line-through"
          >
            {{ calculateAndFormatCopPriceWithIVA(item.price_cop, item) }}
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
            single-line
            style="max-width: 90px; min-width: 90px"
            class="my-2 quantity-input-field"
            :disabled="item.valid_stock_sum === 0"
          />
          <IconBtn
            @click="handleAddProduct(item.id)"
             v-if="item.item_type === 'product'"
            :disabled="
              (inputQuantities.get(item.id) ?? 0) <= 0 ||
              (inputQuantities.get(item.id) ?? 0) > item.valid_stock_sum ||
              item.valid_stock_sum === 0
            "
          >
            <VIcon icon="tabler-plus" />
          </IconBtn>
           <IconBtn
            @click="handleAddPack(item.id)"
            v-else="item.item_type === 'pack'"
            :disabled="(inputQuantities.get(item.id) ?? 0) <= 0"
            color="primary"
            variant="tonal"
            size="small"
          >
            <VIcon icon="tabler-plus" />
          </IconBtn>
        </div>
      </template>
      <template #item.actions="{ item }">
        <IconBtn
          @click="handleViewGroupProducts(item)"
          v-if="item.item_type === 'product'"
          color="info"
        >
          <VIcon icon="tabler-eye" />
        </IconBtn>
        <VBtn
          v-else-if="item.item_type === 'pack'"
          icon
          variant="text"
          size="small"
          color="info"
          @click="handleViewPack(item)"
        >
          <VIcon>tabler-eye</VIcon>
        </VBtn>

        <IconBtn
          @click="handleFailures(item)"
          color="error"
          :disabled="item.item_type === 'pack'"
        >
          <VIcon icon="tabler-alert-triangle" />
        </IconBtn>
      </template>
    </VDataTableServer>

    <!-- Información del descuento -->
    <VCardText v-if="currentDiscount > 0" class="pa-4">
      <div class="d-flex flex-column gap-2">
        <!-- Total de productos -->
        <div class="d-flex justify-space-between align-center">
          <span class="text-sm">Total de productos seleccionados:</span>
          <span class="font-weight-bold">{{ totalProductsInCart }}</span>
        </div>

        <!-- Rango de descuento -->
        <div class="d-flex justify-space-between align-center">
          <span class="text-sm">Rango para descuento:</span>
          <span class="font-weight-bold">
            {{ discountMinProducts }} - {{ discountMaxProducts }} unidades
          </span>
        </div>

        <!-- Estado del descuento -->
        <div class="d-flex justify-space-between align-center">
          <span class="text-sm">Descuento aplicable:</span>
          <span
            :class="{
              'text-success font-weight-bold': shouldApplyDiscount,
              'text-disabled': !shouldApplyDiscount,
            }"
          >
            {{ shouldApplyDiscount ? "SÍ" : "NO" }}
            <span v-if="shouldApplyDiscount"> ({{ currentDiscount }}%)</span>
          </span>
        </div>

        <!-- Vista previa del descuento -->
        <div
          v-if="shouldApplyDiscount"
          class="d-flex justify-space-between align-center bg-success-lighten-5 pa-2 rounded"
        >
          <span class="text-sm text-success font-weight-bold"
            >Descuento estimado:</span
          >
          <span class="text-success font-weight-bold">
            {{ formatCurrency(totalDiscountPreview) }}
          </span>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
