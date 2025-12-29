<script setup>

const props = defineProps({
  sellerCash: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSellerCash: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "USD", key: "total_usd", sortable: true },
  { title: "COP", key: "total_cop", sortable: true },
  { title: "BS", key: "total_bs", sortable: true },
  { title: "total", key: "total_sales", sortable: true },
  { title: "Vendedor", key: "seller.username", sortable: true },
  { title: "estado", key: "status", sortable: true },
  { title: "Acciones", key: "actions", sortable: false },
];

const emit = defineEmits(['update:options',,"print-cash","download-cash"]);

const handleUpdateOptions = (options) => {
  emit('update:options', options);
};

</script>
<template>
 <VCard>
  <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.sellerCash"
      :items-length="props.totalSellerCash"
      :loading="props.loading"
      @update:options="handleUpdateOptions"
    >
    
<template v-slot:item.status="{ item }">
        <VChip
          :color="item.status === 'closed' ? 'success' :  'warning'"
        >
          <span v-if="item.status === 'closed'">Cerrada</span>
          <span v-else-if="item.status === 'open'">Abierta</span>
          <span v-else>{{ item.status }}</span> </VChip>
      </template>

        <template #item.actions="{ item }">
        <div class="d-flex align-center gap-2">
          <IconBtn
             @click="emit('print-cash', item)"
             color="info">
            <VIcon icon="tabler-eye" />
          </IconBtn>
          <IconBtn
            @click="emit('download-cash', item)">
            <VIcon icon="tabler-download" />
          </IconBtn>
        </div>
      </template>

    </VDataTableServer>
 </VCard>
</template>
