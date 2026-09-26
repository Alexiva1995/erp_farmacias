<script setup>
// Props tipados para el resumen financiero
const props = defineProps({
  invoice: {
    type: Object,
    required: true,
  },
  isApprovalMode: {
    type: Boolean,
    default: false,
  },
  isEditableMode: {
    type: Boolean,
    default: false,
  },
  isEditMode: {
    type: Boolean,
    default: false,
  },
  selectedSupplierDiscountId: {
    type: [Number, String, null],
    default: null,
  },
  selectedPaymentRuleId: {
    type: [Number, String, null],
    default: null,
  },
  formattedSupplierDiscounts: {
    type: Array,
    default: () => [],
  },
  formattedPaymentRules: {
    type: Array,
    default: () => [],
  },
  totalWithDiscount: {
    type: Number,
    default: 0,
  },
  editableDetailsTaxAmount: {
    type: Number,
    default: 0,
  },
  isTaxAmountMismatch: {
    type: Boolean,
    default: false,
  },
  formatCurrency: {
    type: Function,
    required: true,
  },
  formatNumber: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits([
  'update:selectedSupplierDiscountId',
  'update:selectedPaymentRuleId',
])
</script>

<template>
  <VCardText class="totals-section pb-6 pt-4 bg-var-theme-background">
    <VRow>
      <!-- Tarjeta 1: Desglose Fiscal Completo (Exento, Base Imponible e IVA) -->
      <VCol cols="12" md="4">
        <VCard variant="outlined" class="h-100 summary-card glassmorphism">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-subtitle-2 text-medium-emphasis">Total Exento (0%)</span>
              <span class="text-body-1 font-weight-bold">{{ formatCurrency(invoice.exempt_amount, invoice.currency) }}</span>
            </div>
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-subtitle-2 text-medium-emphasis">Base Imponible (16%)</span>
              <span class="text-body-1 font-weight-bold">{{ formatCurrency(invoice.taxable_base, invoice.currency) }}</span>
            </div>
            <div class="d-flex justify-space-between align-center border-t pt-2">
              <div class="d-flex align-center">
                <VTooltip
                  v-if="isTaxAmountMismatch && isEditMode"
                  text="El monto de IVA calculado difiere del original."
                >
                  <template #activator="{ props: tipProps }">
                    <VIcon v-bind="tipProps" icon="tabler-alert-circle" color="warning" size="16" class="me-1" />
                  </template>
                </VTooltip>
                <span class="text-subtitle-2 text-medium-emphasis">Impuesto IVA (16%)</span>
              </div>
              <div class="text-right">
                <span class="text-body-1 font-weight-bold">{{ formatCurrency(invoice.tax_amount, invoice.currency) }}</span>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Tarjeta 2: Descuentos y Condiciones Comerciales -->
      <VCol cols="12" md="4">
        <VCard variant="outlined" class="h-100 summary-card glassmorphism">
          <VCardText class="d-flex flex-column justify-center h-100">
            <!-- Descuentos Editable Mode -->
            <div v-if="isEditableMode">
              <span class="text-subtitle-2 font-weight-bold text-high-emphasis mb-1 d-block">Descuento del Proveedor</span>
              <VSelect
                :model-value="selectedSupplierDiscountId"
                :items="formattedSupplierDiscounts"
                item-title="displayText"
                item-value="id"
                label="Seleccionar Descuento"
                placeholder="Sin descuento aplicado"
                variant="outlined"
                density="compact"
                clearable
                hide-details
                class="mt-1"
                @update:model-value="emit('update:selectedSupplierDiscountId', $event)"
              />
            </div>
            <!-- Descuentos Approval Mode -->
            <div v-else-if="isApprovalMode">
              <span class="text-subtitle-2 font-weight-bold text-high-emphasis mb-1 d-block">Condición de Pronto Pago</span>
              <VSelect
                :model-value="selectedPaymentRuleId"
                :items="formattedPaymentRules"
                item-title="displayText"
                item-value="id"
                label="Seleccionar Regla"
                placeholder="Sin descuento aplicado"
                variant="outlined"
                density="compact"
                clearable
                hide-details
                class="mt-1"
                @update:model-value="emit('update:selectedPaymentRuleId', $event)"
              />
            </div>
            <!-- Modo Lectura -->
            <div v-else class="text-center py-2">
              <VIcon icon="tabler-discount-check" size="28" color="secondary" class="mb-1" />
              <div class="text-subtitle-2 font-weight-medium text-medium-emphasis">Condiciones Comerciales</div>
              <div class="text-caption text-disabled">Sin descuentos comerciales adicionales aplicados</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Tarjeta 3: Totales Principales -->
      <VCol cols="12" md="4">
        <VCard color="primary" variant="tonal" class="h-100 summary-card border-primary-variant">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-subtitle-1 font-weight-black">Total Factura</span>
              <span class="text-h5 font-weight-black text-primary">{{ formatCurrency(invoice.total_amount, invoice.currency) }}</span>
            </div>
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-subtitle-2 opacity-80">Total USD Referencial</span>
              <span class="text-subtitle-1 font-weight-bold text-primary">{{ formatCurrency(invoice.total_usd, "USD") }}</span>
            </div>
            <div class="d-flex justify-space-between align-center text-caption opacity-80 border-t pt-1">
              <span>Tasa BCV Aplicada</span>
              <span class="font-weight-bold">{{ formatNumber(invoice.exchange_rate) }} Bs/$</span>
            </div>

            <VDivider v-if="(isApprovalMode && selectedPaymentRuleId) || (isEditableMode && selectedSupplierDiscountId)" class="my-2" />
            <div v-if="(isApprovalMode && selectedPaymentRuleId) || (isEditableMode && selectedSupplierDiscountId)" class="d-flex justify-space-between align-center text-success mt-2">
              <span class="text-subtitle-2 font-weight-bold">Con Descuento</span>
              <span class="text-h6 font-weight-bold">{{ formatCurrency(totalWithDiscount, invoice.currency) }}</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </VCardText>
</template>
