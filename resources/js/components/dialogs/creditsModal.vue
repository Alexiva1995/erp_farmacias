<script setup>
import { defineProps, defineEmits, computed, ref, watch } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatDateTime } from "@/utils/formatDateTime";
import { toast } from "@/plugins/sweetalert";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { useAuthStore } from "@/stores/auth";

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

const today = new Date();

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

const fetchExchangeRates = async () => {
  try {
    const response = await fetch("/api/exchange-rates");
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }
    const apiRates = await response.json();
    const formattedRates = {};
    console.log(apiRates)
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
  } catch (error) {
    toast.error("No se pudieron cargar las tasas de cambio.");
    console.error("Error fetching exchange rates:", error);
  }
};

onMounted(() => {
  fetchExchangeRates();
});

const continueButtonText = computed(() => {
  return currentProgress.value === 100 ? "Finalizar" : "Continuar";
});

const resetProgress = () => {
  currentProgress.value = 0;
  currentStageIndex.value = 0;
  payments.value = [
    {
      method: null,
      amount: null,
      reference: null,
      currency: props.selectedCurrency,
    },
  ];
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

const addPaymentBlock = () => {
  if (remainingAmount.value > 0) {
    payments.value.push({
      method: null,
      amount: null,
      reference: null,
      currency: props.selectedCurrency,
    });
  } else {
    toast.error("El monto total ya ha sido cubierto.");
  }
};

const canAddPaymentBlock = computed(() => {
  const lastPayment = payments.value[payments.value.length - 1];
  if (remainingAmount.value <= 0) return false;
  if (payments.value[0].method === "credit") return false;
  if (!lastPayment.method) return false;
  if (isTransferMethod(lastPayment.method) && !lastPayment.reference)
    return false;

  if (
    lastPayment.method !== "credit" &&
    (Number(lastPayment.amount) <= 0 || lastPayment.amount === null)
  ) {
    return false;
  }
  return true;
});

const showChangeAmount = computed(() => {
  const hasRelevantCashPayment = payments.value.some(
    (payment) =>
      (payment.method === "cash_usd" && payment.currency === "USD") ||
      (payment.method === "cash_cop" && payment.currency === "COP")
  );
  return hasRelevantCashPayment && changeAmount.value > 0;
});

const remainingAmount = computed(() => {
  const rawDifference =
    props.creditsData.total_pending_amount - totalPaidAmount.value;
  if (props.selectedCurrency === "COP") {
    return roundUpToNearestHundred(rawDifference);
  }

  return roundToTwoDecimalPlaces(rawDifference);
});

const totalPaidAmount = computed(() => {
  let currentSum = 0;
  payments.value.forEach((payment, index) => {
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

function roundToTwoDecimalPlaces(num) {
  return Number(Math.round(num + "e+2") + "e-2");
}

const changeAmount = computed(() => {
  const diff = totalPaidAmount.value - props.creditsData.total_pending_amount;
  if (props.selectedCurrency === "COP") {
    return Math.max(
      0,
      roundToTwoDecimalPlaces(
        totalPaidAmount.value -
          roundUpToNearestHundred(props.creditsData.total_pending_amount)
      )
    );
  } else {
    return Math.max(
      0,
      roundToTwoDecimalPlaces(
        totalPaidAmount.value - props.creditsData.total_pending_amount
      )
    );
  }
});

const handleCompletePurchase = () => {
  const tolerance = 1;
  payments.value.forEach((p, index) => {
    if (
      isTransferMethod(p.method) &&
      (p.amount === null || Number(p.amount) === 0)
    ) {
      let amountToAssign = 0;
      if (index === 0) {
        amountToAssign = remainingAmount.value;
        p.currency = props.selectedCurrency;
      } else {
        const baseCurrency = props.selectedCurrency;
        const targetCurrency = p.currency;
        amountToAssign = remainingAmount.value;

        if (baseCurrency !== targetCurrency) {
          const rate = exchangeRates.value?.[baseCurrency]?.[targetCurrency];
          if (rate) {
            amountToAssign = amountToAssign * rate;
          } else {
            console.warn(
              `Advertencia: No se encontró tasa de cambio de ${baseCurrency} a ${targetCurrency}. No se pudo asignar automáticamente el monto.`
            );
            return;
          }
        }
      }
      p.amount = parseFloat(amountToAssign.toFixed(2));
    }
  });

  if (currentProgress.value === 0) {
    let finalRemainingAmount = remainingAmount.value;
    if (Math.abs(finalRemainingAmount) < tolerance) {
      finalRemainingAmount = 0;
    }

    if (finalRemainingAmount < 0 && !showChangeAmount.value) {
      toast.error("El monto total pagado excede el monto de la compra.");
      return;
    }

    /*if (finalRemainingAmount > 0) {
      toast.error("El monto total no ha sido cubierto. Agrega más pagos para continuar.");
      return;
    }*/

    const invalidPayment = payments.value.find((p) => {
      if (!p.method) return true;
      if (isTransferMethod(p.method) && !p.reference) return true;
      if ((Number(p.amount) <= 0 || p.amount === null)) {
        return true;
      }
      return false;
    });

    if (invalidPayment) {
      toast.error(
        "Por favor, revisa y completa los campos de todos los pagos."
      );
      return;
    }
  }

  if (currentProgress.value < 100) {
    currentStageIndex.value++;
    if (currentStageIndex.value < progressStages.length) {
      currentProgress.value = progressStages[currentStageIndex.value];
    } else {
      currentProgress.value = 100;
    }
  } else {
    emit(
      "purchase-completed",
      payments.value,
      changeAmountInCOP.value,
      changeAmountInUSD.value
    );
    dialogVisible.value = false;
    resetProgress();
  }
};

const roundedTotalAmountToPay = computed(() => {
  console.log();
  return props.creditsData.total_pending_amount;
});

const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});

const getPaymentMethodLabel = (methodValue, currency) => {
  if (!methodValue) return "N/A";
  const methodsForCurrency = paymentMethodsByCurrency[currency];
  if (methodsForCurrency) {
    const foundMethod = methodsForCurrency.find((m) => m.value === methodValue);
    if (foundMethod) {
      return foundMethod.label;
    }
  }
  for (const key in paymentMethodsByCurrency) {
    const methods = paymentMethodsByCurrency[key];
    const foundMethod = methods.find((m) => m.value === methodValue);
    if (foundMethod) {
      return foundMethod.label;
    }
  }
  return methodValue.replace(/_/g, " ").toUpperCase();
};


const changeAmountInCOP = computed(() => {
  const vueltoEnMonedaOrden = changeAmount.value;
  if (props.selectedCurrency === "COP") {
    return vueltoEnMonedaOrden;
  }
  const rate = exchangeRates.value?.[props.selectedCurrency]?.["COP"];
  if (rate) {
    const vueltoConvertido = vueltoEnMonedaOrden * rate;
    return roundUpToNearestHundred(vueltoConvertido);
  }
  return 0;
});

const changeAmountInUSD = computed(() => {
  const cashPaymentsInUSD = payments.value.filter(
    (p) => p.method === "cash_usd" && p.currency === "USD"
  );
  if (cashPaymentsInUSD.length === 0) {
    return 0;
  }
  let totalCashPaidInUSD = 0;
  cashPaymentsInUSD.forEach((p) => {
    totalCashPaidInUSD += Number(p.amount) || 0;
  });
  let totalOrdenEnUSD;
  if (props.selectedCurrency === "USD") {
    totalOrdenEnUSD = props.creditsData.total_pending_amount;
  } else {
    const rate = exchangeRates.value?.[props.selectedCurrency]?.["USD"];
    if (!rate) {
      console.error(
        `No se encontró la tasa de cambio de ${props.selectedCurrency} a USD.`
      );
      return 0;
    }
    totalOrdenEnUSD = props.creditsData.total_pending_amount / rate;
  }
  const diff = totalCashPaidInUSD - totalOrdenEnUSD;
  return Math.max(0, roundToTwoDecimalPlaces(diff));
});
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

          <div class="my-4">
            <VRadioGroup v-model="payment.method" inline>
              <VRadio
                v-for="method in paymentMethodsByCurrency[payment.currency] ||
                []"
                :key="method.value"
                :label="method.label"
                :value="method.value"
              />
            </VRadioGroup>
          </div>

          <VTextField
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
              formatCurrency(
                parseFloat(props.creditsData.total_pending_amount),
                props.selectedCurrency
              )
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

      <VCardText v-if="currentProgress === 100">
        <div class="text-center">
          <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
        </div>
        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6"> Fecha </span>
          <span class="font-weight-bold text-h6">
            {{ formatDateTime(today, "date") }}
            {{ formatDateTime(today, "time") }}
          </span>
        </div>
        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6"> Cajero </span>
          <span class="font-weight-bold text-h6">
            {{ userUsername }}
          </span>
        </div>
        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6"> Cedula </span>
          <span class="font-weight-bold text-h6">
            {{ props.creditsData.client?.identification_type || "N/A" }}
            {{ props.creditsData.client?.identification || "N/A" }}
          </span>
        </div>

        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6"> Cliente </span>
          <span class="font-weight-bold text-h6">
            {{ props.creditsData.client.name }}
            {{ props.creditsData.client.last_name }}
          </span>
        </div>

        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6">Métodos de Pago</p>
          <div class="text-end">
            <p
              v-for="(payment, pIndex) in payments"
              :key="`ticket-payment-${pIndex}`"
              class="font-weight-bold my-1"
            >
              <span
                >{{
                  getPaymentMethodLabel(payment.method, payment.currency)
                }}
                ({{ payment.currency }})</span
              >
            </p>
          </div>
        </div>

        <div>
          <VList class="card-list" density="compact" nav>
            <VListItem
              :key="props.creditsData.id"
              class="rounded-0"
            >
              <VListItemTitle class="font-weight-medium me-4 mx-2">Créditos</VListItemTitle>
              <template #append>
                <div class="d-flex align-center">
                  <span class="text-body-1 me-2">{{formatCurrency(parseFloat(props.creditsData.total_pending_amount), props.selectedCurrency)}}</span>
                </div>
              </template>
            </VListItem>
          </VList>
        </div>

        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6 mt-2">Total a pagar:</p>
          <p class="font-weight-bold text-h6 mt-2">
            {{ roundedTotalAmountToPay }} {{ props.selectedCurrency }}
          </p>
        </div>

        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6 mt-2">Pago:</p>
          <div class="text-end">
            <p
              v-for="(payment, pIndex) in payments"
              :key="`ticket-payment-${pIndex}`"
              class="font-weight-bold my-1"
            >
              <span>
                {{ formatCurrency(payment.amount || 0, payment.currency) }}
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
      </VCardText>

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
