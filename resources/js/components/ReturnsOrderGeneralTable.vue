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
  { title: "Fecha", key: "return_date", sortable: true },
  { title: "Producto", key: "product", sortable: false },
];
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

<template #item.product="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.product.name }}</span>
            <span class="text-sm text-disabled">{{ item.product.active_ingredient }}</span>
          </div>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
