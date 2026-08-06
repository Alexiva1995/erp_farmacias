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
  <VRow class="ma-0 mx-n1 mb-5" dense>
    <!-- Ratio de Liquidez -->
    <VCol cols="12" sm="4" class="pa-1">
      <VHover v-slot="{ isHovering, props }">
        <VCard
          v-bind="props"
          :elevation="isHovering ? 6 : 2"
          class="h-100 rounded-xl border-t-0 bg-surface transition-swing shadow-premium"
        >
          <VCardText class="pa-4 d-flex flex-column h-100">
            <div class="d-flex align-center gap-3 mb-3">
              <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
                <VIcon icon="tabler-scale" size="20" />
              </VAvatar>
              <span class="text-overline font-weight-black text-disabled" style="line-height: 1; letter-spacing: 0.1em;">
                Ratio de Liquidez
              </span>
            </div>
            <div class="mt-auto d-flex align-center justify-space-between">
              <span class="text-h4 font-weight-black text-primary leading-none">
                {{ balance.ratios.liquidity }}
              </span>
              <VChip
                size="small"
                variant="flat"
                :color="balance.ratios.liquidity >= 1.5 ? 'success' : 'warning'"
                class="font-weight-black px-3 rounded-lg"
              >
                {{ balance.ratios.liquidity >= 1.5 ? 'ÓPTIMO' : 'VIGILAR' }}
              </VChip>
            </div>
          </VCardText>
        </VCard>
      </VHover>
    </VCol>

    <!-- Solvencia -->
    <VCol cols="12" sm="4" class="pa-1">
      <VHover v-slot="{ isHovering, props }">
        <VCard
          v-bind="props"
          :elevation="isHovering ? 6 : 2"
          class="h-100 rounded-xl border-t-0 bg-surface transition-swing shadow-premium"
        >
          <VCardText class="pa-4 d-flex flex-column h-100">
            <div class="d-flex align-center gap-3 mb-3">
              <VAvatar color="info" variant="tonal" size="38" class="rounded-lg">
                <VIcon icon="tabler-shield-check" size="20" />
              </VAvatar>
              <span class="text-overline font-weight-black text-disabled" style="line-height: 1; letter-spacing: 0.1em;">
                Solvencia
              </span>
            </div>
            <div class="mt-auto d-flex align-center justify-space-between">
              <span class="text-h4 font-weight-black text-info leading-none">
                {{ balance.ratios.solvency }}
              </span>
              <VChip size="small" variant="flat" color="info" class="font-weight-black px-3 rounded-lg">
                NIVEL SEGURO
              </VChip>
            </div>
          </VCardText>
        </VCard>
      </VHover>
    </VCol>

    <!-- Patrimonio Neto -->
    <VCol cols="12" sm="4" class="pa-1">
      <VHover v-slot="{ isHovering, props }">
        <VCard
          v-bind="props"
          :elevation="isHovering ? 6 : 2"
          class="h-100 rounded-xl border-t-0 bg-surface transition-swing shadow-premium"
        >
          <VCardText class="pa-4 d-flex flex-column h-100">
            <div class="d-flex align-center gap-3 mb-3">
              <VAvatar :color="balance.equity >= 0 ? 'primary' : 'error'" variant="tonal" size="38" class="rounded-lg">
                <VIcon icon="tabler-building-bank" size="20" />
              </VAvatar>
              <span class="text-overline font-weight-black text-disabled" style="line-height: 1; letter-spacing: 0.1em;">
                Patrimonio Neto
              </span>
            </div>
            <div class="mt-auto">
              <span class="text-h4 font-weight-black leading-none" :class="balance.equity >= 0 ? 'text-primary' : 'text-error'">
                {{ formatCurrency(balance.equity) }}
              </span>
            </div>
          </VCardText>
        </VCard>
      </VHover>
    </VCol>
  </VRow>
</template>
