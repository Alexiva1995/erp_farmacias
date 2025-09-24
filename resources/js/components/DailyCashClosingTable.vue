<script setup>

const props = defineProps({
  dailyCash: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalDailyCash: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const headers = [
  { title: "ID", key: "client_full_name", sortable: true },
  { title: "Fecha", key: "total_pending_amount", sortable: true },
  { title: "USD", key: "action", sortable: false },
  { title: "COP", key: "total_pending_amount", sortable: true },
  { title: "BS", key: "total_pending_amount", sortable: true },
  { title: "E. USD", key: "action", sortable: false },
  { title: "E. COP", key: "action", sortable: false },
  { title: "Bs PM", key: "action", sortable: false },
  { title: "Bs Tarjeta", key: "action", sortable: false },
  { title: "ACCIONES", key: "action", sortable: false },
];

</script>
<template>
 <VCard title="Cierre de caja diarios">
  <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.dailyCash"
      :items-length="props.totalDailyCash"
      :loading="props.loading"
    >
    </VDataTableServer>
 </VCard>
</template>
