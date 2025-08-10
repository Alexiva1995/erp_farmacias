<script setup>
import { defineProps, defineEmits, computed, ref, watch } from "vue";
import { onMounted, onBeforeUnmount } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatDateTime } from "@/utils/formatDateTime";
import { toast } from "@/plugins/sweetalert";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  orderData: {
    type: Object,
    default: () => ({}),
  },
  totalAmount: {
    type: Number,
    default: 0,
  },
  selectedCurrency: {
    type: String,
    default: "COP",
  },
  orderProducts: {
    type: Array,
    default: () => [],
  },
  selectedDisplayCurrency: {
    type: String,
    default: "COP",
  },
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
const progressStages = [0, 50, 100];
const currentStageIndex = ref(0);

const balanceSwitch = ref(false);
const invoiceSwitch = ref(false);

const changeAmountUSD = ref(0);

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
    { label: "Crédito", value: "credit" },
  ],
};

const exchangeRates = ref({});

const continueButtonText = computed(() => {
  return currentProgress.value === 100 ? "Finalizar" : "Continuar";
});

const isTransferMethod = (method) =>
  ["bank_transfer", "bank_transfer_bs", "mobile_payment", "card"].includes(
    method
  );

function roundToTwoDecimalPlaces(num) {
  return Number(Math.round(num + "e+2") + "e-2");
}

const getPaymentMethodLabel = (methodValue, currency) => {
  if (methodValue === "balance") {
    return "Saldo del Cliente";
  }

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

const fetchExchangeRates = async () => {
  try {
    const response = await fetch("/api/exchange-rates");
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }
    const apiRates = await response.json();
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
  } catch (error) {
    toast.error("No se pudieron cargar las tasas de cambio.");
    console.error("Error fetching exchange rates:", error);
  }
};

onMounted(() => {
  fetchExchangeRates();
});

const roundedTotalAmountToPay = computed(() => {
  if (props.selectedCurrency === "COP") {
    return roundUpToNearestHundred(props.totalAmount);
  }
  return props.totalAmount;
});

const remainingAmount = computed(() => {
  const rawDifference = props.totalAmount - totalPaidAmount.value;
  if (props.selectedCurrency === "COP") {
    return roundUpToNearestHundred(rawDifference);
  }

  return roundToTwoDecimalPlaces(rawDifference);
});

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

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
  resetProgress();
};

const handleCompletePurchase = () => {
  const tolerance = 1;
  payments.value.forEach((p, index) => {
    if (p.method === "balance") {
      return;
    }

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

  if (currentProgress.value === 50 && payments.value[0].method !== "credit") {
    let finalRemainingAmount = remainingAmount.value;
    if (Math.abs(finalRemainingAmount) < tolerance) {
      finalRemainingAmount = 0;
    }

    if (finalRemainingAmount < 0 && !showChangeAmount.value) {
      toast.error("El monto total pagado excede el monto de la compra.");
      return;
    }

    if (finalRemainingAmount > 0) {
      toast.error(
        "El monto total no ha sido cubierto. Agrega más pagos para continuar."
      );
      return;
    }

    const invalidPayment = payments.value.find((p) => {
      if (p.method === "balance") {
        return false;
      }

      if (!p.method) return true;
      if (isTransferMethod(p.method) && !p.reference) return true;
      if (
        p.method !== "credit" &&
        (Number(p.amount) <= 0 || p.amount === null)
      ) {
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
      props.orderData.id,
      payments.value,
      hasCreditPayment.value,
      changeAmountInCOP.value,
      changeAmountInUSD.value,
      {
        balance_switch: balanceSwitch.value,
        invoice_switch: invoiceSwitch.value,
      }
    );
    dialogVisible.value = false;
    resetProgress();
  }
};

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
  balanceSwitch.value = false;
  invoiceSwitch.value = false;
};

const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});

watch(
  () => props.isDialogVisible,
  (newVal) => {
    if (newVal) {
      console.log(
        "El modal se ha abierto. Moneda de la orden:",
        props.selectedCurrency
      );
      console.log("Prop totalAmount:", props.totalAmount);
      resetProgress();
    }
  }
);

const getProductPrice = (product, currency) => {
  const taxRate = product.taxRate || 0;
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    basePrice = product.price || 0;
  }
  let priceWithIva = basePrice * (1 + taxRate);
  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }
  return priceWithIva;
};

const hasCreditPayment = computed(() => {
  return payments.value.some((payment) => payment.method === "credit");
});

const totalSelectedQuantity = computed(() => {
  let total = 0;
  props.orderProducts.forEach((product) => {
    const quantity = parseInt(product.selectedQuantity);
    if (!isNaN(quantity) && quantity > 0) {
      total += quantity;
    }
  });
  return total;
});

const totalCashPaidInUSDOrCOP = computed(() => {
  let cashAmount = 0;
  payments.value.forEach((payment) => {
    if (
      (payment.method === "cash_usd" || payment.method === "cash_cop") &&
      payment.amount
    ) {
      if (payment.currency === props.selectedCurrency) {
        cashAmount += Number(payment.amount);
      } else {
        const rate =
          exchangeRates.value?.[payment.currency]?.[props.selectedCurrency];
        if (rate) {
          cashAmount += Number(payment.amount) * rate;
        }
      }
    }
  });
  return roundToTwoDecimalPlaces(cashAmount);
});

const changeAmount = computed(() => {
  const diff = totalPaidAmount.value - props.totalAmount;
  if (props.selectedCurrency === "COP") {
    return Math.max(
      0,
      roundToTwoDecimalPlaces(
        totalPaidAmount.value - roundUpToNearestHundred(props.totalAmount)
      )
    );
  } else {
    return Math.max(0, roundToTwoDecimalPlaces(totalPaidAmount.value - props.totalAmount));
  }
});

const changeAmountInUSD = computed(() => {
  // 1. Identificar pagos en efectivo en USD
  const cashPaymentsInUSD = payments.value.filter(
    (p) => p.method === "cash_usd" && p.currency === "USD"
  );

  // Si no hay pagos en efectivo en USD, el vuelto es cero.
  if (cashPaymentsInUSD.length === 0) {
    return 0;
  }

  // 2. Calcular el total de los pagos en efectivo en USD
  let totalCashPaidInUSD = 0;
  cashPaymentsInUSD.forEach((p) => {
    totalCashPaidInUSD += Number(p.amount) || 0;
  });

  // 3. Calcular el total de la orden en USD
  // Si la moneda de la orden no es USD, la convertimos.
  let totalOrdenEnUSD;
  if (props.selectedCurrency === "USD") {
    totalOrdenEnUSD = props.totalAmount;
  } else {
    const rate = exchangeRates.value?.[props.selectedCurrency]?.["USD"];
    if (!rate) {
      console.error(
        `No se encontró la tasa de cambio de ${props.selectedCurrency} a USD.`
      );
      return 0;
    }
    totalOrdenEnUSD = props.totalAmount / rate;
  }

  // 4. Calcular el vuelto en USD, solo si la diferencia es positiva.
  const diff = totalCashPaidInUSD - totalOrdenEnUSD;
  return Math.max(0, roundToTwoDecimalPlaces(diff));
});


const changeAmountInCOP = computed(() => {
  // Primero, obtenemos el vuelto en la moneda de la orden
  const vueltoEnMonedaOrden = changeAmount.value;
  
  // Si la moneda de la orden ya es COP, no hacemos nada.
  if (props.selectedCurrency === "COP") {
    return vueltoEnMonedaOrden;
  }
  
  // Si no es COP, la convertimos.
  const rate = exchangeRates.value?.[props.selectedCurrency]?.["COP"];
  if (rate) {
    const vueltoConvertido = vueltoEnMonedaOrden * rate;
    return roundUpToNearestHundred(vueltoConvertido); // Aplicamos el redondeo para COP
  }
  
  // En caso de que no haya tasa de cambio, devolvemos 0.
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

watch(balanceSwitch, (newVal) => {
  if (newVal) {
    if (payments.value[0] && payments.value[0].method !== "balance") {
      payments.value[0].amount = null;
    }

    const clientBalance = props.orderData.client?.balance || 0;
    if (clientBalance <= 0) {
      toast.error("El cliente no tiene saldo disponible.");
      balanceSwitch.value = false;
      return;
    }

    let remainingAmountInOrderCurrency = remainingAmount.value;

    let rateToUSD;
    if (props.selectedCurrency === "USD") {
      rateToUSD = 1;
    } else {
      rateToUSD = exchangeRates.value?.[props.selectedCurrency]?.["USD"];
    }

    if (!rateToUSD) {
      toast.error(
        `No se encontró la tasa de cambio de ${props.selectedCurrency} a USD.`
      );
      balanceSwitch.value = false;
      return;
    }

    const remainingAmountInUSD = remainingAmountInOrderCurrency / rateToUSD;
    const amountToUse = Math.min(remainingAmountInUSD, clientBalance);
    const formattedAmount = parseFloat(amountToUse.toFixed(2));
    const balancePayment = {
      method: "balance",
      amount: formattedAmount,
      currency: "USD",
      reference: "",
    };
    payments.value.unshift(balancePayment);
  } else {
    payments.value = payments.value.filter((p) => p.method !== "balance");
    if (payments.value.length === 0) {
      payments.value.push({
        method: null,
        amount: null,
        reference: null,
        currency: props.selectedCurrency,
      });
    }
  }
});
</script>

<template>
  <VDialog v-model="dialogVisible" max-width="500px">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">Compra</span>
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
            Detalles de compra
          </p>
          <p class="text-center mt-2 text-subtitle-2 text-medium-emphasis">
            Métodos de pago
          </p>
          <p class="text-center mt-2 text-subtitle-2 text-medium-emphasis">
            Ticke de compra
          </p>
        </div>
      </div>

      <VCardText v-if="currentProgress === 0">
        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6">Total de productos:</p>
          <VChip
            label
            :color="chipColor"
            variant="tonal"
            density="default"
            size="small"
            draggable="false"
            class="ms-auto"
          >
            <span class="font-weight-medium">{{ totalSelectedQuantity }}</span>
          </VChip>
        </div>

        <div
          class="scrollable-list-container"
          :class="{ 'show-scroll': props.orderProducts.length > 2 }"
        >
          <VList class="card-list" density="compact" nav>
            <VListItem
              v-for="product in props.orderProducts"
              :key="product.id"
              class="rounded-0"
            >
              <template #prepend>
                <span>{{ product.selectedQuantity }} x</span>
              </template>

              <VListItemTitle class="font-weight-medium me-4 mx-2">{{
                product.title
              }}</VListItemTitle>
              <VListItemSubtitle class="mx-2"
                >{{ product.active_ingredient }}
                {{ product.laboratory }}</VListItemSubtitle
              >

              <template #append>
                <div class="d-flex align-center">
                  <span class="text-body-1 me-2">{{
                    formatCurrency(
                      getProductPrice(product, props.selectedCurrency) *
                        product.selectedQuantity,
                      props.selectedCurrency
                    )
                  }}</span>
                </div>
              </template>
            </VListItem>
          </VList>
        </div>
        <VDivider />
        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6 mt-4">Total a pagar:</p>
          <p class="font-weight-bold text-h6 mt-4">
            {{
              formatCurrency(roundedTotalAmountToPay, props.selectedCurrency)
            }}
          </p>
        </div>
      </VCardText>

      <VCardText v-else-if="currentProgress === 50">
        <div class="d-flex flex-wrap justify-space-between">
          <span>Saldo {{ props.orderData.client?.balance || "0.00" }}</span>
          <VSwitch v-model="balanceSwitch" />
        </div>
        <div class="d-flex flex-wrap justify-space-between">
          <span>Factura</span>
          <VSwitch v-model="invoiceSwitch" />
        </div>
        <VDivider class="my-4" />

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
                  if (
                    index === 0 &&
                    payment.currency !== 'USD' &&
                    m.value === 'credit'
                  ) {
                    return false;
                  }
                  if (index > 0 && m.value === 'credit') {
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
        v-if="payment.method === 'balance'"
        :model-value="payment.amount.toFixed(2)"
        label="Monto del pago"
        :placeholder="getPlaceholderText(index, payment)"
        type="text"
        class="my-4"
        readonly
        :persistent-hint="true"
        hint="Monto del saldo no editable."
      />

      <VTextField
        v-else-if="payment.method && payment.method !== 'credit'"
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

          <VTextField
            v-if="payment.method === 'credit'"
            :model-value="formatCurrency(remainingAmount, payment.currency)"
            label="Monto del crédito"
            readonly
            class="mt-4"
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
              formatCurrency(roundedTotalAmountToPay, props.selectedCurrency)
            }}
          </p>
        </div>

        <div
          v-if="showChangeAmount"
          class="d-flex flex-wrap justify-space-between"
        >
          <p class="font-weight-bold text-h6 mt-2">Monto Devuelto:</p>
          <p class="font-weight-bold text-h6 mt-2">
            {{ formatCurrency(changeAmountInCOP, 'COP') }}
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

      <VCardText v-else-if="currentProgress === 100">
        <div class="text-center">
          <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
        </div>
        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6 mt-4">
            Orden N° {{ props.orderData.id }}
          </span>
          <div class="text-end">
            <span class="d-block font-weight-bold text-h6 mt-4">
              Fecha {{ formatDateTime(props.orderData.created_at, "date") }}
            </span>
            <span class="d-block font-weight-bold text-h6">
              {{ formatDateTime(props.orderData.created_at, "time") }}
            </span>
          </div>
        </div>

        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6"> Cajero </span>
          <span class="font-weight-bold text-h6">
            {{ props.orderData.seller?.username || "N/A" }}
          </span>
        </div>

        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6"> Cedula </span>
          <span class="font-weight-bold text-h6">
            {{ props.orderData.client?.identification_type || "N/A" }}
            {{ props.orderData.client?.identification || "N/A" }}
          </span>
        </div>

        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6"> Cliente </span>
          <span class="font-weight-bold text-h6">
            {{ props.orderData.client.name }}
            {{ props.orderData.client.last_name }}
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

        <div
          class="scrollable-list-container"
          :class="{ 'show-scroll': props.orderProducts.length > 2 }"
        >
          <VList class="card-list" density="compact" nav>
            <VListItem
              v-for="product in props.orderProducts"
              :key="product.id"
              class="rounded-0"
            >
              <template #prepend>
                <span>{{ product.selectedQuantity }} x</span>
              </template>

              <VListItemTitle class="font-weight-medium me-4 mx-2">{{
                product.title
              }}</VListItemTitle>
              <VListItemSubtitle class="mx-2"
                >{{ product.active_ingredient }}
                {{ product.laboratory }}</VListItemSubtitle
              >

              <template #append>
                <div class="d-flex align-center">
                  <span class="text-body-1 me-2">{{
                    formatCurrency(
                      getProductPrice(product, props.selectedCurrency) *
                        product.selectedQuantity,
                      props.selectedCurrency
                    )
                  }}</span>
                </div>
              </template>
            </VListItem>
          </VList>
        </div>
        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6 mt-2">Total a pagar:</p>
          <p class="font-weight-bold text-h6 mt-2">
            {{
              formatCurrency(roundedTotalAmountToPay, props.selectedCurrency)
            }}
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
          v-if="hasCreditPayment"
          class="d-flex flex-wrap justify-space-between"
        >
          <p class="font-weight-bold text-h6">Crédito:</p>
          <p class="font-weight-bold text-h6">
            {{
              formatCurrency(roundedTotalAmountToPay, props.selectedCurrency)
            }}
          </p>
        </div>
        <div
          v-if="showChangeAmount"
          class="d-flex flex-wrap justify-space-between"
        >
          <p class="font-weight-bold text-h6 mt-2">Devolución:</p>
          <p class="font-weight-bold text-h6 mt-2">
            {{ formatCurrency(changeAmountInCOP, 'COP') }}
          </p>
        </div>

        <p class="font-weight-bold text-center text-success">
          ¡GRACIAS POR SU COMPRA!
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

<style scoped>
.scrollable-list-container {
  max-height: 95px;
  overflow-y: hidden;
  transition: overflow-y 0.3s ease-in-out;
}
.scrollable-list-container.show-scroll {
  overflow-y: auto;
}
</style>
