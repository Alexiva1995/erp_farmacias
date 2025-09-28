<script setup>

const props = defineProps({
  sellerCash: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSellerCash: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const headers = [
  { title: "Fecha", key: "total_pending_amount", sortable: true },
  { title: "Acción", key: "action", sortable: false },
];

</script>
<template>
 <VCard>
  <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.sellerCash"
      :items-length="props.totalSellerCash"
      :loading="props.loading"
    >
    </VDataTableServer>
 </VCard>
</template>
