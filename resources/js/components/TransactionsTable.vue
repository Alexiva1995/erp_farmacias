<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  transactions: { type: [Array, Object], default: () => [] },
  loading: { type: Boolean, default: false },
  dataDetailed: { type: Boolean, default: false },
  previousTotalUsd: { type: Number, default: 0 },
  selectedCurrency: { type: String, default: "" },
  selectedTab: { type: String, default: "" },
  totalTransactions: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "update:selectedTab"]);

const headers = [
  { title: "Id", key: "id", sortable: false },
  { title: "Usuario", key: "user_name", sortable: false },
  { title: "Descripción", key: "description", sortable: false },
  { title: "Tipo", key: "type", sortable: false },
  { title: "Monto", key: "amount", sortable: false },
  { title: "Balance", key: "balance", sortable: false },
  { title: "Categoría", key: "category_name", sortable: false },
  { title: "Fecha", key: "transaction_date", sortable: false },
];
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.transactions"
      :items-length="props.totalTransactions"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #body.prepend>
        <tr class="font-weight-bold bg-grey-lighten-3">
          <td colspan="1"></td>
          <td class="py-3 pl-4">
            <span>Balance previo:</span>
          </td>
          <td colspan="2"></td>
          <td>
            <span class="text-success text-h6 pr-4">
              {{ formatCurrency(parseFloat(props.previousTotalUsd), "USD") }}
            </span>
          </td>
        </tr>
      </template>

      <template #item.amount="{ item }">
        <span
          class="font-weight-medium"
          :class="item.movement_type === 'IN' ? 'text-success' : 'text-error'"
          >{{ formatCurrency(parseFloat(item.amount), item.currency) }}</span
        >
      </template>

      <template #item.balance="{ item }">
        <span class="font-weight-medium">{{
          formatCurrency(parseFloat(item.balance), item.currency)
        }}</span>
      </template>
    </VDataTableServer>
  </VCard>
</template>
