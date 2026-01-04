<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed, defineEmits, defineProps, onMounted, ref, watch } from "vue";

const chipColor = "primary";

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
  companyDiscountTotal: {
    type: Number,
    default: 0,
  },
  selectedDiscountType: {
    type: String,
    default: null,
  },
  doctorDiscountTotal: {
    type: Number,
    default: 0,
  },
  recipeDiscountTotal: {
    type: Number,
    default: 0,
  },
  expirationDiscountTotal: {
    type: Number,
    default: 0,
  },
  activeDoctorOffers: {
    type: Array,
    default: () => [],
  },
  prescriptionDiscountPercentage: {
    type: Number,
    default: 0,
  },
  activeCompanyOffers: {
    type: Array,
    default: () => [],
  },
  globalDiscount: {
    type: Object,
    default: () => null, // Por defecto null si no hay descuento activo
  },
  isSpecialTaxpayer: {
    type: Boolean,
    default: false,
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
const progressStages = [0, 100];
const currentStageIndex = ref(0);

const invoiceSwitch = ref(false);
const changeAmountUSD = ref(0);

// ELIMINAR: const speSwitch = ref(false); - Ya no se usa

const ratesLoaded = ref(false);

const payments = ref([
  {
    method: null,
    amount: null,
    reference: null,
    currency: props.selectedCurrency,
    debounceTimeout: null,
    inputAmount: null,
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
    { label: "T. Debito", value: "debit_card" },
    { label: "T. Crédito", value: "credit_card" },
  ],
  USD: [
    { label: "Efectivo", value: "cash_usd" },
    { label: "Binance", value: "binance" },
    { label: "PayPal", value: "paypal" },
    { label: "Crédito", value: "credit" },
    { label: "Saldo", value: "balance" },
  ],
};

const exchangeRates = ref({});

const isCredit = (value) => value === "credit" || value === "credit_card";

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


//funcion de sujeto pasivo especial-esta menu(configuracion)
const appliesSpecialTax = computed(() => {
  return props.isSpecialTaxpayer && (props.selectedCurrency === 'USD' || props.selectedCurrency === 'COP');
});

const specialTaxAmount = computed(() => {
  if (!appliesSpecialTax.value) return 0;
  let tax = props.totalAmount * 0.03;
  if (props.selectedCurrency === "COP") {
    tax = Math.ceil(tax / 100) * 100;
  }
  return tax;
});


const getPaymentMethodLabel = (methodValue, currency) => {
  if (methodValue === "balance") {
    return "Saldo";
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

const totalPaidAmountNonCash = computed(() => {
  let currentSum = 0;
  payments.value.forEach((payment) => {
    if (!payment.method || !payment.method.startsWith("cash_")) {
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
    }
  });
  return currentSum;
});

const fetchExchangeRates = async () => {
  ratesLoaded.value = false;
  try {
    const response = await axios.get("/public/exchange-rates");
    if (response.status != 200) {
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

// ACTUALIZADO: Eliminar lógica SPE adicional, ahora se calcula automáticamente
const roundedTotalAmountToPay = computed(() => {
  //let baseAmount = props.totalAmount;
  let baseAmount = props.totalAmount;

  // ELIMINAR: La lógica SPE antigua que sumaba 75% adicional
  // El descuento SPE ya está incluido en props.totalAmount

  if (appliesSpecialTax.value) {
    baseAmount += specialTaxAmount.value;
  }

  if (props.selectedCurrency === "COP") {
    return roundUpToNearestHundred(baseAmount);
  }
  return parseFloat(baseAmount.toFixed(2));
  //return roundToTwoDecimalPlaces(baseAmount);
});

// ACTUALIZADO: Eliminar lógica SPE adicional
/*const remainingAmount = computed(() => {
  let totalToPay = props.totalAmount;
  // ELIMINAR: La lógica SPE antigua que sumaba 75% adicional
  // El descuento SPE ya está incluido en props.totalAmount

  const rawDifference = totalToPay - totalPaidAmount.value;

  if (props.selectedCurrency === "COP") {
    return roundUpToNearestHundred(rawDifference);
  }

  return roundToTwoDecimalPlaces(rawDifference);
});*/

const remainingAmount = computed(() => {
  // Aplicamos el descuento aquí también
  let totalWithDiscount = props.totalAmount;

  if (appliesSpecialTax.value) {
    totalWithDiscount += specialTaxAmount.value;
  }

  const rawDifference = totalWithDiscount - totalPaidAmount.value;

  if (props.selectedCurrency === "COP") {
    return roundUpToNearestHundred(rawDifference);
  }

  return roundToTwoDecimalPlaces(rawDifference);
});

const getConvertedRemainingAmount = (currency) => {
  const baseCurrency = props.selectedCurrency;
  const targetCurrency = currency;

  if (baseCurrency === targetCurrency) {
    return remainingAmount.value;
  }

  if (!ratesLoaded.value) {
    return 0;
  }

  const rate = exchangeRates.value[baseCurrency]?.[targetCurrency];

  if (!rate) {
    console.warn(
      `No hay tasa de cambio de ${baseCurrency} a ${targetCurrency}`
    );
    return 0;
  }

  let converted = remainingAmount.value * rate;
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
      debounceTimeout: null,
      inputAmount: null,
    });
  } else {
    toast.error("El monto total ya ha sido cubierto.");
  }
};

const canAddPaymentBlock = computed(() => {
  const lastPayment = payments.value[payments.value.length - 1];
  if (remainingAmount.value <= 0) return false;
  if (isCredit(payments.value[0].method)) return false;
  if (!lastPayment.method) return false;
  if (isTransferMethod(lastPayment.method) && !lastPayment.reference)
    return false;

  if (
    !isCredit(lastPayment.method) &&
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
  payments.value.forEach((p, index) => {
    if (p.method === "balance") {
      return;
    }

    if (
      isTransferMethod(p.method) &&
      (p.amount === null || Number(p.amount) === 0)
    ) {
      let amountToAssign = 0;
      amountToAssign = remainingAmount.value;

      if (p.currency !== props.selectedCurrency) {
        const baseCurrency = props.selectedCurrency;
        const targetCurrency = p.currency;

        const rate = exchangeRates.value?.[baseCurrency]?.[targetCurrency];
        if (rate) {
          amountToAssign = amountToAssign * rate;
        } else {
          console.warn(
            `Advertencia: No se encontró tasa de cambio de ${baseCurrency} a ${targetCurrency}. No se pudo asignar automáticamente el monto.`
          );
          return; // No se puede asignar, se detiene este pago.
        }
      }

      p.amount = parseFloat(amountToAssign.toFixed(2));
    }
  });

  if (currentProgress.value === 0 && !isCredit(payments.value[0].method)) {
    let totalToPayCalculated = props.totalAmount;

    if (appliesSpecialTax.value) {
      totalToPayCalculated += specialTaxAmount.value;
    }

    /*if (speSwitch.value) {
      const totalIva = props.orderProducts.reduce((sum, product) => {
        return sum + getIva(product, props.selectedCurrency);
      }, 0);
      const speAmount = totalIva * 0.75;
      totalToPayCalculated += speAmount;
    }*/

    if (props.selectedCurrency === "COP") {
      totalToPayCalculated = roundUpToNearestHundred(totalToPayCalculated);
    } else {
      totalToPayCalculated = roundToTwoDecimalPlaces(totalToPayCalculated);
    }

    const usedCurrencies = payments.value
      .filter((p) => p.currency)
      .map((p) => p.currency);

    const uniqueCurrencies = new Set(usedCurrencies);
    const numberOfCurrencies = uniqueCurrencies.size;

    let tolerance = 0;

    if (numberOfCurrencies > 2) {
      tolerance = 0.6;
    } else {
      tolerance = 0.01;
    }

    let finalRemainingAmount = remainingAmount.value;

    if (totalPaidAmountNonCash.value > totalToPayCalculated + tolerance) {
      toast.error(
        "El monto total de los pagos no en efectivo (Transferencia, Binance, PayPal, etc.) excede el monto total de la compra. Estos métodos no generan vuelto."
      );
      return;
    }

    if (Math.abs(finalRemainingAmount) < tolerance) {
      finalRemainingAmount = 0;
    }
    if (finalRemainingAmount < 0) {
      const excessFromNonCash = roundToTwoDecimalPlaces(
        totalPaidAmountNonCash.value - totalToPayCalculated
      );
      if (excessFromNonCash > tolerance && !showChangeAmount.value) {
        toast.error(
          "El excedente fue generado por un pago no en efectivo. No se permite vuelto."
        );
        return;
      }

      if (!showChangeAmount.value) {
        toast.error(
          "El monto total pagado excede el monto de la compra. El vuelto solo se puede generar con pagos en efectivo (USD o COP)."
        );
        return;
      }
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
      if (!isCredit(p.method) && (Number(p.amount) <= 0 || p.amount === null)) {
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
        invoice_switch: invoiceSwitch.value,
        spe: props.orderData?.client?.is_spe || false, // ACTUALIZADO: Usar SPE del cliente
      }
    );
    dialogVisible.value = false;
    resetProgress();
  }
};

// ACTUALIZADO: Eliminar speSwitch
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
    },
  ];
  invoiceSwitch.value = false;
  // ELIMINAR: speSwitch.value = false;
};

const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});

watch(
  () => props.isDialogVisible,
  (newVal) => {
    if (newVal) {
      resetProgress();
      ratesLoaded.value = false;
      fetchExchangeRates();
    }
  }
);

const getProductPriceSinIva = (product, currency) => {
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    basePrice = product.price || 0;
  }

  let priceSinIva = basePrice;
  if (currency === "COP") {
    priceSinIva = roundUpToNearestHundred(priceSinIva);
  }
  return priceSinIva;
};

// ACTUALIZADO: Función para calcular precio con IVA ajustado para SPE
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

  // Apply visual discount
  if (activeDiscountDisplay.value != null) {
      basePrice = basePrice * getDiscountFactor(product);
  }

  // Calcular el IVA con descuento SPE si aplica
  let effectiveTaxRate = taxRate;
  if (props.orderData?.client?.is_spe) {
    effectiveTaxRate = taxRate * 0.25; // Solo aplicar 25% del IVA para clientes SPE
  }

  let priceWithIva = basePrice * (1 + effectiveTaxRate);

  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }

  priceWithIva = priceWithIva * product.selectedQuantity;
  return priceWithIva;
};

const getDiscountFactor = (product) => {
  if (
    props.globalDiscount &&
    props.globalDiscount.percentage > 0 &&
    product.discount_type !== "expiration"
  ) {
    return 1 - props.globalDiscount.percentage / 100;
  }
  return 1;
};
const getProductPriceSinDescuento = (product, currency) => {
  const taxRate = product.taxRate || 0;
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    basePrice = product.price || 0;
  }

  // Calcular el IVA con descuento SPE si aplica
  let effectiveTaxRate = taxRate;
  if (props.orderData?.client?.is_spe) {
    effectiveTaxRate = taxRate * 0.25; // Solo aplicar 25% del IVA para clientes SPE
  }

  let priceWithIva = basePrice * (1 + effectiveTaxRate);

  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }

  priceWithIva = priceWithIva * product.selectedQuantity;
  return priceWithIva;
};

// ACTUALIZADO: Función para calcular IVA con descuento SPE
const getIva = (product, currency) => {
  const taxRate = product.taxRate || 0;
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    basePrice = product.price || 0;
  }

  let ivaAmount = basePrice * taxRate * product.selectedQuantity;

  // Si el cliente es SPE, aplicar solo el 25% del IVA (descuento del 75%)
  if (props.orderData?.client?.is_spe) {
    ivaAmount = ivaAmount * 0.25;
  }

  if (currency === "COP") {
    ivaAmount = roundUpToNearestHundred(ivaAmount);
  }

  return ivaAmount;
};

// NUEVO: Computed para mostrar el ahorro SPE
const totalSPESavings = computed(() => {
  if (!props.orderData?.client?.is_spe) return 0;

  let totalOriginalIVA = 0;
  props.orderProducts.forEach((product) => {
    const taxRate = product.taxRate || 0;
    let basePrice = 0;
    if (props.selectedCurrency === "BS") {
      basePrice = product.price_bs || 0;
    } else if (props.selectedCurrency === "COP") {
      basePrice = product.price_cop || 0;
    } else {
      basePrice = product.price || 0;
    }

    let originalIva = basePrice * taxRate * product.selectedQuantity;
    totalOriginalIVA += originalIva;
  });

  // El ahorro es el 75% del IVA original
  const savings = totalOriginalIVA * 0.75;
  return props.selectedCurrency === "COP"
    ? roundUpToNearestHundred(savings)
    : savings;
});

const hasCreditPayment = computed(() => {
  return payments.value.some((payment) => isCredit(payment.method));
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

// ACTUALIZADO: Eliminar lógica SPE adicional en changeAmount
const changeAmount = computed(() => {
  //let totalToPay = props.totalAmount;
  let totalToPay = props.totalAmount;

  if (appliesSpecialTax.value) {
    totalToPay += specialTaxAmount.value;
  }

  // ELIMINAR: La lógica SPE antigua que sumaba 75% adicional
  // El descuento SPE ya está incluido en props.totalAmount

  if (props.selectedCurrency === "COP") {
    const totalToPayRounded = roundUpToNearestHundred(totalToPay);
    return Math.max(
      0,
      roundToTwoDecimalPlaces(totalPaidAmount.value - totalToPayRounded)
    );
  } else {
    return Math.max(
      0,
      roundToTwoDecimalPlaces(totalPaidAmount.value - totalToPay)
    );
  }
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

  const diff = totalCashPaidInUSD - totalOrdenEnUSD;
  return Math.max(0, roundToTwoDecimalPlaces(diff));
});

const changeAmountInCOP = computed(() => {
  const vueltoEnMonedaOrden = changeAmount.value;
  if (props.selectedCurrency === "COP") {
    console.log(vueltoEnMonedaOrden);
    return vueltoEnMonedaOrden;
  }

  const rate = exchangeRates.value?.[props.selectedCurrency]?.["COP"];
  console.log(rate);
  if (rate) {
    const vueltoConvertido = vueltoEnMonedaOrden * rate;
    console.log(roundUpToNearestHundred(vueltoConvertido));
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

watch(
  () => payments.value[0].method,
  (newVal, oldVal) => {
    if (newVal === "balance") {
      const clientBalance = props.orderData.client?.balance || 0;
      if (clientBalance <= 0) {
        toast.error("El cliente no tiene saldo disponible.");
        payments.value[0].method = null;
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
        payments.value[0].method = null;
        return;
      }

      const remainingAmountInUSD = remainingAmountInOrderCurrency / rateToUSD;
      const amountToUse = Math.min(remainingAmountInUSD, clientBalance);
      const formattedAmount = parseFloat(amountToUse.toFixed(2));
      payments.value[0].amount = formattedAmount;
      payments.value[0].inputAmount = formattedAmount;
      payments.value[0].currency = "USD";
    } else {
      payments.value[0].amount = null;
      payments.value[0].inputAmount = null;
      payments.value[0].currency = props.selectedCurrency;
    }
  },
  { deep: true }
);

const updateDebouncedAmount = (payment, newValue) => {
  clearTimeout(payment.debounceTimeout);
  payment.debounceTimeout = setTimeout(() => {
    payment.amount = Number(newValue);
  }, 1000);
};

const handleMethodChange = (payment, newMethod) => {
  clearTimeout(payment.debounceTimeout);
  payment.debounceTimeout = null;
  if (newMethod !== "balance") {
    console.log(newMethod);
    payment.amount = null;
    payment.inputAmount = null;
  }
  payment.reference = null;
};

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
  <VDialog v-model="dialogVisible">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold pr-1">Compra </span>
        <VSwitch v-model="invoiceSwitch" />
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText v-if="currentProgress === 0">
        <div v-if="!ratesLoaded" class="text-center py-10">
          <VProgressCircular indeterminate color="primary"></VProgressCircular>
          <p class="mt-4">Cargando tasas de cambio. Por favor, espere...</p>
        </div>

        <div v-else>
          <div class="d-flex align-center justify-space-between">
            <p class="text-h6 font-weight-medium mb-0">Total de productos:</p>
            <VChip
              label
              :color="chipColor"
              variant="tonal"
              density="default"
              size="small"
              :draggable="false"
              class="ms-auto"
            >
              <span class="font-weight-medium mb-0">{{
                totalSelectedQuantity
              }}</span>
            </VChip>
          </div>

          <VTable density="compact" lines="none" class="py-2">
            <tbody>
              <tr
                v-for="(product, index) in props.orderProducts"
                :key="product.id"
              >
                <td>
                  <div class="d-flex flex-column">
                    <span
                      class="text-body-1 font-weight-medium text-high-emphasis"
                    >
                      {{ product.title }}
                    </span>
                    <span class="text-sm text-disabled">
                      {{ product.active_ingredient }}
                      {{ product.laboratory ? `- ${product.laboratory}` : "" }}
                      {{ product.selectedQuantity }} x
                    </span>
                  </div>
                </td>
                <td class="text-right">
                  <div class="d-flex flex-column align-end me-4">
                    <span
                      v-if="index === 0"
                      class="text-caption text-medium-emphasis"
                      >Precio</span
                    >
                    <span class="text-body-1 font-weight-regular">
                      {{
                        formatCurrency(
                          getProductPriceSinIva(
                            product,
                            props.selectedCurrency
                          ) * product.selectedQuantity,
                          props.selectedCurrency
                        )
                      }}
                    </span>
                  </div>
                </td>
                <td class="text-right">
                  <div class="d-flex flex-column align-end me-4">
                    <span
                      v-if="index === 0"
                      class="text-caption text-medium-emphasis"
                      >IVA</span
                    >
                    <span class="text-body-1 font-weight-regular">
                      {{
                        formatCurrency(
                          getIva(product, props.selectedCurrency),
                          props.selectedCurrency
                        )
                      }}
                    </span>
                  </div>
                </td>
                <td class="text-right">
                  <div class="d-flex flex-column align-end">
                    <span
                      v-if="index === 0"
                      class="text-caption text-medium-emphasis"
                      >Total</span
                    >
                    <div class="d-flex align-center gap-1">
                      <span
                        v-if="activeDiscountDisplay"
                        class="text-caption text-disabled text-decoration-line-through me-2 text-error"
                      >
                        {{
                          formatCurrency(
                            getProductPriceSinDescuento(
                              product,
                              props.selectedCurrency
                            ),
                            props.selectedCurrency
                          )
                        }}
                      </span>
                      <span class="text-body-1 font-weight-bold text-black">
                        {{
                          formatCurrency(
                            getProductPrice(product, props.selectedCurrency),
                            props.selectedCurrency
                          )
                        }}
                      </span>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </VTable>

          <VDivider />
          <div
            v-for="(payment, index) in payments"
            :key="index"
            class="payment-block"
          >
            <div class="d-flex align-center flex-wrap">
              <p class="font-weight-medium text-h6 mt-2 mb-0 me-2">
                Método de Pago #{{ index + 1 }}
              </p>

              <div class="d-flex justify-center mt-2 mb-0">
                <VBtn
                  v-if="index === 0"
                  variant="text"
                  color="primary"
                  @click="addPaymentBlock"
                  :disabled="!canAddPaymentBlock"
                >
                  <VIcon start icon="tabler-plus" />
                  Agregar otro método de pago
                </VBtn>
              </div>

              <VCol cols="12" md="2" class="pa-0">
                <VSelect
                  v-if="index > 0"
                  v-model="payment.currency"
                  :items="currencies"
                  item-title="label"
                  item-value="value"
                  label="Moneda del Pago"
                  density="compact"
                  hide-details
                  class="mt-4"
                />
              </VCol>
            </div>

            <VRow class="pb-2">
              <VCol cols="12" md="6">
                <VRadioGroup
                  v-model="payment.method"
                  inline
                  @update:modelValue="
                    (newVal) => handleMethodChange(payment, newVal)
                  "
                >
                  <VRadio
                    v-for="method in (
                      paymentMethodsByCurrency[payment.currency] || []
                    ).filter((m) => {
                      if (
                        index === 0 &&
                        payment.currency !== 'USD' &&
                        m.value === 'credit'
                      ) {
                        return false;
                      }
                      if (m.value === 'balance') {
                        return (
                          index === 0 &&
                          payment.currency === 'USD' &&
                          props.orderData.client?.balance > 0
                        );
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
              </VCol>
              <VCol cols="12" md="6">
                <VRow
                  class="payment-block"
                  v-if="
                    payment.method !== 'balance' && !isCredit(payment.method)
                  "
                  :key="payment.method"
                >
                  <VCol
                    :cols="isTransferMethod(payment.method) ? 12 : 6"
                    :md="isTransferMethod(payment.method) ? 6 : 6"
                  >
                    <VTextField
                      :model-value="payment.inputAmount"
                      @input="
                        updateDebouncedAmount(payment, $event.target.value)
                      "
                      label="Monto del pago"
                      :placeholder="getPlaceholderText(index, payment)"
                      type="number"
                      class="p-2"
                      :persistent-hint="true"
                    >
                      <template #details>
                        <span class="text-error text-left">
                          {{ getPlaceholderText(index, payment) }}
                        </span>
                      </template>
                    </VTextField>
                  </VCol>

                  <VCol
                    v-if="isTransferMethod(payment.method)"
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model="payment.reference"
                      label="Número de Referencia"
                      placeholder="Ingresa el número de referencia del pago"
                      class="p-2"
                    />
                  </VCol>
                </VRow>

                <VRow v-if="payment.method === 'balance'">
                  <VCol cols="12" sm="6">
                    <VTextField
                      :model-value="payment.amount.toFixed(2)"
                      label="Monto del pago"
                      :placeholder="getPlaceholderText(index, payment)"
                      type="text"
                      class="p-2"
                      readonly
                      :persistent-hint="true"
                      hint="Monto del saldo no editable."
                    />
                  </VCol>
                </VRow>

                <VRow v-if="isCredit(payment.method)">
                  <VCol cols="12" sm="6">
                    <VTextField
                      :model-value="
                        formatCurrency(remainingAmount, payment.currency)
                      "
                      label="Monto del crédito"
                      class="p-2"
                      readonly
                    />
                  </VCol>
                </VRow>
              </VCol>
            </VRow>
          </div>
        </div>
        <VDivider />

        <!-- Total a pagar -->

        <div
          v-if="activeDiscountDisplay"
          class="d-flex align-center flex-wrap justify-space-between"
        >
          <p class="text-h6 font-weight-medium mt-2 mb-0">
            {{ activeDiscountDisplay.label }}:
          </p>
          <p class="text-h6 font-weight-medium mt-2 mb-0 text-error">
            - {{ activeDiscountDisplay.formatted }}
          </p>
        </div>

        <div
          v-if="expirationDiscountTotal > 0"
          class="d-flex align-center flex-wrap justify-space-between"
        >
          <p class="text-h6 font-weight-medium mt-2 mb-0">
            Descuento Vencimiento:
          </p>
          <p class="text-h6 font-weight-medium mt-2 mb-0 text-error">
            -
            {{
              formatCurrency(expirationDiscountTotal, props.selectedCurrency)
            }}
          </p>
        </div>

            <div
              v-if="appliesSpecialTax"
              class="d-flex flex-wrap justify-space-between"
            >
              <p class="text-h6 font-weight-medium mt-2 mb-0">
                Recargo Sujeto Pasivo Especial (3%):
              </p>
              <p class="text-h6 font-weight-medium mt-2 mb-0">
                {{ formatCurrency(specialTaxAmount, props.selectedCurrency) }}
              </p>
            </div>

        <div class="d-flex align-center flex-wrap justify-space-between">
          <p class="text-h6 font-weight-medium mt-2 mb-0">Total a pagar:</p>
          <p class="text-h6 font-weight-medium mt-2 mb-0">
            {{
              formatCurrency(roundedTotalAmountToPay, props.selectedCurrency)
            }}
          </p>
        </div>

        <!-- NUEVA sección de información SPE mejorada -->
        <div
          v-if="props.orderData?.client?.is_spe"
          class="bg-success-lighten-4 pa-3 rounded mb-3 mt-3"
        >
          <div
            class="text-subtitle-2 font-weight-bold text-success-darken-2 mb-2"
          >
            <VIcon icon="tabler-discount-check" class="me-1" size="16" />
            Cliente SPE - Descuento aplicado:
          </div>

          <div class="d-flex justify-space-between">
            <span class="text-body-2">IVA Original (sin descuento):</span>
            <span class="text-body-2 font-weight-medium text-disabled">
              {{
                formatCurrency(
                  props.orderProducts.reduce((sum, product) => {
                    const taxRate = product.taxRate || 0;
                    let basePrice = 0;
                    if (props.selectedCurrency === "BS") {
                      basePrice = product.price_bs || 0;
                    } else if (props.selectedCurrency === "COP") {
                      basePrice = product.price_cop || 0;
                    } else {
                      basePrice = product.price || 0;
                    }
                    return sum + basePrice * taxRate * product.selectedQuantity;
                  }, 0),
                  props.selectedCurrency
                )
              }}
            </span>
          </div>

          <div class="d-flex justify-space-between">
            <span class="text-body-2 text-success-darken-2"
              >Descuento SPE (75%):</span
            >
            <span class="text-body-2 font-weight-bold text-success-darken-2">
              -{{ formatCurrency(totalSPESavings, props.selectedCurrency) }}
            </span>
          </div>

          <VDivider class="my-2" />

          <div class="d-flex justify-space-between">
            <span class="text-body-2 font-weight-medium"
              >IVA Final a pagar:</span
            >
            <span class="text-body-2 font-weight-bold text-success-darken-2">
              {{
                formatCurrency(
                  props.orderProducts.reduce((sum, product) => {
                    return sum + getIva(product, props.selectedCurrency);
                  }, 0),
                  props.selectedCurrency
                )
              }}
            </span>
          </div>

          <div class="text-caption text-success-darken-2 mt-2">
            <VIcon icon="tabler-user-check" class="me-1" size="14" />
            {{ props.orderData.client.name }}
            {{ props.orderData.client.last_name }}
            tiene descuento SPE del 75% en IVA
          </div>
        </div>

        <!-- Monto devuelto -->
        <div
          v-if="showChangeAmount"
          class="d-flex align-center flex-wrap justify-space-between"
        >
          <p class="text-h6 font-weight-medium mt-2 mb-0">Monto Devuelto:</p>
          <p class="text-h6 font-weight-medium mt-2 mb-0">
            {{ formatCurrency(changeAmountInCOP, "COP") }}
          </p>
        </div>

        <!-- Monto restante -->
        <div
          v-if="remainingAmount > 0"
          class="d-flex align-center flex-wrap justify-space-between"
        >
          <p class="text-h6 font-weight-medium mt-2 mb-0">
            Monto Restante:&nbsp;
          </p>
          <p class="text-h6 font-weight-medium mt-2 mb-0 text-error">
            {{ formatCurrency(remainingAmount, props.selectedCurrency) }}
          </p>
          <VDivider />
          <div class="d-flex align-center flex-wrap justify-space-between">
            <p class="text-h6 font-weight-medium mt-2 mb-0">Total a pagar:</p>
            <p class="text-h6 font-weight-medium mt-2 mb-0">
              {{
                formatCurrency(roundedTotalAmountToPay, props.selectedCurrency)
              }}
            </p>
          </div>

          <div
            v-if="showChangeAmount"
            class="d-flex align-center flex-wrap justify-space-between"
          >
            <p class="text-h6 font-weight-medium mt-2 mb-0">Monto Devuelto:</p>
            <p class="text-h6 font-weight-medium mt-2 mb-0">
              {{ formatCurrency(changeAmountInCOP, "COP") }}
            </p>
          </div>

          <div
            v-if="remainingAmount > 0"
            class="d-flex align-center flex-wrap justify-space-between"
          >
            <p class="text-h6 font-weight-medium mt-2 mb-0">
              Monto Restante:&nbsp;
            </p>
            <p class="text-h6 font-weight-medium mt-2 mb-0 text-error">
              {{ formatCurrency(remainingAmount, props.selectedCurrency) }}
            </p>
          </div>
        </div>
      </VCardText>

      <!-- Ticket de impresión (sin cambios mayores) -->
      <VCardText v-else-if="currentProgress === 100">
        <div class="d-flex justify-center">
          <div style="width: '50%'">
            <div class="text-center">
              <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
            </div>
            <div class="d-flex flex-wrap justify-space-between">
              <span class="font-weight-bold text-h6 mt-4">
                Orden N° {{ props.orderData.id }}
              </span>
              <div class="text-end">
                <span class="d-block font-weight-bold text-h6 mt-4">
                  {{ formatDateTime(props.orderData.created_at, "date") }}
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
                <span
                  v-if="props.orderData?.client?.is_spe"
                  class="text-success"
                  >(SPE)</span
                >
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

            <!-- Lista de productos en el ticket -->
            <div>
              <VList class="card-list" density="compact" nav>
                <VListItem
                  v-for="product in props.orderProducts"
                  :key="product.id"
                  class="rounded-0"
                >
                  <template #prepend>
                    <span>{{ product.selectedQuantity }} x</span>
                  </template>

                  <VListItemTitle class="font-weight-medium me-4 mx-2">
                    {{ product.title }}
                    <span
                      v-if="props.orderData?.client?.is_spe"
                      class="text-success text-caption"
                    >
                      (SPE)
                    </span>
                  </VListItemTitle>
                  <VListItemSubtitle class="mx-2"
                    >{{ product.active_ingredient }}
                    {{ product.laboratory }}</VListItemSubtitle
                  >

                  <template #append>
                    <div class="d-flex flex-column align-end">
                      <span class="text-body-1 font-weight-bold">
                        {{
                          formatCurrency(
                            getProductPrice(product, props.selectedCurrency),
                            props.selectedCurrency
                          )
                        }}
                      </span>

                      <span
                        v-if="activeDiscountDisplay"
                        class="text-caption text-decoration-line-through text-error"
                        style="margin-top: -4px"
                      >
                        {{
                          formatCurrency(
                            getProductPriceSinDescuento(
                              product,
                              props.selectedCurrency
                            ),
                            props.selectedCurrency
                          )
                        }}
                      </span>
                    </div>
                  </template>
                </VListItem>
              </VList>
            </div>

            <!-- Totales en el ticket -->
            <div
              v-if="activeDiscountDisplay"
              class="d-flex flex-wrap justify-space-between"
            >
              <p class="text-h6 font-weight-medium mt-2 mb-0">
                {{ activeDiscountDisplay.label }}:
              </p>
              <p class="text-h6 font-weight-medium mt-2 mb-0">
                - {{ activeDiscountDisplay.formatted }}
              </p>
            </div>
            <div
              v-if="expirationDiscountTotal > 0"
              class="d-flex flex-wrap justify-space-between"
            >
              <p class="text-h6 font-weight-medium mt-2 mb-0">
                Descuento Vencimiento:
              </p>
              <p class="text-h6 font-weight-medium mt-2 mb-0">
                -
                {{
                  formatCurrency(
                    expirationDiscountTotal,
                    props.selectedCurrency
                  )
                }}
              </p>
            </div>


            <div
              v-if="appliesSpecialTax"
              class="d-flex flex-wrap justify-space-between"
            >
              <p class="text-h6 font-weight-medium mt-2 mb-0">
                 Recargo Sujeto Pasivo Especial (3%):
              </p>
              <p class="text-h6 font-weight-medium mt-2 mb-0">
                {{ formatCurrency(specialTaxAmount, props.selectedCurrency) }}
              </p>
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

            <!-- Mostrar ahorro SPE en el ticket -->
            <div
              v-if="props.orderData?.client?.is_spe"
              class="d-flex flex-wrap justify-space-between"
            >
              <p class="font-weight-bold text-h6 text-success">
                Descuento SPE:
              </p>
              <p class="font-weight-bold text-h6 text-success">
                -{{ formatCurrency(totalSPESavings, props.selectedCurrency) }}
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
                  formatCurrency(
                    roundedTotalAmountToPay,
                    props.selectedCurrency
                  )
                }}
              </p>
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
              ¡GRACIAS POR SU COMPRA!
            </p>
          </div>
        </div>
      </VCardText>

      <!-- Botones del modal -->
      <VCardActions class="p-2 d-flex justify-space-between w-100 mx-auto">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeModal"
          class="w-50"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleCompletePurchase"
          class="w-50"
        >
          {{ continueButtonText }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.v-table__wrapper > table > tbody > tr > td {
  border-bottom: none !important;
}
</style>
