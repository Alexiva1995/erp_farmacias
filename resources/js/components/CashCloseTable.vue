<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";

defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});

const headers = [
  { title: "Nombre de Producto", key: "product.name", sortable: true },
  { title: "Laboratorio", key: "product.laboratory.name", sortable: true },
  { title: "Cantidad", key: "discrepancy", align: "center", sortable: true },
  { title: "Costo", key: "product.unit_cost", align: "end", sortable: true },
  { title: "Usuario Conteo", key: "user.name", sortable: true },
  { title: "Supervisor Aprobación", key: "supervisor.name", sortable: true },
  { title: "Monto", key: "amount", align: "end", sortable: true },
];

/*const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
  }).format(value);
};*/
</script>

<template>
  <VCard>
    <VDataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      class="text-no-wrap"
      item-value="id"
      no-data-text="No hay diferencias registradas para el cierre."
    >
      <template #item.discrepancy="{ item }">
        <VChip
          :color="item.discrepancy > 0 ? 'success' : 'error'"
          label
          size="small"
        >
          <VIcon
            :icon="item.discrepancy > 0 ? 'tabler-plus' : 'tabler-minus'"
            start
          />
          {{ Math.abs(item.discrepancy) }}
        </VChip>
      </template>

      <template #item.amount="{ item }">
        <span
          :class="item.discrepancy > 0 ? 'text-success' : 'text-error'"
          class="font-weight-medium"
        >
          {{ formatCurrency(item.product.sale_price * item.discrepancy) }}
        </span>
      </template>

      <template #item.product.name="{ item }">
        <span class="font-weight-medium">{{ item.product.name }}</span>
      </template>

      <template #item.product.laboratory.name="{ item }">
        <span>{{ item.product.laboratory?.name || "N/A" }}</span>
      </template>

      <template #item.product.unit_cost="{ item }">
        <span class="font-weight-medium">
          {{ formatCurrency(parseFloat(item.product.unit_cost || 0)) }}
        </span>
      </template>

      <template #item.supervisor.name="{ item }">
        <span>{{ item.supervisor?.name || "N/A" }}</span>
      </template>
    </VDataTable>
  </VCard>
</template>
