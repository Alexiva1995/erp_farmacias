<script setup>
import { ref, reactive, computed, watch, nextTick } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import CheckoutPaymentMethods from "@/components/dialogs/checkout/CheckoutPaymentMethods.vue";
import CheckoutSummary from "@/components/dialogs/checkout/CheckoutSummary.vue";
import { useTpvCheckoutCalculations } from "@/composables/useTpvCheckoutCalculations";
import { useTpvCheckoutManager } from "@/composables/useTpvCheckoutManager";
import { useTpvCheckoutUI } from "@/composables/useTpvCheckoutUI";

const brandingStore = useBrandingStore();

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  creditsData: { type: Object, default: () => ({}) },
  selectedCurrency: { type: String, default: "USD" },
});

const emit = defineEmits([
  "update:isDialogVisible",
  "purchase-completed",
  "modal-closed",
]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const currentProgress = ref(0);
const selectedCurrencyTab = ref(props.selectedCurrency || "USD");
const payments = ref([]);

const totalPendingAmount = computed(() =>
  parseFloat(props.creditsData?.total_pending_amount || 0)
);

const orderDataProxy = computed(() => ({
  client: props.creditsData?.client || null,
  total_amount: totalPendingAmount.value,
}));

// Props reactivos adaptados para los composables compartidos del TPV
const checkoutProps = reactive({
  get isDialogVisible() {
    return props.isDialogVisible;
  },
  get orderData() {
    return orderDataProxy.value;
  },
  get totalAmount() {
    return totalPendingAmount.value;
  },
  get selectedCurrency() {
    return props.selectedCurrency || "USD";
  },
  orderProducts: [],
  isCreditPayment: true,
  isOffline: false,
  skipFiscal: true,
  isSpecialTaxpayer: false,
  foreignOrdersCount: 0,
  allForeignSalesSpe: false,
  isExternalLoading: false,
});

watch(
  () => props.isDialogVisible,
  (isVisible) => {
    if (isVisible) {
      currentProgress.value = 0;
      selectedCurrencyTab.value = props.selectedCurrency || "USD";
      payments.value = [
        {
          method: null,
          amount: null,
          reference: null,
          currency: props.selectedCurrency || "USD",
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
  { deep: true }
);

// 1. Módulo de Cálculos compartido con TPV
const {
  ratesLoaded,
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
  changeCurrencyMode,
  customChangeUsdDelivered,
  effectiveChangeDeliveredUsd,
  effectiveChangeDeliveredCop,
  setChangeCurrencyMode,
  setCustomChangeUsdDelivered,
} = useTpvCheckoutCalculations(checkoutProps, payments, brandingStore);

// 2. Módulo UI compartido con TPV (excluye crédito/cashea/saldo)
const {
  paymentMethodsByCurrency,
  getPaymentMethodLabel,
  getPaymentMethodIcon,
  isPaymentMethodActive,
  isPaymentMethodAdded,
  isLastPaymentAdded,
  getAvailableMethodsForCurrency,
  hasMissingReferences,
} = useTpvCheckoutUI({
  props: checkoutProps,
  payments,
  brandingStore,
  selectedCurrencyTab,
  currentProgress,
  requiresReference,
});

// 3. Módulo Gestor compartido con TPV
const {
  issubmitting,
  selectPaymentMethod,
  selectQuickCash,
  confirmPaymentComplete,
  handleCompletePurchase,
} = useTpvCheckoutManager({
  props: checkoutProps,
  emit: (eventName, ...args) => {
    emit(eventName, ...args);
    if (eventName === "purchase-completed") {
      dialogVisible.value = false;
    }
  },
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
  exchangeRates: ref({}),
  currentProgress,
  brandingStore,
  changeCurrencyMode,
  effectiveChangeDeliveredUsd,
  effectiveChangeDeliveredCop,
});

const closeModal = () => {
  dialogVisible.value = false;
  emit("modal-closed");
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
  if (paymentIndex < 0 || paymentIndex >= payments.value.length) return;
  const payment = payments.value[paymentIndex];
  if (!payment) return;
  if (payment.debounceTimeout) clearTimeout(payment.debounceTimeout);
  payments.value.splice(paymentIndex, 1);
};

const handlePaymentEnter = (event, payment) => {
  event.preventDefault();
  confirmPaymentComplete(payment);
};
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    :fullscreen="$vuetify.display.xs"
    max-width="1050"
    transition="dialog-bottom-transition"
    class="buys-modal-dialog"
    scrollable
    :z-index="20000"
    style="z-index: 20000 !important;"
  >
    <VCard class="rounded-xl glass-card elevation-4 max-h-90vh overflow-hidden d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 border-b bg-surface">
        <div class="d-flex align-center">
          <VIcon icon="tabler-wallet" color="primary" class="me-2" size="24" />
          <span class="text-h6 font-weight-bold letter-spacing-1 text-high-emphasis">
            Registrar Abono de Crédito
          </span>
        </div>

        <VChip
          v-if="creditsData?.client"
          color="primary"
          variant="tonal"
          size="small"
          class="ms-4 font-weight-bold"
        >
          <VIcon start icon="tabler-user" size="14" />
          {{ creditsData.client.name }} {{ creditsData.client.last_name }}
        </VChip>

        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />

      <VCardText class="pa-0 bg-light-grey">
        <div v-if="!ratesLoaded" class="pa-10 text-center">
          <VProgressCircular indeterminate color="primary" size="48" />
          <div class="mt-3 text-subtitle-2 font-weight-bold uppercase letter-spacing-1">
            Cargando Tasas...
          </div>
        </div>

        <VRow v-else no-gutters class="modal-content-row">
          <!-- Columna Izquierda: Métodos de Pago (60%) -->
          <VCol cols="12" md="7" lg="7" class="pa-4 border-e overflow-y-auto modal-scroll-col">
            <div class="pe-md-2">
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

          <!-- Columna Derecha: Resumen de Cobro y Devolución (40%) -->
          <VCol cols="12" md="5" lg="5" class="pa-4 bg-surface overflow-y-auto modal-scroll-col d-flex flex-column">
            <div class="ps-md-2">
              <CheckoutSummary
                :selected-currency="selectedCurrency"
                :selected-currency-tab="selectedCurrencyTab"
                :active-discount-display="null"
                :expiration-discount-total="0"
                :applies-special-tax="false"
                :special-tax-amount="0"
                :rounded-total-amount-to-pay="roundedTotalAmountToPay"
                :payments="payments"
                :remaining-amount="remainingAmount"
                :show-change-amount="showChangeAmount"
                :change-amount="changeAmount"
                :change-amount-in-cop="changeAmountInCop"
                :change-amount-in-usd="changeAmountInUsd"
                :enable-custom-change-currency="brandingStore?.settings?.enable_custom_change_currency"
                :change-currency-mode="changeCurrencyMode"
                :custom-change-usd-delivered="customChangeUsdDelivered"
                :effective-change-delivered-usd="effectiveChangeDeliveredUsd"
                :effective-change-delivered-cop="effectiveChangeDeliveredCop"
                :get-converted-remaining-amount="getConvertedRemainingAmount"
                :get-payment-method-label="getPaymentMethodLabel"
                :edit-payment-amount="editPaymentAmount"
                :remove-payment-from-summary="removePaymentFromSummary"
                :is-last-payment-added="isLastPaymentAdded"
                :handle-payment-enter="handlePaymentEnter"
                :confirm-payment-complete="confirmPaymentComplete"
                continue-button-text="Registrar Abono"
                :issubmitting="issubmitting"
                :is-external-loading="false"
                :has-missing-references="hasMissingReferences"
                :order-data="orderDataProxy"
                :allow-partial-payment="true"
                @complete-purchase="handleCompletePurchase"
                @close-modal="closeModal"
                @confirm-payment="confirmPaymentComplete"
                @handle-payment-enter="handlePaymentEnter"
                @remove-payment="removePaymentFromSummary"
                @update:change-currency-mode="setChangeCurrencyMode"
                @update:custom-change-usd-delivered="setCustomChangeUsdDelivered"
              />
            </div>
          </VCol>
        </VRow>
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
