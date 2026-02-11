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
  { title: "USD", key: "sale_price", sortable: true, align: "end" },
  { title: "Bs", key: "price_bs", sortable: true, align: "end" },
  { title: "COP", key: "price_cop", sortable: true, align: "end" },
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

// Calcular precio con descuento si aplica (empresa, oferta individual o categoría)
const calculatePriceWithDiscount = (basePrice, product = null) => {
  const price = parseFloat(basePrice) || 0;

  // Oferta individual o categoría: prioridad si el producto la tiene
  if ((product?.discount_type === "individual" || product?.discount_type === "category") && parseFloat(product?.discount_percentage || 0) > 0) {
    const pct = parseFloat(product.discount_percentage);
    return price * (1 - pct / 100);
  }

  if (shouldApplyDiscount.value && props.currentDiscount > 0) {
    const discountAmount = price * (props.currentDiscount / 100);
    return price - discountAmount;
  }

  return price;
};

// Calcular precio con IVA y descuento (si aplica)
const calculatePriceWithIVAAndDiscount = (basePrice, product) => {
  let effectivePrice = calculatePriceWithDiscount(basePrice, product);
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

// Función para determinar el estilo de la fila según stock y expiración
const getRowClass = (item) => {
  const classes = [];
  
  // Stock en 0: fondo rojo, letras blancas
  if (item.valid_stock_sum === 0) {
    classes.push('row-zero-stock');
    return classes.join(' '); // Retornar inmediatamente si stock es 0
  }
  
  // Verificar si está vencido o tiene menos de 6 meses por vencer
  if (item.next_expiration) {
    const expirationDate = new Date(item.next_expiration);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const sixMonthsFromNow = new Date();
    sixMonthsFromNow.setMonth(today.getMonth() + 6);
    sixMonthsFromNow.setHours(23, 59, 59, 999);
    
    // Si está vencido (antes de hoy) o tiene menos de 6 meses por vencer
    if (expirationDate < today || expirationDate <= sixMonthsFromNow) {
      classes.push('row-zero-stock'); // Fondo rojo para productos vencidos o próximos a vencer
    }
  }
  
  return classes.join(' ');
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
      :row-props="(item) => ({ class: getRowClass(item) })"
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
          <div class="d-flex flex-column" style="min-width: 0; width: 100%;">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{ 'text-primary': item.psychotropic == 1 }"
              style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal;"
            >
              {{ item.name }}
              <span v-if="item.iva == 1"> (G)</span>
              <span v-if="item.is_colombian_origin == 1"> (COL)</span>
            </span>
            <span 
              class="text-sm text-disabled"
              style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal;"
            >
              {{ item.active_ingredient }}
            </span>
            <span 
              class="text-sm text-disabled"
              style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal;"
            >
              {{ item.origin?.name }}
            </span>
          </div>
        </div>
      </template>
      <template #item.laboratory_name="{ item }">
        <span class="font-weight-medium">{{ item.laboratory_name }}</span>
      </template>
      <template #item.sale_price="{ item }">
        <div class="d-flex flex-column align-end">
          <del v-if="(item.discount_type === 'individual' || item.discount_type === 'category') && item.discount_percentage > 0" class="precio-tachado">
            {{ formatCurrency(calculatePriceWithIVA(item.sale_price, item)) }}
          </del>
          <span v-else-if="shouldApplyDiscount && currentDiscount > 0" class="precio-tachado">
            {{ formatCurrency(calculatePriceWithIVA(item.sale_price, item)) }}
          </span>
          <span
            :class="((item.discount_percentage > 0) || (shouldApplyDiscount && currentDiscount > 0)) ? 'precio-oferta' : 'precio-normal'"
            class="font-weight-medium"
          >
            {{ formatCurrency(calculatePriceWithIVAAndDiscount(item.sale_price, item)) }}
          </span>
        </div>
      </template>
      <template #item.price_bs="{ item }">
        <div class="d-flex flex-column align-end">
          <del v-if="(item.discount_type === 'individual' || item.discount_type === 'category') && item.discount_percentage > 0" class="precio-tachado">
            {{ formatCurrency(calculatePriceWithIVA(item.price_bs, item)) }}
          </del>
          <span v-else-if="shouldApplyDiscount && currentDiscount > 0" class="precio-tachado">
            {{ formatCurrency(calculatePriceWithIVA(item.price_bs, item)) }}
          </span>
          <span
            :class="((item.discount_percentage > 0) || (shouldApplyDiscount && currentDiscount > 0)) ? 'precio-oferta' : 'precio-normal'"
            class="font-weight-medium"
          >
            {{ formatCurrency(calculatePriceWithIVAAndDiscount(item.price_bs, item)) }}
          </span>
        </div>
      </template>
      <template #item.price_cop="{ item }">
        <div class="d-flex flex-column align-end">
          <del v-if="(item.discount_type === 'individual' || item.discount_type === 'category') && item.discount_percentage > 0" class="precio-tachado">
            {{ calculateAndFormatCopPriceWithIVA(item.price_cop, item) }}
          </del>
          <span v-else-if="shouldApplyDiscount && currentDiscount > 0" class="precio-tachado">
            {{ calculateAndFormatCopPriceWithIVA(item.price_cop, item) }}
          </span>
          <span
            :class="((item.discount_percentage > 0) || (shouldApplyDiscount && currentDiscount > 0)) ? 'precio-oferta' : 'precio-normal'"
            class="font-weight-medium"
          >
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

<style scoped>
:deep(.row-zero-stock) {
  background-color: rgb(var(--v-theme-error)) !important;
  color: white !important;
}

:deep(.row-zero-stock td),
:deep(.row-zero-stock th) {
  color: white !important;
}

:deep(.row-zero-stock .text-disabled),
:deep(.row-zero-stock .text-black),
:deep(.row-zero-stock .text-high-emphasis),
:deep(.row-zero-stock .precio-normal),
:deep(.row-zero-stock .precio-tachado) {
  color: rgba(255, 255, 255, 0.9) !important;
}

:deep(.row-expiring) {
  background-color: rgb(var(--v-theme-warning)) !important;
}

:deep(.row-expiring td),
:deep(.row-expiring th) {
  color: rgb(var(--v-theme-on-warning)) !important;
}

:deep(.row-expiring .text-disabled) {
  color: rgba(0, 0, 0, 0.6) !important;
}

/* Si una fila tiene ambas clases (stock 0 y expirando), el rojo tiene prioridad */
:deep(.row-zero-stock.row-expiring) {
  background-color: rgb(var(--v-theme-error)) !important;
  color: white !important;
}

:deep(.row-zero-stock.row-expiring td),
:deep(.row-zero-stock.row-expiring th) {
  color: white !important;
}

/* Precio normal (sin oferta): hereda color del tema como /inventory/products */
.precio-normal {
  /* Sin color explícito - igual que ProductTable */
}

/* Precio original tachado (cuando hay oferta) */
.precio-tachado {
  color: #a0a0a0;
  text-decoration: line-through;
  font-size: 0.75rem;
}

/* Precio con oferta individual */
.precio-oferta {
  color: rgb(var(--v-theme-success));
  font-weight: 600;
}
</style>
