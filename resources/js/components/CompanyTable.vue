<script setup lang="js">

const props= defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  // search: { type: String, required: true },
})

const headers = [
  { title: 'id',              key: 'id',},
  { title: 'Nombre',          key: 'name',},
  { title: 'Tipo',            key: 'type_company', sortable: false},
  { title: 'Identificación',  key: 'identification'},
  { title: 'Dirección',       key: 'address', sortable: false },
  { title: 'Acciones',        key: 'acciones', sortable: false },
];

const emit= defineEmits(["edit",'delete','verClientes',"update:options"])
</script>
<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items-per-page="props.itemsPerPage"
      :items="props.items"
      :items-length="props.total"
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
        <IconBtn @click="emit('verClientes', item.id)"
          ><VIcon icon="tabler-users"
        /></IconBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
