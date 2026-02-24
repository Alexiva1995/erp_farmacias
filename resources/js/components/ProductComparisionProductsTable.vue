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
});

const emit = defineEmits([
  "update:options",
  "send-product",
  "update:searchQuery",
  "update:isStrictSearch", // Emit para el modo estricto
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

const allHeaders = [
  { title: "Proveedor", key: "supplier_name", sortable: false, width: "170px" },
  { title: "Nombre", key: "name", sortable: true, width: "400px" },
  { title: "Usd", key: "unit_cost_usd", sortable: true },
  { title: "Usd %", key: "final_cost_usd", sortable: true },
  { title: "Bs", key: "unit_cost_bs", sortable: true },
  { title: "Bs %", key: "final_cost_bs", sortable: true },
  { title: "Vencimiento", key: "expiration", sortable: false },
  { title: "Acciones", key: "actions", sortable: false, width: "230px" },
];

const headers = computed(() =>
  allHeaders.filter((h) => {
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
            style="width: 80px"
            :error="!!quantityErrors[item.id]"
            :error-messages="quantityErrors[item.id]"
          />

          <VTooltip text="Agregar al Pedido del Día" location="top">
            <template #activator="{ props: tooltipProps }">
              <IconBtn
                v-bind="tooltipProps"
                @click="
                  $emit('send-product', {
                    id: item.id,
                    quantity: getQty(item.id),
                  })
                "
              >
                <VIcon icon="tabler-plus" />
              </IconBtn>
            </template>
          </VTooltip>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
