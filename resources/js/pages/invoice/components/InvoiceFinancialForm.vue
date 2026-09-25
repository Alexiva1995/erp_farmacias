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
    <!-- SECCIÓN 3: Desglose de Montos e Impuestos con Tarjeta de Resumen -->
    <div class="d-flex align-center gap-2 mb-3">
      <VIcon icon="tabler-calculator" size="20" color="primary" />
      <span class="text-subtitle-1 font-weight-bold">3. Desglose de Montos e Impuestos</span>
    </div>

    <VRow density="compact" class="align-stretch">
      <!-- Columna de Entradas de Montos e Impuestos -->
      <VCol cols="12" md="7" lg="8">
        <VCard variant="flat" class="pa-0">
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
        </VCard>
      </VCol>

      <!-- Columna: Tarjeta de Resumen Financiero (Total Summary Card) -->
      <VCol cols="12" md="5" lg="4">
        <VCard variant="outlined" class="pa-4 rounded-lg h-100 d-flex flex-column justify-space-between" style="background-color: rgb(var(--v-theme-surface)); border-color: rgba(var(--v-theme-primary), 0.25);">
          <div>
            <div class="d-flex align-center justify-space-between mb-3 pb-2 border-b">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-receipt-2" color="primary" size="20" />
                <span class="text-subtitle-2 font-weight-bold text-uppercase">Resumen Financiero</span>
              </div>
              <VChip size="x-small" color="primary" variant="tonal" class="font-weight-medium">
                {{ formData.currency }}
              </VChip>
            </div>

            <!-- Desglose de Subtotales -->
            <div class="d-flex justify-space-between text-caption py-1">
              <span class="text-medium-emphasis">Monto Exento:</span>
              <span class="font-weight-medium">{{ getCurrencySymbol }} {{ Number(formData.exempt_amount || 0).toFixed(2) }}</span>
            </div>
            <div class="d-flex justify-space-between text-caption py-1">
              <span class="text-medium-emphasis">Base Imponible:</span>
              <span class="font-weight-medium">{{ getCurrencySymbol }} {{ Number(formData.taxable_base || 0).toFixed(2) }}</span>
            </div>
            <div class="d-flex justify-space-between text-caption py-1">
              <span class="text-medium-emphasis">Impuesto IVA (16%):</span>
              <span class="font-weight-medium">{{ getCurrencySymbol }} {{ computedTaxAmount.toFixed(2) }}</span>
            </div>
          </div>

          <VDivider class="my-3" />

          <div>
            <!-- Total de la Factura Principal -->
            <div class="d-flex align-baseline justify-space-between mb-2">
              <span class="text-body-2 font-weight-bold text-medium-emphasis">TOTAL FACTURA:</span>
              <span class="text-h5 font-weight-bold text-primary">
                {{ getCurrencySymbol }} {{ computedTotalAmount.toFixed(2) }}
              </span>
            </div>

            <!-- Referencia en USD -->
            <div v-if="formData.currency !== 'USD'" class="pa-2 rounded d-flex align-center justify-space-between" style="background-color: rgba(var(--v-theme-success), 0.08);">
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-currency-dollar" color="success" size="18" />
                <span class="text-caption font-weight-medium text-success">Total Ref. USD:</span>
              </div>
              <span class="text-subtitle-1 font-weight-bold text-success">
                $ {{ computedTotalUsd.toFixed(2) }}
              </span>
            </div>
            <div v-else class="text-caption text-medium-emphasis text-right italic">
              Moneda base oficial en USD
            </div>
          </div>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

