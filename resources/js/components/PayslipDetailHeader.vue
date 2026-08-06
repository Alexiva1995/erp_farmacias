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
    class="header-premium mb-6 overflow-hidden position-relative rounded-lg"
    :class="props.mobile ? 'rounded-0' : ''"
  >
    <div class="header-overlay pa-6">
      <div class="d-flex align-center flex-wrap gap-4">
        <VAvatar color="white" variant="flat" size="64" class="rounded-lg shadow-lg">
          <VIcon icon="tabler-file-spreadsheet" size="32" color="primary" />
        </VAvatar>
        <div class="flex-grow-1">
          <div class="d-flex align-center gap-2 mb-1">
            <h1 class="text-h4 font-weight-black text-white leading-tight" style="color: #ffffff !important;">
              {{ props.selectedPayslip?.name || 'Cargando Detalles...' }}
            </h1>
            <VChip
              v-if="props.selectedPayslip?.status !== undefined"
              :color="props.selectedPayslip?.status === 1 ? 'success' : 'warning'"
              variant="flat"
              size="x-small"
              class="font-weight-black rounded px-3"
            >
              {{ props.selectedPayslip?.status === 1 ? 'FINALIZADA' : 'PENDIENTE' }}
            </VChip>
          </div>
          <div class="d-flex align-center flex-wrap gap-4 text-white">
            <span class="d-flex align-center text-xs font-weight-bold" style="color: #ffffff !important;">
              <VIcon icon="tabler-calendar" size="14" class="me-1 text-white" />
              {{ props.selectedPayslip?.period }}
            </span>
            <span class="d-flex align-center text-xs font-weight-bold" style="color: #ffffff !important;">
              <VIcon icon="tabler-currency-dollar" size="14" class="me-1 text-white" />
              Ref: 1 USD = {{ formatRate(props.selectedPayslip?.exchange_rate) }} {{ props.selectedPayslip?.currency_code }}
            </span>
          </div>
        </div>
        
        <!-- Selector de Pestañas Premium (Píldora) -->
        <div class="tab-pill-container bg-surface-dark-pill pa-1 rounded-pill d-flex gap-1">
          <VBtn
            size="small"
            :variant="props.tab === 'legal' ? 'flat' : 'text'"
            color="white"
            class="rounded-pill font-weight-black px-6"
            :class="props.tab === 'legal' ? 'text-primary' : 'text-white'"
            @click="emit('change-tab', 'legal')"
          >
            LEGAL (Bs)
          </VBtn>
          <VBtn
            size="small"
            :variant="props.tab === 'full' ? 'flat' : 'text'"
            color="white"
            class="rounded-pill font-weight-black px-6"
            :class="props.tab === 'full' ? 'text-primary' : 'text-white'"
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
  background: linear-gradient(135deg, #7A0099 0%, #4A0066 100%);
  min-height: 140px;
}

.header-overlay {
  background: rgba(0, 0, 0, 0.25);
  height: 100%;
}

.bg-surface-dark-pill {
  background-color: rgba(0, 0, 0, 0.35);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.shadow-lg {
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5) !important;
}

.leading-tight {
  line-height: 1.25;
}
</style>
