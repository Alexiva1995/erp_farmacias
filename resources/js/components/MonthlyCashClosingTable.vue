<script setup>

const props = defineProps({
  monthlyCash: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalMonthlyCash: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const headers = [
  { title: "Fecha", key: "closing_date", sortable: true },
  { title: "USD", key: "amount_usd", sortable: true },
  { title: "COP", key: "amount_cop", sortable: true },
  { title: "BS", key: "amount_bs", sortable: true },
  { title: "promedio de venta", key: "daily_average", sortable: true },
  { title: "Acción", key: "action", sortable: false },
];

</script>
<template>
 <VCard title="Cierre de caja mensual">
  <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.monthlyCash"
      :items-length="props.totalMonthlyCash"
      :loading="props.loading"
    >
    </VDataTableServer>
 </VCard>
</template>
