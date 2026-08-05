<script setup>
import { computed } from "vue";

const props = defineProps({
  selectedYear: {
    type: Number,
    required: true,
  },
  totalVentas: {
    type: Number,
    default: 0,
  },
  totalCompras: {
    type: Number,
    default: 0,
  },
  tramoISLR: {
    type: Object,
    required: true,
  },
  impuestoISLR: {
    type: Number,
    default: 0,
  },
  impuestoISLREnBolivares: {
    type: Number,
    default: 0,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const utilidadOperativa = computed(() => props.totalVentas - props.totalCompras);

const formatCurrency = (amount) => {
  const val = parseFloat(amount) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(val);
};
</script>

<template>
  <VSkeletonLoader
    v-if="loading"
    type="card"
    height="220"
    class="rounded-lg border-0 mt-2"
  />
  <VCard
    v-else
    class="ma-0 rounded-lg border-0 shadow-sm overflow-hidden bg-surface mt-2"
  >
    <VCardTitle class="pa-4 px-6 d-flex align-center flex-wrap gap-2">
      <div class="d-flex align-center">
        <VAvatar
          color="secondary"
          variant="tonal"
          size="32"
          class="me-3 rounded-lg"
        >
          <VIcon icon="tabler-report-analytics" size="18" />
        </VAvatar>
        <span class="text-sm font-weight-black uppercase">Consolidado Fiscal Anual</span>
      </div>
      <VSpacer />
      <VChip color="secondary" size="small" class="font-weight-black">
        EJERCICIO {{ selectedYear }}
      </VChip>
    </VCardTitle>

    <VDivider class="opacity-10" />

    <VCardText class="pa-0">
      <VRow no-gutters>
        <!-- Resumen Operativo -->
        <VCol cols="12" md="6" class="border-e">
          <div class="pa-6">
            <div class="d-flex align-center gap-2 mb-4">
              <VIcon icon="tabler-building-bank" color="primary" />
              <span class="text-subtitle-2 font-weight-black uppercase">Resumen Operativo</span>
            </div>

            <div class="d-flex flex-column gap-3">
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Total Ventas Brutas:</span>
                <span class="text-sm font-weight-black">Bs. {{ formatCurrency(totalVentas) }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Total Compras Brutas:</span>
                <span class="text-sm font-weight-black">Bs. {{ formatCurrency(totalCompras) }}</span>
              </div>
              <VDivider />
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption font-weight-black">Utilidad Operativa:</span>
                <span
                  class="text-sm font-weight-black"
                  :class="utilidadOperativa >= 0 ? 'text-success' : 'text-error'"
                >
                  Bs. {{ formatCurrency(utilidadOperativa) }}
                </span>
              </div>
            </div>
          </div>
        </VCol>

        <!-- Proyección de Impuesto -->
        <VCol cols="12" md="6">
          <div class="pa-6">
            <div class="d-flex align-center gap-2 mb-4">
              <VIcon icon="tabler-scale" color="warning" />
              <span class="text-subtitle-2 font-weight-black uppercase">Proyección de Impuesto</span>
            </div>

            <div class="d-flex flex-column gap-3">
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Tarifa Aplicable:</span>
                <VChip size="x-small" color="primary" rounded>{{ tramoISLR.tasa }}%</VChip>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Sustraendo Aplicable:</span>
                <span class="text-sm font-weight-black">{{ tramoISLR.sustraendo }} U.T.</span>
              </div>
              <VDivider />
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption font-weight-black">Total a Pagar Estimado:</span>
                <div class="text-right">
                  <div class="text-h6 font-weight-black text-warning">
                    Bs. {{ formatCurrency(impuestoISLREnBolivares) }}
                  </div>
                  <div class="text-super-xs text-disabled">
                    {{ impuestoISLR.toFixed(2) }} U.T.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}
</style>
