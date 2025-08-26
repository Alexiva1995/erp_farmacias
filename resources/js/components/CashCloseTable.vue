<script setup>
defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});

const headers = [
  { title: "Nombre de Producto", key: "product.name", sortable: true },
  { title: "Cantidad", key: "discrepancy", align: "center", sortable: true },
  { title: "Usuario", key: "user.name", sortable: true },
  { title: "Monto", key: "amount", align: "end", sortable: true },
];

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
  }).format(value);
};
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
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{ item.product.name }}</span>
          <span class="text-caption text-disabled"
            >Precio unitario:
            {{ formatCurrency(item.product.sale_price) }}</span
          >
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>
