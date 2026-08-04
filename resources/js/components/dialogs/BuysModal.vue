<script setup>
import { ref, computed, watch } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import CheckoutPaymentMethods from "@/components/dialogs/checkout/CheckoutPaymentMethods.vue";
import CheckoutReceipt from "@/components/dialogs/checkout/CheckoutReceipt.vue";
import CheckoutSummary from "@/components/dialogs/checkout/CheckoutSummary.vue";

import { useTpvCheckoutCalculations } from "@/composables/useTpvCheckoutCalculations";
import { useTpvCheckoutManager } from "@/composables/useTpvCheckoutManager";
import { useTpvCheckoutUI } from "@/composables/useTpvCheckoutUI";

const brandingStore = useBrandingStore();

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  orderData: { type: Object, default: () => ({}) },
  totalAmount: { type: Number, default: 0 },
  selectedCurrency: { type: String, default: "COP" },
  orderProducts: { type: Array, default: () => [] },
  selectedDisplayCurrency: { type: String, default: "COP" },
  companyDiscountTotal: { type: Number, default: 0 },
  selectedDiscountType: { type: String, default: null },
  doctorDiscountTotal: { type: Number, default: 0 },
  recipeDiscountTotal: { type: Number, default: 0 },
  expirationDiscountTotal: { type: Number, default: 0 },
  activeDoctorOffers: { type: Array, default: () => [] },
  prescriptionDiscountPercentage: { type: Number, default: 0 },
  activeCompanyOffers: { type: Array, default: () => [] },
  globalDiscount: { type: Object, default: () => null },
  isSpecialTaxpayer: { type: Boolean, default: false },
  isExternalLoading: { type: Boolean, default: false },
  foreignOrdersCount: { type: Number, default: 0 },
  allForeignSalesSpe: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:isDialogVisible",
  "purchase-completed",
  "modal-closed",
  "printTicke-completed",
  "print-fiscal",
  "finish-and-reload",
]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const currentProgress = ref(0);
const invoiceSwitch = ref(true);
const selectedCurrencyTab = ref(props.selectedCurrency || "COP");
const payments = ref([]);

watch(
  () => props.isDialogVisible,
  (isVisible) => {
    if (isVisible) {
      currentProgress.value = 0;
      selectedCurrencyTab.value = props.selectedCurrency || "COP";
      payments.value = [
        {
          method: null,
          amount: null,
          reference: null,
          currency: props.selectedCurrency || "COP",
          debounceTimeout: null,
          inputAmount: null,
          _isEditing: false,
          _isInputActive: false,
          _isReferenceActive: false,
          _referenceError: false,
          _amountConfirmed: false,
          _amountError: false,
        },
      ];
    }
  },
  { immediate: true }
);

watch(
  payments,
  (newPayments) => {
    newPayments.forEach((p) => {
      if (
        p.reference &&
        p.currency === "BS" &&
        ["debit_card", "credit_card", "card"].includes(p.method)
      ) {
        const cleanRef = p.reference.toString().trim();
        if (cleanRef) {
          localStorage.setItem("tpv_last_card_reference_bs", cleanRef);
        }
      }
    });
  },
  { deep: true },
);

// 1. Módulo de Cálculos
const {
  exchangeRates,
  ratesLoaded,
  isCredit,
  isCashMethod,
  requiresReference,
  appliesSpecialTax,
  specialTaxAmount,
  getEffectiveRate,
  roundedTotalAmountToPay,
  remainingAmount,
  getConvertedRemainingAmount,
  changeAmount,
  changeAmountInCop,
  changeAmountInUsd,
  showChangeAmount,
} = useTpvCheckoutCalculations(props, payments, brandingStore);

// 2. Módulo UI
const {
  currencies,
  paymentMethodsByCurrency,
  continueButtonText,
  getPaymentMethodLabel,
  getPaymentMethodIcon,
  isPaymentMethodActive,
  isPaymentMethodAdded,
  isLastPaymentAdded,
  getAvailableMethodsForCurrency,
  hasMissingReferences,
} = useTpvCheckoutUI({
  props,
  payments,
  brandingStore,
  selectedCurrencyTab,
  currentProgress,
  requiresReference,
});

// 3. Módulo Gestor de Proceso y Finalización
const {
  issubmitting,
  receiptOrderData,
  receiptOrderProducts,
  receiptPayments,
  getProductPrice,
  selectPaymentMethod,
  selectQuickCash,
  confirmPaymentComplete,
  handleCompletePurchase,
} = useTpvCheckoutManager({
  props,
  emit,
  payments,
  remainingAmount,
  getConvertedRemainingAmount,
  getEffectiveRate,
  isCashMethod,
  requiresReference,
  hasMissingReferences,
  appliesSpecialTax,
  specialTaxAmount,
  roundedTotalAmountToPay,
  changeAmount,
  changeAmountInCop,
  changeAmountInUsd,
  exchangeRates,
  currentProgress,
});

const closeModal = () => {
  dialogVisible.value = false;
  emit("modal-closed");
};

const handleCancelAfterTicket = () => {
  closeModal();
  emit("finish-and-reload");
};

const handlePrintTicket = () => {
  emit("printTicke-completed", receiptOrderData.value || props.orderData);
};

const editPaymentAmount = (payment) => {
  payment._isInputActive = true;
  payment.inputAmount = payment.amount ? payment.amount.toString() : "";
  payment._previousAmount = payment.amount;
  payment._amountConfirmed = false;
  payment._isReferenceActive = requiresReference(payment.method, payment.currency);
  payment._referenceError = false;
  payment._amountError = false;

  nextTick(() => {
    const paymentIndex = payments.value.indexOf(payment);
    const input = document.querySelector(`.payment-input[data-payment-index="${paymentIndex}"]`);
    if (input) {
      input.focus();
      input.select();
    }
  });
};

const removePaymentFromSummary = (paymentIndex) => {
  const paymentsWithMethod = payments.value.filter((p) => p.method);
  if (paymentsWithMethod.length === 0) return;

  const lastPaymentWithMethod = paymentsWithMethod[paymentsWithMethod.length - 1];
  const lastPaymentIndex = payments.value.indexOf(lastPaymentWithMethod);

  if (paymentIndex !== lastPaymentIndex) {
    toast.error("Solo se puede eliminar el último método de pago agregado.");
    return;
  }

  const payment = payments.value[paymentIndex];
  if (payment.debounceTimeout) clearTimeout(payment.debounceTimeout);
  payments.value.splice(paymentIndex, 1);
};

const handlePaymentEnter = (event, payment) => {
  event.preventDefault();
  confirmPaymentComplete(payment);
};

const hasCreditPayment = computed(() => {
  return Array.isArray(payments.value) && payments.value.some((p) => isCredit(p.method));
});

const totalSPESavings = computed(() => {
  return (props.prescriptionDiscountPercentage || 0) * props.totalAmount;
});

const activeDiscountDisplay = computed(() => {
  const type = props.selectedDiscountType;
  const currency = props.selectedCurrency;
  const config = {
    Empresa: {
      label: "Descuento Empresa",
      amount: props.companyDiscountTotal,
      formatted: formatCurrency(props.companyDiscountTotal, currency),
    },
    Medico: {
      label: "Descuento Médico",
      amount: props.doctorDiscountTotal,
      formatted: formatCurrency(props.doctorDiscountTotal, currency),
    },
    Recipe: {
      label: "Descuento Recipe",
      amount: props.recipeDiscountTotal,
      formatted: formatCurrency(props.recipeDiscountTotal, currency),
    },
  };
  const current = config[type];
  return current && current.amount > 0 ? current : null;
});
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    :fullscreen="$vuetify.display.xs"
    max-width="1050"
    transition="dialog-bottom-transition"
    class="buys-modal-dialog"
    scrollable
  >
    <VCard class="rounded-xl glass-card elevation-4 max-h-90vh overflow-hidden d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 border-b bg-surface">
        <div class="d-flex align-center">
          <VIcon icon="tabler-shopping-cart-check" color="primary" class="me-3" size="28" />
          <span class="text-h5 font-weight-black uppercase letter-spacing-1">Finalizar Compra</span>
        </div>
        <div class="ms-6 d-flex align-center">
          <span class="text-caption font-weight-bold me-2 uppercase">Factura</span>
          <VSwitch v-model="invoiceSwitch" density="compact" color="primary" hide-details readonly disabled />
        </div>
        <VSpacer />
        <VBtn
          icon
          variant="text"
          @click="currentProgress === 0 ? closeModal() : handleCancelAfterTicket()"
        >
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText v-if="currentProgress === 0" class="pa-0 bg-light-grey">
        <div v-if="!ratesLoaded" class="pa-10 text-center">
          <VProgressCircular indeterminate color="primary" size="48" />
          <div class="mt-3 text-subtitle-2 font-weight-bold uppercase letter-spacing-1">Cargando Tasas...</div>
        </div>
        
        <VRow v-else no-gutters class="modal-content-row">
          <!-- Columna Izquierda: Métodos de Pago -->
          <VCol cols="12" md="7" lg="7" class="pa-3 border-e overflow-y-auto modal-scroll-col">
            <div class="d-flex flex-column gap-3">
              <CheckoutPaymentMethods 
                v-model:selectedCurrencyTab="selectedCurrencyTab"
                :currencies="['USD', 'COP', 'BS']"
                :payment-methods-by-currency="paymentMethodsByCurrency"
                :remaining-amount="remainingAmount"
                :get-converted-remaining-amount="getConvertedRemainingAmount"
                :is-payment-method-active="isPaymentMethodActive"
                :is-payment-method-added="isPaymentMethodAdded"
                :get-payment-method-icon="getPaymentMethodIcon"
                :get-available-methods-for-currency="getAvailableMethodsForCurrency"
                @select-payment-method="selectPaymentMethod"
                @select-quick-cash="selectQuickCash"
              />
            </div>
          </VCol>

          <!-- Columna Derecha: Resumen de Cobro y Devolución -->
          <VCol cols="12" md="5" lg="5" class="pa-3 bg-surface overflow-y-auto modal-scroll-col">
            <CheckoutSummary 
              :selected-currency="selectedCurrency"
              :selected-currency-tab="selectedCurrencyTab"
              :active-discount-display="activeDiscountDisplay"
              :expiration-discount-total="expirationDiscountTotal"
              :applies-special-tax="appliesSpecialTax"
              :special-tax-amount="specialTaxAmount"
              :rounded-total-amount-to-pay="roundedTotalAmountToPay"
              :payments="payments"
              :remaining-amount="remainingAmount"
              :show-change-amount="showChangeAmount"
              :change-amount="changeAmount"
              :change-amount-in-cop="changeAmountInCop"
              :get-converted-remaining-amount="getConvertedRemainingAmount"
              :get-payment-method-label="getPaymentMethodLabel"
              :edit-payment-amount="editPaymentAmount"
              :remove-payment-from-summary="removePaymentFromSummary"
              :is-last-payment-added="isLastPaymentAdded"
              :handle-payment-enter="handlePaymentEnter"
              :confirm-payment-complete="confirmPaymentComplete"
              :continue-button-text="continueButtonText"
              :issubmitting="issubmitting"
              :is-external-loading="isExternalLoading"
              :has-missing-references="hasMissingReferences"
              :order-data="orderData"
              @complete-purchase="handleCompletePurchase"
              @close-modal="closeModal"
              @confirm-payment="confirmPaymentComplete"
              @handle-payment-enter="handlePaymentEnter"
              @remove-payment="removePaymentFromSummary"
            />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Ticket Final -->
      <VCardText v-else class="pa-0">
        <CheckoutReceipt 
          :exchange-rates="exchangeRates"
          :order-data="receiptOrderData || orderData"
          :order-products="receiptOrderProducts.length > 0 ? receiptOrderProducts : orderProducts"
          :selected-currency="selectedCurrency"
          :get-payment-method-label="getPaymentMethodLabel"
          :payments="receiptPayments.length > 0 ? receiptPayments : payments"
          :get-product-price="getProductPrice"
          :active-discount-display="activeDiscountDisplay"
          :expiration-discount-total="expirationDiscountTotal"
          :applies-special-tax="appliesSpecialTax"
          :special-tax-amount="specialTaxAmount"
          :rounded-total-amount-to-pay="roundedTotalAmountToPay"
          :total-s-p-e-savings="totalSPESavings"
          :has-credit-payment="hasCreditPayment"
          :show-change-amount="showChangeAmount"
          :change-amount="changeAmount"
          :change-amount-in-cop="changeAmountInCop"
          @print="handlePrintTicket"
          @print-fiscal="(order) => emit('print-fiscal', order || orderData)"
          @cancel="handleCancelAfterTicket"
        />
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.glass-card {
  backdrop-filter: blur(10px);
  background: rgba(var(--v-theme-surface), 0.8) !important;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.uppercase {
  text-transform: uppercase;
}

.max-h-90vh {
  max-height: 90vh !important;
}

.modal-content-row {
  max-height: calc(90vh - 70px);
}

.modal-scroll-col {
  max-height: calc(90vh - 70px);
}
</style>
