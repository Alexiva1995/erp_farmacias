<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { defineProps, defineEmits } from "vue";

const props = defineProps({
  selectedCurrency: String,
  selectedCurrencyTab: String,
  activeDiscountDisplay: Object,
  expirationDiscountTotal: Number,
  appliesSpecialTax: Boolean,
  specialTaxAmount: Number,
  roundedTotalAmountToPay: Number,
  payments: Array,
  remainingAmount: Number,
  showChangeAmount: Boolean,
  changeAmountInCOP: Number,
  getConvertedRemainingAmount: Function,
  getPaymentMethodLabel: Function,
  editPaymentAmount: Function,
  removePaymentFromSummary: Function,
  isLastPaymentAdded: Function,
  handlePaymentEnter: Function,
  confirmPaymentComplete: Function,
  continueButtonText: String,
  issubmitting: Boolean,
  isExternalLoading: Boolean,
  hasMissingReferences: Function,
  orderData: Object,
});

const emit = defineEmits(["complete-purchase", "close-modal", "confirm-payment", "handle-payment-enter", "remove-payment"]);
</script>

<template>
  <div class="sticky-summary">
    <VCard variant="flat" border class="rounded-xl glass-card highlight-border mb-3">
      <VCardText class="pa-4">
        <div class="text-subtitle-2 font-weight-black mb-4 uppercase letter-spacing-1 border-b pb-1 text-primary">Resumen de Pago</div>

        <!-- Descuentos -->
        <div v-if="activeDiscountDisplay" class="d-flex justify-space-between mb-1">
          <span class="text-body-2">{{ activeDiscountDisplay.label }}:</span>
          <span class="text-body-2 font-weight-medium text-error">- {{ activeDiscountDisplay.formatted }}</span>
        </div>

        <div v-if="expirationDiscountTotal > 0" class="d-flex justify-space-between mb-1">
          <span class="text-body-2">Descuento Vencimiento:</span>
          <span class="text-body-2 font-weight-medium text-error">- {{ formatCurrency(expirationDiscountTotal, selectedCurrency) }}</span>
        </div>

        <div v-if="appliesSpecialTax" class="d-flex justify-space-between mb-1">
          <span class="text-body-2">Recargo SPE (3%):</span>
          <span class="text-body-2 font-weight-medium">{{ formatCurrency(specialTaxAmount, selectedCurrency) }}</span>
        </div>

        <VDivider class="my-2" />

        <div class="d-flex justify-space-between mb-3">
          <span class="text-subtitle-1 font-weight-black">Total Compra:</span>
          <span class="text-subtitle-1 font-weight-black text-primary">{{ formatCurrency(roundedTotalAmountToPay, selectedCurrency) }}</span>
        </div>

        <!-- Lista de Pagos Agregados -->
        <div v-if="payments.filter(p => p.method).length > 0" class="mb-4 added-payments-list">
          <div 
            v-for="(payment, idx) in payments.filter(p => p.method)" 
            :key="idx" 
            class="d-flex justify-space-between align-center mb-2 payment-row-compact pb-1 border-b-dashed"
          >
            <div class="d-flex flex-column overflow-hidden">
              <span class="text-tiny font-weight-bold uppercase text-truncate">{{ getPaymentMethodLabel(payment.method, payment.currency) }}</span>
              <span v-if="payment.reference" class="text-tiny-extra text-medium-emphasis text-truncate">Ref: {{ payment.reference }}</span>
            </div>

            <div class="d-flex align-center gap-1 flex-grow-1 justify-end">
              <div v-if="payment._isInputActive" class="d-flex flex-column gap-1 flex-grow-1">
                <div class="d-flex align-center gap-1 justify-end">
                  <span class="text-tiny-extra font-weight-black text-primary uppercase">{{ payment.currency }}</span>
                  <input
                    v-model="payment.inputAmount"
                    class="payment-input-compact text-right pa-1 border rounded"
                    style="inline-size: 80px;"
                    placeholder="0.00"
                    @keydown.enter="emit('handle-payment-enter', $event, payment)"
                  />
                  <VBtn v-if="!payment._isReferenceActive" icon="tabler-check" size="x-small" color="success" variant="text" density="comfortable" @click="emit('confirm-payment', payment)" />
                  <VBtn icon="tabler-trash" size="x-small" color="error" variant="text" density="comfortable" @click="emit('remove-payment', payments.indexOf(payment))" />
                </div>
                
                <div v-if="payment._isReferenceActive" class="d-flex align-center gap-1 justify-end">
                  <input
                    v-model="payment.reference"
                    class="payment-input-compact text-right pa-1 border rounded flex-grow-1"
                    placeholder="Referencia"
                    @keydown.enter="emit('confirm-payment', payment)"
                  />
                  <VBtn icon="tabler-check" size="x-small" color="success" variant="text" density="comfortable" @click="emit('confirm-payment', payment)" />
                </div>
              </div>
              <template v-else>
                <span class="text-body-2 font-weight-black text-error">-{{ formatCurrency(payment.amount || 0, payment.currency) }}</span>
                <VBtn icon="tabler-pencil" size="x-small" color="primary" variant="text" density="comfortable" @click="props.editPaymentAmount(payment)" />
                <VBtn 
                  icon="tabler-x" 
                  size="x-small" 
                  color="error" 
                  variant="text" 
                  density="comfortable" 
                  :disabled="!props.isLastPaymentAdded(payment)" 
                  @click="props.removePaymentFromSummary(payments.indexOf(payment))" 
                />
              </template>
            </div>
          </div>
        </div>

        <div class="d-flex justify-space-between mb-2 pa-2 rounded bg-light">
          <span class="text-subtitle-2 font-weight-black">Restante:</span>
          <span class="text-subtitle-2 font-weight-black" :class="remainingAmount <= 0.01 ? 'text-success' : 'text-error'">
            {{ formatCurrency(getConvertedRemainingAmount(selectedCurrencyTab), selectedCurrencyTab) }}
          </span>
        </div>

        <div v-if="showChangeAmount" class="d-flex justify-space-between mb-3 px-2">
          <span class="text-caption font-weight-bold">Devolución:</span>
          <span class="text-caption font-weight-black text-success">{{ formatCurrency(changeAmountInCOP, 'COP') }}</span>
        </div>

        <VCardActions class="pa-0 d-flex flex-column gap-2">
          <VBtn 
            variant="flat" 
            block 
            size="large" 
            class="rounded-lg font-weight-black uppercase py-3 checkout-btn"
            :style="remainingAmount <= 0.01 && !hasMissingReferences() ? 'background: linear-gradient(135deg, #28C76F, #129e51); color: white;' : ''"
            :disabled="issubmitting || isExternalLoading || (remainingAmount > 0.01 || hasMissingReferences())"
            @click="emit('complete-purchase')"
          >
            <VIcon icon="tabler-circle-check" class="me-2" size="20" />
            {{ continueButtonText }}
          </VBtn>
          <VBtn color="secondary" variant="tonal" block size="small" class="rounded-lg font-weight-bold" @click="emit('close-modal')">
            Regresar al Pedido
          </VBtn>
        </VCardActions>
      </VCardText>
    </VCard>

    <!-- Información SPE -->
    <div v-if="orderData?.client?.is_spe" class="bg-success-lighten-5 pa-3 rounded-xl border border-success border-opacity-10">
      <div class="d-flex align-center mb-1 text-success-darken-2">
        <VIcon icon="tabler-discount-check" class="me-1" size="16" />
        <span class="font-weight-black uppercase text-tiny">Beneficio SPE</span>
      </div>
      <div class="text-tiny text-success-darken-1">
        IVA al 25% para <strong>{{ orderData.client.name }}</strong>.
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-tiny {
  font-size: 0.75rem;
}

.text-tiny-extra {
  font-size: 0.65rem;
}

.payment-row-compact {
  block-size: auto;
}

.payment-input-compact {
  font-size: 0.8rem;
  outline: none;
}

.checkout-btn {
  block-size: 48px !important;
}

.bg-light {
  background: rgba(var(--v-theme-on-surface), 0.03);
}

.added-payments-list {
  max-block-size: 150px;
  overflow-y: auto;
}
</style>
