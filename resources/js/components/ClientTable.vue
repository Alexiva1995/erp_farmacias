<script setup lang="js">
import day from "dayjs";
import { computed } from "vue";

const props= defineProps({
  clients: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalClients: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: undefined },
})

const sortByModel = computed(() => {
  if (props.sortBy) {
    return [{ key: props.sortBy, order: props.orderBy || 'asc' }]
  }
  return []
})

const emit= defineEmits(["edit",'delete','update:options','view-stats'])

const clientTypeColor = (type) => {
  const map = {
    'VIP': 'warning',
    'Frecuente': 'success',
    'En Riesgo': 'error',
    'Ocasional': 'info',
    'Nuevo': 'secondary',
  }
  return map[type] || 'secondary'
}

const headers = [
  { title: 'id', key: 'id', sortable: true,},
  { title: 'Nombre', key: 'name', value: item => `${item.name} ${(item.last_name==null)?"":item.last_name}`, sortable: true, },
  { title: 'Identidad', key: 'identification', value: item => `${item.identification_type}${item.identification}`, sortable: true, },
  { title: 'Empresa', key: 'company.name', sortable: false },
  { title: 'Tipo', key: 'client_type', sortable: true },
  { title: 'Dirección', key: 'address', sortable: true  },
  { title: 'Fecha',    key: 'created_at', sortable: true, value: item =>{
    const fechaStr = item.created_at.replace('Z', '');
    const fecha = day(fechaStr).format('DD/MM/YYYY');
    return fecha;
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
      <template #item.id="{ item }"
        ><span class="font-weight-medium">{{ item.id }}</span></template
      >
      <template #item.client_type="{ item }">
        <VChip
          v-if="item.client_type"
          :color="clientTypeColor(item.client_type)"
          size="small"
          variant="tonal"
        >
          {{ item.client_type }}
        </VChip>
        <span v-else class="text-medium-emphasis">—</span>
      </template>
      <template #item.acciones="{ item }">
        <IconBtn @click="emit('view-stats', item.id)" color="info"
          ><VIcon icon="tabler-eye"
        /></IconBtn>
        <IconBtn @click="emit('edit', item.id)" color="warning"
          ><VIcon icon="tabler-edit"
        /></IconBtn>
        <IconBtn @click="emit('delete', item.id)" color="error"
          ><VIcon icon="tabler-trash"
        /></IconBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
