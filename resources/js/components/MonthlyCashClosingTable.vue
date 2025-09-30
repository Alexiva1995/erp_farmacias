<script setup>

const props = defineProps({
  monthlyCash: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalMonthlyCash: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const headers = [
  { title: "Fecha", key: "total_pending_amount", sortable: true },
  { title: "USD", key: "action", sortable: false },
  { title: "COP", key: "total_pending_amount", sortable: true },
  { title: "BS", key: "total_pending_amount", sortable: true },
  { title: "promedio de venta", key: "action", sortable: false },
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
