<script setup>

const props = defineProps({
  dailyCash: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalDailyCash: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "USD", key: "total_usd", sortable: true },
  { title: "COP", key: "total_cop", sortable: true },
  { title: "BS", key: "total_bs", sortable: true },
  { title: "E. USD", key: "usd_delivered", sortable: true },
  { title: "E. COP", key: "cop_delivered", sortable: true },
  { title: "Bs PM", key: "bs_mobile", sortable: true },
  { title: "Bs Tarjeta", key: "bs_card", sortable: true },
  { title: "ACCIONES", key: "actions", sortable: false },
];

const date = (order) => {
  const time = new Date(order);
  return time.toISOString().split("T")[0];
};

const emit = defineEmits(['update:options','view-cash','delivery','reference','closing-daily']);
const handleUpdateOptions = (options) => {
  emit('update:options', options);
};

</script>
<template>
 <VCard title="Cierre de caja diarios">
  <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.dailyCash"
      :items-length="props.totalDailyCash"
      :loading="props.loading"
      @update:options="handleUpdateOptions"
    >
       <template #item.date="{ item }">
        <span>{{ date(item.created_at) }}</span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-2">
          <IconBtn
            @click="emit('view-cash', item)">
            <VIcon icon="tabler-eye" />
          </IconBtn>
          <IconBtn
            @click="emit('delivery', item)">
            <VIcon icon="tabler-box" />
          </IconBtn>
           <IconBtn
            @click="emit('reference', item)">
            <VIcon icon="tabler-clipboard-list" />
          </IconBtn>
          <IconBtn
            @click="emit('closing-daily', item)">
            <VIcon icon="tabler-printer" />
          </IconBtn>
        </div>
      </template>

    </VDataTableServer>
 </VCard>
</template>
