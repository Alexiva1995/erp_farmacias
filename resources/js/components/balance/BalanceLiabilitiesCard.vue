<script setup>
defineProps({
  balance: {
    type: Object,
    required: true,
  },
  formatCurrency: {
    type: Function,
    required: true,
  },
});
</script>

<template>
  <VCard class="rounded-lg border shadow-sm w-100 h-100 d-flex flex-column">
    <VCardItem>
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-trending-down" color="error" class="me-2" />
        Pasivos y Obligaciones
      </VCardTitle>
    </VCardItem>
    <VDivider />
    <VCardText class="d-flex flex-column flex-grow-1">
      <VList density="comfortable" class="flex-grow-1">
        <VListItem>
          <template #prepend>
            <VIcon icon="tabler-users" color="error" class="me-3" />
          </template>
          <VListItemTitle>Cuentas por Pagar (Proveedores)</VListItemTitle>
          <template #append>
            <span class="font-weight-bold">{{ formatCurrency(balance.liabilities.details.supplier_debts) }}</span>
          </template>
        </VListItem>
        <VListItem>
          <template #prepend>
            <VIcon icon="tabler-building-bank" color="secondary" class="me-3" />
          </template>
          <VListItemTitle>Préstamos Bancarios</VListItemTitle>
          <template #append>
            <span class="font-weight-bold">{{ formatCurrency(balance.liabilities.details.loans) }}</span>
          </template>
        </VListItem>
      </VList>
      <div class="mt-auto">
        <VDivider class="my-4" />
        <div class="d-flex justify-space-between px-4 align-center mb-2">
          <span class="text-h6">Total Pasivos</span>
          <span class="text-h6 text-error font-weight-black">{{ formatCurrency(balance.liabilities.total) }}</span>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
