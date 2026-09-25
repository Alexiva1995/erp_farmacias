<script setup>
const props = defineProps({
  formData: { type: Object, required: true },
  currencyOptions: { type: Array, required: true },
  shouldShowExchangeRate: { type: Boolean, required: true },
  getCurrencySymbol: { type: String, required: true },
  computedTaxAmount: { type: Number, required: true },
  validationErrors: { type: Object, default: () => ({}) },
});
</script>

<template>
  <div>
    <!-- SECCIÓN 3: Desglose de Montos e Impuestos -->
    <div class="d-flex align-center gap-2 mb-3">
      <VIcon icon="tabler-calculator" size="20" color="primary" />
      <span class="text-subtitle-1 font-weight-bold">3. Desglose de Montos e Impuestos</span>
    </div>

    <VRow density="compact">
      <VCol cols="12" sm="6">
        <VSelect
          v-model="formData.currency"
          :items="currencyOptions"
          label="Moneda *"
          item-title="title"
          item-value="value"
          variant="outlined"
          density="compact"
          prepend-inner-icon="tabler-coin"
          :error-messages="validationErrors.currency"
        />
      </VCol>

      <VCol v-if="shouldShowExchangeRate" cols="12" sm="6">
        <VTextField
          v-model.number="formData.exchange_rate"
          label="Tasa de Cambio *"
          type="number"
          step="0.0001"
          placeholder="0.00"
          variant="outlined"
          density="compact"
          prepend-inner-icon="tabler-trending-up"
          :error-messages="validationErrors.exchange_rate"
        />
      </VCol>

      <VCol cols="12" sm="4">
        <VTextField
          v-model.number="formData.exempt_amount"
          label="Monto Exento"
          type="number"
          step="0.01"
          placeholder="0.00"
          :prefix="getCurrencySymbol"
          variant="outlined"
          density="compact"
          prepend-inner-icon="tabler-receipt-tax"
          :error-messages="validationErrors.exempt_amount"
        />
      </VCol>

      <VCol cols="12" sm="4">
        <VTextField
          v-model.number="formData.taxable_base"
          label="Base Imponible (16%)"
          type="number"
          step="0.01"
          placeholder="0.00"
          :prefix="getCurrencySymbol"
          variant="outlined"
          density="compact"
          prepend-inner-icon="tabler-percentage"
          :error-messages="validationErrors.taxable_base"
        />
      </VCol>

      <VCol cols="12" sm="4">
        <VTextField
          :model-value="computedTaxAmount"
          label="IVA 16% (Auto)"
          type="number"
          :prefix="getCurrencySymbol"
          readonly
          variant="filled"
          density="compact"
          prepend-inner-icon="tabler-math-symbols"
          hint="16% de la base"
          persistent-hint
          :error-messages="validationErrors.tax_amount"
        />
      </VCol>
    </VRow>
  </div>
</template>
