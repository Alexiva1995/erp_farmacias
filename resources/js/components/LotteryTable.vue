<script setup lang="js">

import day from 'dayjs';

const props= defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
})

const headers = [
  { title: 'id',                       key: 'id', sortable: true},
  { title: 'Identificación',           key: 'identificationClient', sortable: false,value: item => item.client.identification_type+item.client.identification},
  { title: 'Cliente',                  key: 'nameClient', sortable: false, value: item => item.client.name+" "+item.client.last_name},
  { title: 'Vendedor',                 key: 'nameSeller', sortable: false, value: item => item.seller.username},
  { title: 'Monto',                    key: 'total_amount_usd', sortable:true},
  { title: 'Moneda',                   key: 'currency', sortable:true},
  { title: 'Fecha',                    key: 'created_at', sortable: true, value: item => day(item.created_at).format("DD/MM/YYYY") },
];

const emit= defineEmits(["edit",'delete',"update:options"])
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
    </VDataTableServer>
  </VCard>
</template>
