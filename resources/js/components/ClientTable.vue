<script setup lang="js">

const props= defineProps({
  clients: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalClients: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
})

const headers = [
  { title: 'id', key: 'id'},
  { title: 'Nombre', key: 'fullName', value: item => `${item.name} ${item.last_name}` },
  { title: 'Identidad', key: 'identificaction', value: item => `${item.identification_type}${item.identification}` },
  { title: 'Empresa', key: 'company.name' },
  { title: 'Dirección', key: 'address' },
];
</script>

<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items-per-page="props.itemsPerPage"
      :items="props.clients"
      :items-length="props.totalClients"
      :loading="loading"
      :page="props.page"
    >
    </VDataTableServer>
  </VCard>
</template>
