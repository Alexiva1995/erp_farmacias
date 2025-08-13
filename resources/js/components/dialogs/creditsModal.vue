<script setup>
import { defineProps, defineEmits, computed, ref, watch } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatDateTime } from "@/utils/formatDateTime";
import { toast } from "@/plugins/sweetalert";

const currentProgress = ref(0);
const progressStages = [0, 100];
const currentStageIndex = ref(0);

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  creditsData: {
    type: Object,
    default: () => ({}),
  },
  selectedCurrency: {
    type: String,
    default: "USD",
  },
});


const payments = ref([
  {
    method: null,
    amount: null,
    reference: null,
    currency: props.selectedCurrency,
  },
]);

const currencies = [
  { label: "Pesos Colombianos (COP)", value: "COP" },
  { label: "Bolívares (BS)", value: "BS" },
  { label: "Dólares (USD)", value: "USD" },
];

const paymentMethodsByCurrency = {
  COP: [
    { label: "Efectivo", value: "cash_cop" },
    { label: "Transferencia", value: "bank_transfer" },
  ],
  BS: [
    { label: "Efectivo", value: "cash_bs" },
    { label: "Pago Móvil", value: "mobile_payment" },
    { label: "Transferencia", value: "bank_transfer_bs" },
    { label: "Tarjeta", value: "card" },
  ],
  USD: [
    { label: "Efectivo", value: "cash_usd" },
    { label: "Binance", value: "binance" },
    { label: "PayPal", value: "paypal" },
  ],
};

const exchangeRates = ref({});

const isTransferMethod = (method) =>
  ["bank_transfer", "bank_transfer_bs", "mobile_payment", "card"].includes(
    method
  );

const emit = defineEmits([
  "update:isDialogVisible",
  "purchase-completed",
  "modal-closed",
]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
};

const continueButtonText = computed(() => {
  return currentProgress.value === 100 ? "Finalizar" : "Continuar";
});

const resetProgress = () => {
  currentProgress.value = 0;
  currentStageIndex.value = 0;
};

const getConvertedRemainingAmount = (currency) => {
  const baseCurrency = props.selectedCurrency;
  const targetCurrency = currency;
  const rate = exchangeRates.value[baseCurrency]?.[targetCurrency];
  let converted = remainingAmount.value;
  if (rate) {
    converted = remainingAmount.value * rate;
  }
  return parseFloat(converted.toFixed(2));
};

const getPlaceholderText = (index, payment) => {
  const convertedRemaining = getConvertedRemainingAmount(payment.currency);
  return `Monto restante: ${formatCurrency(
    convertedRemaining,
    payment.currency
  )}`;
};

</script>
<template>
  <VDialog v-model="dialogVisible" max-width="500px">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">Créditos</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <div class="demo-space-y px-4 pt-4">
        <VProgressLinear
          v-model="currentProgress"
          color="primary"
          height="10"
          rounded
        />
        <div class="d-flex flex-wrap justify-space-between">
          <p class="text-center mt-2 text-subtitle-2 text-medium-emphasis">
            Métodos de pago
          </p>
          <p class="text-center mt-2 text-subtitle-2 text-medium-emphasis">
            Ticke de pago de créditos
          </p>
        </div>
      </div>
      <VCardText v-if="currentProgress === 0">
        <div
          v-for="(payment, index) in payments"
          :key="index"
          class="payment-block"
        >
          <p class="font-weight-bold text-h6 mt-4">
            Método de Pago #{{ index + 1 }}
            <span v-if="index > 0"> ({{ payment.currency }})</span>
          </p>

          <VSelect
            v-if="index > 0"
            v-model="payment.currency"
            :items="currencies"
            item-title="label"
            item-value="value"
            label="Moneda del Pago"
            class="mt-4"
          />

          <div class="my-4" v-if="payment.method !== 'balance'">
            <VRadioGroup v-model="payment.method" inline>
              <VRadio
                v-for="method in (
                  paymentMethodsByCurrency[payment.currency] || []
                ).filter((m) => {
                  if (index === 0 && payment.currency === 'USD') {
                    return true;
                  }
                  if (index === 0 && payment.currency !== 'USD') {
                    return false;
                  }
                  return true;
                })"
                :key="method.value"
                :label="method.label"
                :value="method.value"
              />
            </VRadioGroup>
          </div>

          <VTextField
            v-else-if="payment.method"
            v-model.number="payment.amount"
            label="Monto del pago"
            :placeholder="getPlaceholderText(index, payment)"
            type="number"
            class="my-4"
          />

          <VTextField
            v-if="payment.method && isTransferMethod(payment.method)"
            v-model="payment.reference"
            label="Número de Referencia"
            placeholder="Ingresa el número de referencia del pago"
            class="m-2"
          />
          <VDivider class="mt-4" />
        </div>
        <div class="d-flex justify-center mt-4">
          <VBtn
            variant="text"
            color="primary"
            @click="addPaymentBlock"
            :disabled="!canAddPaymentBlock"
          >
            <VIcon start icon="tabler-plus" />
            Agregar otro método de pago
          </VBtn>
        </div>

        <VDivider />
        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6 mt-4">Total a pagar:</p>
          <p class="font-weight-bold text-h6 mt-4">
            {{
              formatCurrency(parseFloat(props.creditsData.total_pending_amount), props.selectedCurrency)
            }}
          </p>
        </div>

        <div
          v-if="showChangeAmount"
          class="d-flex flex-wrap justify-space-between"
        >
          <p class="font-weight-bold text-h6 mt-2">Monto Devuelto:</p>
          <p class="font-weight-bold text-h6 mt-2">
            {{ formatCurrency(changeAmountInCOP, "COP") }}
          </p>
        </div>

        <div
          v-if="remainingAmount > 0"
          class="d-flex flex-wrap justify-space-between"
        >
          <p class="font-weight-bold text-h6">Monto Restante:</p>
          <p class="font-weight-bold text-h6 text-error">
            {{ formatCurrency(remainingAmount, props.selectedCurrency) }}
          </p>
        </div>
      </VCardText>

      <VCardText v-if="currentProgress === 100"> </VCardText>

      <VCardActions class="p-4 d-flex flex-wrap justify-space-between">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeModal"
          class="flex-grow-1"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleCompletePurchase"
          class="flex-grow-1"
        >
          {{ continueButtonText }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
