<script setup lang="js">
import day from 'dayjs';
import { computed } from 'vue';

const props= defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
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

const headers = [
  { title: 'ID',                        key: 'id', sortable: true},
  { title: 'Identificación',           key: 'identificationClient', sortable: false, value: item => item.client?.identification_type + item.client?.identification},
  { title: 'Cliente',                  key: 'nameClient', sortable: false, value: item => (item.client?.name || '') + " " + (item.client?.last_name || '')},
  { title: 'Vendedor',                 key: 'nameSeller', sortable: false, value: item => item.seller?.username || ''},
  { title: 'Monto USD',               key: 'total_amount_usd', sortable: true},
  { title: 'Moneda',                   key: 'currency', sortable: true},
  { title: 'Fecha',                    key: 'created_at', sortable: true, value: item => {
    const fechaStr = item.created_at.replace('Z', '');
    return day(fechaStr).format('DD/MM/YYYY');
  }},
];

const emit= defineEmits(["update:options"])
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
      :sort-by="sortByModel"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>
      <template #item.total_amount_usd="{ item }">
        <span class="font-weight-medium text-success">
          ${{ item.total_amount_usd ? Number(item.total_amount_usd).toFixed(2) : '0.00' }}
        </span>
      </template>
      <template #item.currency="{ item }">
        <VChip size="small" variant="tonal" :color="item.currency === 'USD' ? 'success' : item.currency === 'BS' ? 'info' : 'warning'">
          {{ item.currency }}
        </VChip>
      </template>
    </VDataTableServer>
  </VCard>
</template>
