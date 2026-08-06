<script setup>
const props = defineProps({
  totals: { type: Object, required: true },
  formatCurrency: { type: Function, required: true },
});
</script>

<template>
  <VRow class="mb-6">
    <VCol cols="12" sm="4">
      <VCard class="rounded-lg border-0 shadow-sm stats-card overflow-hidden">
        <VCardText class="d-flex align-center pa-4">
          <VAvatar color="success" variant="tonal" size="48" class="rounded-lg me-4">
            <VIcon icon="tabler-circle-plus" size="24" />
          </VAvatar>
          <div>
            <p class="text-super-xs font-weight-black text-disabled uppercase mb-0">Total Asignaciones</p>
            <h5 class="text-h5 font-weight-black text-success">{{ props.formatCurrency(props.totals.positive_vouchers) }}</h5>
          </div>
        </VCardText>
      </VCard>
    </VCol>
    <VCol cols="12" sm="4">
      <VCard class="rounded-lg border-0 shadow-sm stats-card overflow-hidden">
        <VCardText class="d-flex align-center pa-4">
          <VAvatar color="error" variant="tonal" size="48" class="rounded-lg me-4">
            <VIcon icon="tabler-circle-minus" size="24" />
          </VAvatar>
          <div>
            <p class="text-super-xs font-weight-black text-disabled uppercase mb-0">Total Deducciones</p>
            <h5 class="text-h5 font-weight-black text-error">{{ props.formatCurrency(props.totals.negative_vouchers) }}</h5>
          </div>
        </VCardText>
      </VCard>
    </VCol>
    <VCol cols="12" sm="4">
      <VCard class="rounded-lg border-0 shadow-sm stats-card overflow-hidden bg-primary-gradient shadow-lg">
        <VCardText class="d-flex align-center pa-4">
          <VAvatar color="white" variant="flat" size="48" class="rounded-lg me-4 opacity-20">
            <VIcon icon="tabler-wallet" size="24" color="white" />
          </VAvatar>
          <div>
            <p class="text-super-xs font-weight-black text-white-opacity-60 uppercase mb-0">Neto Consolidado</p>
            <h5 class="text-h5 font-weight-black text-white">{{ props.formatCurrency(props.totals.total) }}</h5>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.bg-primary-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #9575cd 100%);
}

.text-white-opacity-60 {
  color: rgba(255, 255, 255, 0.6);
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.shadow-lg {
  box-shadow: 0 10px 30px -10px rgba(var(--v-theme-primary), 0.5) !important;
}

.stats-card {
  transition: transform 0.2s ease;
}

.stats-card:hover {
  transform: translateY(-4px);
}
</style>
