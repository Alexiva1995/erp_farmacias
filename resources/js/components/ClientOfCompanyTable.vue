<script setup lang="js">
import day from "dayjs";
import { computed } from "vue";

const props= defineProps({
  companyId: { type: [String, Number], required: true },
  clients: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalClients: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: undefined },
})

const emit= defineEmits(["edit",'delete','update:options','remove-from-company'])

const sortByModel = computed(() => {
  if (props.sortBy) {
    return [{ key: props.sortBy, order: props.orderBy || 'asc' }]
  }
  return []
})

const headers = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Nombre', key: 'name', value: item => `${item.name} ${(item.last_name==null)?"":item.last_name}`, sortable: true },
  { title: 'Identidad', key: 'identification', value: item => `${item.identification_type}${item.identification}`, sortable: true },
  { title: 'Dirección', key: 'address', sortable: true },
  { title: 'Fecha', key: 'created_at', sortable: true, value: item => {
    const fechaStr = item.created_at.replace('Z', '');
    return day(fechaStr).format('DD/MM/YYYY');
  }},
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
      :sort-by="sortByModel"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>
      <template #item.acciones="{ item }">
        <IconBtn @click="emit('edit', item.id)" color="warning">
          <VIcon icon="tabler-edit" />
        </IconBtn>
        <IconBtn @click="emit('delete', item.id)" color="error">
          <VIcon icon="tabler-trash" />
        </IconBtn>
        <VTooltip text="Quitar de la empresa" location="top">
          <template #activator="{ props: tooltipProps }">
            <IconBtn
              v-bind="tooltipProps"
              @click="emit('remove-from-company', item.id)"
              color="secondary"
            >
              <VIcon icon="tabler-unlink" />
            </IconBtn>
          </template>
        </VTooltip>
      </template>
    </VDataTableServer>
  </VCard>
</template>
