<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed, defineEmits, defineProps, nextTick, onMounted, ref, watch } from "vue";

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
const productsPanelExpanded = ref([0]); // Panel de productos expandido por defecto (0 = primer panel expandido)
const selectedCurrencyTab = ref(props.selectedCurrency); // Pestaña de moneda seleccionada (inicializada con la moneda del pedido)

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
    method
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

// Esta función ya no se usa, pero la mantenemos por compatibilidad
const addPaymentBlock = () => {
  // Ya no se usa, los métodos se agregan automáticamente al seleccionar
};

const removeLastPayment = () => {
  if (payments.value.length > 1) {
    const lastPayment = payments.value[payments.value.length - 1];
    // Limpiar timeout si existe
    if (lastPayment.debounceTimeout) {
      clearTimeout(lastPayment.debounceTimeout);
    }
    payments.value.pop();
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

// Función para verificar si hay pagos con referencias faltantes
const hasMissingReferences = () => {
  // Solo verificar pagos que tienen método asignado y monto confirmado
  return payments.value.some(payment => {
    if (!payment.method) return false;
    if (!payment.amount || payment.amount <= 0) return false; // Solo verificar si el monto está confirmado
    if (requiresReference(payment.method, payment.currency)) {
      const hasReference = payment.reference && payment.reference.trim() !== '';
      return !hasReference;
    }
    return false;
  });
};

const closeModal = () => {
  // Validar referencias antes de cerrar
  if (hasMissingReferences()) {
    toast.error("Por favor complete todas las referencias de pago antes de cerrar.");
    return;
  }
  
  emit("update:isDialogVisible", false);
  emit("modal-closed");
  resetProgress();
};

const handleCompletePurchase = () => {
  // Validar referencias antes de continuar
  if (hasMissingReferences()) {
    toast.error("Por favor complete todas las referencias de pago antes de continuar.");
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
        if (!p.reference || p.reference.trim() === '') {
          return true; // Falta referencia
        }
      }
      
      return false;
    });

    if (invalidPayment) {
      // Mensaje más específico según qué falta
      let errorMessage = "Por favor, revisa y completa los campos de todos los pagos.";
      
      if (!invalidPayment.amount || Number(invalidPayment.amount) <= 0) {
        errorMessage = `El método "${getPaymentMethodLabel(invalidPayment.method, invalidPayment.currency)}" no tiene un monto válido.`;
      } else if (requiresReference(invalidPayment.method, invalidPayment.currency) && (!invalidPayment.reference || invalidPayment.reference.trim() === '')) {
        errorMessage = `El método "${getPaymentMethodLabel(invalidPayment.method, invalidPayment.currency)}" requiere una referencia.`;
      }
      
      toast.error(errorMessage);
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
    // Cuando currentProgress === 100, ejecutar la lógica de completar la compra
    emit(
      "purchase-completed",
      props.orderData.id,
      payments.value,
      hasCreditPayment.value,
      changeAmountInCOP.value,
      changeAmountInUSD.value,
      {
        invoice_switch: invoiceSwitch.value,
        spe: props.orderData?.client?.is_spe || false,
      }
    );
    // NO cerrar el modal aquí, solo mostrar el ticket
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
  invoiceSwitch.value = false;
  // ELIMINAR: speSwitch.value = false;
};

const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});

// Función para manejar la impresión del ticket (solo imprime, no completa la orden)
const handlePrintTicket = async () => {
  // La orden ya fue completada cuando se hizo clic en "Continuar"
  // Aquí solo imprimimos el ticket
  await nextTick();
  const printContents = document.getElementById("orderPrint");
  if (printContents) {
    const printWindow = window.open("", "", "height=600,width=800");
    printWindow.document.write(
      "<html><head><title>Farmacia Barrio Sucre</title>"
    );
    const styleSheets = document.styleSheets;
    for (let i = 0; i < styleSheets.length; i++) {
      const sheet = styleSheets[i];
      try {
        if (sheet.cssRules) {
          let cssText = "";
          for (let j = 0; j < sheet.cssRules.length; j++) {
            cssText += sheet.cssRules[j].cssText;
          }
          printWindow.document.write(`<style>${cssText}</style>`);
        } else if (sheet.href) {
          printWindow.document.write(
            `<link rel="stylesheet" href="${sheet.href}">`
          );
        }
      } catch (e) {
        console.warn(
          "No se pudo acceder a la hoja de estilo:",
          sheet.href || sheet,
          e
        );
      }
    }
    printWindow.document.write("</head><body>");
    printWindow.document.write(printContents.innerHTML);
    printWindow.document.write("</body></html>");
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
  } else {
    console.warn(
      "Elemento #orderPrint no encontrado para impresión tipo ticket. Imprimiendo toda la página."
    );
    window.print();
  }
};

// Función para cancelar después de ver el ticket
const handleCancelAfterTicket = () => {
  dialogVisible.value = false;
  resetProgress();
};

watch(
  () => props.isDialogVisible,
  (newVal) => {
    if (newVal) {
      resetProgress();
      ratesLoaded.value = false;
      fetchExchangeRates();
      // Establecer la pestaña inicial a la moneda del pedido
      selectedCurrencyTab.value = props.selectedCurrency;
    }
  }
);

// Watch para actualizar el monto restante cuando cambia la pestaña
watch(
  () => selectedCurrencyTab.value,
  () => {
    // El monto restante se actualiza automáticamente a través del computed
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

// Computed para verificar si un pago está configurado (tiene monto)
const isPaymentConfigured = (payment) => {
  if (payment.method === 'balance') {
    return payment.amount > 0;
  }
  if (isCredit(payment.method)) {
    return true; // Los créditos siempre están configurados
  }
  return payment.method && payment.amount && Number(payment.amount) > 0;
};

// Computed para obtener el monto formateado de un pago
const getPaymentAmount = (payment) => {
  if (payment.method === 'balance') {
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
    .filter(p => p.isConfigured);
});

// Computed para obtener pagos válidos para el ticket (con método y monto > 0)
const validPaymentsForTicket = computed(() => {
  return payments.value.filter(payment => {
    // Filtrar pagos que tengan método válido (no null, no undefined)
    if (!payment.method || payment.method === null || payment.method === undefined) {
      return false;
    }
    
    // Verificar que el label del método no sea "N/A"
    const methodLabel = getPaymentMethodLabel(payment.method, payment.currency);
    if (methodLabel === 'N/A') {
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
  if (methodValue === 'balance') {
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
    availablePayment.inputAmount = '';
    availablePayment.amount = null; // No confirmar hasta que el usuario lo haga
    availablePayment._isInputActive = true;
    // Usar nextTick para asegurar que el DOM esté actualizado
    nextTick(() => {
      const paymentIndex = payments.value.indexOf(availablePayment);
      const input = document.querySelector(`.payment-input[data-payment-index="${paymentIndex}"]`);
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
  if (payment._isInputActive && payment.inputAmount !== null && payment.inputAmount !== '' && payment.inputAmount !== undefined) {
    const numValue = parseFloat(payment.inputAmount);
    if (!isNaN(numValue) && numValue > 0) {
      // Validación: métodos no-efectivo no pueden exceder el monto restante
      if (!isCashMethod(payment.method)) {
        // Si se está editando, sumar el monto anterior al restante para validar correctamente
        const previousAmount = payment.amount || 0;
        let remainingInPaymentCurrency = getConvertedRemainingAmount(payment.currency);
        
        // Si hay un monto anterior, sumarlo al restante para obtener el restante real
        if (previousAmount > 0) {
          if (payment.currency === props.selectedCurrency) {
            remainingInPaymentCurrency += previousAmount;
          } else {
            // Convertir el monto anterior a la moneda base y luego a la moneda del pago
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
          const referenceInput = document.querySelector(`.payment-reference-input[data-payment-index="${paymentIndex}"]`);
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
  } else if (payment._isInputActive && (!payment.inputAmount || payment.inputAmount === '')) {
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
  // Validar monto primero
  if (!payment.amount || payment.amount <= 0) {
    toast.error("Por favor ingrese un monto válido.");
    // Si estamos en modo edición, mantener el input activo
    if (payment._isInputActive) {
      payment._amountError = true;
      nextTick(() => {
        const paymentIndex = payments.value.indexOf(payment);
        const input = document.querySelector(`.payment-input[data-payment-index="${paymentIndex}"]`);
        if (input) {
          input.focus();
        }
      });
    }
    return;
  }
  
  // Validar referencia si es requerida
  if (requiresReference(payment.method, payment.currency)) {
    if (!payment.reference || payment.reference.trim() === '') {
      toast.error("Por favor ingrese la referencia del pago.");
      payment._referenceError = true;
      // Activar el input de referencia para que el usuario pueda escribir
      payment._isReferenceActive = true;
      nextTick(() => {
        const paymentIndex = payments.value.indexOf(payment);
        const referenceInput = document.querySelector(`.payment-reference-input[data-payment-index="${paymentIndex}"]`);
        if (referenceInput) {
          referenceInput.focus();
        }
      });
      return;
    }
    payment._referenceError = false;
  }
  
  // Si todo está bien, desactivar inputs y limpiar estados
  payment._isInputActive = false;
  payment._isReferenceActive = false;
  payment._referenceError = false;
  payment._amountError = false;
  payment._amountConfirmed = false;
  payment._previousAmount = undefined;
  
  // El monto restante se actualizará automáticamente porque payment.amount ya está actualizado
};

// Función helper para manejar Enter en el input de monto
const handlePaymentEnter = (event, payment) => {
  // Prevenir el comportamiento por defecto del Enter
  event.preventDefault();
  
  // Primero confirmar el monto
  if (payment.inputAmount !== null && payment.inputAmount !== '' && payment.inputAmount !== undefined) {
    const numValue = parseFloat(payment.inputAmount);
    if (!isNaN(numValue) && numValue > 0) {
      // Validación: métodos no-efectivo no pueden exceder el monto restante
      if (!isCashMethod(payment.method)) {
        const previousAmount = payment.amount || 0;
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
      
      // Confirmar el monto
      payment.amount = numValue;
      payment.inputAmount = numValue.toString();
      payment._previousAmount = undefined;
      
      // Si requiere referencia, mantener el bloque activo y activar el input de referencia automáticamente
      if (requiresReference(payment.method, payment.currency)) {
        // Mantener _isInputActive = true para que se muestre el bloque de inputs
        payment._isReferenceActive = true;
        payment._amountConfirmed = true; // Marcar que el monto ya está confirmado
        nextTick(() => {
          const paymentIndex = payments.value.indexOf(payment);
          const referenceInput = document.querySelector(`.payment-reference-input[data-payment-index="${paymentIndex}"]`);
          if (referenceInput) {
            referenceInput.focus();
            referenceInput.select();
          }
        });
      } else {
        // Si no requiere referencia, confirmar el pago completo directamente
        payment._isInputActive = false;
        payment._isReferenceActive = false;
        payment._amountConfirmed = false;
        confirmPaymentComplete(payment);
      }
    } else {
      toast.error("Por favor ingrese un monto válido.");
    }
  } else {
    toast.error("Por favor ingrese un monto válido.");
  }
};

// Función helper para manejar Tab en el input de monto
const handlePaymentTab = (payment) => {
  if (requiresReference(payment.method, payment.currency)) {
    payment._isReferenceActive = true;
    nextTick(() => {
      const paymentIndex = payments.value.indexOf(payment);
      const referenceInput = document.querySelector(`.payment-reference-input[data-payment-index="${paymentIndex}"]`);
      if (referenceInput) referenceInput.focus();
    });
  }
};

// Función para activar edición de un pago
const editPaymentAmount = (payment) => {
  // Activar modo edición
  payment._isInputActive = true;
  payment.inputAmount = payment.amount ? payment.amount.toString() : '';
  
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
    const input = document.querySelector(`.payment-input[data-payment-index="${paymentIndex}"]`);
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
  let cleanValue = value.replace(/[^0-9.]/g, '');
  
  // Si hay más de un punto, mantener solo el primero
  const firstDotIndex = cleanValue.indexOf('.');
  if (firstDotIndex !== -1) {
    const beforeDot = cleanValue.substring(0, firstDotIndex + 1);
    const afterDot = cleanValue.substring(firstDotIndex + 1).replace(/\./g, '');
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
  const paymentsWithMethod = payments.value.filter(p => p.method);
  
  if (paymentsWithMethod.length === 0) {
    return;
  }
  
  // Encontrar el último pago con método (el más reciente)
  const lastPaymentWithMethod = paymentsWithMethod[paymentsWithMethod.length - 1];
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
    'cash_bs': 'tabler-cash',
    'cash_cop': 'tabler-cash',
    'cash_usd': 'tabler-cash',
    'mobile_payment': 'tabler-device-mobile',
    'bank_transfer': 'tabler-transfer',
    'bank_transfer_bs': 'tabler-transfer',
    'debit_card': 'tabler-credit-card',
    'credit_card': 'tabler-credit-card',
    'binance': 'tabler-currency-bitcoin',
    'paypal': 'tabler-brand-paypal',
    'credit': 'tabler-file-invoice',
    'balance': 'tabler-wallet',
  };
  return icons[methodValue] || 'tabler-wallet';
};

// Computed para verificar si un método está activo (tiene monto configurado)
const isPaymentMethodActive = (methodValue, currency) => {
  return payments.value.some(p => 
    p.method === methodValue && 
    p.currency === currency && 
    (p.amount > 0 || p._isInputActive)
  );
};

// Computed para verificar si un método ya fue agregado para una moneda (para deshabilitar)
const isPaymentMethodAdded = (methodValue, currency) => {
  return payments.value.some(p => 
    p.method === methodValue && 
    p.currency === currency
  );
};

// Función para verificar si un pago es el último agregado (para habilitar/deshabilitar botón eliminar)
const isLastPaymentAdded = (payment) => {
  const paymentsWithMethod = payments.value.filter(p => p.method);
  if (paymentsWithMethod.length === 0) return false;
  const lastPayment = paymentsWithMethod[paymentsWithMethod.length - 1];
  return payments.value.indexOf(payment) === payments.value.indexOf(lastPayment);
};

// Función para obtener métodos disponibles para una moneda
const getAvailableMethodsForCurrency = (currency) => {
  const methods = paymentMethodsByCurrency[currency] || [];
  return methods.filter((m) => {
    // El método 'credit' está disponible en USD
    if (m.value === 'balance') {
      return currency === 'USD' && props.orderData.client?.balance > 0;
    }
    return true;
  });
};
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
      <VCardText v-if="currentProgress === 0" class="pa-0">
        <div v-if="!ratesLoaded" class="text-center py-10">
          <VProgressCircular indeterminate color="primary"></VProgressCircular>
          <p class="mt-4">Cargando tasas de cambio. Por favor, espere...</p>
        </div>

        <div v-else class="d-flex gap-4" style="min-height: 500px;">
          <!-- COLUMNA IZQUIERDA: Productos (Arriba) y Métodos de Pago (Abajo) -->
          <div class="flex-grow-1" style="flex: 1; overflow-y: auto;">
            <div class="pa-4">
              <!-- Lista de Productos (Arriba - Expandida por defecto) -->
              <VExpansionPanels v-model="productsPanelExpanded" variant="accordion" class="mb-4">
                <VExpansionPanel>
                  <VExpansionPanelTitle>
                    <div class="d-flex align-center justify-space-between w-100">
                      <div class="d-flex align-center">
                        <VIcon icon="tabler-package" class="me-2" size="20" />
                        <span class="font-weight-medium text-body-1">Ver detalle de productos</span>
                      </div>
                      <VChip
                        label
                        :color="chipColor"
                        variant="tonal"
                        density="compact"
                        size="small"
                        class="ms-auto me-2"
                      >
                        <span class="font-weight-medium">{{ totalSelectedQuantity }} productos</span>
                      </VChip>
                    </div>
                  </VExpansionPanelTitle>
                  <VExpansionPanelText>
                    <VTable density="compact" lines="none" class="py-2">
                      <thead>
                        <tr>
                          <th class="text-left" style="width: 40%">Producto</th>
                          <th class="text-right" style="width: 20%">Precio</th>
                          <th class="text-right" style="width: 20%">IVA</th>
                          <th class="text-right" style="width: 20%">Total</th>
                        </tr>
                      </thead>
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
                              <span class="text-sm" style="color: rgba(0, 0, 0, 0.6)">
                                {{ product.active_ingredient }}
                                {{ product.laboratory ? `- ${product.laboratory}` : "" }}
                                {{ product.selectedQuantity }} x
                              </span>
                            </div>
                          </td>
                          <td class="text-right">
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
                          </td>
                          <td class="text-right">
                            <span class="text-body-1 font-weight-regular">
                              {{
                                formatCurrency(
                                  getIva(product, props.selectedCurrency),
                                  props.selectedCurrency
                                )
                              }}
                            </span>
                          </td>
                          <td class="text-right">
                            <div class="d-flex align-center gap-1 justify-end">
                              <span
                                v-if="activeDiscountDisplay"
                                class="text-caption text-disabled text-decoration-line-through text-error"
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
                          </td>
                        </tr>
                      </tbody>
                    </VTable>
                  </VExpansionPanelText>
                </VExpansionPanel>
              </VExpansionPanels>

              <!-- Selección de Métodos de Pago (Abajo - Con Pestañas) -->
              <div class="mt-4">
                <div class="d-flex align-center mb-3">
                  <VIcon icon="tabler-credit-card" class="me-2" size="20" />
                  <p class="font-weight-medium text-h6 mb-0">Métodos de Pago</p>
                </div>
                
                <!-- Pestañas de Monedas -->
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

                <!-- Contenido de cada pestaña -->
                <VTabsWindow v-model="selectedCurrencyTab">
                  <VTabsWindowItem
                    v-for="currency in currencies"
                    :key="currency.value"
                    :value="currency.value"
                  >
                    <!-- Métodos de pago para esta moneda -->
                    <div class="d-flex flex-wrap gap-2 mt-2">
                      <VBtn
                        v-for="method in getAvailableMethodsForCurrency(currency.value)"
                        :key="method.value"
                        :class="[
                          'payment-method-btn',
                          { 
                            'payment-method-btn--active': isPaymentMethodActive(method.value, currency.value),
                            'payment-method-btn--added': isPaymentMethodAdded(method.value, currency.value)
                          }
                        ]"
                        :variant="isPaymentMethodActive(method.value, currency.value) ? 'flat' : 'outlined'"
                        :color="isPaymentMethodActive(method.value, currency.value) ? 'primary' : 'default'"
                        :disabled="remainingAmount <= 0 || isPaymentMethodAdded(method.value, currency.value)"
                        @click="selectPaymentMethod(method.value, currency.value)"
                        size="small"
                      >
                        <VIcon 
                          :icon="getPaymentMethodIcon(method.value)" 
                          size="18"
                          class="me-1"
                        />
                        {{ method.label }}
                        <VIcon
                          v-if="isPaymentMethodActive(method.value, currency.value)"
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
          <div style="width: 400px; position: sticky; top: 0; align-self: flex-start; max-height: calc(100vh - 200px); display: flex; flex-direction: column;">
            <VCard variant="outlined" class="flex-grow-1" style="display: flex; flex-direction: column;">
              <VCardText style="flex: 1; overflow-y: auto;">
                <div class="text-h6 font-weight-bold mb-4">Resumen de Pago</div>
                
                <!-- Descuentos -->
                <div
                  v-if="activeDiscountDisplay"
                  class="d-flex justify-space-between mb-2"
                >
                  <span class="text-body-1">{{ activeDiscountDisplay.label }}:</span>
                  <span class="text-body-1 font-weight-medium text-error">
                    - {{ activeDiscountDisplay.formatted }}
                  </span>
                </div>

                <div
                  v-if="expirationDiscountTotal > 0"
                  class="d-flex justify-space-between mb-2"
                >
                  <span class="text-body-1">Descuento Vencimiento:</span>
                  <span class="text-body-1 font-weight-medium text-error">
                    - {{ formatCurrency(expirationDiscountTotal, props.selectedCurrency) }}
                  </span>
                </div>

                <div
                  v-if="appliesSpecialTax"
                  class="d-flex justify-space-between mb-2"
                >
                  <span class="text-body-1">Recargo SPE (3%):</span>
                  <span class="text-body-1 font-weight-medium">
                    {{ formatCurrency(specialTaxAmount, props.selectedCurrency) }}
                  </span>
                </div>

                <VDivider class="my-3" />

                <!-- Total Compra -->
                <div class="d-flex justify-space-between mb-3">
                  <span class="text-h6 font-weight-bold">Total Compra:</span>
                  <span class="text-h6 font-weight-bold">
                    {{ formatCurrency(roundedTotalAmountToPay, props.selectedCurrency) }}
                  </span>
                </div>

                <!-- Lista de Pagos con Inputs Integrados (Tipo Recibo) -->
                <div v-if="payments.filter(p => p.method).length > 0" class="mb-3">
                  <div
                    v-for="(payment, idx) in payments.filter(p => p.method)"
                    :key="idx"
                    class="d-flex justify-space-between align-center mb-3 payment-row"
                    style="min-height: 32px;"
                  >
                    <span class="text-body-1">
                      {{ getPaymentMethodLabel(payment.method, payment.currency) }}:
                    </span>
                    
                    <!-- Input activo (cuando _isInputActive es true) -->
                    <div
                      v-if="payment._isInputActive"
                      class="d-flex align-center gap-2 fade-in"
                      style="flex: 0 0 auto;"
                    >
                      <input
                        :ref="el => { 
                          if (el && payment._isInputActive && !payment._isReferenceActive && !payment._amountConfirmed) {
                            nextTick(() => {
                              el.focus();
                            });
                          }
                        }"
                        :value="payment.inputAmount || ''"
                        @input="updatePaymentAmountLive(payment, $event.target.value)"
                        @keydown.enter="handlePaymentEnter($event, payment)"
                        @keyup.tab="handlePaymentTab(payment)"
                        @blur="confirmPaymentAmount(payment)"
                        @focus="$event.target.style.borderBottomColor = 'rgb(var(--v-theme-primary))'"
                        :readonly="payment._amountConfirmed"
                        type="text"
                        inputmode="decimal"
                        class="payment-input"
                        :data-payment-index="payments.indexOf(payment)"
                        :placeholder="formatCurrency(getConvertedRemainingAmount(payment.currency), payment.currency)"
                        :style="{
                          border: 'none',
                          borderBottom: payment._amountError ? '2px solid rgb(var(--v-theme-error))' : '1px solid rgba(0, 0, 0, 0.42)',
                          background: payment._amountConfirmed ? 'rgba(0, 0, 0, 0.04)' : 'rgba(0, 0, 0, 0.02)',
                          padding: '4px 8px',
                          width: '120px',
                          textAlign: 'right',
                          fontSize: '14px',
                          transition: 'all 0.2s ease',
                          cursor: payment._amountConfirmed ? 'default' : 'text'
                        }"
                      />
                      <span class="text-caption text-medium-emphasis">{{ payment.currency }}</span>
                      
                      <!-- Input de Referencia (si es requerido) -->
                      <input
                        v-if="requiresReference(payment.method, payment.currency) && payment._isReferenceActive"
                        :ref="el => { 
                          if (el && payment._isReferenceActive) {
                            nextTick(() => {
                              el.focus();
                            });
                          }
                        }"
                        :value="payment.reference || ''"
                        @input="payment.reference = $event.target.value; payment._referenceError = false"
                        @keydown.enter.prevent="confirmPaymentComplete(payment)"
                        @blur="
                          if (payment.reference && payment.reference.trim() !== '') {
                            payment._referenceError = false;
                          }
                        "
                        @focus="$event.target.style.borderBottomColor = 'rgb(var(--v-theme-primary))'"
                        type="text"
                        class="payment-reference-input"
                        :data-payment-index="payments.indexOf(payment)"
                        placeholder="Referencia"
                        :style="{
                          border: 'none',
                          borderBottom: payment._referenceError ? '2px solid rgb(var(--v-theme-error))' : '1px solid rgba(0, 0, 0, 0.42)',
                          background: 'rgba(0, 0, 0, 0.02)',
                          padding: '4px 8px',
                          width: '90px',
                          fontSize: '13px',
                          transition: 'all 0.2s ease'
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
                        <VTooltip activator="parent" location="top">Confirmar</VTooltip>
                      </VBtn>
                      <VBtn
                        icon
                        variant="text"
                        size="x-small"
                        color="error"
                        :disabled="!isLastPaymentAdded(payment)"
                        @click="removePaymentFromSummary(payments.indexOf(payment))"
                      >
                        <VIcon icon="tabler-x" size="16" />
                        <VTooltip activator="parent" location="top">
                          {{ isLastPaymentAdded(payment) ? 'Eliminar' : 'Solo se puede eliminar el último método agregado' }}
                        </VTooltip>
                      </VBtn>
                    </div>
                    
                    <!-- Texto fijo con icono de editar (cuando _isInputActive es false) -->
                    <div
                      v-else
                      class="d-flex flex-column align-end gap-1 fade-in"
                      style="flex: 0 0 auto;"
                    >
                      <div class="d-flex align-center gap-2">
                        <span class="text-body-1 font-weight-medium text-error">
                          -{{ formatCurrency(payment.amount || 0, payment.currency) }}
                        </span>
                        <VBtn
                          icon
                          variant="text"
                          size="x-small"
                          color="primary"
                          @click="editPaymentAmount(payment)"
                        >
                          <VIcon icon="tabler-pencil" size="16" />
                          <VTooltip activator="parent" location="top">Editar</VTooltip>
                        </VBtn>
                        <VBtn
                          icon
                          variant="text"
                          size="x-small"
                          color="error"
                          :disabled="!isLastPaymentAdded(payment)"
                          @click="removePaymentFromSummary(payments.indexOf(payment))"
                        >
                          <VIcon icon="tabler-x" size="16" />
                          <VTooltip activator="parent" location="top">
                            {{ isLastPaymentAdded(payment) ? 'Eliminar' : 'Solo se puede eliminar el último método agregado' }}
                          </VTooltip>
                        </VBtn>
                      </div>
                      <!-- Mostrar referencia si existe y es requerida -->
                      <div
                        v-if="requiresReference(payment.method, payment.currency) && payment.reference"
                        class="text-caption text-medium-emphasis"
                        style="font-size: 11px;"
                      >
                        Ref: {{ payment.reference }}
                      </div>
                    </div>
                  </div>
                </div>

                <VDivider class="my-3" />

                <!-- Monto Restante (convertido a la moneda de la pestaña seleccionada) -->
                <div class="d-flex justify-space-between mb-4">
                  <span class="text-h6 font-weight-bold">Restante:</span>
                  <span
                    class="text-h6 font-weight-bold"
                    :class="remainingAmount <= 0 ? 'text-success' : 'text-error'"
                  >
                    {{ formatCurrency(getConvertedRemainingAmount(selectedCurrencyTab), selectedCurrencyTab) }}
                  </span>
                </div>

                <!-- Monto Devuelto -->
                <div
                  v-if="showChangeAmount"
                  class="d-flex justify-space-between mb-4"
                >
                  <span class="text-body-1 font-weight-medium">Monto Devuelto:</span>
                  <span class="text-body-1 font-weight-bold text-success">
                    {{ formatCurrency(changeAmountInCOP, "COP") }}
                  </span>
                </div>
              </VCardText>

              <!-- Botones fijos en la parte inferior -->
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
                  :style="remainingAmount <= 0 && !hasMissingReferences() ? 'background-color: #28C76F; color: white;' : 'background-color: rgba(0, 0, 0, 0.12); color: rgba(0, 0, 0, 0.38);'"
                  variant="flat"
                  @click="handleCompletePurchase"
                  block
                  :disabled="currentProgress === 0 && (remainingAmount > 0.01 || hasMissingReferences())"
                >
                  {{ continueButtonText }}
                </VBtn>
              </VCardActions>
            </VCard>

            <!-- Información SPE (si aplica) -->
            <div
              v-if="props.orderData?.client?.is_spe"
              class="bg-success-lighten-4 pa-3 rounded mt-3"
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
          </div>
        </div>

        <!-- NUEVA sección de información SPE mejorada -->
        <div
          v-if="props.orderData?.client?.is_spe"
          class="bg-success-lighten-4 pa-3 rounded mt-3"
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
                    {{ getPaymentMethodLabel(payment.method, payment.currency) }}: 
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
        
        <!-- Botones de Imprimir y Cancelar después del ticket -->
        <VDivider class="my-4" />
        <VCardActions class="pa-4 d-flex flex-column gap-2">
          <VBtn
            color="primary"
            variant="flat"
            @click="handlePrintTicket"
            block
            size="large"
          >
            <VIcon icon="tabler-printer" class="me-2" />
            Imprimir
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
.v-table__wrapper > table > tbody > tr > td {
  border-bottom: none !important;
}

.payment-input {
  outline: none;
}

.payment-input:focus {
  border-bottom-color: rgb(var(--v-theme-primary)) !important;
  background: rgba(var(--v-theme-primary), 0.04) !important;
}

.fade-in {
  animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
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
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

.payment-method-card:hover:not(.payment-method-card--add) {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12);
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

.payment-method-card:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.payment-method-btn {
  transition: all 0.2s ease;
}

.payment-method-btn--active {
  box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.3);
}

.payment-method-btn--added {
  opacity: 0.6;
  cursor: not-allowed;
}

.payment-method-btn--added:hover {
  transform: none;
}
</style>
