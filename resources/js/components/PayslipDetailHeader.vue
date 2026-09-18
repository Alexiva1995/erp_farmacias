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

const formattedTitle = computed(() => {
  const period = props.selectedPayslip?.period || props.selectedPayslip?.name || '';
  // Limpiar si dice "Nómina (" o fechas repetidas
  const clean = period.replace(/^Nómina\s*\(/i, '').replace(/\)$/, '').trim();
  return clean || 'Período de Nómina';
});
</script>

<template>
  <div
    class="header-premium mb-4 overflow-hidden position-relative rounded-sm border bg-surface shadow-sm"
    :class="props.mobile ? 'rounded-0' : ''"
  >
    <div class="px-3 py-2 px-sm-4 py-sm-2 d-flex align-center flex-wrap justify-space-between gap-2">
      <!-- Lado Izquierdo: Botón Volver + Avatar + Período + Status (Todo en una sola línea) -->
      <div class="d-flex align-center gap-2 flex-wrap">
        <IconBtn
          to="/finances/payslips"
          variant="tonal"
          color="secondary"
          size="32"
          class="rounded-sm flex-shrink-0"
        >
          <VIcon icon="tabler-arrow-left" size="18" />
          <VTooltip activator="parent" location="top">Volver a Nóminas</VTooltip>
        </IconBtn>

        <VAvatar color="primary" variant="tonal" size="32" class="rounded-sm flex-shrink-0">
          <VIcon icon="tabler-file-spreadsheet" size="18" />
        </VAvatar>

        <span class="text-subtitle-2 font-weight-bold text-high-emphasis leading-none">
          {{ formattedTitle }}
        </span>

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

      <!-- Lado Derecho: Tasa limpia (sin 'Ref:') y Selector de Pestañas -->
      <div class="d-flex align-center flex-wrap gap-2">
        <!-- Badge de Tasa de Cambio limpia -->
        <div class="rate-badge d-flex align-center px-2 py-1 rounded-sm border bg-surface-variant-subtle text-caption">
          <VIcon icon="tabler-currency-dollar" size="14" class="me-1 text-primary" />
          <span class="font-weight-bold text-high-emphasis">
            1 USD = {{ formatRate(props.selectedPayslip?.exchange_rate) }} {{ props.selectedPayslip?.currency_code }}
          </span>
        </div>

        <!-- Selector de Pestañas -->
        <div class="tab-pill-container pa-1 rounded-sm border bg-surface-variant-subtle d-flex gap-1">
          <VBtn
            size="x-small"
            :variant="props.tab === 'legal' ? 'elevated' : 'text'"
            :color="props.tab === 'legal' ? 'primary' : 'default'"
            class="rounded-sm font-weight-black px-3 text-xs"
            height="26"
            @click="emit('change-tab', 'legal')"
          >
            LEGAL (Bs)
          </VBtn>
          <VBtn
            size="x-small"
            :variant="props.tab === 'full' ? 'elevated' : 'text'"
            :color="props.tab === 'full' ? 'primary' : 'default'"
            class="rounded-sm font-weight-black px-3 text-xs"
            height="26"
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
  border-radius: 5px !important;
  background: linear-gradient(
    135deg,
    rgba(var(--v-theme-primary), 0.04) 0%,
    rgba(var(--v-theme-surface), 1) 100%
  ) !important;
}

.rate-badge {
  border-radius: 5px !important;
  background-color: rgba(var(--v-theme-on-surface), 0.03);
}

.tab-pill-container {
  border-radius: 5px !important;
}

.tab-pill-container :deep(.v-btn) {
  border-radius: 4px !important;
}

.leading-tight {
  line-height: 1.25 !important;
}
</style>
