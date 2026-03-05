<script setup>
import { computed, reactive, ref, watch } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProducts: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  quantityErrors: { type: Object, default: () => ({}) },
  enableUsdAmountCol: { type: Boolean, default: false },
  enableDiscountCol: { type: Boolean, default: false },
  // Props de búsqueda
  searchQuery: { type: String, default: "" },
  isStrictSearch: { type: Boolean, default: false },
  // Producto seleccionado desde la tabla inferior (para calcular diferencia de precio)
  selectedProduct: { type: Object, default: null },
});

const emit = defineEmits([
  "update:options",
  "send-product",
  "update:searchQuery",
  "update:isStrictSearch",
]);

const localSearch = ref(props.searchQuery);

// Sincronizar localSearch si cambia desde fuera
watch(
  () => props.searchQuery,
  (newVal) => {
    if (newVal !== localSearch.value) {
      localSearch.value = newVal;
    }
  },
);

const rows = reactive({});
const getQty = (id) => rows[id] || 1;

const formatBs = (amount) => {
  return (
    new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount) + " Bs."
  );
};
const formatUsd = (amount) => {
  return (
    new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount) + " $"
  );
};

/**
 * Calcula el porcentaje de diferencia entre el precio del proveedor
 * y el costo actual del producto seleccionado.
 * Retorna: { diff: Number, label: String, color: String } o null si no aplica.
 */
const getPriceDiff = (item) => {
  if (!props.selectedProduct) return null;

  // Tomamos el costo actual del producto seleccionado (en USD)
  const currentCost = parseFloat(props.selectedProduct.current_unit_cost ?? props.selectedProduct.unit_cost ?? 0);
  if (!currentCost || currentCost === 0) return null;

  // El precio del proveedor en USD (sin descuento o con descuento según la vista)
  const supplierCost = parseFloat(
    props.enableDiscountCol ? item.final_cost_usd : item.unit_cost_usd
  );
  if (!supplierCost) return null;

  // diff > 0 = más barato (ahorro), diff < 0 = más caro (sobrepago)
  const diff = ((currentCost - supplierCost) / currentCost) * 100;
  const absDiff = Math.abs(diff).toFixed(0);

  if (diff > 0.5) {
    return { diff, label: `${absDiff}% más barato`, color: "success" };
  } else if (diff < -0.5) {
    return { diff, label: `${absDiff}% más caro`, color: "error" };
  }
  return { diff: 0, label: "Precio igual", color: "warning" };
};

const allHeaders = [
  { title: "Proveedor", key: "supplier_name", sortable: false, width: "170px" },
  { title: "Nombre", key: "name", sortable: true, width: "400px" },
  { title: "Diferencia", key: "price_diff", sortable: false },
  { title: "Usd", key: "unit_cost_usd", sortable: true },
  { title: "Usd %", key: "final_cost_usd", sortable: true },
  { title: "Bs", key: "unit_cost_bs", sortable: true },
  { title: "Bs %", key: "final_cost_bs", sortable: true },
  { title: "Vencimiento", key: "expiration", sortable: false },
  { title: "Acciones", key: "actions", sortable: false, width: "230px" },
];

const headers = computed(() =>
  allHeaders.filter((h) => {
    // La columna de diferencia solo aparece si hay un producto seleccionado
    if (h.key === "price_diff" && !props.selectedProduct) return false;

    // Si Divisas ($) está activo, ocultar columnas de BS, y viceversa
    if (props.enableUsdAmountCol && h.key.includes("bs")) return false;
    if (!props.enableUsdAmountCol && h.key.includes("usd")) return false;

    // Las columnas con descuento (%) solo se muestran si enableDiscountCol es true
    if (h.key.includes("final_cost") && !props.enableDiscountCol) return false;

    return true;
  }),
);
</script>

<template>
  <VCard>
    <VCardText class="py-4 gap-4">
      <AppTextField
        :model-value="localSearch"
        placeholder="Buscar por Nombre o Laboratorio"
        clearable
        @update:model-value="$emit('update:searchQuery', $event)"
        class="w-25"
      />
      <VCheckbox
        label="Búsqueda Estricta"
        :model-value="props.isStrictSearch"
        @update:model-value="$emit('update:isStrictSearch', $event)"
      />
    </VCardText>

    <VDivider />

    <!-- Banner de producto en comparación -->
    <VAlert
      v-if="selectedProduct"
      type="info"
      variant="tonal"
      density="compact"
      class="mx-4 my-2"
      :icon="false"
    >
      <div class="d-flex align-center gap-2">
        <VIcon icon="tabler-arrows-exchange" color="info" size="18" />
        <span class="text-body-2">
          Comparando precios de:
          <strong>{{ selectedProduct.name }}</strong>
          <span v-if="selectedProduct.current_unit_cost" class="ml-2 text-disabled">
            ( Costo actual: <strong>${{ parseFloat(selectedProduct.current_unit_cost).toFixed(2) }}</strong> )
          </span>
        </span>
      </div>
    </VAlert>

    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProducts"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <!-- Template Nombre -->
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis text-wrap"
            >
              {{ item.name }}
            </span>
            <span class="text-sm text-disabled">
              {{ item.active_ingredient }}
            </span>
          </div>
        </div>
      </template>

      <!-- Columna de diferencia de precio vs. producto seleccionado -->
      <template #item.price_diff="{ item }">
        <template v-if="getPriceDiff(item)">
          <VChip
            :color="getPriceDiff(item).color"
            size="small"
            variant="tonal"
            :prepend-icon="getPriceDiff(item).diff > 0 ? 'tabler-trending-down' : getPriceDiff(item).diff < 0 ? 'tabler-trending-up' : 'tabler-minus'"
          >
            {{ getPriceDiff(item).label }}
          </VChip>
        </template>
        <span v-else class="text-disabled text-sm">—</span>
      </template>

      <!-- Templates de Monedas -->
      <template #item.unit_cost_usd="{ item }">
        <span>{{ formatUsd(item.unit_cost_usd) }}</span>
      </template>

      <template #item.final_cost_usd="{ item }">
        <span>{{ formatUsd(item.final_cost_usd) }}</span>
      </template>

      <template #item.unit_cost_bs="{ item }">
        <span>{{ formatBs(item.unit_cost_bs) }}</span>
      </template>

      <template #item.final_cost_bs="{ item }">
        <span>{{ formatBs(item.final_cost_bs) }}</span>
      </template>

      <!-- Acciones -->
      <template #item.actions="{ item }">
        <div class="d-flex align-center ga-2">
          <VTextField
            v-model.number="rows[item.id]"
            label="Cantidad"
            min="1"
            type="number"
            variant="outlined"
            density="compact"
            hide-details="auto"
            style="inline-size: 80px;"
            :error="!!quantityErrors[item.id]"
            :error-messages="quantityErrors[item.id]"
          />

          <VTooltip text="Agregar al Pedido del Día" location="top">
            <template #activator="{ props: tooltipProps }">
              <IconBtn
                v-bind="tooltipProps"
                color="primary"
                @click="
                  $emit('send-product', {
                    id: item.id,
                    quantity: getQty(item.id),
                  })
                "
              >
                <VIcon icon="tabler-shopping-cart-plus" />
              </IconBtn>
            </template>
          </VTooltip>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
