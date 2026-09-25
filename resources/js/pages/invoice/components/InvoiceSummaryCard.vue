<script setup>
const props = defineProps({
  formData: { type: Object, required: true },
  getCurrencySymbol: { type: String, required: true },
  computedTaxAmount: { type: Number, required: true },
  computedTotalAmount: { type: Number, required: true },
  computedTotalUsd: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  isEditMode: { type: Boolean, default: false },
});

const emit = defineEmits(["submit", "cancel"]);
</script>

<template>
  <VCard
    variant="outlined"
    class="rounded-lg sticky-card pa-4"
    style="background-color: rgb(var(--v-theme-surface)); border-color: rgba(var(--v-theme-primary), 0.25);"
  >
    <div class="d-flex align-center justify-space-between mb-3 pb-2 border-b">
      <div class="d-flex align-center gap-2">
        <VIcon icon="tabler-receipt-2" color="primary" size="22" />
        <span class="text-subtitle-1 font-weight-bold text-uppercase">Resumen Financiero</span>
      </div>
      <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
        {{ formData.currency }}
      </VChip>
    </div>

    <!-- Desglose de Subtotales -->
    <div class="d-flex flex-column gap-1 mb-2">
      <div class="d-flex justify-space-between text-body-2 py-1">
        <span class="text-medium-emphasis">Monto Exento:</span>
        <span class="font-weight-medium">{{ getCurrencySymbol }} {{ Number(formData.exempt_amount || 0).toFixed(2) }}</span>
      </div>
      <div class="d-flex justify-space-between text-body-2 py-1">
        <span class="text-medium-emphasis">Base Imponible (16%):</span>
        <span class="font-weight-medium">{{ getCurrencySymbol }} {{ Number(formData.taxable_base || 0).toFixed(2) }}</span>
      </div>
      <div class="d-flex justify-space-between text-body-2 py-1">
        <span class="text-medium-emphasis">Impuesto IVA (16%):</span>
        <span class="font-weight-medium">{{ getCurrencySymbol }} {{ computedTaxAmount.toFixed(2) }}</span>
      </div>
    </div>

    <VDivider class="my-3" />

    <!-- Total de la Factura Principal -->
    <div class="mb-3">
      <div class="text-caption font-weight-bold text-medium-emphasis text-uppercase mb-1">
        Total Factura ({{ formData.currency }})
      </div>
      <div class="text-h4 font-weight-bold text-primary">
        {{ getCurrencySymbol }} {{ computedTotalAmount.toFixed(2) }}
      </div>
    </div>

    <!-- Referencia en USD -->
    <div
      v-if="formData.currency !== 'USD'"
      class="pa-3 rounded-lg d-flex align-center justify-space-between mb-4"
      style="background-color: rgba(var(--v-theme-success), 0.08); border: 1px solid rgba(var(--v-theme-success), 0.2);"
    >
      <div class="d-flex align-center gap-2">
        <VIcon icon="tabler-currency-dollar" color="success" size="20" />
        <div>
          <div class="text-caption font-weight-medium text-success">Total Ref. USD</div>
          <div v-if="formData.exchange_rate > 0" class="text-caption text-medium-emphasis" style="font-size: 0.7rem;">
            Tasa: Bs {{ Number(formData.exchange_rate).toFixed(2) }}
          </div>
        </div>
      </div>
      <span class="text-h6 font-weight-bold text-success">
        $ {{ computedTotalUsd.toFixed(2) }}
      </span>
    </div>
    <div v-else class="text-caption text-medium-emphasis mb-4 italic">
      Factura cotizada en Dólares (USD)
    </div>

    <VDivider class="my-3" />

    <!-- Botones de Acción (CTA) Integrados -->
    <div class="d-flex flex-column gap-2 mt-2">
      <VBtn
        color="primary"
        variant="flat"
        size="large"
        block
        :loading="loading"
        prepend-icon="tabler-device-floppy"
        @click="emit('submit')"
      >
        {{ isEditMode ? "Actualizar Factura" : "Registrar Factura" }}
      </VBtn>

      <VBtn
        color="secondary"
        variant="outlined"
        block
        prepend-icon="tabler-x"
        @click="emit('cancel')"
        :disabled="loading"
      >
        Cancelar
      </VBtn>
    </div>
  </VCard>
</template>

<style scoped>
.sticky-card {
  position: sticky;
  top: 1.5rem;
}
</style>
