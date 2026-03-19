<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import {
  computed,
  defineEmits,
  defineProps,
  nextTick,
  onMounted,
  ref,
  watch,
} from "vue";

const chipColor = "primary";

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

const currentProgress = ref(0);
const progressStages = [0, 100];
const currentStageIndex = ref(0);
const ratesLoaded = ref(false);
const selectedCurrencyTab = ref(props.selectedCurrency);
const today = new Date();

const payments = ref([
  {
    method: null,
    amount: null,
    reference: null,
    currency: props.selectedCurrency,
    debounceTimeout: null,
    inputAmount: null,
    _isEditing: false,
    _isInputActive: false,
    _isReferenceActive: false,
    _referenceError: false,
    _amountConfirmed: false,
    _amountError: false,
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

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const userUsername = computed(() => {
  return currentUser.value?.username || "N/A";
});

const isTransferMethod = (method) =>
  ["bank_transfer", "bank_transfer_bs", "mobile_payment", "card"].includes(
    method
  );

const isCashMethod = (method) => {
  return ["cash_bs", "cash_usd", "cash_cop"].includes(method);
};

const requiresReference = (method) => {
  if (isCashMethod(method)) return false;
  return true;
};

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
  resetProgress();
};

const handleCancelAfterTicket = () => {
  dialogVisible.value = false;
  emit("modal-closed");
  resetProgress();
};

function roundToTwoDecimalPlaces(num) {
  return Number(Math.round(num + "e+2") + "e-2");
}

const resetProgress = () => {
  currentProgress.value = 0;
  currentStageIndex.value = 0;
  payments.value = [
    {
      method: null,
      amount: null,
      reference: null,
      currency: props.selectedCurrency,
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
};

const fetchExchangeRates = async () => {
  ratesLoaded.value = false;
  try {
    const response = await axios.get("/public/exchange-rates");
    if (response.status !== 200) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }
    const apiRates = response.data;
    const formattedRates = {};
    apiRates.forEach((rateItem) => {
      const currencyCode = rateItem.currency_code;
      const rateValue = parseFloat(rateItem.rate);

      if (!formattedRates["USD"]) {
        formattedRates["USD"] = {};
      }
      formattedRates["USD"][currencyCode] = rateValue;
      if (!formattedRates[currencyCode]) {
        formattedRates[currencyCode] = {};
      }
      if (rateValue !== 0) {
        formattedRates[currencyCode]["USD"] = 1 / rateValue;
      }

      if (formattedRates["COP"] && formattedRates["BS"]) {
        formattedRates["COP"]["BS"] = parseFloat(
          (formattedRates["COP"]["USD"] * formattedRates["USD"]["BS"]).toFixed(
            9
          )
        );
        formattedRates["BS"]["COP"] = parseFloat(
          (formattedRates["BS"]["USD"] * formattedRates["USD"]["COP"]).toFixed(
            9
          )
        );
      }
    });
    exchangeRates.value = formattedRates;
    ratesLoaded.value = true;
  } catch (error) {
    toast.error("No se pudieron cargar las tasas de cambio.");
    console.error("Error fetching exchange rates:", error);
    ratesLoaded.value = false;
  }
};

onMounted(() => {
  fetchExchangeRates();
});

watch(
  () => props.isDialogVisible,
  (newVal) => {
    if (newVal) {
      resetProgress();
      ratesLoaded.value = false;
      fetchExchangeRates();
      selectedCurrencyTab.value = props.selectedCurrency;
      payments.value = [
        {
          method: null,
          amount: null,
          reference: null,
          currency: props.selectedCurrency,
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
  }
);

const continueButtonText = computed(() => {
  return currentProgress.value === 100 ? "Finalizar" : "Continuar";
});

const roundedTotalAmountToPay = computed(() => {
  const amount = parseFloat(props.creditsData?.total_pending_amount || 0);
  if (props.selectedCurrency === "COP") {
    return roundUpToNearestHundred(amount);
  }
  return parseFloat(amount.toFixed(2));
});

const remainingAmount = computed(() => {
  const totalToPay = parseFloat(props.creditsData?.total_pending_amount || 0);
  const rawDifference = totalToPay - totalPaidAmount.value;

  if (props.selectedCurrency === "COP") {
    return roundUpToNearestHundred(rawDifference);
  }
  return roundToTwoDecimalPlaces(rawDifference);
});

const totalPaidAmount = computed(() => {
  let currentSum = 0;
  payments.value.forEach((payment) => {
    let amount = Number(payment.amount) || 0;
    let amountToAdd = 0;

    if (payment.currency === props.selectedCurrency) {
      amountToAdd = amount;
    } else {
      const rate =
        exchangeRates.value?.[payment.currency]?.[props.selectedCurrency];
      if (rate) {
        let convertedAmount = amount * rate;
        amountToAdd = convertedAmount;
      }
    }
    currentSum = roundToTwoDecimalPlaces(currentSum + amountToAdd);
  });
  return currentSum;
});

const getConvertedRemainingAmount = (currency) => {
  const baseCurrency = props.selectedCurrency;
  const targetCurrency = currency;

  if (baseCurrency === targetCurrency) {
    return remainingAmount.value;
  }

  if (!ratesLoaded.value) return 0;

  const rate = exchangeRates.value[baseCurrency]?.[targetCurrency];
  if (!rate) return 0;

  let converted = remainingAmount.value * rate;
  return parseFloat(converted.toFixed(2));
};

const hasMissingReferences = () => {
  return payments.value.some((payment) => {
    if (!payment.method) return false;
    if (!payment.amount || payment.amount <= 0) return false;
    if (requiresReference(payment.method)) {
      return !payment.reference || payment.reference.trim() === "";
    }
    return false;
  });
};

const changeAmount = computed(() => {
  const totalToPay = parseFloat(props.creditsData?.total_pending_amount || 0);
  if (props.selectedCurrency === "COP") {
    const totalToPayRounded = roundUpToNearestHundred(totalToPay);
    return Math.max(
      0,
      roundToTwoDecimalPlaces(totalPaidAmount.value - totalToPayRounded)
    );
  }
  return Math.max(
    0,
    roundToTwoDecimalPlaces(totalPaidAmount.value - totalToPay)
  );
});

const changeAmountInUSD = computed(() => {
  const cashPaymentsInUSD = payments.value.filter(
    (p) => p.method === "cash_usd" && p.currency === "USD"
  );

  if (cashPaymentsInUSD.length === 0) return 0;

  let totalCashPaidInUSD = 0;
  cashPaymentsInUSD.forEach((p) => {
    totalCashPaidInUSD += Number(p.amount) || 0;
  });

  let totalOrdenEnUSD;
  if (props.selectedCurrency === "USD") {
    totalOrdenEnUSD = parseFloat(props.creditsData?.total_pending_amount || 0);
  } else {
    const rate = exchangeRates.value?.[props.selectedCurrency]?.["USD"];
    if (!rate) return 0;
    totalOrdenEnUSD =
      parseFloat(props.creditsData?.total_pending_amount || 0) / rate;
  }

  const diff = totalCashPaidInUSD - totalOrdenEnUSD;
  return Math.max(0, roundToTwoDecimalPlaces(diff));
});

const changeAmountInCOP = computed(() => {
  const vueltoEnMonedaOrden = changeAmount.value;
  if (props.selectedCurrency === "COP") return vueltoEnMonedaOrden;

  const rate = exchangeRates.value?.[props.selectedCurrency]?.["COP"];
  if (rate) {
    const vueltoConvertido = vueltoEnMonedaOrden * rate;
    return roundUpToNearestHundred(vueltoConvertido);
  }
  return 0;
});

const showChangeAmount = computed(() => {
  const hasRelevantCashPayment = payments.value.some(
    (payment) =>
      (payment.method === "cash_usd" && payment.currency === "USD") ||
      (payment.method === "cash_cop" && payment.currency === "COP")
  );
  return hasRelevantCashPayment && changeAmount.value > 0;
});

const getPaymentMethodLabel = (methodValue, currency) => {
  if (!methodValue) return "N/A";
  const methodsForCurrency = paymentMethodsByCurrency[currency];
  if (methodsForCurrency) {
    const foundMethod = methodsForCurrency.find((m) => m.value === methodValue);
    if (foundMethod) return foundMethod.label;
  }
  for (const key in paymentMethodsByCurrency) {
    const methods = paymentMethodsByCurrency[key];
    const foundMethod = methods.find((m) => m.value === methodValue);
    if (foundMethod) return foundMethod.label;
  }
  return methodValue.replace(/_/g, " ").toUpperCase();
};

const validPaymentsForTicket = computed(() => {
  return payments.value.filter((payment) => {
    if (!payment.method) return false;
    const amount = Number(payment.amount) || 0;
    if (amount <= 0) return false;
    return true;
  });
});

const selectPaymentMethod = (methodValue, currency = null) => {
  const targetCurrency = currency || props.selectedCurrency;

  if (remainingAmount.value <= 0) {
    toast.error("El monto total ya ha sido cubierto.");
    return;
  }

  const newPayment = {
    method: methodValue,
    amount: null,
    reference: null,
    currency: targetCurrency,
    debounceTimeout: null,
    inputAmount: null,
    _isEditing: false,
    _isInputActive: false,
    _isReferenceActive: false,
    _referenceError: false,
    _amountConfirmed: false,
    _amountError: false,
  };

  payments.value.push(newPayment);
  const availablePayment = payments.value[payments.value.length - 1];

  availablePayment.inputAmount = "";
  availablePayment.amount = null;
  availablePayment._isInputActive = true;

  nextTick(() => {
    const paymentIndex = payments.value.indexOf(availablePayment);
    const input = document.querySelector(
      `.payment-input[data-payment-index="${paymentIndex}"]`
    );
    if (input) input.focus();
  });
};

const confirmPaymentAmount = (payment) => {
  if (
    payment._isInputActive &&
    payment.inputAmount !== null &&
    payment.inputAmount !== "" &&
    payment.inputAmount !== undefined
  ) {
    const numValue = parseFloat(payment.inputAmount);
    if (!isNaN(numValue) && numValue > 0) {
      if (!isCashMethod(payment.method)) {
        const previousAmount = payment.amount || 0;
        let remainingInPaymentCurrency =
          getConvertedRemainingAmount(payment.currency);

        if (previousAmount > 0) {
          if (payment.currency === props.selectedCurrency) {
            remainingInPaymentCurrency += previousAmount;
          } else {
            const rateToBase =
              exchangeRates.value?.[payment.currency]?.[props.selectedCurrency];
            const rateToPayment =
              exchangeRates.value?.[props.selectedCurrency]?.[payment.currency];
            if (rateToBase && rateToPayment) {
              const previousInBase = previousAmount * rateToBase;
              remainingInPaymentCurrency += previousInBase * rateToPayment;
            }
          }
        }

        if (numValue > remainingInPaymentCurrency) {
          toast.error(
            `El monto no puede exceder el restante: ${formatCurrency(remainingInPaymentCurrency, payment.currency)}`
          );
          payment._amountError = true;
          return;
        }
      }

      payment.amount = numValue;
      payment.inputAmount = numValue.toString();
      payment._previousAmount = undefined;
      payment._amountError = false;

      if (requiresReference(payment.method)) {
        payment._isReferenceActive = true;
        payment._amountConfirmed = true;
        nextTick(() => {
          const paymentIndex = payments.value.indexOf(payment);
          const referenceInput = document.querySelector(
            `.payment-reference-input[data-payment-index="${paymentIndex}"]`
          );
          if (referenceInput) {
            referenceInput.focus();
            referenceInput.select();
          }
        });
      } else {
        payment._isInputActive = false;
        payment._isReferenceActive = false;
        payment._amountConfirmed = false;
      }
    } else {
      payment._amountError = true;
      toast.error("Por favor ingrese un monto válido.");
    }
  } else if (
    payment._isInputActive &&
    (!payment.inputAmount || payment.inputAmount === "")
  ) {
    if (payment._previousAmount !== undefined) {
      payment.inputAmount = payment._previousAmount.toString();
      payment.amount = payment._previousAmount;
    }
    payment._isInputActive = false;
    payment._isReferenceActive = false;
  }
};

const confirmPaymentComplete = (payment) => {
  if (!payment.amount || payment.amount <= 0) {
    toast.error("Por favor ingrese un monto válido.");
    if (payment._isInputActive) payment._amountError = true;
    return;
  }

  if (requiresReference(payment.method)) {
    if (!payment.reference || payment.reference.trim() === "") {
      toast.error("Por favor ingrese la referencia del pago.");
      payment._referenceError = true;
      payment._isReferenceActive = true;
      return;
    }
    payment._referenceError = false;
  }

  payment._isInputActive = false;
  payment._isReferenceActive = false;
  payment._referenceError = false;
  payment._amountError = false;
  payment._amountConfirmed = false;
  payment._previousAmount = undefined;
};

const handlePaymentEnter = (event, payment) => {
  event.preventDefault();

  if (
    payment.inputAmount !== null &&
    payment.inputAmount !== "" &&
    payment.inputAmount !== undefined
  ) {
    const numValue = parseFloat(payment.inputAmount);
    if (!isNaN(numValue) && numValue > 0) {
      if (!isCashMethod(payment.method)) {
        const previousAmount = payment.amount || 0;
        let remainingInPaymentCurrency =
          getConvertedRemainingAmount(payment.currency);

        if (previousAmount > 0) {
          if (payment.currency === props.selectedCurrency) {
            remainingInPaymentCurrency += previousAmount;
          } else {
            const rateToBase =
              exchangeRates.value?.[payment.currency]?.[props.selectedCurrency];
            const rateToPayment =
              exchangeRates.value?.[props.selectedCurrency]?.[payment.currency];
            if (rateToBase && rateToPayment) {
              const previousInBase = previousAmount * rateToBase;
              remainingInPaymentCurrency += previousInBase * rateToPayment;
            }
          }
        }

        if (numValue > remainingInPaymentCurrency) {
          toast.error(
            `El monto no puede exceder el restante: ${formatCurrency(remainingInPaymentCurrency, payment.currency)}`
          );
          return;
        }
      }

      payment.amount = numValue;
      payment.inputAmount = numValue.toString();
      payment._previousAmount = undefined;

      if (requiresReference(payment.method)) {
        payment._isReferenceActive = true;
        payment._amountConfirmed = true;
        nextTick(() => {
          const paymentIndex = payments.value.indexOf(payment);
          const referenceInput = document.querySelector(
            `.payment-reference-input[data-payment-index="${paymentIndex}"]`
          );
          if (referenceInput) {
            referenceInput.focus();
            referenceInput.select();
          }
        });
      } else {
        payment._isInputActive = false;
        payment._isReferenceActive = false;
        confirmPaymentComplete(payment);
      }
    } else {
      toast.error("Por favor ingrese un monto válido.");
    }
  } else {
    toast.error("Por favor ingrese un monto válido.");
  }
};

const handlePaymentTab = (payment) => {
  if (requiresReference(payment.method)) {
    payment._isReferenceActive = true;
    nextTick(() => {
      const paymentIndex = payments.value.indexOf(payment);
      const referenceInput = document.querySelector(
        `.payment-reference-input[data-payment-index="${paymentIndex}"]`
      );
      if (referenceInput) referenceInput.focus();
    });
  }
};

const editPaymentAmount = (payment) => {
  payment._isInputActive = true;
  payment.inputAmount = payment.amount ? payment.amount.toString() : "";
  payment._previousAmount = payment.amount;
  payment._amountConfirmed = false;
  payment._isReferenceActive = requiresReference(payment.method);
  payment._referenceError = false;
  payment._amountError = false;

  nextTick(() => {
    const paymentIndex = payments.value.indexOf(payment);
    const input = document.querySelector(
      `.payment-input[data-payment-index="${paymentIndex}"]`
    );
    if (input) {
      input.focus();
      input.select();
    }
  });
};

const updatePaymentAmountLive = (payment, value) => {
  let cleanValue = value.replace(/[^0-9.]/g, "");
  const firstDotIndex = cleanValue.indexOf(".");
  if (firstDotIndex !== -1) {
    const beforeDot = cleanValue.substring(0, firstDotIndex + 1);
    const afterDot = cleanValue.substring(firstDotIndex + 1).replace(/\./g, "");
    cleanValue = beforeDot + afterDot;
  }
  payment.inputAmount = cleanValue;
  if (payment._previousAmount === undefined) {
    payment._previousAmount = payment.amount;
  }
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

const getPaymentMethodIcon = (methodValue) => {
  const icons = {
    cash_bs: "tabler-cash",
    cash_cop: "tabler-cash",
    cash_usd: "tabler-cash",
    mobile_payment: "tabler-device-mobile",
    bank_transfer: "tabler-transfer",
    bank_transfer_bs: "tabler-transfer",
    card: "tabler-credit-card",
    binance: "tabler-currency-bitcoin",
    paypal: "tabler-brand-paypal",
  };
  return icons[methodValue] || "tabler-wallet";
};

const isPaymentMethodActive = (methodValue, currency) => {
  return payments.value.some(
    (p) =>
      p.method === methodValue &&
      p.currency === currency &&
      (p.amount > 0 || p._isInputActive)
  );
};

const isPaymentMethodAdded = (methodValue, currency) => {
  return payments.value.some(
    (p) => p.method === methodValue && p.currency === currency
  );
};

const isLastPaymentAdded = (payment) => {
  const paymentsWithMethod = payments.value.filter((p) => p.method);
  if (paymentsWithMethod.length === 0) return false;
  const lastPayment = paymentsWithMethod[paymentsWithMethod.length - 1];
  return payments.value.indexOf(payment) === payments.value.indexOf(lastPayment);
};

const getAvailableMethodsForCurrency = (currency) => {
  return paymentMethodsByCurrency[currency] || [];
};

const handleCompletePurchase = () => {
  if (hasMissingReferences()) {
    toast.error("Por favor complete todas las referencias de pago.");
    return;
  }

  const tolerance = 0.01;
  const finalRemainingAmount = remainingAmount.value;
  if (Math.abs(finalRemainingAmount) > tolerance && finalRemainingAmount > 0) {
    toast.error("El monto pagado no cubre el total. Complete el pago.");
    return;
  }

  if (currentProgress.value === 0) {
    currentStageIndex.value++;
    currentProgress.value = 100;
  } else {
    emit(
      "purchase-completed",
      payments.value.map((p) => ({
        method: p.method,
        amount: p.amount,
        currency: p.currency,
        reference: p.reference || undefined,
      })),
      changeAmountInCOP.value,
      changeAmountInUSD.value
    );
    dialogVisible.value = false;
    resetProgress();
  }
};

const logoSrc = computed(() => BASE64_LOGO_DATA);
</script>

<template>
  <VDialog v-model="dialogVisible" fullscreen transition="dialog-bottom-transition">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold pr-1">Pago de Créditos</span>
        <VSpacer />
        <VBtn
          icon
          variant="text"
          @click="
            currentProgress === 0 ? closeModal() : handleCancelAfterTicket()
          "
        >
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />

      <VCardText v-if="currentProgress === 0" class="pa-0">
        <div v-if="!ratesLoaded" class="text-center py-10">
          <VProgressCircular indeterminate color="primary"></VProgressCircular>
          <p class="mt-4">Cargando tasas de cambio. Por favor, espere...</p>
        </div>

        <div v-else class="d-flex gap-4" style="min-block-size: 500px;">
          <!-- COLUMNA IZQUIERDA: Resumen Créditos + Métodos de Pago -->
          <div class="flex-grow-1" style="flex: 1; overflow-y: auto;">
            <div class="pa-4">
              <!-- Resumen del Cliente y Crédito -->
              <VCard variant="outlined" class="mb-4">
                <VCardText>
                  <div class="d-flex align-center mb-3">
                    <VIcon icon="tabler-user" class="me-2" size="20" />
                    <p class="font-weight-medium text-h6 mb-0">
                      {{ creditsData.client?.name }}
                      {{ creditsData.client?.last_name }}
                    </p>
                  </div>
                  <div class="text-body-2 text-medium-emphasis mb-2">
                    {{ creditsData.client?.identification_type
                    }}{{ creditsData.client?.identification }}
                  </div>
                  <div class="d-flex justify-space-between">
                    <span class="text-body-1">Total pendiente:</span>
                    <span class="text-h6 font-weight-bold">
                      {{
                        formatCurrency(
                          parseFloat(
                            creditsData.total_pending_amount || 0
                          ),
                          selectedCurrency
                        )
                      }}
                    </span>
                  </div>
                </VCardText>
              </VCard>

              <!-- Métodos de Pago con Pestañas -->
              <div class="mt-4">
                <div class="d-flex align-center mb-3">
                  <VIcon icon="tabler-credit-card" class="me-2" size="20" />
                  <p class="font-weight-medium text-h6 mb-0">
                    Métodos de Pago
                  </p>
                </div>

                <VTabs v-model="selectedCurrencyTab" class="mb-3">
                  <VTab
                    v-for="currency in currencies"
                    :key="currency.value"
                    :value="currency.value"
                    class="text-capitalize"
                  >
                    {{ currency.label }}
                  </VTab>
                </VTabs>

                <VTabsWindow v-model="selectedCurrencyTab">
                  <VTabsWindowItem
                    v-for="currency in currencies"
                    :key="currency.value"
                    :value="currency.value"
                  >
                    <div class="d-flex flex-wrap gap-2 mt-2">
                      <VBtn
                        v-for="method in getAvailableMethodsForCurrency(
                          currency.value
                        )"
                        :key="method.value"
                        :class="[
                          'payment-method-btn',
                          {
                            'payment-method-btn--active': isPaymentMethodActive(
                              method.value,
                              currency.value
                            ),
                            'payment-method-btn--added': isPaymentMethodAdded(
                              method.value,
                              currency.value
                            ),
                          },
                        ]"
                        :variant="
                          isPaymentMethodActive(
                            method.value,
                            currency.value
                          )
                            ? 'flat'
                            : 'outlined'
                        "
                        :color="
                          isPaymentMethodActive(
                            method.value,
                            currency.value
                          )
                            ? 'primary'
                            : 'default'
                        "
                        :disabled="
                          remainingAmount <= 0 ||
                          isPaymentMethodAdded(
                            method.value,
                            currency.value
                          )
                        "
                        @click="
                          selectPaymentMethod(
                            method.value,
                            currency.value
                          )
                        "
                        size="small"
                      >
                        <VIcon
                          :icon="getPaymentMethodIcon(method.value)"
                          size="18"
                          class="me-1"
                        />
                        {{ method.label }}
                        <VIcon
                          v-if="
                            isPaymentMethodActive(
                              method.value,
                              currency.value
                            )
                          "
                          icon="tabler-check"
                          size="16"
                          class="ms-1"
                        />
                      </VBtn>
                    </div>
                  </VTabsWindowItem>
                </VTabsWindow>
              </div>
            </div>
          </div>

          <!-- COLUMNA DERECHA: Resumen de Pago (Sticky) -->
          <div
            style="
              position: sticky;
              display: flex;
              flex-direction: column;
              align-self: flex-start;
              inline-size: 400px;
              inset-block-start: 0;
              max-block-size: calc(100vh - 200px);
"
          >
            <VCard variant="outlined" class="flex-grow-1" style="display: flex; flex-direction: column;">
              <VCardText style="flex: 1; overflow-y: auto;">
                <div class="text-h6 font-weight-bold mb-4">Resumen de Pago</div>

                <VDivider class="my-3" />

                <div class="d-flex justify-space-between mb-3">
                  <span class="text-h6 font-weight-bold">Total a Pagar:</span>
                  <span class="text-h6 font-weight-bold">
                    {{
                      formatCurrency(
                        roundedTotalAmountToPay,
                        props.selectedCurrency
                      )
                    }}
                  </span>
                </div>

                <!-- Lista de Pagos -->
                <div
                  v-if="payments.filter((p) => p.method).length > 0"
                  class="mb-3"
                >
                  <div
                    v-for="(payment, idx) in payments.filter((p) => p.method)"
                    :key="idx"
                    class="d-flex justify-space-between align-center mb-3 payment-row"
                    style="min-block-size: 32px;"
                  >
                    <span class="text-body-1">
                      {{
                        getPaymentMethodLabel(
                          payment.method,
                          payment.currency
                        )
                      }}:
                    </span>

                    <div
                      v-if="payment._isInputActive"
                      class="d-flex align-center gap-2 fade-in"
                      style="flex: 0 0 auto;"
                    >
                      <input
                        :value="payment.inputAmount || ''"
                        @input="
                          updatePaymentAmountLive(
                            payment,
                            $event.target.value
                          )
                        "
                        @keydown.enter="handlePaymentEnter($event, payment)"
                        @keyup.tab="handlePaymentTab(payment)"
                        @blur="confirmPaymentAmount(payment)"
                        @focus="
                          $event.target.style.borderBottomColor =
                            'rgb(var(--v-theme-primary))'
                        "
                        :readonly="payment._amountConfirmed"
                        type="text"
                        inputmode="decimal"
                        class="payment-input"
                        :data-payment-index="payments.indexOf(payment)"
                        :placeholder="
                          formatCurrency(
                            getConvertedRemainingAmount(payment.currency),
                            payment.currency
                          )
                        "
                        :style="{
                          border: 'none',
                          borderBottom: payment._amountError
                            ? '2px solid rgb(var(--v-theme-error))'
                            : '1px solid rgba(0, 0, 0, 0.42)',
                          background: payment._amountConfirmed
                            ? 'rgba(0, 0, 0, 0.04)'
                            : 'rgba(0, 0, 0, 0.02)',
                          padding: '4px 8px',
                          width: '120px',
                          textAlign: 'right',
                          fontSize: '14px',
                          transition: 'all 0.2s ease',
                          cursor: payment._amountConfirmed
                            ? 'default'
                            : 'text',
                        }"
                      />
                      <span class="text-caption text-medium-emphasis">{{
                        payment.currency
                      }}</span>

                      <input
                        v-if="
                          requiresReference(payment.method) &&
                          payment._isReferenceActive
                        "
                        :value="payment.reference || ''"
                        @input="
                          (payment.reference = $event.target.value);
                          (payment._referenceError = false)
                        "
                        @keydown.enter.prevent="confirmPaymentComplete(payment)"
                        type="text"
                        class="payment-reference-input"
                        :data-payment-index="payments.indexOf(payment)"
                        placeholder="Referencia"
                        :style="{
                          border: 'none',
                          borderBottom: payment._referenceError
                            ? '2px solid rgb(var(--v-theme-error))'
                            : '1px solid rgba(0, 0, 0, 0.42)',
                          background: 'rgba(0, 0, 0, 0.02)',
                          padding: '4px 8px',
                          width: '90px',
                          fontSize: '13px',
                          transition: 'all 0.2s ease',
                        }"
                      />

                      <VBtn
                        icon
                        variant="text"
                        size="x-small"
                        color="success"
                        @click="confirmPaymentComplete(payment)"
                      >
                        <VIcon icon="tabler-check" size="16" />
                        <VTooltip activator="parent" location="top"
                          >Confirmar</VTooltip
                        >
                      </VBtn>
                      <VBtn
                        icon
                        variant="text"
                        size="x-small"
                        color="error"
                        :disabled="!isLastPaymentAdded(payment)"
                        @click="
                          removePaymentFromSummary(payments.indexOf(payment))
                        "
                      >
                        <VIcon icon="tabler-x" size="16" />
                        <VTooltip activator="parent" location="top">
                          {{
                            isLastPaymentAdded(payment)
                              ? "Eliminar"
                              : "Solo se puede eliminar el último método agregado"
                          }}
                        </VTooltip>
                      </VBtn>
                    </div>

                    <div
                      v-else
                      class="d-flex flex-column align-end gap-1 fade-in"
                      style="flex: 0 0 auto;"
                    >
                      <div class="d-flex align-center gap-2">
                        <span
                          class="text-body-1 font-weight-medium text-error"
                        >
                          -{{
                            formatCurrency(
                              payment.amount || 0,
                              payment.currency
                            )
                          }}
                        </span>
                        <VBtn
                          icon
                          variant="text"
                          size="x-small"
                          color="primary"
                          @click="editPaymentAmount(payment)"
                        >
                          <VIcon icon="tabler-pencil" size="16" />
                          <VTooltip activator="parent" location="top"
                            >Editar</VTooltip
                          >
                        </VBtn>
                        <VBtn
                          icon
                          variant="text"
                          size="x-small"
                          color="error"
                          :disabled="!isLastPaymentAdded(payment)"
                          @click="
                            removePaymentFromSummary(
                              payments.indexOf(payment)
                            )
                          "
                        >
                          <VIcon icon="tabler-x" size="16" />
                          <VTooltip activator="parent" location="top">
                            {{
                              isLastPaymentAdded(payment)
                                ? "Eliminar"
                                : "Solo se puede eliminar el último método agregado"
                            }}
                          </VTooltip>
                        </VBtn>
                      </div>
                      <div
                        v-if="
                          requiresReference(payment.method) &&
                          payment.reference
                        "
                        class="text-caption text-medium-emphasis"
                        style="font-size: 11px;"
                      >
                        Ref: {{ payment.reference }}
                      </div>
                    </div>
                  </div>
                </div>

                <VDivider class="my-3" />

                <div class="d-flex justify-space-between mb-4">
                  <span class="text-h6 font-weight-bold">Restante:</span>
                  <span
                    class="text-h6 font-weight-bold"
                    :class="
                      remainingAmount <= 0 ? 'text-success' : 'text-error'
                    "
                  >
                    {{
                      formatCurrency(
                        getConvertedRemainingAmount(selectedCurrencyTab),
                        selectedCurrencyTab
                      )
                    }}
                  </span>
                </div>

                <div
                  v-if="showChangeAmount"
                  class="d-flex justify-space-between mb-4"
                >
                  <span class="text-body-1 font-weight-medium"
                    >Monto Devuelto:</span
                  >
                  <span class="text-body-1 font-weight-bold text-success">
                    {{ formatCurrency(changeAmountInCOP, "COP") }}
                  </span>
                </div>
              </VCardText>

              <VDivider />
              <VCardActions class="pa-4 d-flex flex-column gap-2">
                <VBtn
                  color="secondary"
                  variant="outlined"
                  @click="closeModal"
                  block
                >
                  Cancelar
                </VBtn>
                <VBtn
                  :style="
                    remainingAmount <= 0 && !hasMissingReferences()
                      ? 'background-color: #28C76F; color: white;'
                      : 'background-color: rgba(0, 0, 0, 0.12); color: rgba(0, 0, 0, 0.38);'
                  "
                  variant="flat"
                  @click="handleCompletePurchase"
                  block
                  :disabled="
                    currentProgress === 0 &&
                    (remainingAmount > 0.01 || hasMissingReferences())
                  "
                >
                  {{ continueButtonText }}
                </VBtn>
              </VCardActions>
            </VCard>
          </div>
        </div>
      </VCardText>

      <!-- Ticket de confirmación -->
      <VCardText v-else-if="currentProgress === 100">
        <div class="d-flex justify-center">
          <div style="inline-size: 50%;">
            <div class="text-center">
              <img width="130" :src="logoSrc" alt="Logotipo" />
            </div>
            <div class="d-flex flex-wrap justify-space-between">
              <span class="font-weight-bold text-h6 mt-4">Pago de Créditos</span>
              <div class="text-end">
                <span class="d-block font-weight-bold text-h6 mt-4">
                  {{ formatDateTime(today, "date") }}
                  {{ formatDateTime(today, "time") }}
                </span>
              </div>
            </div>

            <div class="d-flex flex-wrap justify-space-between">
              <span class="font-weight-bold text-h6">Cajero</span>
              <span class="font-weight-bold text-h6">{{ userUsername }}</span>
            </div>

            <div class="d-flex flex-wrap justify-space-between">
              <span class="font-weight-bold text-h6">Cédula</span>
              <span class="font-weight-bold text-h6">
                {{ creditsData.client?.identification_type || "N/A" }}
                {{ creditsData.client?.identification || "N/A" }}
              </span>
            </div>

            <div class="d-flex flex-wrap justify-space-between">
              <span class="font-weight-bold text-h6">Cliente</span>
              <span class="font-weight-bold text-h6">
                {{ creditsData.client?.name }}
                {{ creditsData.client?.last_name }}
              </span>
            </div>

            <div
              v-if="validPaymentsForTicket.length > 0"
              class="d-flex flex-wrap justify-space-between"
            >
              <p class="font-weight-bold text-h6">Métodos de Pago</p>
              <div class="text-end">
                <p
                  v-for="(payment, pIndex) in validPaymentsForTicket"
                  :key="`ticket-payment-method-${pIndex}`"
                  class="font-weight-bold my-1"
                >
                  <span>
                    {{
                      getPaymentMethodLabel(
                        payment.method,
                        payment.currency
                      )
                    }}
                    ({{ payment.currency }})
                  </span>
                </p>
              </div>
            </div>

            <div class="d-flex flex-wrap justify-space-between">
              <p class="font-weight-bold text-h6 mt-2">Total a pagar:</p>
              <p class="font-weight-bold text-h6 mt-2">
                {{
                  formatCurrency(
                    roundedTotalAmountToPay,
                    props.selectedCurrency
                  )
                }}
              </p>
            </div>

            <div
              v-if="validPaymentsForTicket.length > 0"
              class="d-flex flex-wrap justify-space-between"
            >
              <p class="font-weight-bold text-h6 mt-2">Pago:</p>
              <div class="text-end">
                <p
                  v-for="(payment, pIndex) in validPaymentsForTicket"
                  :key="`ticket-payment-amount-${pIndex}`"
                  class="font-weight-bold my-1"
                >
                  <span>
                    {{
                      getPaymentMethodLabel(
                        payment.method,
                        payment.currency
                      )
                    }}:
                    {{
                      formatCurrency(
                        payment.amount || 0,
                        payment.currency
                      )
                    }}
                  </span>
                </p>
              </div>
            </div>

            <div
              v-if="showChangeAmount"
              class="d-flex flex-wrap justify-space-between"
            >
              <p class="font-weight-bold text-h6 mt-2">Devolución:</p>
              <p class="font-weight-bold text-h6 mt-2">
                {{ formatCurrency(changeAmountInCOP, "COP") }}
              </p>
            </div>

            <p class="font-weight-bold text-center text-success">
              ¡GRACIAS POR PREFERIRNOS!
            </p>
          </div>
        </div>

        <VDivider class="my-4" />
        <VCardActions class="pa-4 d-flex flex-column gap-2">
          <VBtn
            color="primary"
            variant="flat"
            @click="handleCompletePurchase"
            block
            size="large"
          >
            {{ continueButtonText }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="outlined"
            @click="handleCancelAfterTicket"
            block
          >
            Cancelar
          </VBtn>
        </VCardActions>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.payment-input {
  outline: none;
}

.payment-input:focus {
  background: rgba(var(--v-theme-primary), 0.04) !important;
  border-block-end-color: rgb(var(--v-theme-primary)) !important;
}

.ticket-header {
  animation: fade-in 0.5s ease-out;
}

.fade-in {
  animation: fade-in 0.3s ease-in;
}

@keyframes fade-in {
  from {
    opacity: 0;
  }

  to {
    opacity: 1;
  }
}

.payment-row {
  transition: all 0.2s ease;
}

.payment-method-btn {
  transition: all 0.2s ease;
}

.payment-method-btn--active {
  box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.3);
}

.payment-method-btn--added {
  cursor: not-allowed;
  opacity: 0.6;
}

.payment-method-btn--added:hover {
  transform: none;
}
</style>
