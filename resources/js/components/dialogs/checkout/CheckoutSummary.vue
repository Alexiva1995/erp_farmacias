<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";


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
  changeAmount: Number,
  changeAmountInCop: Number,
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
      <VCardText class="pa-4 d-flex flex-column gap-3">

        <!-- BLOQUE 1: Detalle del Cobro -->
        <div class="summary-section">
          <div class="text-caption font-weight-bold uppercase letter-spacing-1 text-primary mb-3 d-flex align-center gap-1">
            <VIcon icon="tabler-receipt" size="16" />
            <span>Detalle del Cobro</span>
          </div>

          <!-- Descuentos y metadatos secundarios -->
          <div v-if="activeDiscountDisplay" class="d-flex justify-space-between align-center mb-1.5">
            <span class="text-caption text-medium-emphasis">{{ activeDiscountDisplay.label }}:</span>
            <span class="text-caption font-weight-bold text-error">- {{ activeDiscountDisplay.formatted }}</span>
          </div>

          <div v-if="expirationDiscountTotal > 0" class="d-flex justify-space-between align-center mb-1.5">
            <span class="text-caption text-medium-emphasis">Desc. Vencimiento (Incluido):</span>
            <span class="text-caption font-weight-bold text-error">- {{ formatCurrency(expirationDiscountTotal, selectedCurrency) }}</span>
          </div>

          <div v-if="appliesSpecialTax" class="d-flex justify-space-between align-center mb-1.5">
            <span class="text-caption text-medium-emphasis">Recargo SPE (3%):</span>
            <span class="text-caption font-weight-bold">{{ formatCurrency(specialTaxAmount, selectedCurrency) }}</span>
          </div>

          <!-- Total Compra -->
          <div class="d-flex justify-space-between align-center py-2 px-3 rounded-lg bg-grey-lighten-4 mb-3 border">
            <span class="text-body-1 font-weight-bold text-high-emphasis">Total Compra:</span>
            <span class="text-h6 font-weight-bold text-primary">{{ formatCurrency(roundedTotalAmountToPay, selectedCurrency) }}</span>
          </div>

          <!-- Lista de Pagos Agregados -->
          <div v-if="payments.filter(p => p.method).length > 0" class="added-payments-list d-flex flex-column gap-2 mb-2">
            <div
              v-for="(payment, idx) in payments.filter(p => p.method)"
              :key="idx"
              class="pa-3 rounded-lg border bg-surface d-flex flex-column payment-card-item"
            >
              <!-- Fila principal: ícono + nombre + referencia inline + monto + acciones -->
              <div class="d-flex justify-space-between align-center">

                <!-- Izquierda: ícono billetera (me-2) + nombre + ref inline debajo -->
                <div class="d-flex align-center overflow-hidden">
                  <VIcon icon="tabler-wallet" size="16" class="text-primary shrink-0 me-2" />
                  <div class="d-flex flex-column overflow-hidden">
                    <span class="text-caption font-weight-bold text-high-emphasis uppercase text-truncate">
                      {{ getPaymentMethodLabel(payment.method, payment.currency) }}
                    </span>
                    <!-- Referencia inline (solo número, sin fila extra) -->
                    <span v-if="!payment._isInputActive && payment.reference" class="payment-ref-inline">
                      # {{ payment.reference }}
                    </span>
                  </div>
                </div>

                <!-- Derecha: monto + editar (warning/amarillo) + borrar (error/rojo) -->
                <div v-if="!payment._isInputActive" class="d-flex align-center gap-1 shrink-0 ms-2">
                  <span
                    class="text-caption font-weight-bold text-error cursor-pointer px-1 rounded hover-editable-amount"
                    title="Clic para editar monto"
                    @click="props.editPaymentAmount(payment)"
                  >
                    -{{ formatCurrency(payment.amount || 0, payment.currency) }}
                  </span>

                  <!-- Editar: amarillo/warning — mismo size que billetera -->
                  <VBtn
                    icon="tabler-pencil"
                    size="26"
                    color="warning"
                    variant="text"
                    density="comfortable"
                    class="rounded-circle"
                    title="Editar monto"
                    @click="props.editPaymentAmount(payment)"
                  />

                  <!-- Borrar: rojo/error — mismo size que billetera -->
                  <VBtn
                    icon="tabler-trash"
                    size="26"
                    color="error"
                    variant="text"
                    density="comfortable"
                    class="rounded-circle"
                    title="Eliminar método"
                    :disabled="!props.isLastPaymentAdded(payment)"
                    @click="props.removePaymentFromSummary(payments.indexOf(payment))"
                  />
                </div>
              </div>

              <!-- Formulario de edición: monto + referencia -->
              <div v-if="payment._isInputActive" class="d-flex flex-column gap-2.5 pt-2 mt-1 border-t">
                <!-- Campo de monto -->
                <div class="d-flex align-center gap-2">
                  <span class="text-caption font-weight-bold text-primary uppercase shrink-0" style="min-inline-size: 36px;">{{ payment.currency }}</span>
                  <input
                    v-model="payment.inputAmount"
                    class="payment-input-box text-right border rounded-lg font-weight-bold flex-grow-1"
                    placeholder="0.00"
                    @keydown.enter="emit('handle-payment-enter', $event, payment)"
                  />
                  <VBtn
                    v-if="!payment._isReferenceActive"
                    icon="tabler-check"
                    size="26"
                    :color="(parseFloat(payment.inputAmount) > 0) ? 'success' : 'secondary'"
                    variant="tonal"
                    class="rounded-lg shrink-0"
                    :disabled="!(parseFloat(payment.inputAmount) > 0)"
                    @click="emit('confirm-payment', payment)"
                  />
                  <VBtn
                    icon="tabler-trash"
                    size="26"
                    color="error"
                    variant="tonal"
                    class="rounded-lg shrink-0"
                    @click="emit('remove-payment', payments.indexOf(payment))"
                  />
                </div>

                <!-- Campo de referencia -->
                <div v-if="payment._isReferenceActive" class="d-flex align-center gap-2">
                  <span class="text-caption font-weight-bold text-medium-emphasis shrink-0" style="min-inline-size: 36px;">Ref:</span>
                  <div class="position-relative flex-grow-1 d-flex align-center">
                    <input
                      v-model="payment.reference"
                      class="payment-input-box border rounded-lg flex-grow-1 font-weight-medium"
                      style="padding: 6px 28px 6px 10px;"
                      placeholder="N° referencia (mín. 4 dígitos)"
                      @keydown.enter="emit('confirm-payment', payment)"
                    />
                    <VIcon
                      v-if="payment.reference"
                      icon="tabler-x"
                      size="14"
                      class="cursor-pointer text-disabled position-absolute"
                      style="right: 8px;"
                      @click="payment.reference = ''"
                    />
                  </div>
                  <VBtn
                    icon="tabler-check"
                    size="26"
                    :color="(payment.reference && payment.reference.trim().length >= 4) ? 'success' : 'secondary'"
                    variant="tonal"
                    class="rounded-lg shrink-0"
                    :disabled="!payment.reference || payment.reference.trim().length < 4"
                    @click="emit('confirm-payment', payment)"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <VDivider class="my-1" />

        <!-- BLOQUE 2: Tarjeta unificada de estado + Acciones -->
        <div class="d-flex flex-column gap-3">

          <!-- Tarjeta VUELTO (verde) — cuando se pagó de más -->
          <div v-if="showChangeAmount" class="change-card change-card--success">
            <div class="d-flex align-center gap-1 mb-2">
              <VIcon icon="tabler-coins" size="14" class="change-card__icon" />
              <span class="change-card__label">CAMBIO / VUELTO</span>
            </div>
            <div class="change-card__amount">
              {{ formatCurrency(changeAmountInCop, 'COP') }}
            </div>
            <div v-if="selectedCurrency !== 'COP'" class="change-card__secondary">
              <span>Equivalente en {{ selectedCurrency }}:</span>
              <strong>{{ formatCurrency(changeAmount, selectedCurrency) }}</strong>
            </div>
          </div>

          <!-- Tarjeta PENDIENTE (rojo) — cuando aún falta cobrar -->
          <div v-else-if="remainingAmount > 0.01" class="change-card change-card--pending">
            <div class="d-flex align-center gap-1 mb-2">
              <VIcon icon="tabler-clock-exclamation" size="14" class="change-card__icon--pending" />
              <span class="change-card__label--pending">PENDIENTE</span>
            </div>
            <div class="change-card__amount--pending">
              {{ formatCurrency(getConvertedRemainingAmount(selectedCurrencyTab), selectedCurrencyTab) }}
            </div>
          </div>

          <!-- Botones de acción -->
          <VCardActions class="pa-0 d-flex flex-column gap-2">
            <!-- Cobrar: verde sólido cuando saldado, magenta primario mientras falta -->
            <VBtn
              variant="flat"
              block
              size="large"
              class="rounded-lg font-weight-bold py-3 checkout-btn text-none elevation-2 cta-continue-btn"
              :color="remainingAmount <= 0.01 && !hasMissingReferences() ? 'success' : 'primary'"
              :style="remainingAmount <= 0.01 && !hasMissingReferences() ? 'background: linear-gradient(135deg, #28C76F, #129e51); color: white;' : ''"
              :disabled="issubmitting || isExternalLoading || (remainingAmount > 0.01 || hasMissingReferences())"
              @click="emit('complete-purchase')"
            >
              <VIcon icon="tabler-circle-check" class="me-2" size="20" />
              <span>{{ continueButtonText }}</span>
            </VBtn>

            <!-- Regresar al pedido -->
            <VBtn
              color="secondary"
              variant="outlined"
              block
              size="small"
              height="38"
              class="rounded-lg font-weight-medium text-none btn-outline-back"
              @click="emit('close-modal')"
            >
              <VIcon icon="tabler-arrow-left" class="me-1.5" size="16" />
              <span>Regresar al pedido</span>
            </VBtn>
          </VCardActions>
        </div>

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

.text-super-xs {
  font-size: 0.7rem;
}

/* Referencia inline debajo del nombre del método */
.payment-ref-inline {
  font-size: 0.7rem;
  font-weight: 600;
  color: #6b7280;
  line-height: 1.2;
  margin-top: 1px;
}

.payment-card-item {
  transition: all 0.15s ease;
}

.payment-card-item:hover {
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
}

.hover-editable-amount {
  transition: background-color 0.15s ease;
}

.hover-editable-amount:hover {
  background-color: rgba(var(--v-theme-error), 0.08);
}

.payment-input-box {
  font-size: 0.85rem;
  outline: none;
  background-color: #fafafa;
  padding: 6px 10px;
}

.payment-input-box:focus {
  border-color: rgb(var(--v-theme-primary)) !important;
  background-color: #ffffff;
}

.added-payments-list {
  /* Sin scroll interno: la lista crece y empuja el contenido hacia abajo */
}

.cta-continue-btn {
  font-size: 1rem !important; /* 16px */
  font-weight: 700 !important;
}

.btn-outline-back {
  font-size: 0.875rem !important; /* 14px */
  border-color: rgba(var(--v-theme-on-surface), 0.2) !important;
  color: #4b5563 !important;
}

.btn-outline-back:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.04) !important;
  border-color: rgba(var(--v-theme-on-surface), 0.35) !important;
  color: #1f2937 !important;
}

/* ── Tarjeta de estado unificada (Verde = Vuelto / Rojo = Pendiente) ── */
.change-card {
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 4px;
}

/* Variante: VUELTO (verde pastel) */
.change-card--success {
  background-color: #f0fdf4;
  border: 1.5px solid #bbf7d0;
}

.change-card__label {
  font-size: 0.75rem !important;   /* 12px */
  font-weight: 700 !important;
  letter-spacing: 0.8px;
  color: #15803d;
  text-transform: uppercase;
}

.change-card__icon {
  color: #15803d !important;
}

.change-card__amount {
  font-size: 1.5rem !important;    /* 24px */
  font-weight: 900 !important;
  color: #15803d !important;
  line-height: 1.1;
  letter-spacing: -0.5px;
}

.change-card__secondary {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.75rem;
  color: #4b7a5c;
  margin-top: 4px;
  padding-top: 6px;
  border-top: 1px dashed #bbf7d0;
  width: 100%;
  justify-content: center;
}

.change-card__secondary strong {
  font-weight: 700;
  color: #15803d;
}

/* Variante: PENDIENTE (rojo pastel) */
.change-card--pending {
  background-color: #fff1f2;
  border: 1.5px solid #fecdd3;
}

.change-card__label--pending {
  font-size: 0.75rem !important;   /* 12px */
  font-weight: 700 !important;
  letter-spacing: 0.8px;
  color: #b91c1c;
  text-transform: uppercase;
}

.change-card__icon--pending {
  color: #b91c1c !important;
}

.change-card__amount--pending {
  font-size: 1.5rem !important;    /* 24px */
  font-weight: 900 !important;
  color: #dc2626 !important;
  line-height: 1.1;
  letter-spacing: -0.5px;
}
</style>
