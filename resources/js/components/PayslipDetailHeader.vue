<script setup>
import { computed } from "vue";

const props = defineProps({
  selectedPayslip: { type: Object, default: () => ({}) },
  tab: { type: String, required: true },
  mobile: { type: Boolean, default: false },
});

const emit = defineEmits(["change-tab"]);

const formatRate = (rate) => {
  return Math.round(Number(rate) || 0)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ".");
};
</script>

<template>
  <div
    class="header-premium mb-4 overflow-hidden position-relative rounded-xl border bg-surface shadow-sm"
    :class="props.mobile ? 'rounded-0' : ''"
  >
    <div class="pa-4 pa-sm-5 d-flex align-center flex-wrap justify-space-between gap-4">
      <!-- Lado Izquierdo: Avatar + Título + Status -->
      <div class="d-flex align-center gap-3">
        <VAvatar color="primary" variant="tonal" size="44" class="rounded-lg">
          <VIcon icon="tabler-file-spreadsheet" size="24" />
        </VAvatar>
        <div class="d-flex flex-column">
          <div class="d-flex align-center gap-2">
            <h1 class="text-h6 font-weight-black text-high-emphasis leading-tight mb-0">
              {{ props.selectedPayslip?.name || 'Detalles de Nómina' }}
            </h1>
            <VChip
              v-if="props.selectedPayslip?.status !== undefined"
              :color="props.selectedPayslip?.status === 1 ? 'success' : 'warning'"
              variant="flat"
              size="x-small"
              class="font-weight-black rounded px-2"
            >
              {{ props.selectedPayslip?.status === 1 ? 'FINALIZADA' : 'PENDIENTE' }}
            </VChip>
          </div>
          <span class="text-caption text-medium-emphasis font-weight-medium mt-1">
            {{ props.selectedPayslip?.period }}
          </span>
        </div>
      </div>

      <!-- Lado Derecho: Badge Tasa y Selector de Pestañas -->
      <div class="d-flex align-center flex-wrap gap-3">
        <!-- Badge de Tasa de Cambio -->
        <div class="rate-badge d-flex align-center px-3 py-1 rounded-lg border bg-surface-variant-subtle text-caption">
          <VIcon icon="tabler-currency-dollar" size="16" class="me-1 text-primary" />
          <span class="text-medium-emphasis me-1">Ref:</span>
          <span class="font-weight-bold text-high-emphasis">
            1 USD = {{ formatRate(props.selectedPayslip?.exchange_rate) }} {{ props.selectedPayslip?.currency_code }}
          </span>
        </div>

        <!-- Selector de Pestañas (Píldora) -->
        <div class="tab-pill-container pa-1 rounded-lg border bg-surface-variant-subtle d-flex gap-1">
          <VBtn
            size="small"
            :variant="props.tab === 'legal' ? 'elevated' : 'text'"
            :color="props.tab === 'legal' ? 'primary' : 'default'"
            class="rounded-md font-weight-black px-4 text-xs"
            @click="emit('change-tab', 'legal')"
          >
            LEGAL (Bs)
          </VBtn>
          <VBtn
            size="small"
            :variant="props.tab === 'full' ? 'elevated' : 'text'"
            :color="props.tab === 'full' ? 'primary' : 'default'"
            class="rounded-md font-weight-black px-4 text-xs"
            @click="emit('change-tab', 'full')"
          >
            COMPLETA (COP)
          </VBtn>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.header-premium {
  background: linear-gradient(
    135deg,
    rgba(var(--v-theme-primary), 0.04) 0%,
    rgba(var(--v-theme-surface), 1) 100%
  ) !important;
}

.rate-badge {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
}

.leading-tight {
  line-height: 1.25 !important;
}
</style>
