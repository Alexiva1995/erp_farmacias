<script setup>
const props = defineProps({
  orders: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOrders: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  headers: {type: Array,required: true},
});

const emit = defineEmits(["update:options","print-order", "view-order"]);

const date = (order) => {
  const time = new Date(order);
  return time.toISOString().split("T")[0];
};

const handleView = (orderId) => {
  emit('view-order', orderId);
}
</script>
<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="props.headers"
      :items="props.orders"
      :items-length="props.totalOrders"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
     <template v-slot:item.identification="{ item }">
      {{ item.client.identification_type }} {{ item.client.identification }}
    </template>
    <template v-slot:item.client_full_name="{ item }">
      {{ item.client.name }} {{ item.client.last_name }}
    </template>
    <template v-slot:item.currency="{ item }">
        <span v-if="item.payment_methods?.some(p => p.method === 'credit')">
          {{ item.currency }}*
        </span>
        <span v-else>
          {{ item.currency }}
        </span>
      </template>

<template v-slot:item.status="{ item }">
        <VChip
          :color="item.status === 'Completed' ? 'success' : item.status === 'Abandoned' ? 'warning' : 'error'"
        >
          <span v-if="item.status === 'Completed'">Completada</span>
          <span v-else-if="item.status === 'Abandoned'">Abandonada</span>
          <span v-else-if="item.status === 'Cancelled'">Cancelada</span>
          <span v-else>{{ item.status }}</span> </VChip>
      </template>
      
   <template #item.date="{ item }">
        <span>{{ date(item.order_date) }}</span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-2">
          <IconBtn
            @click="handleView(item.id)">
            <VIcon icon="tabler-eye" />
          </IconBtn>
          <IconBtn
            @click="$emit('print-order', item.id)">
            <VIcon icon="tabler-printer" />
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
