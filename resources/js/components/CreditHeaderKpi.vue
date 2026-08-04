<script setup>
defineProps({
  totalCredits: { type: Number, required: true },
  totalPendingSum: { type: Number, required: true },
});
</script>

<template>
  <VRow class="mb-4">
    <VCol cols="12" md="6">
      <div class="d-flex align-center gap-3">
        <VAvatar color="primary" variant="tonal" size="48" class="rounded-lg">
          <VIcon size="28" icon="tabler-credit-card" />
        </VAvatar>
        <div>
          <h1 class="text-h5 font-weight-bold mb-0">Gestión de Créditos a Clientes</h1>
          <p class="text-caption text-medium-emphasis mb-0">
            Control de saldos pendientes, estados de cuenta e historial de abonos
          </p>
        </div>
      </div>
    </VCol>

    <VCol cols="12" md="6">
      <VRow density="compact">
        <VCol cols="6">
          <VCard variant="flat" border class="pa-3 rounded-lg">
            <div class="d-flex align-center justify-space-between">
              <div>
                <span class="text-caption text-medium-emphasis font-weight-medium">Clientes Deudores</span>
                <div class="text-h6 font-weight-black text-primary">{{ totalCredits }}</div>
              </div>
              <VIcon color="primary" icon="tabler-users" size="24" />
            </div>
          </VCard>
        </VCol>
        <VCol cols="6">
          <VCard variant="flat" border class="pa-3 rounded-lg">
            <div class="d-flex align-center justify-space-between">
              <div>
                <span class="text-caption text-medium-emphasis font-weight-medium">Deuda Visible ($)</span>
                <div class="text-h6 font-weight-black text-error">
                  ${{ totalPendingSum.toFixed(2) }}
                </div>
              </div>
              <VIcon color="error" icon="tabler-cash-off" size="24" />
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
