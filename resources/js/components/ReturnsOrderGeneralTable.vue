<script setup>
const props = defineProps({
  returns: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalReturns: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});
const emit = defineEmits(["update:options"]);
const expanded = ref([]);

const headers = [
  { title: "ID", key: "id", sortable: true, width: "100px" },
  { title: "N° Orden", key: "order_id", sortable: true, width: "100px" },
  { title: "Usuario", key: "client", sortable: true },
  { title: "Identificación", key: "identificacion", sortable: true },
  { title: "Monto", key: "amount_refunded", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Producto", key: "product", sortable: false },
];

const date = (order) => {
  const time = new Date(order);
  return time.toISOString().split("T")[0];
};

</script>

<template>
  <VCard>
    <VDataTableServer
      v-model:expanded="expanded"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.returns"
      :items-length="props.totalReturns"
      :loading="props.loading"
      item-key="id"
      class="text-no-wrap"
      fixed-header
      height="auto"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.client="{ item }">
        <span class="font-weight-medium">
          {{ item.order.seller.username }}
        </span>
      </template>

      <template #item.identificacion="{ item }">
        <span class="font-weight-medium">
          {{ item.order.client.identification_type }}
          {{ item.order.client.identification }}
        </span>
      </template>

   <template #item.date="{ item }">
        <span>{{ date(item.return_date) }}</span>
      </template>

<template #item.product="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.product.name }}</span>
            <span v-if="item.product?.laboratory?.name" class="text-caption text-medium-emphasis">
              {{ item.product.laboratory.name }}
            </span>
          </div>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
