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
  <VCard title="Análisis de Inventario ABC (Pareto)">
    <VDataTable
      :headers="headers"
      :items="props.items"
      :loading="props.loading"
      class="text-no-wrap"
      density="compact"
    >
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
          style="width: 100px; display: inline-block; margin-right: 8px"
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
