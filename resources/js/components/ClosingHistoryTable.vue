<script setup>
const props = defineProps({
  closing: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalClosing: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});
const emit = defineEmits(["update:options"]);
const headers = [
  { title: "id", key: "id", sortable: true},
  { title: "Fecha", key: "date", sortable: true, maxWidth: '55px'},
  { title: 'Acción', key: 'actions', sortable: false, maxWidth: '95px'},
];
</script>

<template>
<VCard>
<VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.closing"
      :items-length="props.totalClosing"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
</VDataTableServer>
  </VCard>
</template>
