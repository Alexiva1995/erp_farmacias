<script setup>
const props = defineProps({
  atRisk: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
};
</script>

<template>
  <VCard variant="outlined" class="rounded-lg elevation-1 h-100">
    <VCardItem class="py-3 border-b bg-error-lighten-5">
      <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-bold text-uppercase">
        <VIcon icon="tabler-alert-triangle" class="me-2 text-error" size="20" />
        Clientes Críticos en Riesgo (RFM)
      </VCardTitle>
    </VCardItem>

    <VCardText v-if="loading && atRisk.length === 0" class="pa-4">
      <VSkeletonLoader type="table-thead, table-tbody" />
    </VCardText>

    <template v-else-if="atRisk.length > 0">
      <VTable density="compact" class="text-caption">
        <thead>
          <tr>
            <th class="text-uppercase font-weight-bold">Cliente</th>
            <th class="text-uppercase font-weight-bold text-end">Gasto (USD)</th>
            <th class="text-uppercase font-weight-bold text-center">Última Compra</th>
            <th class="text-uppercase font-weight-bold text-center">Días</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="client in atRisk" :key="client.id">
            <td>
              <div class="font-weight-bold text-primary">
                {{ client.name }} {{ client.last_name }}
              </div>
              <div class="text-caption text-disabled">
                {{ client.phone || 'Sin teléfono' }}
              </div>
            </td>
            <td class="text-end font-weight-bold text-error">
              {{ formatCurrency(client.monetary) }}
            </td>
            <td class="text-center">
              {{ client.last_order_date }}
            </td>
            <td class="text-center">
              <VChip size="x-small" label color="error" variant="tonal" class="font-weight-bold">
                {{ client.recency_days }}d
              </VChip>
            </td>
          </tr>
        </tbody>
      </VTable>
    </template>

    <VCardText v-else class="pa-6">
      <VEmptyState
        icon="tabler-user-check"
        title="Sin clientes críticos"
        text="Excelente. No hay clientes valiosos en riesgo de abandono en este momento."
      />
    </VCardText>
  </VCard>
</template>
