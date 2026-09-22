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
  deducciones: {
    type: Number,
    default: 0,
  },
  noDeducibles: {
    type: Number,
    default: 0,
  },
  baseImponible: {
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
  retencionesAcumuladas: {
    type: Number,
    default: 0,
  },
  anticiposCE: {
    type: Number,
    default: 0,
  },
  isCeEnabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const utilidadOperativa = computed(() => props.totalVentas - props.totalCompras - props.deducciones);

// Créditos y anticipos totales deducibles del impuesto
const totalCreditosAnticipos = computed(() => {
  return props.retencionesAcumuladas + (props.isCeEnabled ? props.anticiposCE : 0);
});

// Impuesto Neto a Liquidar
const impuestoNetoALiquidar = computed(() => {
  return Math.max(0, props.impuestoISLREnBolivares - totalCreditosAnticipos.value);
});

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
    height="280"
    class="rounded-lg border-0 mt-2"
  />
  <VCard
    v-else
    class="ma-0 rounded-lg border-0 shadow-sm overflow-hidden bg-surface mt-2"
  >
    <VCardTitle class="pa-4 px-6 d-flex align-center flex-wrap gap-2">
      <div class="d-flex align-center">
        <VAvatar
          color="primary"
          variant="tonal"
          size="32"
          class="me-3 rounded-lg"
        >
          <VIcon icon="tabler-file-analytics" size="18" />
        </VAvatar>
        <span class="text-sm font-weight-black uppercase">Consolidado & Conciliación Fiscal</span>
      </div>
      <VSpacer />
      <div class="d-flex align-center gap-2">
        <VChip v-if="isCeEnabled" color="warning" size="small" variant="tonal" class="font-weight-black">
          SUJETO PASIVO ESPECIAL (CE)
        </VChip>
        <VChip color="primary" size="small" class="font-weight-black">
          EJERCICIO {{ selectedYear }}
        </VChip>
      </div>
    </VCardTitle>

    <VDivider class="opacity-10" />

    <VCardText class="pa-0">
      <VRow no-gutters>
        <!-- Resumen Operativo y Determinación de Renta -->
        <VCol cols="12" md="6" class="border-e">
          <div class="pa-6">
            <div class="d-flex align-center gap-2 mb-4">
              <VIcon icon="tabler-building-bank" color="primary" size="20" />
              <span class="text-subtitle-2 font-weight-black uppercase">Determinación de Renta Neta</span>
            </div>

            <div class="d-flex flex-column gap-3">
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Renta Bruta (Ingresos Fiscales):</span>
                <span class="text-sm font-weight-black text-success">Bs. {{ formatCurrency(totalVentas) }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">(-) Costos de Ventas (Compras con Factura):</span>
                <span class="text-sm font-weight-black text-error">- Bs. {{ formatCurrency(totalCompras) }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">(-) Gastos Operativos Deducibles:</span>
                <span class="text-sm font-weight-black text-error">- Bs. {{ formatCurrency(deducciones) }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-disabled">Gastos No Deducibles (Informativo):</span>
                <span class="text-xs font-weight-bold text-disabled">Bs. {{ formatCurrency(noDeducibles) }}</span>
              </div>

              <VDivider class="my-1 opacity-20" />

              <div class="d-flex justify-space-between align-center">
                <span class="text-caption font-weight-black text-primary">Renta Neta Gravable (Base Imponible):</span>
                <span class="text-sm font-weight-black text-primary">
                  Bs. {{ formatCurrency(baseImponible) }}
                </span>
              </div>
            </div>
          </div>
        </VCol>

        <!-- Proyección, Retenciones y Liquidación Final -->
        <VCol cols="12" md="6">
          <div class="pa-6">
            <div class="d-flex align-center gap-2 mb-4">
              <VIcon icon="tabler-scale" color="warning" size="20" />
              <span class="text-subtitle-2 font-weight-black uppercase">Liquidación y Conciliación Final</span>
            </div>

            <div class="d-flex flex-column gap-3">
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Tarifa Legal Aplicable (Tarifa N° 2):</span>
                <div class="d-flex align-center gap-1">
                  <VChip size="x-small" color="primary" variant="tonal" class="font-weight-black">{{ tramoISLR.tasa }}%</VChip>
                  <span class="text-super-xs text-medium-emphasis">Sustraendo: {{ tramoISLR.sustraendo }} U.T.</span>
                </div>
              </div>

              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">Impuesto Determinado:</span>
                <div class="text-right">
                  <span class="text-sm font-weight-black">Bs. {{ formatCurrency(impuestoISLREnBolivares) }}</span>
                  <div class="text-super-xs text-disabled">({{ impuestoISLR.toFixed(2) }} U.T.)</div>
                </div>
              </div>

              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">(-) Retenciones ISLR Acumuladas:</span>
                <span class="text-sm font-weight-bold text-secondary">- Bs. {{ formatCurrency(retencionesAcumuladas) }}</span>
              </div>

              <div v-if="isCeEnabled" class="d-flex justify-space-between align-center">
                <span class="text-caption text-medium-emphasis">(-) Anticipos Quincenales ISLR (CE):</span>
                <span class="text-sm font-weight-bold text-secondary">- Bs. {{ formatCurrency(anticiposCE) }}</span>
              </div>

              <VDivider class="my-1 opacity-20" />

              <div class="d-flex justify-space-between align-center">
                <div>
                  <span class="text-caption font-weight-black text-warning">TOTAL NETO A LIQUIDAR:</span>
                  <div class="text-super-xs text-disabled">Monto final a pagar al SENIAT</div>
                </div>
                <div class="text-right">
                  <div class="text-h6 font-weight-black text-warning">
                    Bs. {{ formatCurrency(impuestoNetoALiquidar) }}
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
