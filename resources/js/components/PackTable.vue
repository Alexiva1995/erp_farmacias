<script setup>
const props = defineProps({
  packs: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalPacks: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

const headers = [
  { title: "id", key: "id", sortable: true },
  { title: "Pack", key: "name", sortable: true, width: "40%" },
  { title: "Stock", key: "stock", sortable: true },
  { title: "Exp.", key: "next_expiration", sortable: true },
  { title: "Precio Venta", key: "sale_price", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

</script>
<template>
 <VCard>
 <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.packs"
      :items-length="props.totalPacks"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
 </VDataTableServer>
 </VCard>
</template>
