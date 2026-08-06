<script setup>
const props = defineProps({
  formData: { type: Object, required: true },
  currencyOptions: { type: Array, required: true },
  shouldShowExchangeRate: { type: Boolean, required: true },
  getCurrencySymbol: { type: String, required: true },
  computedTaxAmount: { type: Number, required: true },
  computedTotalAmount: { type: Number, required: true },
  computedTotalUsd: { type: Number, required: true },
  validationErrors: { type: Object, default: () => ({}) },
});
</script>

<template>
  <div>
    <!-- Bloque Financiero: Monedas, Tasas y Bases Imponibles -->
    <VRow density="compact" class="align-center mb-2">
      <VCol cols="12" md="2">
        <VSelect
          v-model="formData.currency"
          :items="currencyOptions"
          label="Moneda"
          item-title="title"
          item-value="value"
          variant="solo-filled"
          flat
          :error-messages="validationErrors.currency"
        >
          <template #prepend-inner>
            <VIcon icon="tabler-coin" color="primary" size="20" />
          </template>
        </VSelect>
      </VCol>
      
      <VCol v-if="shouldShowExchangeRate" cols="12" md="2">
        <VExpandTransition>
          <VTextField
            v-model.number="formData.exchange_rate"
            label="Tasa"
            type="number"
            variant="outlined"
            color="primary"
            :error-messages="validationErrors.exchange_rate"
          >
            <template #prepend-inner>
              <VIcon icon="tabler-trending-up" size="20" />
            </template>
          </VTextField>
        </VExpandTransition>
      </VCol>

      <VCol cols="12" md="2">
        <VTextField
          v-model.number="formData.exempt_amount"
          label="Exento"
          type="number"
          :prefix="getCurrencySymbol"
          variant="underlined"
          :error-messages="validationErrors.exempt_amount"
        />
      </VCol>

      <VCol cols="12" md="3">
        <VTextField
          v-model.number="formData.taxable_base"
          label="Base (16%)"
          type="number"
          :prefix="getCurrencySymbol"
          variant="underlined"
          :error-messages="validationErrors.taxable_base"
        />
      </VCol>

      <VCol cols="12" md="3">
        <VTextField
          :model-value="computedTaxAmount"
          label="IVA (Auto)"
          type="number"
          :prefix="getCurrencySymbol"
          readonly
          variant="underlined"
          :error-messages="validationErrors.tax_amount"
        />
      </VCol>
    </VRow>

    <!-- Tarjetas Resumen de Totales -->
    <VRow density="compact" class="mt-4">
      <VCol cols="12" md="6">
        <VAlert
          color="primary"
          variant="outlined"
          class="pa-4 d-flex justify-space-between align-center"
        >
          <span class="text-subtitle-2 text-medium-emphasis">Total Factura ({{ formData.currency }})</span>
          <span class="text-h5 font-weight-bold text-primary">{{ getCurrencySymbol }} {{ computedTotalAmount }}</span>
        </VAlert>
      </VCol>
      
      <VCol cols="12" md="6">
        <VAlert
          color="success"
          variant="outlined"
          class="pa-4 d-flex justify-space-between align-center"
        >
          <span class="text-subtitle-2 text-medium-emphasis">Referencia Total (USD)</span>
          <span class="text-h5 font-weight-bold text-success">$ {{ computedTotalUsd }}</span>
        </VAlert>
      </VCol>
    </VRow>
  </div>
</template>
