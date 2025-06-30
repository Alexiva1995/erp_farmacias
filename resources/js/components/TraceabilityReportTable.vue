<script setup>
const props = defineProps({
  sales: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSales: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Stock", key: "stock_before", sortable: true },
  { title: "Cantidad", key: "quantity", sortable: false },
  { title: "Fecha", key: "movement_date", sortable: true },
  { title: "Tipo", key: "movement_type", sortable: true },
  { title: "Provedor", key: "supplier.name", sortable: true },
  { title: "Cliente", key: "user.email", sortable: true },
];
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.sales"
      :items-length="props.totalSales"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">#{{ item.id }}</span>
      </template>

      <template #item.movement_date="{ item }">
        <span>{{ new Date(item.movement_date).toLocaleDateString() }}</span>
      </template>

      <template #item.customer.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{
              item.user.username
            }}</span>
            <span class="text-sm text-disabled">{{ item.user.email }}</span>
          </div>
        </div>
      </template>

      <template #item.quantity="{ item }">
        <span>{{ item.quantity }}</span>
      </template>

      <template #item.movement_type="{ item }">
        {{ item.movement_type }}
      </template>

      <template #item.total_amount="{ item }">
        <span class="font-weight-medium text-high-emphasis"
          >${{
            parseFloat(
              item.order_id != null
                ? item.order.total_amount
                : item.invoice_id != null
                ? item.invoice.total_amount
                : "N/A"
            ).toFixed(2)
          }}</span
        >
      </template>
    </VDataTableServer>
  </VCard>
</template>
