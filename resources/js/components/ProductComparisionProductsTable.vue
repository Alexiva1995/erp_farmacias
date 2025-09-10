<script setup>
const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProducts: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  quantityErrors: { type: Object, default: () => ({}) },
  enableUsdAmountCol: { type: Boolean, default: false },
  enableDiscountCol: { type: Boolean, default: false },
});

const emit = defineEmits(["update:options", "send-product"]);

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
  { title: "Nombre", key: "name", sortable: false },
  { title: "Proveedor", key: "supplier_name", sortable: false },
  { title: "Usd", key: "unit_cost_usd", sortable: false },
  { title: "Usd %", key: "final_cost_usd", sortable: false },
  { title: "Bs", key: "unit_cost_bs", sortable: false },
  { title: "Bs %", key: "final_cost_bs", sortable: false },
  { title: "Vencimiento", key: "expiration", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const headers = computed(() =>
  allHeaders.filter((h) => {
    if (props.enableDiscountCol) {
      if (["unit_cost_usd", "unit_cost_bs"].includes(h.key)) return false;

      if (props.enableUsdAmountCol && h.key === "final_cost_bs") return false;
      if (!props.enableUsdAmountCol && h.key === "final_cost_usd") return false;
    } else {
      if (!props.enableUsdAmountCol && h.key.includes("usd")) return false;
      if (props.enableUsdAmountCol && h.key.includes("bs")) return false;
    }

    return true;
  })
);
</script>

<template>
  <VCard>
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
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>

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
            style="width: 200px"
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
