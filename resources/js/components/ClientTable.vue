<script setup lang="js">
import day from "dayjs";


const props= defineProps({
  clients: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalClients: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  // search: { type: String, required: true },
})

const emit= defineEmits(["edit",'delete','update:options'])

const headers = [
  { title: 'id', key: 'id', sortable: true,},
  { title: 'Nombre', key: 'name', value: item => `${item.name} ${(item.last_name==null)?"":item.last_name}`, sortable: true, },
  { title: 'Identidad', key: 'identification', value: item => `${item.identification_type}${item.identification}`, sortable: true, },
  { title: 'Empresa', key: 'company.name', sortable: false },
  { title: 'Dirección', key: 'address', sortable: true  },
  { title: 'Fecha',    key: 'created_at', sortable: true, value: item => day(item.created_at).format("DD/MM/YYYY") },
  { title: 'Acciones', key: 'acciones', sortable: false },
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
      @update:options="(options) => emit('update:options', options)"
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
