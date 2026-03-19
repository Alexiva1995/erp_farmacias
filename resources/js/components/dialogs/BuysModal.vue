<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import CheckoutPaymentMethods from "@/components/dialogs/checkout/CheckoutPaymentMethods.vue";
import CheckoutProductList from "@/components/dialogs/checkout/CheckoutProductList.vue";
import CheckoutReceipt from "@/components/dialogs/checkout/CheckoutReceipt.vue";
import CheckoutSummary from "@/components/dialogs/checkout/CheckoutSummary.vue";
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
  isExternalLoading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "update:isDialogVisible",
  "purchase-completed",
  "modal-closed",
  "printTicke-completed",
  "finish-and-reload",
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
const productsPanelExpanded = ref([0]); // Panel de productos expandido por defecto (0 = primer panel expandido)
const selectedCurrencyTab = ref(props.selectedCurrency); // Pestaña de moneda seleccionada (inicializada con la moneda del pedido)

// ELIMINAR: const speSwitch = ref(false); - Ya no se usa

const ratesLoaded = ref(false);

const issubmitting = ref(false);

const payments = ref([
  {
    method: null,
    amount: null,
    reference: null,
    currency: props.selectedCurrency,
    debounceTimeout: null,
    inputAmount: null,
    _isEditing: false,
    _isInputActive: false, // Flag para controlar si el input está activo
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
    method,
  );

// Función para verificar si un método es de efectivo (permite vuelto)
const isCashMethod = (method) => {
  return ["cash_bs", "cash_usd", "cash_cop"].includes(method);
};

// Función para verificar si un método requiere referencia
const requiresReference = (method, currency) => {
  // Efectivo: nunca requiere referencia
  if (isCashMethod(method)) {
    return false;
  }

  // Crédito en USD: no requiere referencia
  if (isCredit(method) && currency === "USD") {
    return false;
  }

  // Saldo: no requiere referencia
  if (method === "balance") {
    return false;
  }

  // Todos los demás métodos (transferencia, pago móvil, tarjeta, binance, paypal, etc.) requieren referencia
  return true;
};

function roundToTwoDecimalPlaces(num) {
  return Number(Math.round(num + "e+2") + "e-2");
}

//funcion de sujeto pasivo especial-esta menu(configuracion)
const appliesSpecialTax = computed(() => {
  return (
    props.isSpecialTaxpayer &&
    (props.selectedCurrency === "USD" || props.selectedCurrency === "COP")
  );
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
            9,
          ),
        );
        formattedRates["BS"]["COP"] = parseFloat(
          (formattedRates["BS"]["USD"] * formattedRates["USD"]["COP"]).toFixed(
            9,
          ),
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
  let totalWithDiscount = props.totalAmount;

  if (appliesSpecialTax.value) {
    totalWithDiscount += specialTaxAmount.value;
  }

  const rawDifference = totalWithDiscount - totalPaidAmount.value;

  // El monto restante no debe ser negativo. Si se paga de más, el excedente va a devolución.
  if (rawDifference < 0) return 0;

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
      `No hay tasa de cambio de ${baseCurrency} a ${targetCurrency}`,
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
    payment.currency,
  )}`;
};

// Esta función ya no se usa, pero la mantenemos por compatibilidad

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

// Función para verificar si hay pagos con referencias faltantes
const hasMissingReferences = () => {
  // Solo verificar pagos que tienen método asignado y monto confirmado
  return payments.value.some((payment) => {
    if (!payment.method) return false;
    if (!payment.amount || payment.amount <= 0) return false; // Solo verificar si el monto está confirmado
    if (requiresReference(payment.method, payment.currency)) {
      const hasReference = payment.reference && payment.reference.trim() !== "";
      return !hasReference;
    }
    return false;
  });
};

const closeModal = () => {
  // Validar referencias antes de cerrar
  if (hasMissingReferences()) {
    toast.error(
      "Por favor complete todas las referencias de pago antes de cerrar.",
    );
    return;
  }

  emit("update:isDialogVisible", false);
  emit("modal-closed");
  resetProgress();
};

const handleCompletePurchase = () => {
  const baseCurrency = props.selectedCurrency;
  const missingRates = [];
  if (issubmitting.value) return;

  payments.value.forEach((p) => {
    if (p.amount > 0 && p.currency && p.currency !== baseCurrency) {
      const rate = exchangeRates.value?.[p.currency]?.[baseCurrency];
      if (!rate || rate === 0) {
        missingRates.push(`${p.currency} a ${baseCurrency}`);
      }
    }
  });

  if (missingRates.length > 0) {
    const uniqueMissing = [...new Set(missingRates)]; // Eliminar duplicados
    toast.error(
      `No se puede finalizar. Faltan tasas de cambio para convertir: ${uniqueMissing.join(", ")}.`,
    );
    fetchExchangeRates();
    return;
  }

  // Validar referencias antes de continuar
  if (hasMissingReferences()) {
    toast.error(
      "Por favor complete todas las referencias de pago antes de continuar.",
    );
    return;
  }

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
            `Advertencia: No se encontró tasa de cambio de ${baseCurrency} a ${targetCurrency}. No se pudo asignar automáticamente el monto.`,
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
        "El monto total de los pagos no en efectivo (Transferencia, Binance, PayPal, etc.) excede el monto total de la compra. Estos métodos no generan vuelto.",
      );
      return;
    }

    if (Math.abs(finalRemainingAmount) < tolerance) {
      finalRemainingAmount = 0;
    }
    if (finalRemainingAmount < 0) {
      const excessFromNonCash = roundToTwoDecimalPlaces(
        totalPaidAmountNonCash.value - totalToPayCalculated,
      );
      if (excessFromNonCash > tolerance && !showChangeAmount.value) {
        toast.error(
          "El excedente fue generado por un pago no en efectivo. No se permite vuelto.",
        );
        return;
      }

      if (!showChangeAmount.value) {
        toast.error(
          "El monto total pagado excede el monto de la compra. El vuelto solo se puede generar con pagos en efectivo (USD o COP).",
        );
        return;
      }
    }

    if (finalRemainingAmount > 0) {
      toast.error(
        "El monto total no ha sido cubierto. Agrega más pagos para continuar.",
      );
      return;
    }

    const invalidPayment = payments.value.find((p) => {
      // Ignorar pagos sin método
      if (!p.method) return false;

      // Ignorar pagos que están en modo edición (inputs activos)
      if (p._isInputActive || p._isReferenceActive) return false;

      // Ignorar saldo (se maneja diferente)
      if (p.method === "balance") {
        return false;
      }

      // Validar monto primero (debe estar confirmado)
      if (!isCredit(p.method)) {
        if (!p.amount || Number(p.amount) <= 0) {
          return true; // Falta monto
        }
      }

      // Validar referencia si es requerida (solo si el monto ya está confirmado)
      if (requiresReference(p.method, p.currency)) {
        if (!p.reference || p.reference.trim() === "") {
          return true; // Falta referencia
        }
      }

      return false;
    });

    if (invalidPayment) {
      // Mensaje más específico según qué falta
      let errorMessage =
        "Por favor, revisa y completa los campos de todos los pagos.";

      if (!invalidPayment.amount || Number(invalidPayment.amount) <= 0) {
        errorMessage = `El método "${getPaymentMethodLabel(invalidPayment.method, invalidPayment.currency)}" no tiene un monto válido.`;
      } else if (
        requiresReference(invalidPayment.method, invalidPayment.currency) &&
        (!invalidPayment.reference || invalidPayment.reference.trim() === "")
      ) {
        errorMessage = `El método "${getPaymentMethodLabel(invalidPayment.method, invalidPayment.currency)}" requiere una referencia.`;
      }

      toast.error(errorMessage);
      return;
    }
  }

  if (currentProgress.value < 100) {
    try {
      issubmitting.value = true;

      currentStageIndex.value++;
      if (currentStageIndex.value < progressStages.length) {
        currentProgress.value = progressStages[currentStageIndex.value];
      } else {
        currentProgress.value = 100;
      }

      const validPayments = payments.value.filter(
        (p) => p.amount > 0 && p.method !== null,
      );
      emit(
        "purchase-completed",
        props.orderData.id,
        validPayments,
        hasCreditPayment.value,
        changeAmountInCOP.value,
        changeAmountInUSD.value,
        {
          invoice_switch: invoiceSwitch.value,
          spe:
            selectedCurrencyTab.value !== "BS" ||
            props.orderData?.client?.is_spe ||
            false,
        },
        changeAmount.value,
      );
    } catch (error) {
      console.error("Error al completar la compra:", error);
      toast.error("Hubo un problema al procesar el pago. Intente de nuevo.");
      issubmitting.value = false;
    }
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
      _isEditing: false,
      _isInputActive: false,
      _isReferenceActive: false,
      _referenceError: false,
      _amountConfirmed: false,
      _amountError: false,
    },
  ];
  invoiceSwitch.value = props.selectedCurrency !== "BS";
  // ELIMINAR: speSwitch.value = false;
};

const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});

// Función para manejar la impresión del ticket (solo imprime, no completa la orden)
const handlePrintTicket = async () => {
  // La orden ya fue completada cuando se hizo clic en "Continuar"
  // Aquí solo imprimimos el ticket desde el componente padre
  emit("printTicke-completed");
};

// Función para cancelar después de ver el ticket
const handleCancelAfterTicket = () => {
  dialogVisible.value = false;
  emit("finish-and-reload");
  resetProgress();
};

watch(
  () => props.isDialogVisible,
  (newVal) => {
    if (newVal) {
      issubmitting.value = false;
      resetProgress();
      ratesLoaded.value = false;
      fetchExchangeRates();
      // Establecer la pestaña inicial a la moneda del pedido
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
  },
);

// Watch para actualizar el monto restante cuando cambia la pestaña
watch(
  () => selectedCurrencyTab.value,
  (newCurrency) => {
    // El switch se ajusta automáticamente según la moneda:
    // Habilitado para USD/COP (monedas distintas a BS), Deshabilitado para BS.
    invoiceSwitch.value = newCurrency !== "BS";
  },
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
  let totalToPay = props.totalAmount;

  if (appliesSpecialTax.value) {
    totalToPay += specialTaxAmount.value;
  }

  if (props.selectedCurrency === "COP") {
    totalToPay = roundUpToNearestHundred(totalToPay);
  } else {
    totalToPay = roundToTwoDecimalPlaces(totalToPay);
  }

  const diff = totalPaidAmount.value - totalToPay;
  return Math.max(0, roundToTwoDecimalPlaces(diff));
});

const changeAmountInUSD = computed(() => {
  const cashPaymentsInUSD = payments.value.filter(
    (p) => p.method === "cash_usd" && p.currency === "USD",
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
        `No se encontró la tasa de cambio de ${props.selectedCurrency} a USD.`,
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
      (payment.method === "cash_bs" && payment.currency === "BS") ||
      (payment.method === "cash_cop" && payment.currency === "COP"),
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
          `No se encontró la tasa de cambio de ${props.selectedCurrency} a USD.`,
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
  { deep: true },
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

// Computed para verificar si un pago está configurado (tiene monto)
const isPaymentConfigured = (payment) => {
  if (payment.method === "balance") {
    return payment.amount > 0;
  }
  if (isCredit(payment.method)) {
    return true; // Los créditos siempre están configurados
  }
  return payment.method && payment.amount && Number(payment.amount) > 0;
};

// Computed para obtener el monto formateado de un pago
const getPaymentAmount = (payment) => {
  if (payment.method === "balance") {
    return payment.amount || 0;
  }
  if (isCredit(payment.method)) {
    return remainingAmount.value;
  }
  return Number(payment.amount) || 0;
};

// Función helper para obtener el label del método de pago (ya existe getPaymentMethodLabel)

// Computed para la lista de pagos configurados (para el resumen)
const configuredPayments = computed(() => {
  return payments.value
    .map((payment, index) => ({
      ...payment,
      index: index + 1,
      isConfigured: isPaymentConfigured(payment),
      amount: getPaymentAmount(payment),
      label: getPaymentMethodLabel(payment.method, payment.currency),
    }))
    .filter((p) => p.isConfigured);
});

// Computed para obtener pagos válidos para el ticket (con método y monto > 0)
const validPaymentsForTicket = computed(() => {
  return payments.value.filter((payment) => {
    // Filtrar pagos que tengan método válido (no null, no undefined)
    if (
      !payment.method ||
      payment.method === null ||
      payment.method === undefined
    ) {
      return false;
    }

    // Verificar que el label del método no sea "N/A"
    const methodLabel = getPaymentMethodLabel(payment.method, payment.currency);
    if (methodLabel === "N/A") {
      return false;
    }

    // Filtrar pagos con monto válido (> 0)
    const amount = Number(payment.amount) || 0;
    if (amount <= 0) {
      return false;
    }

    return true;
  });
});

// Función para seleccionar un método de pago desde el lado izquierdo
const selectPaymentMethod = (methodValue, currency = null) => {
  const targetCurrency = currency || props.selectedCurrency;

  // Validar que haya monto restante
  if (remainingAmount.value <= 0) {
    toast.error("El monto total ya ha sido cubierto.");
    return;
  }

  // Siempre crear un nuevo pago al final (orden cronológico)
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

  // Si es balance o crédito, asignar el monto automáticamente
  if (methodValue === "balance") {
    const clientBalance = props.orderData.client?.balance || 0;
    if (clientBalance <= 0) {
      toast.error("El cliente no tiene saldo disponible.");
      availablePayment.method = null;
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
      toast.error("No se encontró la tasa de cambio.");
      availablePayment.method = null;
      return;
    }
    const remainingAmountInUSD = remainingAmountInOrderCurrency / rateToUSD;
    const amountToUse = Math.min(remainingAmountInUSD, clientBalance);
    availablePayment.amount = parseFloat(amountToUse.toFixed(2));
    availablePayment.inputAmount = availablePayment.amount;
    availablePayment._isInputActive = false;
  } else if (isCredit(methodValue)) {
    // Crédito: asignar el monto restante automáticamente
    availablePayment.amount = remainingAmount.value;
    availablePayment.inputAmount = remainingAmount.value;
    availablePayment._isInputActive = false;
    availablePayment._isReferenceActive = false; // Crédito en USD no requiere referencia
  } else {
    // Para otros métodos, dejar el input vacío para que el usuario escriba
    availablePayment.inputAmount = "";
    availablePayment.amount = null; // No confirmar hasta que el usuario lo haga
    availablePayment._isInputActive = true;
    // Usar nextTick para asegurar que el DOM esté actualizado
    nextTick(() => {
      const paymentIndex = payments.value.indexOf(availablePayment);
      const input = document.querySelector(
        `.payment-input[data-payment-index="${paymentIndex}"]`,
      );
      if (input) {
        input.focus();
      }
    });
  }

  // Inicializar referencia según si requiere o no
  if (!requiresReference(methodValue, targetCurrency)) {
    availablePayment.reference = null;
  }
};

// Función para confirmar el monto (onBlur o Enter o Check)
const confirmPaymentAmount = (payment) => {
  // Solo confirmar si el input está activo y tiene valor
  if (
    payment._isInputActive &&
    payment.inputAmount !== null &&
    payment.inputAmount !== "" &&
    payment.inputAmount !== undefined
  ) {
    const numValue = parseFloat(payment.inputAmount);
    if (!isNaN(numValue) && numValue > 0) {
      // Validación: métodos no-efectivo no pueden exceder el monto restante
      if (!isCashMethod(payment.method)) {
        // Si se está editando, sumar el monto anterior al restante para validar correctamente
        const previousAmount = payment.amount || 0;
        let remainingInPaymentCurrency = getConvertedRemainingAmount(
          payment.currency,
        );

        // Si hay un monto anterior, sumarlo al restante para obtener el restante real
        if (previousAmount > 0) {
          if (payment.currency === props.selectedCurrency) {
            remainingInPaymentCurrency += previousAmount;
          } else {
            // Convertir el monto anterior a la moneda base y luego a la moneda del pago
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
            `El monto no puede exceder el restante: ${formatCurrency(remainingInPaymentCurrency, payment.currency)}`,
          );
          payment._amountError = true;
          return;
        }
      }
      // Solo ahora actualizar el amount, lo que actualizará el restante
      payment.amount = numValue;
      payment.inputAmount = numValue.toString();
      payment._previousAmount = undefined; // Limpiar el monto anterior guardado
      payment._amountError = false;

      // Si requiere referencia, mantener el bloque activo y activar el input de referencia automáticamente
      if (requiresReference(payment.method, payment.currency)) {
        // Mantener _isInputActive = true para que se muestre el bloque de inputs
        // pero el input de monto ya no será editable (se mostrará como texto)
        payment._isReferenceActive = true;
        // Marcar que el monto ya está confirmado
        payment._amountConfirmed = true;
        nextTick(() => {
          const paymentIndex = payments.value.indexOf(payment);
          const referenceInput = document.querySelector(
            `.payment-reference-input[data-payment-index="${paymentIndex}"]`,
          );
          if (referenceInput) {
            referenceInput.focus();
            referenceInput.select();
          }
        });
      } else {
        // Si no requiere referencia, desactivar todos los inputs y confirmar el pago
        payment._isInputActive = false;
        payment._isReferenceActive = false;
        payment._amountConfirmed = false;
        // El pago está completo, no necesitamos hacer nada más
      }
    } else {
      // Si el valor no es válido, mantener el input activo
      payment._amountError = true;
      toast.error("Por favor ingrese un monto válido.");
    }
  } else if (
    payment._isInputActive &&
    (!payment.inputAmount || payment.inputAmount === "")
  ) {
    // Si el input está activo pero vacío, restaurar el valor anterior si existe
    if (payment._previousAmount !== undefined) {
      payment.inputAmount = payment._previousAmount.toString();
      payment.amount = payment._previousAmount;
    }
    payment._isInputActive = false;
    payment._isReferenceActive = false;
  }
};

// Función para confirmar el pago completo (monto + referencia si aplica)
const confirmPaymentComplete = (payment) => {
  // 1. Sincronizar inputAmount con amount si el input está activo
  if (payment._isInputActive) {
    const numValue = parseFloat(payment.inputAmount);
    if (isNaN(numValue) || numValue <= 0) {
      toast.error("Por favor ingrese un monto válido.");
      payment._amountError = true;
      return;
    }

    // Validación: métodos no-efectivo no pueden exceder el monto restante
    if (!isCashMethod(payment.method)) {
      const previousAmount = payment._previousAmount !== undefined ? payment._previousAmount : (payment.amount || 0);
      let remainingInPaymentCurrency = getConvertedRemainingAmount(payment.currency);

      if (previousAmount > 0) {
        if (payment.currency === props.selectedCurrency) {
          remainingInPaymentCurrency += previousAmount;
        } else {
          const rateToBase = exchangeRates.value?.[payment.currency]?.[props.selectedCurrency];
          const rateToPayment = exchangeRates.value?.[props.selectedCurrency]?.[payment.currency];
          if (rateToBase && rateToPayment) {
            const previousInBase = previousAmount * rateToBase;
            remainingInPaymentCurrency += previousInBase * rateToPayment;
          }
        }
      }

      if (numValue > remainingInPaymentCurrency) {
        toast.error(`El monto no puede exceder el restante: ${formatCurrency(remainingInPaymentCurrency, payment.currency)}`);
        return;
      }
    }

    // Actualizar el monto oficial
    payment.amount = numValue;
    payment.inputAmount = numValue.toString();
  }

  // 2. Validar que tengamos un monto válido asignado
  if (!payment.amount || payment.amount <= 0) {
    toast.error("Por favor ingrese un monto válido.");
    if (payment._isInputActive) {
      payment._amountError = true;
      nextTick(() => {
        const paymentIndex = payments.value.indexOf(payment);
        const input = document.querySelector(`.payment-input[data-payment-index="${paymentIndex}"]`);
        if (input) input.focus();
      });
    }
    return;
  }

  // 3. Validar referencia si es requerida
  if (requiresReference(payment.method, payment.currency)) {
    if (!payment.reference || payment.reference.trim() === "") {
      toast.error("Por favor ingrese la referencia del pago.");
      payment._referenceError = true;
      payment._isReferenceActive = true;
      payment._amountConfirmed = true; // El monto ya está validado y asignado
      nextTick(() => {
        const paymentIndex = payments.value.indexOf(payment);
        const referenceInput = document.querySelector(`.payment-reference-input[data-payment-index="${paymentIndex}"]`);
        if (referenceInput) {
          referenceInput.focus();
          referenceInput.select();
        }
      });
      return;
    }
    payment._referenceError = false;
  }

  // 4. Si todo está bien, cerrar estados
  payment._isInputActive = false;
  payment._isReferenceActive = false;
  payment._referenceError = false;
  payment._amountError = false;
  payment._amountConfirmed = false;
  payment._previousAmount = undefined;
};

// Función helper para manejar Enter en el input de monto
const handlePaymentEnter = (event, payment) => {
  event.preventDefault();
  confirmPaymentComplete(payment);
};

// Función helper para manejar Tab en el input de monto
const handlePaymentTab = (payment) => {
  if (requiresReference(payment.method, payment.currency)) {
    payment._isReferenceActive = true;
    nextTick(() => {
      const paymentIndex = payments.value.indexOf(payment);
      const referenceInput = document.querySelector(
        `.payment-reference-input[data-payment-index="${paymentIndex}"]`,
      );
      if (referenceInput) referenceInput.focus();
    });
  }
};

// Función para activar edición de un pago
const editPaymentAmount = (payment) => {
  // Activar modo edición
  payment._isInputActive = true;
  payment.inputAmount = payment.amount ? payment.amount.toString() : "";

  // Guardar el monto anterior para validación (necesario para calcular el restante correctamente)
  payment._previousAmount = payment.amount;

  // Resetear el estado de confirmación para permitir editar
  payment._amountConfirmed = false;

  // Si requiere referencia, también activar el input de referencia para poder editarla
  if (requiresReference(payment.method, payment.currency)) {
    payment._isReferenceActive = true;
  } else {
    payment._isReferenceActive = false;
  }

  // Limpiar errores
  payment._referenceError = false;
  payment._amountError = false;

  // Usar nextTick para asegurar que el DOM esté actualizado y poner foco en el input de Monto
  nextTick(() => {
    const paymentIndex = payments.value.indexOf(payment);
    const input = document.querySelector(
      `.payment-input[data-payment-index="${paymentIndex}"]`,
    );
    if (input) {
      input.focus();
      input.select();
    }
  });
};

// Función para actualizar monto en tiempo real (solo actualiza el input, NO el amount hasta confirmar)
const updatePaymentAmountLive = (payment, value) => {
  // Permitir solo números y punto decimal, pero sin restricciones que bloqueen la escritura
  // Permitir múltiples puntos pero solo procesar el primero
  let cleanValue = value.replace(/[^0-9.]/g, "");

  // Si hay más de un punto, mantener solo el primero
  const firstDotIndex = cleanValue.indexOf(".");
  if (firstDotIndex !== -1) {
    const beforeDot = cleanValue.substring(0, firstDotIndex + 1);
    const afterDot = cleanValue.substring(firstDotIndex + 1).replace(/\./g, "");
    cleanValue = beforeDot + afterDot;
  }

  // Solo actualizar el inputAmount mientras el usuario escribe
  // NO actualizar payment.amount hasta que se confirme (Check o onBlur)
  payment.inputAmount = cleanValue;

  // Guardar el monto anterior para restaurarlo si se cancela
  if (payment._previousAmount === undefined) {
    payment._previousAmount = payment.amount;
  }
};

// Función para eliminar un pago del resumen (solo el último método agregado)
const removePaymentFromSummary = (paymentIndex) => {
  // Obtener todos los pagos con método asignado
  const paymentsWithMethod = payments.value.filter((p) => p.method);

  if (paymentsWithMethod.length === 0) {
    return;
  }

  // Encontrar el último pago con método (el más reciente)
  const lastPaymentWithMethod =
    paymentsWithMethod[paymentsWithMethod.length - 1];
  const lastPaymentIndex = payments.value.indexOf(lastPaymentWithMethod);

  // Solo permitir eliminar el último método agregado
  if (paymentIndex !== lastPaymentIndex) {
    toast.error("Solo se puede eliminar el último método de pago agregado.");
    return;
  }

  const payment = payments.value[paymentIndex];

  // Limpiar timeouts
  if (payment.debounceTimeout) {
    clearTimeout(payment.debounceTimeout);
  }

  // Guardar el monto antes de eliminar para actualizar el restante
  const amountToRestore = payment.amount || 0;

  // Eliminar el pago del array (no solo limpiar, sino remover completamente)
  payments.value.splice(paymentIndex, 1);

  // El monto restante se actualiza automáticamente a través del computed
  // ya que el pago fue removido del array
};

// Función para obtener el icono del método de pago
const getPaymentMethodIcon = (methodValue) => {
  const icons = {
    cash_bs: "tabler-cash",
    cash_cop: "tabler-cash",
    cash_usd: "tabler-cash",
    mobile_payment: "tabler-device-mobile",
    bank_transfer: "tabler-transfer",
    bank_transfer_bs: "tabler-transfer",
    debit_card: "tabler-credit-card",
    credit_card: "tabler-credit-card",
    binance: "tabler-currency-bitcoin",
    paypal: "tabler-brand-paypal",
    credit: "tabler-file-invoice",
    balance: "tabler-wallet",
  };
  return icons[methodValue] || "tabler-wallet";
};

// Computed para verificar si un método está activo (tiene monto configurado)
const isPaymentMethodActive = (methodValue, currency) => {
  return payments.value.some(
    (p) =>
      p.method === methodValue &&
      p.currency === currency &&
      (p.amount > 0 || p._isInputActive),
  );
};

// Computed para verificar si un método ya fue agregado para una moneda (para deshabilitar)
const isPaymentMethodAdded = (methodValue, currency) => {
  return payments.value.some(
    (p) => p.method === methodValue && p.currency === currency,
  );
};

// Función para verificar si un pago es el último agregado (para habilitar/deshabilitar botón eliminar)
const isLastPaymentAdded = (payment) => {
  const paymentsWithMethod = payments.value.filter((p) => p.method);
  if (paymentsWithMethod.length === 0) return false;
  const lastPayment = paymentsWithMethod[paymentsWithMethod.length - 1];
  return (
    payments.value.indexOf(payment) === payments.value.indexOf(lastPayment)
  );
};

// Función para obtener métodos disponibles para una moneda
const getAvailableMethodsForCurrency = (currency) => {
  const methods = paymentMethodsByCurrency[currency] || [];
  return methods.filter((m) => {
    // El método 'credit' está disponible en USD
    if (m.value === "balance") {
      return currency === "USD" && props.orderData.client?.balance > 0;
    }
    return true;
  });
};
</script>
<template>
  <VDialog
    v-model="dialogVisible"
    :fullscreen="$vuetify.display.xs"
    transition="dialog-bottom-transition"
    class="buys-modal-dialog"
  >
    <VCard class="rounded-xl overflow-hidden glass-card elevation-4">
      <VCardTitle class="d-flex align-center pa-4 border-b bg-surface">
        <div class="d-flex align-center">
          <VIcon icon="tabler-shopping-cart-check" color="primary" class="me-3" size="28" />
          <span class="text-h5 font-weight-black uppercase letter-spacing-1">Finalizar Compra</span>
        </div>
        <div class="ms-6 d-flex align-center">
          <span class="text-caption font-weight-bold me-2 uppercase">Factura</span>
          <VSwitch v-model="invoiceSwitch" density="compact" color="primary" hide-details />
        </div>
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
      <VCardText v-if="currentProgress === 0" class="pa-0 bg-light-grey">
        <div v-if="!ratesLoaded" class="pa-10 text-center">
          <VProgressCircular indeterminate color="primary" size="48" />
          <div class="mt-3 text-subtitle-2 font-weight-bold uppercase letter-spacing-1">Cargando Tasas...</div>
        </div>
        
        <VRow v-else no-gutters>
          <!-- Columna Izquierda: Detalle y Métodos (Compacto) -->
          <VCol cols="12" md="7" lg="8" class="pa-3 border-e">
            <div class="d-flex flex-column gap-3">
              <CheckoutProductList 
                :products="orderProducts" 
                :selected-currency="selectedCurrency"
                :get-product-price="getProductPrice"
                :get-product-price-sin-iva="getProductPriceSinIva"
                :get-iva="getIva"
              />

              <CheckoutPaymentMethods 
                v-model:selectedCurrencyTab="selectedCurrencyTab"
                :currencies="[{value: 'USD'}, {value: 'COP'}, {value: 'BS'}]"
                :payment-methods-by-currency="paymentMethodsByCurrency"
                :remaining-amount="remainingAmount"
                :is-payment-method-active="isPaymentMethodActive"
                :is-payment-method-added="isPaymentMethodAdded"
                :get-payment-method-icon="getPaymentMethodIcon"
                :get-available-methods-for-currency="getAvailableMethodsForCurrency"
                @select-payment-method="selectPaymentMethod"
              />
            </div>
          </VCol>

          <!-- Columna Derecha: Resumen -->
          <VCol cols="12" md="5" lg="4" class="pa-3 bg-surface">
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
              :change-amount-in-cop="changeAmountInCOP"
              :get-converted-remaining-amount="getConvertedRemainingAmount"
              :get-payment-method-label="getPaymentMethodLabel"
              :edit-payment-amount="editPaymentAmount"
              :remove-payment-from-summary="removePaymentFromSummary"
              :is-last-payment-added="isLastPaymentAdded"
              :handle-payment-enter="handlePaymentEnter"
              :confirm-payment-complete="confirmPaymentComplete"
              :continue-button-text="'Completar Venta'"
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
          :order-data="orderData"
          :order-products="orderProducts"
          :selected-currency="selectedCurrency"
          :get-payment-method-label="getPaymentMethodLabel"
          :payments="payments"
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
          :change-amount-in-cop="changeAmountInCOP"
          @print="handlePrintTicket"
          @cancel="handleCancelAfterTicket"
        />
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.v-table__wrapper > table > tbody > tr > td {
  border-block-end: none !important;
}

.payment-input {
  outline: none;
}

.payment-input:focus {
  background: rgba(var(--v-theme-primary), 0.04) !important;
  border-block-end-color: rgb(var(--v-theme-primary)) !important;
}

.fade-in {
  animation: fade-in 0.3s ease-in;
}

@keyframes fade-in {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.payment-row {
  transition: all 0.2s ease;
}

.payment-method-card {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 8%);
}

.payment-method-card:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.payment-method-card:hover:not(.payment-method-card--add) {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 12%);
  transform: translateY(-2px);
}

.payment-method-card--active {
  border: 2px solid rgb(var(--v-theme-primary)) !important;
  background-color: rgba(var(--v-theme-primary), 0.05);
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.2);
}

.payment-method-card--add:hover {
  border-color: rgb(var(--v-theme-primary)) !important;
  background-color: rgba(var(--v-theme-primary), 0.02);
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

.highlight-border {
  border: 1px solid rgba(var(--v-theme-primary), 0.1) !important;
}

/* Responsividad para móviles */
@media (max-width: 600px) {
  .payment-summary-col {
    position: relative !important;
    inline-size: 100% !important;
    max-block-size: none !important;
  }
}
</style>
