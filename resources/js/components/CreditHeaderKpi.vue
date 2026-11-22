<script setup>
defineProps({
  totalDebtors: { type: Number, required: true },
  totalPendingSum: { type: Number, required: true },
  overdueDebtors: { type: Number, default: 0 },
  overdueAmountUsd: { type: Number, default: 0 },
});
</script>

<template>
  <VRow class="mb-4">
    <VCol cols="12" md="5">
      <div class="d-flex align-center gap-3">
        <VAvatar color="primary" variant="tonal" size="48" class="rounded-lg">
          <VIcon size="28" icon="tabler-credit-card" />
        </VAvatar>
        <div>
          <h1 class="text-h5 font-weight-bold mb-0">Créditos y Cuentas por Cobrar</h1>
          <p class="text-caption text-medium-emphasis mb-0">
            Control de saldos pendientes, abonos y recordatorios de cobro
          </p>
        </div>
      </div>
    </VCol>

    <VCol cols="12" md="7">
      <VRow density="compact">
        <VCol cols="4">
          <VCard variant="flat" border class="pa-3 rounded-lg h-100">
            <div class="d-flex align-center justify-space-between">
              <div>
                <span class="text-caption text-medium-emphasis font-weight-medium">Deudores</span>
                <div class="text-h6 font-weight-black text-primary">{{ totalDebtors }}</div>
              </div>
              <VIcon color="primary" icon="tabler-users" size="24" />
            </div>
          </VCard>
        </VCol>
        <VCol cols="4">
          <VCard variant="flat" border class="pa-3 rounded-lg h-100">
            <div class="d-flex align-center justify-space-between">
              <div>
                <span class="text-caption text-medium-emphasis font-weight-medium">Por Cobrar ($)</span>
                <div class="text-h6 font-weight-black text-error">
                  ${{ Number(totalPendingSum || 0).toFixed(2) }}
                </div>
              </div>
              <VIcon color="error" icon="tabler-cash" size="24" />
            </div>
          </VCard>
        </VCol>
        <VCol cols="4">
          <VCard variant="flat" border class="pa-3 rounded-lg h-100">
            <div class="d-flex align-center justify-space-between">
              <div>
                <span class="text-caption text-medium-emphasis font-weight-medium">
                  Vencido ({{ overdueDebtors }})
                </span>
                <div class="text-h6 font-weight-black text-warning">
                  ${{ Number(overdueAmountUsd || 0).toFixed(2) }}
                </div>
              </div>
              <VIcon color="warning" icon="tabler-clock-exclamation" size="24" />
            </div>
          </VCard>
        </VCol>
      </VRow>
    </VCol>
  </VRow>
</template>

<style scoped>
.gap-3 { gap: 12px !important; }
</style>
