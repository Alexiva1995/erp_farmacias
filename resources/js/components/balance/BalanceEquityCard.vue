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
        <VIcon icon="tabler-building-bank" color="primary" class="me-2" />
        Patrimonio y Ecuación Contable
      </VCardTitle>
    </VCardItem>
    <VDivider />
    <VCardText class="d-flex flex-column flex-grow-1 pa-3 pa-sm-4">
      <VList density="compact" class="flex-grow-1">
        <!-- Subtítulo de Patrimonio -->
        <div class="text-super-xs font-weight-black text-primary text-uppercase px-2 mb-1">
          Composición Patrimonial
        </div>
        <VListItem class="rounded-lg px-2">
          <template #prepend>
            <VAvatar size="28" color="primary" variant="tonal" class="me-3">
              <VIcon icon="tabler-coin" size="16" />
            </VAvatar>
          </template>
          <VListItemTitle class="text-body-2 font-weight-medium">Capital y Resultados Acumulados</VListItemTitle>
          <template #append>
            <span class="font-weight-bold" :class="balance.equity >= 0 ? 'text-primary' : 'text-error'">
              {{ formatCurrency(balance.equity) }}
            </span>
          </template>
        </VListItem>

        <VDivider class="my-2" />

        <!-- Subtítulo de Ecuación Contable -->
        <div class="text-super-xs font-weight-black text-disabled text-uppercase px-2 mb-1">
          Ecuación Patrimonial (Activo = Pasivo + Patrimonio)
        </div>
        
        <VListItem class="rounded-lg px-2">
          <template #prepend>
            <VIcon icon="tabler-plus" size="16" color="error" class="me-3" />
          </template>
          <VListItemTitle class="text-caption font-weight-medium">Total Pasivos</VListItemTitle>
          <template #append>
            <span class="text-caption font-weight-bold text-error">{{ formatCurrency(balance.liabilities.total) }}</span>
          </template>
        </VListItem>

        <VListItem class="rounded-lg px-2">
          <template #prepend>
            <VIcon icon="tabler-plus" size="16" color="primary" class="me-3" />
          </template>
          <VListItemTitle class="text-caption font-weight-medium">Patrimonio Neto</VListItemTitle>
          <template #append>
            <span class="text-caption font-weight-bold" :class="balance.equity >= 0 ? 'text-primary' : 'text-error'">
              {{ formatCurrency(balance.equity) }}
            </span>
          </template>
        </VListItem>

        <VListItem class="rounded-lg px-2 bg-grey-50">
          <template #prepend>
            <VIcon icon="tabler-equal" size="16" color="success" class="me-3" />
          </template>
          <VListItemTitle class="text-caption font-weight-bold text-high-emphasis">Pasivo + Patrimonio</VListItemTitle>
          <template #append>
            <span class="text-caption font-weight-black text-success">
              {{ formatCurrency(Number(balance.liabilities.total || 0) + Number(balance.equity || 0)) }}
            </span>
          </template>
        </VListItem>
      </VList>

      <div class="mt-auto pt-2">
        <VAlert
          :color="balance.equity >= 0 ? 'primary' : 'error'"
          variant="tonal"
          class="rounded-lg border-opacity-25 mb-0"
          density="compact"
        >
          <div class="d-flex justify-space-between align-center">
            <span class="text-caption font-weight-bold text-uppercase">Patrimonio Neto Total</span>
            <span class="text-h6 font-weight-black" :class="balance.equity >= 0 ? 'text-primary' : 'text-error'">
              {{ formatCurrency(balance.equity) }}
            </span>
          </div>
        </VAlert>
      </div>
    </VCardText>
  </VCard>
</template>
