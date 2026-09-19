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
    <VCardText class="d-flex flex-column flex-grow-1 pa-3 pa-sm-4">
      <VList density="compact" class="flex-grow-1">
        <!-- Pasivo Corriente -->
        <div class="text-super-xs font-weight-black text-error text-uppercase px-2 mb-1">
          Pasivo Corriente (Corto Plazo)
        </div>

        <VListItem class="rounded-lg px-1.5">
          <template #prepend>
            <VAvatar size="24" color="error" variant="tonal" class="me-1.5">
              <VIcon icon="tabler-users" size="14" />
            </VAvatar>
          </template>
          <VListItemTitle class="text-caption font-weight-medium text-wrap leading-tight">Cuentas por Pagar a Proveedores</VListItemTitle>
          <template #append>
            <span class="text-caption font-weight-bold">{{ formatCurrency(balance.liabilities.details.supplier_debts) }}</span>
          </template>
        </VListItem>

        <VDivider class="my-2" />

        <!-- Pasivo No Corriente / Financiero -->
        <div class="text-super-xs font-weight-black text-secondary text-uppercase px-2 mb-1">
          Obligaciones Financieras
        </div>

        <VListItem class="rounded-lg px-1.5">
          <template #prepend>
            <VAvatar size="24" color="secondary" variant="tonal" class="me-1.5">
              <VIcon icon="tabler-building-bank" size="14" />
            </VAvatar>
          </template>
          <VListItemTitle class="text-caption font-weight-medium text-wrap leading-tight">Préstamos y Financiamientos</VListItemTitle>
          <template #append>
            <span class="text-caption font-weight-bold">{{ formatCurrency(balance.liabilities.details.loans) }}</span>
          </template>
        </VListItem>
      </VList>
      <div class="mt-auto pt-2">
        <VAlert color="error" variant="tonal" class="rounded-lg border-opacity-25 mb-0" density="compact">
          <div class="d-flex justify-space-between align-center">
            <span class="text-caption font-weight-bold text-uppercase">Total Pasivos</span>
            <span class="text-h6 font-weight-black text-error">{{ formatCurrency(balance.liabilities.total) }}</span>
          </div>
        </VAlert>
      </div>
    </VCardText>
  </VCard>
</template>
