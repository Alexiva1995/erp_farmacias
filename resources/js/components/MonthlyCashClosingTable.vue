<script setup>

const props = defineProps({
  monthlyCash: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalMonthlyCash: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const headers = [
  { title: "Fecha", key: "closing_date", sortable: true },
  { title: "USD", key: "amount_usd", sortable: true },
  { title: "COP", key: "amount_cop", sortable: true },
  { title: "BS", key: "amount_bs", sortable: true },
  { title: "promedio de venta", key: "daily_average", sortable: true },
  { title: "Acciones", key: "actions", sortable: false },
];

const emit = defineEmits(['update:options','view-cash','seller-cash']);

const handleUpdateOptions = (options) => {
  emit('update:options', options);
};

</script>
<template>
 <VCard title="Cierre de caja mensual">
  <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.monthlyCash"
      :items-length="props.totalMonthlyCash"
      :loading="props.loading"
      @update:options="handleUpdateOptions"
    >
      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-2">
          <IconBtn
            @click="emit('view-cash', item)"
            color="info">
            <VIcon icon="tabler-eye" />
          </IconBtn>
           <IconBtn
            @click="emit('seller-cash', item)">
            <VIcon icon="tabler-clipboard-list" />
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
 </VCard>
</template>
