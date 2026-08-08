import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed, ref } from "vue";

export function useCheckout(props) {
  const ratesLoaded = ref(false);
  const exchangeRates = ref({});
  const payments = ref([]);

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

  const isCredit = (value) => value === "credit" || value === "credit_card";
  const isCashMethod = (method) => ["cash_bs", "cash_usd", "cash_cop"].includes(method);

  const requiresReference = (method, currency) => {
    if (isCashMethod(method)) return false;
    if (isCredit(method) && currency === "USD") return false;
    if (method === "balance") return false;
    return true;
  };

  function roundToTwoDecimalPlaces(num) {
    if (!num || isNaN(num)) return 0
    return Math.round((Number(num) + Number.EPSILON) * 100) / 100
  }

  const fetchExchangeRates = async () => {
    ratesLoaded.value = false;
    try {
      const response = await axios.get("/public/exchange-rates");
      const apiRates = response.data;
      const formattedRates = {};
      apiRates.forEach((rateItem) => {
        const currencyCode = rateItem.currency_code;
        const rateValue = parseFloat(rateItem.rate);
        if (!formattedRates["USD"]) formattedRates["USD"] = {};
        formattedRates["USD"][currencyCode] = rateValue;
        if (!formattedRates[currencyCode]) formattedRates[currencyCode] = {};
        if (rateValue !== 0) formattedRates[currencyCode]["USD"] = 1 / rateValue;
        
        if (formattedRates["COP"] && formattedRates["BS"]) {
          formattedRates["COP"]["BS"] = parseFloat((formattedRates["COP"]["USD"] * formattedRates["USD"]["BS"]).toFixed(9));
          formattedRates["BS"]["COP"] = parseFloat((formattedRates["BS"]["USD"] * formattedRates["USD"]["COP"]).toFixed(9));
        }
      });
      exchangeRates.value = formattedRates;
      ratesLoaded.value = true;
    } catch (error) {
      toast.error("No se pudieron cargar las tasas de cambio.");
      ratesLoaded.value = false;
    }
  };

  const totalPaidAmount = computed(() => {
    let currentSum = 0;
    payments.value.forEach((payment) => {
      let amount = Number(payment.amount) || 0;
      let amountToAdd = 0;
      if (payment.currency === props.selectedCurrency) {
        amountToAdd = amount;
      } else {
        const rate = exchangeRates.value?.[payment.currency]?.[props.selectedCurrency];
        if (rate) amountToAdd = amount * rate;
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
          const rate = exchangeRates.value?.[payment.currency]?.[props.selectedCurrency];
          if (rate) amountToAdd = amount * rate;
        }
        currentSum = roundToTwoDecimalPlaces(currentSum + amountToAdd);
      }
    });
    return currentSum;
  });

  const appliesSpecialTax = computed(() => {
    return props.isSpecialTaxpayer && (props.selectedCurrency === "USD" || props.selectedCurrency === "COP");
  });

  const specialTaxAmount = computed(() => {
    if (!appliesSpecialTax.value) return 0;
    let tax = props.totalAmount * 0.03;
    if (props.selectedCurrency === "COP") tax = Math.ceil(tax / 100) * 100;
    return tax;
  });

  const roundedTotalAmountToPay = computed(() => {
    let baseAmount = props.totalAmount;
    if (appliesSpecialTax.value) baseAmount += specialTaxAmount.value;
    if (props.selectedCurrency === "COP") return roundUpToNearestHundred(baseAmount);
    return parseFloat(baseAmount.toFixed(2));
  });

  const remainingAmount = computed(() => {
    let totalWithDiscount = props.totalAmount;
    if (appliesSpecialTax.value) totalWithDiscount += specialTaxAmount.value;
    const rawDifference = totalWithDiscount - totalPaidAmount.value;
    if (props.selectedCurrency === "COP") return roundUpToNearestHundred(rawDifference);
    return roundToTwoDecimalPlaces(rawDifference);
  });

  const getConvertedRemainingAmount = (currency) => {
    const baseCurrency = props.selectedCurrency;
    let result = 0;
    if (baseCurrency === currency) {
      result = remainingAmount.value;
    } else if (ratesLoaded.value) {
      // Usar la tasa oficial estándar COP para el total/restante a pagar
      const targetRateKey = currency === "COP" ? "COP" : currency;
      const rate = exchangeRates.value[baseCurrency]?.[targetRateKey];
      if (rate) result = remainingAmount.value * rate;
    }
    if (currency === "COP") return roundUpToNearestHundred(result);
    return parseFloat(result.toFixed(2));
  };

  const getDiscountFactor = (product) => {
    if (props.globalDiscount && props.globalDiscount.percentage > 0 && product.discount_type !== "expiration") {
      return 1 - props.globalDiscount.percentage / 100;
    }
    return 1;
  };

  const getProductPrice = (product, currency) => {
    const taxRate = product.taxRate || 0;
    let basePrice = 0;
    if (currency === "BS") basePrice = product.price_bs || 0;
    else if (currency === "COP") basePrice = product.price_cop || 0;
    else basePrice = product.price || 0;

    basePrice = basePrice * getDiscountFactor(product);

    let effectiveTaxRate = taxRate;
    if (props.orderData?.client?.is_spe) effectiveTaxRate = taxRate * 0.25;

    let priceWithIva = basePrice * (1 + effectiveTaxRate);
    if (currency === "COP") priceWithIva = roundUpToNearestHundred(priceWithIva);
    return priceWithIva * product.selectedQuantity;
  };

  const getIva = (product, currency) => {
    const taxRate = product.taxRate || 0;
    let basePrice = 0;
    if (currency === "BS") basePrice = product.price_bs || 0;
    else if (currency === "COP") basePrice = product.price_cop || 0;
    else basePrice = product.price || 0;

    let ivaAmount = basePrice * taxRate * product.selectedQuantity;
    if (props.orderData?.client?.is_spe) ivaAmount = ivaAmount * 0.25;
    if (currency === "COP") ivaAmount = roundUpToNearestHundred(ivaAmount);
    return ivaAmount;
  };

  const totalSPESavings = computed(() => {
    if (!props.orderData?.client?.is_spe) return 0;
    let totalOriginalIVA = 0;
    props.orderProducts.forEach((product) => {
      const taxRate = product.taxRate || 0;
      let basePrice = 0;
      if (props.selectedCurrency === "BS") basePrice = product.price_bs || 0;
      else if (props.selectedCurrency === "COP") basePrice = product.price_cop || 0;
      else basePrice = product.price || 0;
      totalOriginalIVA += basePrice * taxRate * product.selectedQuantity;
    });
    const savings = totalOriginalIVA * 0.75;
    return props.selectedCurrency === "COP" ? roundUpToNearestHundred(savings) : savings;
  });

  const changeAmount = computed(() => {
    let totalToPay = props.totalAmount;
    if (appliesSpecialTax.value) totalToPay += specialTaxAmount.value;
    if (props.selectedCurrency === "COP") {
      const totalToPayRounded = roundUpToNearestHundred(totalToPay);
      return Math.max(0, roundToTwoDecimalPlaces(totalPaidAmount.value - totalToPayRounded));
    }
    return Math.max(0, roundToTwoDecimalPlaces(totalPaidAmount.value - totalToPay));
  });

  const changeAmountInCOP = computed(() => {
    const vueltoEnMonedaOrden = changeAmount.value;
    if (props.selectedCurrency === "COP") return vueltoEnMonedaOrden;
    // Tasa preferencial COPC para dar vueltos en COP cuando se paga en USD, fallback a COP
    const rate = exchangeRates.value?.[props.selectedCurrency]?.["COPC"] || exchangeRates.value?.[props.selectedCurrency]?.["COP"];
    if (rate) return roundUpToNearestHundred(vueltoEnMonedaOrden * rate);
    return 0;
  });

  const changeAmountInUSD = computed(() => {
    const cashPaymentsInUSD = payments.value.filter((p) => p.method === "cash_usd" && p.currency === "USD");
    if (cashPaymentsInUSD.length === 0) return 0;
    let totalCashPaidInUSD = 0;
    cashPaymentsInUSD.forEach((p) => { totalCashPaidInUSD += Number(p.amount) || 0; });
    let totalOrdenEnUSD;
    if (props.selectedCurrency === "USD") {
      totalOrdenEnUSD = props.totalAmount;
    } else {
      const rate = exchangeRates.value?.[props.selectedCurrency]?.["USD"];
      if (!rate) return 0;
      totalOrdenEnUSD = props.totalAmount / rate;
    }
    return Math.max(0, roundToTwoDecimalPlaces(totalCashPaidInUSD - totalOrdenEnUSD));
  });

  const showChangeAmount = computed(() => {
    const hasRelevantCashPayment = payments.value.some(
      (p) => (p.method === "cash_usd" && p.currency === "USD") || (p.method === "cash_cop" && p.currency === "COP") || (p.method === "cash_bs" && p.currency === "BS")
    );
    return hasRelevantCashPayment && changeAmount.value > 0;
  });

  const hasMissingReferences = () => {
    return payments.value.some((p) => {
      if (!p.method || !p.amount || p.amount <= 0) return false;
      if (requiresReference(p.method, p.currency)) {
        return !p.reference || p.reference.trim() === "";
      }
      return false;
    });
  };

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

    if (methodValue === "balance") {
      const clientBalance = props.orderData.client?.balance || 0;
      if (clientBalance <= 0) {
        toast.error("El cliente no tiene saldo disponible.");
        payments.value.pop();
        return;
      }
      const rateToUSD = props.selectedCurrency === "USD" ? 1 : exchangeRates.value?.[props.selectedCurrency]?.["USD"];
      if (!rateToUSD) {
        toast.error("No se encontró la tasa de cambio.");
        payments.value.pop();
        return;
      }
      const remainingInUSD = remainingAmount.value / rateToUSD;
      availablePayment.amount = parseFloat(Math.min(remainingInUSD, clientBalance).toFixed(2));
      availablePayment.inputAmount = availablePayment.amount;
    } else if (isCredit(methodValue)) {
      availablePayment.amount = remainingAmount.value;
      availablePayment.inputAmount = remainingAmount.value;
    } else {
      availablePayment.inputAmount = "";
      availablePayment._isInputActive = true;
    }
  };

  const getProductPriceSinIva = (product, currency) => {
    let basePrice = (currency === "BS") ? (product.price_bs || 0) : (currency === "COP" ? (product.price_cop || 0) : (product.price || 0));
    return (currency === "COP") ? roundUpToNearestHundred(basePrice) : basePrice;
  };

  const getProductPriceSinDescuento = (product, currency) => {
    const taxRate = product.taxRate || 0;
    let basePrice = (currency === "BS") ? (product.price_bs || 0) : (currency === "COP" ? (product.price_cop || 0) : (product.price || 0));
    let effectiveTaxRate = props.orderData?.client?.is_spe ? (taxRate * 0.25) : taxRate;
    let priceWithIva = basePrice * (1 + effectiveTaxRate);
    if (currency === "COP") priceWithIva = roundUpToNearestHundred(priceWithIva);
    return priceWithIva * product.selectedQuantity;
  };

  const isLastPaymentAdded = (payment) => {
    if (payments.value.length === 0) return false;
    return payments.value[payments.value.length - 1] === payment;
  };

  return {
    ratesLoaded,
    exchangeRates,
    payments,
    fetchExchangeRates,
    totalPaidAmount,
    totalPaidAmountNonCash,
    appliesSpecialTax,
    specialTaxAmount,
    roundedTotalAmountToPay,
    remainingAmount,
    getConvertedRemainingAmount,
    getProductPrice,
    getProductPriceSinIva,
    getProductPriceSinDescuento,
    getIva,
    totalSPESavings,
    changeAmount,
    changeAmountInCOP,
    changeAmountInUSD,
    showChangeAmount,
    hasMissingReferences,
    selectPaymentMethod,
    isCredit,
    isLastPaymentAdded,
    isCashMethod,
    requiresReference,
    paymentMethodsByCurrency,
  };
}
