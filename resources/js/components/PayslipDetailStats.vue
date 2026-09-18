<script setup>
const props = defineProps({
  totals: { type: Object, required: true },
  formatCurrency: { type: Function, required: true },
});
</script>

<template>
  <VRow class="mb-4">
    <!-- Asignaciones -->
    <VCol cols="12" sm="4">
      <VCard class="rounded-xl border shadow-sm stats-card overflow-hidden bg-surface">
        <VCardText class="d-flex align-center pa-4">
          <VAvatar color="success" variant="tonal" size="44" class="rounded-lg me-4">
            <VIcon icon="tabler-circle-plus" size="24" />
          </VAvatar>
          <div>
            <p class="text-super-xs font-weight-black text-disabled uppercase mb-0 letter-spacing-1">Total Asignaciones</p>
            <h4 class="text-h5 font-weight-black text-success leading-tight mt-1">{{ props.formatCurrency(props.totals.positive_vouchers) }}</h4>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Deducciones -->
    <VCol cols="12" sm="4">
      <VCard class="rounded-xl border shadow-sm stats-card overflow-hidden bg-surface">
        <VCardText class="d-flex align-center pa-4">
          <VAvatar color="error" variant="tonal" size="44" class="rounded-lg me-4">
            <VIcon icon="tabler-circle-minus" size="24" />
          </VAvatar>
          <div>
            <p class="text-super-xs font-weight-black text-disabled uppercase mb-0 letter-spacing-1">Total Deducciones</p>
            <h4 class="text-h5 font-weight-black text-error leading-tight mt-1">{{ props.formatCurrency(props.totals.negative_vouchers) }}</h4>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Neto Consolidado -->
    <VCol cols="12" sm="4">
      <VCard class="rounded-xl border shadow-sm stats-card overflow-hidden bg-surface net-card position-relative">
        <div class="net-indicator" />
        <VCardText class="d-flex align-center pa-4">
          <VAvatar color="primary" variant="tonal" size="44" class="rounded-lg me-4">
            <VIcon icon="tabler-wallet" size="24" />
          </VAvatar>
          <div>
            <p class="text-super-xs font-weight-black text-disabled uppercase mb-0 letter-spacing-1">Neto Consolidado</p>
            <h4 class="text-h5 font-weight-black text-primary leading-tight mt-1">{{ props.formatCurrency(props.totals.total) }}</h4>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.stats-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stats-card:hover {
  transform: translateY(-2px);
}

.net-card {
  border-left: 4px solid rgb(var(--v-theme-primary)) !important;
}

.net-indicator {
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background-color: rgb(var(--v-theme-primary));
}
</style>
