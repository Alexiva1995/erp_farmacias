<script setup>

const props = defineProps({
  credits: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalCredits: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(['update:options']);

const headers = [
  { title: 'Nombre', key: 'name', sortable: true },
  { title: 'Monto', key: 'name',  sortable: true},
  { title: 'Añadir', key: 'action', sortable: false},
];

</script>

<template>
<VCard>
<VDataTableServer 
:items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.credits"
      :items-length="props.totalCredits"
      :loading="props.loading"
      class="text-no-wrap"
      fixed-header height="auto"
      @update:options="options => emit('update:options', options)"
    >
</VDataTableServer>
</VCard>
</template>
