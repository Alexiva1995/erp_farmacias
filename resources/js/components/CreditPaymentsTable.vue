<script setup>
import { translateMethod } from "@/utils/paymentMethods";

const props = defineProps({
  payments: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalPayments: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

const headers = [
  { title: "Fecha", key: "date", sortable: true },
  { title: "Cliente", key: "client", sortable: true },
  { title: "Vendedor", key: "seller", sortable: true },
  { title: "Monto", key: "amount", sortable: true },
  { title: "Método", key: "method", sortable: true },
  { title: "Moneda", key: "currency", sortable: true },
  { title: "Referencia", key: "reference", sortable: true },
];
</script>
<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.payments"
      :items-length="props.totalPayments"
      :loading="props.loading"
      class="text-no-wrap"
      fixed-header
      height="auto"
      @update:options="(options) => emit('update:options', options)"
    >
      <template v-slot:item.date="{ item }">
        <span>{{ item.date ? item.date.split(" ")[0] : "N/A" }}</span>
      </template>
      <template v-slot:item.method="{ item }">
        <span>{{ translateMethod(item.method) }}</span>
      </template>
    </VDataTableServer>
  </VCard>
</template>
