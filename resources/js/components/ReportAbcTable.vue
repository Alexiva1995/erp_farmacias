<script setup>
const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const headers = [
  { title: "Producto", key: "name" },
  { title: "Ven. Prom.", key: "sales_average", align: "end" },
  { title: "Stock", key: "stock", align: "end" },
  { title: "Cober.", key: "coverage_months", align: "end" },
  { title: "Ventas Totales ($)", key: "total_sales", align: "end" },
  { title: "% Participación", key: "participation_percentage", align: "end" },
  { title: "% Acumulado", key: "accumulated_percentage", align: "end" },
  { title: "Clasificación ABC", key: "classification", align: "center" },
];

const formatCurrency = (value) => {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(value);
};

const formatPercent = (value) => {
  return value.toFixed(2) + "%";
};

const getClassColor = (cls) => {
  switch (cls) {
    case "A":
      return "success";
    case "B":
      return "warning";
    case "C":
      return "error";
    default:
      return "grey";
  }
};
</script>

<template>
  <VCard title="Análisis de Inventario ABC ">
    <VDataTable
      :headers="headers"
      :items="props.items"
      :loading="props.loading"
      class="text-no-wrap"
      density="compact"
    >
      <template #item.sales_average="{ item }">
        {{ item.sales_average }}
      </template>

      <template #item.stock="{ item }">
        {{ item.stock }}
      </template>

      <template #item.coverage_months="{ item }">
        <VChip
          v-if="item.is_dead_stock"
          color="error"
          size="small"
          class="font-weight-bold"
        >
          Sin Movimiento
        </VChip>
        <span v-else> {{ item.coverage_months }} meses </span>
      </template>

      <template #item.total_sales="{ item }">
        <span class="font-weight-medium text-high-emphasis">
          {{ formatCurrency(item.total_sales) }}
        </span>
      </template>

      <template #item.participation_percentage="{ item }">
        {{ formatPercent(item.participation_percentage) }}
      </template>

      <template #item.accumulated_percentage="{ item }">
        <VProgressLinear
          :model-value="item.accumulated_percentage"
          color="primary"
          height="6"
          rounded
          class="mb-1"
          style=" display: inline-block;inline-size: 100px; margin-inline-end: 8px;"
        />
        <span class="text-caption">{{
          formatPercent(item.accumulated_percentage)
        }}</span>
      </template>

      <template #item.classification="{ item }">
        <VChip
          :color="getClassColor(item.classification)"
          size="small"
          class="font-weight-bold"
          variant="tonal"
        >
          Clase {{ item.classification }}
        </VChip>
      </template>
    </VDataTable>
  </VCard>
</template>
