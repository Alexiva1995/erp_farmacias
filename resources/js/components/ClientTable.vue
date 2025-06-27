<script setup lang="js">


const props= defineProps({
  clients: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalClients: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  // search: { type: String, required: true },
})

const emit= defineEmits(["edit",'delete'])

const headers = [
  { title: 'id', key: 'id', sortable: false,},
  { title: 'Nombre', key: 'fullName', value: item => `${item.name} ${item.last_name}`, sortable: false, },
  { title: 'Identidad', key: 'identificaction', value: item => `${item.identification_type}${item.identification}`, sortable: false, },
  { title: 'Empresa', key: 'company.name', sortable: false, },
  { title: 'Dirección', key: 'address' },
  { title: 'Acciones', key: 'acciones' },
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
      <template #item.id="{ item }"
        ><span class="font-weight-medium">{{ item.id }}</span></template
      >
      <template #item.acciones="{ item }">
        <IconBtn @click="emit('edit', item.id)"
          ><VIcon icon="tabler-edit"
        /></IconBtn>
        <IconBtn @click="emit('delete', item.id)"
          ><VIcon icon="tabler-trash"
        /></IconBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
