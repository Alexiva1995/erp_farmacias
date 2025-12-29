<script setup>
import { computed } from "vue";

const props = defineProps({
  logs: { type: Array, required: true },
  totalLogs: { type: Number, required: true },
  loading: { type: Boolean, required: true },
  page: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  selectedLogs: { type: Array, required: true },
});

const emit = defineEmits([
  "update:options",
  "update:selectedLogs",
  "generate-donation",
]);

const headers = [
  { title: "ID", key: "product.id", sortable: true },
  { title: "Producto", key: "product_name", sortable: false },
  { 
    title: "Laboratorio", 
    key: "laboratory_name", 
    sortable: false,
    value: (item) => item.product?.laboratory?.name || "—"
  },
  { title: "Lote", key: "lot_number", align: "center", sortable: false },
  { title: "Vencimiento", key: "expired_at", align: "center", sortable: true },
  {
    title: "Cant. Caducada",
    key: "expired_quantity",
    align: "center",
    sortable: false,
  },
  {
    title: "Costo Total",
    key: "total_lost_value",
    align: "end",
    sortable: false,
  },
];

const selected = computed({
  get: () => props.selectedLogs,
  set: (value) => emit("update:selectedLogs", value),
});

const formatCurrency = (value) => {
  if (value === null || value === undefined) return "$0";
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
  }).format(value);
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  const options = { year: "numeric", month: "2-digit", day: "2-digit" };
  return new Date(dateString).toLocaleDateString("es-ES", options);
};
</script>

<template>
  <VCard>
    <VCardText class="d-flex justify-end pa-4">
      <VTooltip text="Generar Donación" location="top">
        <template #activator="{ props: tooltipProps }">
          <IconBtn
            v-bind="tooltipProps"
            color="success"
            :disabled="selected.length === 0"
            @click="emit('generate-donation')"
          >
            <VIcon icon="tabler-gift" />
          </IconBtn>
        </template>
      </VTooltip>
    </VCardText>

    <VDivider />

    <VDataTableServer
      v-model="selected"
      :headers="headers"
      :items="props.logs"
      :items-length="props.totalLogs"
      :loading="props.loading"
      :page="props.page"
      :items-per-page="props.itemsPerPage"
      item-value="id"
      show-select
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.product_name="{ item }">
        <div class="d-flex align-center">
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium">{{
              item.product_name?.toUpperCase() || ""
            }}</span>
            <span v-if="item.product" class="text-sm text-disabled">{{
              item.product.active_ingredient
            }}</span>
          </div>
        </div>
      </template>

      <template #item.laboratory_name="{ item }">
        <span>{{ item.product?.laboratory?.name || "—" }}</span>
      </template>

      <template #item.expired_at="{ item }">
        <span>{{ formatDate(item.expired_at) }}</span>
      </template>

      <template #item.total_lost_value="{ item }">
        <span class="font-weight-medium">{{ formatCurrency(item.total_lost_value) }}</span>
      </template>
    </VDataTableServer>
  </VCard>
</template>
