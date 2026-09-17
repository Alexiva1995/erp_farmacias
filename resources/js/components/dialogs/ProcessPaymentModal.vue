<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  exchangeRate: {
    type: Number,
    default: 1,
  },
  paymentGroup: {
    type: Object,
    default: null,
  },
  invoices: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue", "close", "payment-processed"]);

const { mobile } = useDisplay();

const todayDateStr = new Date().toISOString().split("T")[0];

const form = ref({
  payment_type: "full",
  is_partial: false,
  payment_currency: "VES",
  payment_amount: 0,
  payment_date: todayDateStr,
  photo_url: null,
  reference: `CAMBISTA-${todayDateStr}`,
  payment_method: "cambista",
  destination_bank: null,
});

// Bancos oficiales estáticos de Dronena para conciliación y reporte automatizado
const dronenaBanks = [
  { title: "BANCRECER - 01680051115101043568", value: "BANCRECER - 01680051115101043568" },
  { title: "BANESCO - 01340326153261014466", value: "BANESCO - 01340326153261014466" },
  { title: "BANESCO - 01340326153263034391", value: "BANESCO - 01340326153263034391" },
  { title: "BICENTENARIO - 01750350280080076402", value: "BICENTENARIO - 01750350280080076402" },
  { title: "DEL CARIBE - 01140300053000149682", value: "DEL CARIBE - 01140300053000149682" },
  { title: "EXTERIOR - 01150036650360015433", value: "EXTERIOR - 01150036650360015433" },
  { title: "MERCANTIL - 01050102481102031682", value: "MERCANTIL - 01050102481102031682" },
  { title: "NACIONAL DE CREDITO - 01910137192100014169", value: "NACIONAL DE CREDITO - 01910137192100014169" },
  { title: "PROVINCIAL - 01080087140100005071", value: "PROVINCIAL - 01080087140100005071" },
  { title: "SOFITASA - 01370060580000013941", value: "SOFITASA - 01370060580000013941" },
  { title: "VENEZOLANO DE CREDITO - 01040154230154000097", value: "VENEZOLANO DE CREDITO - 01040154230154000097" },
  { title: "VENEZUELA - 01020211670006291538", value: "VENEZUELA - 01020211670006291538" },
];

// Bancos oficiales de Droguerías Cobeca / C.A. Mafarta (Droguería 3)
const mafartaBanks = [
  { title: "BANCO DE VENEZUELA - 01020219190006814326", value: "01020219190006814326" },
  { title: "BANCO PROVINCIAL - 01080358610100010280", value: "01080358610100010280" },
  { title: "BANCO MERCANTIL - 01050063001063242401", value: "01050063001063242401" },
  { title: "BANCO MERCANTIL (Sec) - 01050063011063037247", value: "01050063011063037247" },
  { title: "BANCO BANESCO - 01340340643403004226", value: "01340340643403004226" },
  { title: "BANCO SOFITASA - 01370001040000394901", value: "01370001040000394901" },
  { title: "BANCO NACIONAL DE CREDITO (BNC) - 01910031692131058703", value: "01910031692131058703" },
  { title: "BANCO VENEZOLANO DE CREDITO - 01040107130107115544", value: "01040107130107115544" },
];

// Bancos oficiales de Cristmedicals (Droguería Cristmedicals / Cristalmedicals)
const cristmedicalsBanks = [
  { title: "BANPLUS (MovilPay) - 0174 0144 1214 4440 2133", value: "30" },
  { title: "BANCO PROVINCIAL - 0108 0014 4401 0034 7852", value: "01080014440100347852" },
  { title: "BANESCO - 0134 0435 6943 5102 6986", value: "01340435694351026986" },
  { title: "BANCO DE VENEZUELA - 0102 0219 1900 0117 6179", value: "01020219190001176179" },
  { title: "BANCO NACIONAL DE CREDITO (BNC) - 0191 0040 5621 4008 9488", value: "01910040562140089488" },
];

// Bancos oficiales de Droguería Mega (Dromega)
const dromegaBanks = [
  { title: "Bancaribe - 01140432414320836811", value: "C1141" },
  { title: "Banco Activo - 01710049136001316984", value: "C1711" },
  { title: "Banco de Venezuela - 01020859950000228921", value: "C1022" },
  { title: "Banco del Tesoro - 01630305403053006965", value: "C1631" },
  { title: "Banco Digital de los Trabajadores - 01750040640073861853", value: "C1752" },
  { title: "Banco Exterior - 01150113331001027842", value: "C1151" },
  { title: "Banco Fondo Común - 01510174131000220507", value: "C1511" },
  { title: "Banco Nacional de Crédito (BNC) - BNC0142872100064533", value: "C1911" },
  { title: "Banco Provincial - PROVINCIAL00033506", value: "C1081" },
  { title: "Banco Venezolano de Crédito - 01040107100107232141", value: "C0104" },
  { title: "Banesco Banco Universal - 01340030060301009127", value: "C1341" },
  { title: "Banplus - 01740125481254202334", value: "C1741" },
  { title: "Mercantil Banco Universal - MERCANTIL1065296975", value: "C1051" },
];

const isDronenaPayment = computed(() => {
  if (props.paymentGroup?.supplier_name) {
    const name = String(props.paymentGroup.supplier_name).toUpperCase();
    if (name.includes("NENA") || name.includes("DRONENA")) return true;
  }
  return props.invoices.some((inv) => {
    const sName = String(inv.supplier?.name || inv.supplier_name || "").toUpperCase();
    return sName.includes("NENA") || sName.includes("DRONENA") || inv.supplier_id === 1010;
  });
});

const isMafartaPayment = computed(() => {
  if (props.paymentGroup?.supplier_name) {
    const name = String(props.paymentGroup.supplier_name).toUpperCase();
    if (name.includes("MAFARTA") || name.includes("COBECA") || name.includes("MARFATA")) return true;
  }
  return props.invoices.some((inv) => {
    const sName = String(inv.supplier?.name || inv.supplier_name || "").toUpperCase();
    return sName.includes("MAFARTA") || sName.includes("COBECA") || sName.includes("MARFATA") || inv.supplier_id === 1011;
  });
});

const isCristmedicalsPayment = computed(() => {
  if (props.paymentGroup?.supplier_name) {
    const name = String(props.paymentGroup.supplier_name).toUpperCase();
    if (name.includes("CRIST") || name.includes("CRISTALMEDICALS")) return true;
  }
  return props.invoices.some((inv) => {
    const sName = String(inv.supplier?.name || inv.supplier_name || "").toUpperCase();
    return sName.includes("CRIST") || sName.includes("CRISTALMEDICALS") || inv.supplier_id === 1002;
  });
});

const isDromegaPayment = computed(() => {
  if (props.paymentGroup?.supplier_name) {
    const name = String(props.paymentGroup.supplier_name).toUpperCase();
    if (name.includes("DROMEGA") || name.includes("MEGA")) return true;
  }
  return props.invoices.some((inv) => {
    const sName = String(inv.supplier?.name || inv.supplier_name || "").toUpperCase();
    return sName.includes("DROMEGA") || sName.includes("MEGA") || inv.supplier_id === 1005;
  });
});

const isSumiandesPayment = computed(() => {
  if (props.paymentGroup?.supplier_name) {
    const name = String(props.paymentGroup.supplier_name).toUpperCase();
    if (name.includes("SUMIANDES")) return true;
  }
  return props.invoices.some((inv) => {
    const sName = String(inv.supplier?.name || inv.supplier_name || "").toUpperCase();
    return sName.includes("SUMIANDES") || inv.supplier_id === 1008;
  });
});

const isDrosymcaPayment = computed(() => {
  if (props.paymentGroup?.supplier_name) {
    const name = String(props.paymentGroup.supplier_name).toUpperCase();
    if (name.includes("DROSYM") || name.includes("DROSI")) return true;
  }
  return props.invoices.some((inv) => {
    const sName = String(inv.supplier?.name || inv.supplier_name || "").toUpperCase();
    return sName.includes("DROSYM") || sName.includes("DROSI") || inv.supplier_id === 1006;
  });
});

const isJohanPayment = computed(() => {
  if (props.paymentGroup?.supplier_name) {
    const name = String(props.paymentGroup.supplier_name).toUpperCase();
    if (name.includes("JOHAN") || name.includes("COLOMBIANO") || name.includes("JC")) return true;
  }
  return props.invoices.some((inv) => {
    const sName = String(inv.supplier?.name || inv.supplier_name || "").toUpperCase();
    return sName.includes("JOHAN") || sName.includes("COLOMBIANO") || sName.includes("JC");
  });
});

const supplierPaymentEmail = computed(() => {
  if (props.paymentGroup?.payment_email) return props.paymentGroup.payment_email;
  const firstWithEmail = props.invoices.find((inv) => inv.supplier?.payment_email || inv.supplier?.email);
  return firstWithEmail?.supplier?.payment_email || firstWithEmail?.supplier?.email || null;
});

const shouldShowDestinationBank = computed(() => {
  // Para Mafarta / Cobeca, SIEMPRE mostrar banco destino sin importar el método de pago
  if (isMafartaPayment.value) return true;

  if (form.value.payment_method === 'cash' || form.value.payment_method === 'credit') return false;
  if (isJohanPayment.value || isSumiandesPayment.value || isDrosymcaPayment.value) return false;
  return isDromegaPayment.value || isCristmedicalsPayment.value || isDronenaPayment.value;
});

const destinationBankOptions = computed(() => {
  if (!shouldShowDestinationBank.value) return [];
  if (isDromegaPayment.value) return dromegaBanks;
  if (isCristmedicalsPayment.value) return cristmedicalsBanks;
  if (isMafartaPayment.value) return mafartaBanks;
  if (isDronenaPayment.value) return dronenaBanks;
  return [];
});

watch(shouldShowDestinationBank, (show) => {
  if (!show) {
    form.value.destination_bank = null;
  } else if (isMafartaPayment.value && !form.value.destination_bank) {
    form.value.destination_bank = mafartaBanks[0].value;
  }
});


const loading = ref(false);
const uploading = ref(false);
const exchangeRates = ref({});
const errors = ref({});

// Multi-moneda y conversión de origen
const sourceCurrency = ref("COP");
const exchangeRateApplied = ref(1);
const sourceAmountCalculated = ref(0);
const customConversionMode = ref(false);

const getRateStorageKey = (src, dest) => `last_exchange_rate_${src}_${dest}`;

const getLastUsedRate = (src, dest) => {
  try {
    const saved = localStorage.getItem(getRateStorageKey(src, dest));
    if (saved && !isNaN(parseFloat(saved)) && parseFloat(saved) > 0) {
      return parseFloat(saved);
    }
  } catch (e) {
    // Ignorar errores de acceso a localStorage
  }
  return null;
};

const saveLastUsedRate = (src, dest, rate) => {
  try {
    if (rate && !isNaN(parseFloat(rate)) && parseFloat(rate) > 0) {
      localStorage.setItem(getRateStorageKey(src, dest), String(rate));
    }
  } catch (e) {
    // Ignorar errores de acceso a localStorage
  }
};

watch(() => form.value.payment_method, (newMethod) => {
  if (newMethod === 'cambista') {
    if (!form.value.reference || form.value.reference.startsWith('CAMBISTA-') || form.value.reference.startsWith('EFECTIVO-')) {
      const dateStr = form.value.payment_date || new Date().toISOString().split("T")[0];
      form.value.reference = `CAMBISTA-${dateStr}`;
    }
  } else if (newMethod === 'cash') {
    if (!form.value.reference || form.value.reference.startsWith('EFECTIVO-') || form.value.reference.startsWith('CAMBISTA-')) {
      const dateStr = form.value.payment_date || new Date().toISOString().split("T")[0];
      form.value.reference = `EFECTIVO-${dateStr}`;
    }
  } else if (form.value.reference && (form.value.reference.startsWith('EFECTIVO-') || form.value.reference.startsWith('CAMBISTA-'))) {
    form.value.reference = '';
  }
});

watch(() => form.value.payment_date, (newDate) => {
  if (form.value.payment_method === 'cambista' && (!form.value.reference || form.value.reference.startsWith('CAMBISTA-') || form.value.reference.startsWith('EFECTIVO-'))) {
    form.value.reference = `CAMBISTA-${newDate}`;
  } else if (form.value.payment_method === 'cash' && (!form.value.reference || form.value.reference.startsWith('EFECTIVO-') || form.value.reference.startsWith('CAMBISTA-'))) {
    form.value.reference = `EFECTIVO-${newDate}`;
  }
});

watch(() => form.value.payment_currency, (newCurrency) => {
  if (newCurrency === 'COP') {
    form.value.payment_method = 'cambista';
  }
});

const availablePaymentMethods = computed(() => {
  const currency = sourceCurrency.value;
  const methodMap = {
    CAMBISTA: { value: "cambista", label: "Cambista", icon: "tabler-arrows-exchange" },
    BANK: { value: "transfer", label: "Transferencia / Banco", icon: "tabler-building-bank" },
    MOBILE: { value: "mobile", label: "Pago móvil", icon: "tabler-device-mobile" },
    CASH: { value: "cash", label: "Efectivo", icon: "tabler-cash" },
    BINANCE: { value: "binance", label: "Binance", icon: "tabler-brand-binance" },
    PAYPAL: { value: "paypal", label: "PayPal", icon: "tabler-brand-paypal" },
    CREDIT: { value: "credit", label: "Crédito", icon: "tabler-hand-finger" },
  };

  const allowed = currency === "VES" || currency === "BS" 
    ? ["BANK", "MOBILE", "CASH"]
    : currency === "COP" 
    ? ["CAMBISTA", "CASH", "BANK"]
    : ["BANK", "CASH", "BINANCE", "PAYPAL", "CREDIT"];

  return allowed.map((key) => methodMap[key]);
});

watch(sourceCurrency, (newSource) => {
  // El método de salida por defecto en COP es Cambista, en otras monedas es Efectivo
  if (newSource === 'COP') {
    form.value.payment_method = 'cambista';
  } else {
    form.value.payment_method = 'cash';
  }
});

const validatePaymentAmount = (value) => {
  if (!value || isNaN(parseFloat(value)) || parseFloat(value) <= 0) return ["Monto inválido"];
  return [];
};

const isFormValid = computed(() => {
  const basicValidation = props.invoices.length > 0 && 
         validatePaymentAmount(form.value.payment_amount).length === 0 && 
         form.value.payment_date && 
         form.value.payment_method;

  // Si el método es efectivo (cash) o cambista, no es obligatoria la referencia con comprobante
  if (form.value.payment_method === 'cash' || form.value.payment_method === 'cambista') {
    return basicValidation && !uploading.value;
  }

  // Si hay referencia, DEBE haber foto (comprobante)
  const referenceValidation = !form.value.reference || (form.value.reference && form.value.photo_url);

  return basicValidation && referenceValidation && !uploading.value;
});

const totalInUSD = computed(() => {
  return props.invoices.reduce((sum, invoice) => {
    const sName = String(invoice.supplier?.name || invoice.supplier_name || "").toUpperCase();
    const isCrist = sName.includes("CRIST") || invoice.supplier_id === 1002;
    if (isCrist && invoice.total_amount_discount && parseFloat(invoice.total_amount_discount) > 0) {
      return sum + parseFloat(invoice.total_amount_discount);
    }
    return sum + (parseFloat(invoice.total_usd) || 0);
  }, 0);
});

const totalInBS = computed(() => {
  return props.invoices.reduce((sum, invoice) => {
    let amount = 0;
    const sName = String(invoice.supplier?.name || invoice.supplier_name || "").toUpperCase();
    const isCrist = sName.includes("CRIST") || invoice.supplier_id === 1002;

    // Si es Cristmedicals, tomar directamente el monto real en Bs sincronizado desde el portal
    if (isCrist) {
      if (invoice.net_payable_amount && parseFloat(invoice.net_payable_amount) > 0) {
        amount = parseFloat(invoice.net_payable_amount);
      } else {
        amount = parseFloat(invoice.total_amount) || 0;
      }
    } else if (invoice.is_indexed) {
      // Si la factura está indexada, el usuario quiere usar la "tasa de hoy"
      amount = (parseFloat(invoice.total_usd) || 0) * props.exchangeRate;
    } else {
      // Si no está indexada, es su monto en dólares por la tasa de la factura
      const invUsd = parseFloat(invoice.total_usd) || 0;
      const invRate = parseFloat(invoice.exchange_rate) || 0;
      if (invUsd > 0 && invRate > 0) {
        amount = invUsd * invRate;
      } else if (invoice.currency === "Bs" || invoice.currency === "VES") {
        amount = parseFloat(invoice.total_amount) || 0;
      } else {
        amount = parseFloat(invoice.total_amount_bs) || 0;
      }
    }

    // Restar descuento por Nota de Débito Referencial si aplica
    if (invoice.nd_referential_amount && parseFloat(invoice.nd_referential_amount) > 0) {
      amount = Math.max(0, amount - parseFloat(invoice.nd_referential_amount));
    }

    return sum + amount;
  }, 0);
});

const fetchExchangeRates = async () => {
  try {
    const { data } = await axios.get("/public/exchange-rates");
    const rates = {};
    data.forEach(r => rates[r.currency_code] = parseFloat(r.rate));
    exchangeRates.value = rates;
    updateSourceCalculations();
  } catch (error) {
    console.error("Error al cargar tasas:", error);
  }
};

const closeModal = () => {
  emit("update:modelValue", false);
  emit("close");
  resetForm();
};

const resetForm = () => {
  const today = new Date().toISOString().split("T")[0];
  form.value = {
    payment_type: "full",
    is_partial: false,
    payment_currency: "VES",
    payment_amount: 0,
    payment_date: today,
    photo_url: null,
    reference: `EFECTIVO-${today}`,
    payment_method: "cash",
    destination_bank: null,
  };
  sourceCurrency.value = "COP";
  customConversionMode.value = false;
  errors.value = {};
};

const updateSourceCalculations = () => {
  const destCurrency = form.value.payment_currency;
  const payAmount = Number(form.value.payment_amount) || 0;
  const srcCurr = sourceCurrency.value;

  if (srcCurr === destCurrency) {
    sourceAmountCalculated.value = payAmount;
    exchangeRateApplied.value = 1;
    return;
  }

  // Verificar si hay una última tasa de cambio guardada por el usuario para este par de monedas
  const lastUsedRate = getLastUsedRate(srcCurr, destCurrency);

  // Si el destino es VES y origen es COP
  if (destCurrency === 'VES' && srcCurr === 'COP') {
    const copRate = exchangeRates.value["COP"] || 4000;
    const bcvRate = exchangeRates.value["BS"] || props.exchangeRate || 1;
    // Tasa COP por 1 Bolívar
    const copPerBs = (copRate / bcvRate);
    if (!customConversionMode.value) {
      exchangeRateApplied.value = lastUsedRate || Number(copPerBs.toFixed(4));
    }
    const rawCop = payAmount * exchangeRateApplied.value;
    // Redondear siempre a múltiplos de 100 COP para transacciones de efectivo reales (ej: 236.800 COP)
    sourceAmountCalculated.value = Math.round(rawCop / 100) * 100;
  } 
  // Si el destino es VES y origen es USD
  else if (destCurrency === 'VES' && srcCurr === 'USD') {
    const bcvRate = exchangeRates.value["BS"] || props.exchangeRate || 1;
    if (!customConversionMode.value) {
      exchangeRateApplied.value = lastUsedRate || Number(bcvRate.toFixed(4));
    }
    sourceAmountCalculated.value = exchangeRateApplied.value > 0 ? Number((payAmount / exchangeRateApplied.value).toFixed(2)) : 0;
  }
  // Si el destino es USD y origen es COP
  else if (destCurrency === 'USD' && srcCurr === 'COP') {
    const copRate = exchangeRates.value["COP"] || 4000;
    if (!customConversionMode.value) {
      exchangeRateApplied.value = lastUsedRate || Number(copRate.toFixed(2));
    }
    const rawCop = payAmount * exchangeRateApplied.value;
    sourceAmountCalculated.value = Math.round(rawCop / 100) * 100;
  }
  // Si el destino es USD y origen es VES
  else if (destCurrency === 'USD' && srcCurr === 'VES') {
    const bcvRate = exchangeRates.value["BS"] || props.exchangeRate || 1;
    if (!customConversionMode.value) {
      exchangeRateApplied.value = lastUsedRate || Number(bcvRate.toFixed(4));
    }
    sourceAmountCalculated.value = Number((payAmount * exchangeRateApplied.value).toFixed(2));
  }
  else {
    sourceAmountCalculated.value = payAmount;
  }
};

watch([sourceCurrency, () => form.value.payment_amount, () => form.value.payment_currency], () => {
  updateSourceCalculations();
});

watch(exchangeRateApplied, (newRate) => {
  const destCurrency = form.value.payment_currency;
  const payAmount = Number(form.value.payment_amount) || 0;
  const srcCurr = sourceCurrency.value;

  if (srcCurr === destCurrency) return;

  if (newRate && !isNaN(parseFloat(newRate)) && parseFloat(newRate) > 0) {
    saveLastUsedRate(srcCurr, destCurrency, parseFloat(newRate));
  }

  if (destCurrency === 'VES' && srcCurr === 'COP') {
    const rawCop = payAmount * Number(newRate || 0);
    sourceAmountCalculated.value = Math.round(rawCop / 100) * 100;
  } else if (destCurrency === 'VES' && srcCurr === 'USD') {
    sourceAmountCalculated.value = Number(newRate) > 0 ? Number((payAmount / Number(newRate)).toFixed(2)) : 0;
  } else if (destCurrency === 'USD' && srcCurr === 'COP') {
    const rawCop = payAmount * Number(newRate || 0);
    sourceAmountCalculated.value = Math.round(rawCop / 100) * 100;
  }
});

const onPasteReceipt = (e) => {
  const items = e.clipboardData?.items;
  if (!items) return;
  for (const item of items) {
    if (item.type.indexOf('image') !== -1) {
      const blob = item.getAsFile();
      handleFileUpload(blob);
      break;
    }
  }
};

const processPayment = async () => {
  loading.value = true;
  try {
    const frontendToEnumMap = {
      cambista: "CAMBISTA", cash: "CASH", card: "CARD", mobile: "MOBILE", transfer: "TRANSFER",
      binance: "BINANCE", paypal: "PAYPAL", credit: "CREDIT"
    };

    const response = await axios.post("/finances/pending-payments/process-payment", {
      ...form.value,
      source_currency: sourceCurrency.value,
      exchange_rate_applied: exchangeRateApplied.value,
      source_amount: sourceAmountCalculated.value,
      payment_type: form.value.is_partial ? "partial" : "full",
      payment_method: frontendToEnumMap[form.value.payment_method],
      invoice_ids: props.invoices.map(i => i.id).filter(Boolean),
      invoice_numbers: props.invoices.map(i => i.invoice_number).filter(Boolean),
    });

    if (response.data.status === "success") {
      saveLastUsedRate(sourceCurrency.value, form.value.payment_currency, exchangeRateApplied.value);
      const msg = response.data.message || "Pago procesado";
      toast.success(msg);
      emit("payment-processed");
      closeModal();
    } else {
      toast.error(response.data.message);
    }

  } catch (error) {
    console.error("Error al procesar:", error);
    const serverMsg = error.response?.data?.message || 
                     (error.response?.data?.errors ? Object.values(error.response.data.errors).flat().join(", ") : null) || 
                     "Error al procesar el pago";
    toast.error(serverMsg);
  } finally {
    loading.value = false;
  }
};

const selectedPaymentMethodIcon = computed(() => {
  const method = availablePaymentMethods.value.find((m) => m.value === form.value.payment_method);
  return method ? method.icon : "tabler-wallet";
});

const handleFileUpload = async (file) => {
  if (!file) return;
  const actualFile = Array.isArray(file) ? file[0] : file;
  if (!actualFile) return;

  uploading.value = true;
  const formData = new FormData();
  formData.append("file", actualFile);
  try {
    const { data } = await axios.post("/finances/pending-payments/upload-receipt", formData, {
      headers: { "Content-Type": "multipart/form-data" }
    });
    form.value.photo_url = data.data.url;

    // Si se detectó automáticamente el número de referencia
    if (data.data.extracted_reference) {
      const cleanRef = String(data.data.extracted_reference).replace(/^#+/, '').trim();
      form.value.reference = cleanRef;
      toast.success(`Comprobante subido. Referencia detectada: ${cleanRef}`);
    } else {
      toast.success("Comprobante subido correctamente");
    }
  } catch (error) {
    toast.error("Error al subir comprobante");
  } finally {
    uploading.value = false;
  }
};

const getInvoiceBsAmount = (invoice) => {
  let amount = 0;
  const sName = String(invoice.supplier?.name || invoice.supplier_name || "").toUpperCase();
  const isCrist = sName.includes("CRIST") || invoice.supplier_id === 1002;

  if (isCrist) {
    if (invoice.net_payable_amount && parseFloat(invoice.net_payable_amount) > 0) {
      amount = parseFloat(invoice.net_payable_amount);
    } else {
      amount = parseFloat(invoice.total_amount) || 0;
    }
  } else if (invoice.is_indexed) {
    amount = (parseFloat(invoice.total_usd) || 0) * props.exchangeRate;
  } else {
    const invUsd = parseFloat(invoice.total_usd) || 0;
    const invRate = parseFloat(invoice.exchange_rate) || 0;
    if (invUsd > 0 && invRate > 0) {
      amount = invUsd * invRate;
    } else if (invoice.currency === "Bs" || invoice.currency === "VES") {
      amount = parseFloat(invoice.total_amount) || 0;
    } else {
      amount = parseFloat(invoice.total_amount_bs) || 0;
    }
  }

  if (invoice.nd_referential_amount && parseFloat(invoice.nd_referential_amount) > 0) {
    amount = Math.max(0, amount - parseFloat(invoice.nd_referential_amount));
  }

  return Number(amount.toFixed(2));
};

const getInvoiceUsdAmount = (invoice) => {
  const sName = String(invoice.supplier?.name || invoice.supplier_name || "").toUpperCase();
  const isCrist = sName.includes("CRIST") || invoice.supplier_id === 1002;
  if (isCrist && invoice.total_amount_discount && parseFloat(invoice.total_amount_discount) > 0) {
    return parseFloat(invoice.total_amount_discount);
  }
  return parseFloat(invoice.total_usd) || 0;
};

const formatCurrency = (amount, currencyCode = null, omitCurrency = false) => {
  const code = currencyCode || form.value.payment_currency || "USD";
  const num = Number(amount) || 0;
  return new Intl.NumberFormat("es-VE", {
    style: omitCurrency ? "decimal" : "currency",
    currency: code === "VES" ? "VES" : (code === "Bs" ? "VES" : code),
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num);
};

const formatNumber = (value, currency = null) => {
  const num = Number(value) || 0;
  const isCop = currency === 'COP' || (!currency && sourceCurrency.value === 'COP');
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: isCop ? 0 : 2,
    maximumFractionDigits: isCop ? 0 : 2,
  }).format(num);
};

watch(() => form.value.payment_currency, (newCurrency) => {
  if (!form.value.is_partial) {
    if (newCurrency === "VES" || newCurrency === "BS") {
      form.value.payment_amount = Number(totalInBS.value.toFixed(2));
    } else if (newCurrency === "COP") {
      const copRate = exchangeRates.value["COP"] || 1;
      form.value.payment_amount = Number((totalInUSD.value * copRate).toFixed(2));
    } else {
      form.value.payment_amount = Number(totalInUSD.value.toFixed(2));
    }
  }
});

watch(() => props.modelValue, (val) => {
  if (val) {
    fetchExchangeRates();
    const today = new Date().toISOString().split("T")[0];
    form.value.payment_date = today;
    form.value.reference = `CAMBISTA-${today}`;
    form.value.photo_url = null;
    form.value.payment_currency = 'VES';
    sourceCurrency.value = 'COP';
    form.value.payment_method = 'cambista';
    form.value.payment_amount = Number(totalInBS.value.toFixed(2));
    customConversionMode.value = false;

    if (isDromegaPayment.value) {
      form.value.destination_bank = dromegaBanks[12].value;
    } else if (isCristmedicalsPayment.value) {
      form.value.destination_bank = '30';
      const totalBsReal = props.invoices.reduce((acc, inv) => acc + (Number(inv.net_payable_amount) || 0), 0);
      form.value.payment_amount = totalBsReal > 0 ? Number(totalBsReal.toFixed(2)) : Number(totalInBS.value.toFixed(2));
    } else if (isMafartaPayment.value) {
      form.value.destination_bank = mafartaBanks[0].value;
    } else if (isDronenaPayment.value) {
      form.value.destination_bank = dronenaBanks[0].value;
    } else {
      form.value.destination_bank = null;
    }

    updateSourceCalculations();
  }
});
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    max-width="850"
    persistent
    @update:model-value="closeModal"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Header Corporativo -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-currency-dollar"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Procesar Pago
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Registro de Transacción Financiera
              </span>
            </div>
          </div>
          <VSpacer />
          <IconBtn
            color="white"
            variant="tonal"
            size="small"
            class="rounded-lg"
            @click="closeModal"
            :disabled="loading || uploading"
          >
            <VIcon icon="tabler-x" size="20" />
            <VTooltip activator="parent" location="top">Cerrar</VTooltip>
          </IconBtn>
        </div>
      </VCardTitle>
      <VCardText class="pa-3 pa-sm-4 bg-light overflow-y-auto" style="max-block-size: 75vh;">
        <!-- Resumen de Deuda y Montos Destacados -->
        <VCard
          variant="flat"
          class="rounded-xl border shadow-sm mb-3 bg-white overflow-hidden"
        >
          <div class="pa-3 d-flex align-center justify-space-between flex-wrap gap-2">
            <div class="d-flex align-center gap-3">
              <VAvatar
                color="primary"
                variant="tonal"
                size="40"
                class="rounded-lg text-primary"
              >
                <VIcon
                  icon="tabler-receipt-2"
                  size="22"
                />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Monto Total a Liquidar</span>
                <div class="d-flex align-center gap-2 flex-wrap">
                  <span class="text-h5 font-weight-black text-primary leading-none">{{ formatNumber(totalInUSD, 'USD') }} USD</span>
                  <VDivider
                    vertical
                    class="opacity-10"
                    style="block-size: 16px;"
                  />
                  <span class="text-h6 font-weight-black text-success leading-none">{{ formatNumber(totalInBS, 'VES') }} Bs</span>
                </div>
              </div>
            </div>
            <div class="d-flex flex-column align-end">
              <div class="d-flex align-center gap-1 mb-1">
                <VChip
                  v-if="supplierPaymentEmail"
                  size="small"
                  variant="tonal"
                  color="info"
                  class="font-weight-bold rounded"
                >
                  <VIcon start size="14">tabler-mail-fast</VIcon>
                  Envío automático a {{ supplierPaymentEmail }}
                </VChip>
                <VChip
                  size="small"
                  variant="flat"
                  color="primary"
                  class="font-weight-black rounded"
                >
                  {{ props.invoices.length }} {{ props.invoices.length === 1 ? 'FACTURA' : 'FACTURAS' }}
                </VChip>
              </div>
              <span class="text-super-xs text-disabled uppercase font-weight-bold">Tasa BCV Referencial: {{ formatNumber(exchangeRate, 'VES') }} Bs/USD</span>
            </div>
          </div>

          <!-- Tabla con Detalle de Cada Factura -->
          <VDivider class="opacity-10" />
          <div class="pa-3 bg-light-hint">
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-super-xs font-weight-black text-disabled uppercase">Detalle de Facturas a Pagar</span>
              <span class="text-super-xs font-weight-bold text-medium-emphasis">Tasa individual por factura</span>
            </div>
            
            <VTable density="compact" class="rounded-lg border bg-white invoice-detail-table">
              <thead>
                <tr>
                  <th class="text-xs font-weight-bold">N° Factura</th>
                  <th class="text-xs font-weight-bold">N° Control</th>
                  <th class="text-xs font-weight-bold text-end">Monto USD</th>
                  <th class="text-xs font-weight-bold text-end">Monto en Bs</th>
                  <th class="text-xs font-weight-bold text-center">Indexada</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="inv in props.invoices" :key="inv.id">
                  <td class="text-xs font-weight-bold text-primary">#{{ inv.invoice_number }}</td>
                  <td class="text-xs text-medium-emphasis">{{ inv.control_number && inv.control_number !== 'N/A' ? inv.control_number : 'S/N' }}</td>
                  <td class="text-xs font-weight-bold text-end">{{ formatNumber(getInvoiceUsdAmount(inv), 'USD') }} USD</td>
                  <td class="text-xs font-weight-bold text-success text-end">{{ formatNumber(getInvoiceBsAmount(inv), 'VES') }} Bs</td>
                  <td class="text-center">
                    <VChip
                      size="x-small"
                      :color="inv.is_indexed ? 'warning' : 'default'"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ inv.is_indexed ? 'Sí' : 'No' }}
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </VCard>

        <VRow @paste="onPasteReceipt">
          <!-- ── BLOQUE 1: Egreso de Caja Real ──────────────────────────────── -->
          <VCol cols="12" md="6">
            <div class="d-flex align-center gap-2 mb-2">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">1. Egreso de Caja Real</span>
            </div>

            <VCard variant="flat" class="pa-3 bg-white rounded-lg elevation-1 border h-100 d-flex flex-column justify-space-between">
              <VRow dense>
                <VCol cols="12" sm="6">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Caja / Moneda Salida</span>
                  <VSelect
                    v-model="sourceCurrency"
                    :items="[
                      { title: 'COP', value: 'COP' },
                      { title: 'USD', value: 'USD' },
                      { title: 'Bs', value: 'VES' },
                    ]"
                    variant="outlined"
                    density="compact"
                    class="premium-input mb-1"
                    hide-details
                  />
                </VCol>

                <VCol cols="12" sm="6">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Método de Salida</span>
                  <VSelect
                    v-model="form.payment_method"
                    :items="availablePaymentMethods"
                    item-title="label"
                    item-value="value"
                    :prepend-inner-icon="selectedPaymentMethodIcon"
                    variant="outlined"
                    density="compact"
                    class="premium-input mb-1"
                    hide-details
                  >
                    <template #item="{ props, item }">
                      <VListItem v-bind="props" :prepend-icon="item.raw.icon" />
                    </template>
                  </VSelect>
                </VCol>

                <!-- Tasa de Conversión si Moneda Origen !== Moneda Destino -->
                <VCol v-if="sourceCurrency !== form.payment_currency" cols="12">
                  <div class="pa-2 rounded-lg bg-light-hint border mb-1">
                    <div class="d-flex align-center justify-space-between mb-1">
                      <span class="text-super-xs font-weight-bold text-primary uppercase">
                        Tasa Conversión
                      </span>
                      <span class="text-super-xs text-medium-emphasis font-weight-bold">
                        {{ (sourceCurrency === 'COP' && form.payment_currency === 'VES') ? 'COP X 1Bs' : (sourceCurrency === 'USD' && form.payment_currency === 'VES' ? 'Bs X 1 USD' : (sourceCurrency === 'COP' && form.payment_currency === 'USD' ? 'COP X 1 USD' : (sourceCurrency === 'VES' ? 'Bs' : sourceCurrency) + ' X 1 ' + (form.payment_currency === 'VES' ? 'Bs' : form.payment_currency))) }}
                      </span>
                    </div>
                    <VTextField
                      v-model="exchangeRateApplied"
                      type="number"
                      step="0.0001"
                      variant="outlined"
                      density="compact"
                      class="premium-input mb-1"
                      hide-details
                      @input="customConversionMode = true"
                    />
                    <div class="d-flex align-center justify-space-between text-caption text-medium-emphasis">
                      <span>Descuento Real:</span>
                      <span class="font-weight-black text-high-emphasis">
                        {{ formatNumber(sourceAmountCalculated) }} {{ sourceCurrency === 'VES' ? 'Bs' : sourceCurrency }}
                      </span>
                    </div>
                  </div>
                </VCol>

                <VCol cols="12">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Fecha del Pago</span>
                  <AppDateTimePicker
                    v-model="form.payment_date"
                    variant="outlined"
                    density="compact"
                    class="premium-input"
                    hide-details
                  />
                </VCol>
              </VRow>
            </VCard>
          </VCol>

          <!-- ── BLOQUE 2: Liquidación al Proveedor ────────────────────────── -->
          <VCol cols="12" md="6">
            <div class="d-flex align-center gap-2 mb-2">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">2. Liquidación al Proveedor</span>
            </div>

            <VCard variant="flat" class="pa-3 bg-white rounded-lg elevation-1 border h-100 d-flex flex-column justify-space-between">
              <VRow dense>
                <VCol cols="12">
                  <div class="d-flex align-center justify-space-between mb-1">
                    <span class="text-super-xs font-weight-black text-disabled uppercase">Moneda & Monto Recibido</span>
                    <VCheckbox
                      v-model="form.is_partial"
                      density="compact"
                      hide-details
                      color="warning"
                    >
                      <template #label>
                        <span class="text-super-xs font-weight-bold text-warning uppercase">¿Es abono?</span>
                      </template>
                    </VCheckbox>
                  </div>

                  <div class="d-flex gap-2 mb-1">
                    <VSelect
                      v-model="form.payment_currency"
                      :items="[ {title: 'Bs', value: 'VES'}, {title: 'USD', value: 'USD'}, {title: 'COP', value: 'COP'} ]"
                      variant="outlined"
                      density="compact"
                      style="max-width: 90px;"
                      hide-details
                    />
                    <VTextField
                      v-model="form.payment_amount"
                      type="number"
                      step="0.01"
                      variant="outlined"
                      density="compact"
                      class="premium-input flex-grow-1"
                      hide-details
                      :prefix="form.payment_currency === 'USD' ? '$' : (form.payment_currency === 'VES' ? 'Bs' : '$')"
                    />
                  </div>
                </VCol>

                <VCol v-if="shouldShowDestinationBank" cols="12">
                  <span class="text-super-xs font-weight-black text-primary uppercase mb-1 d-block">
                    {{ isCristmedicalsPayment ? 'Banco Destino Cristmedicals' : (isMafartaPayment ? 'Banco Destino Cobeca / Mafarta' : (isDronenaPayment ? 'Banco Destino Dronena' : (isDromegaPayment ? 'Banco Destino Droguería Mega' : 'Banco Destino'))) }}
                  </span>
                  <VSelect
                    v-model="form.destination_bank"
                    :items="destinationBankOptions"
                    item-title="title"
                    item-value="value"
                    placeholder="SELECCIONE BANCO DESTINO"
                    variant="outlined"
                    density="compact"
                    class="premium-input mb-1"
                    clearable
                    prepend-inner-icon="tabler-building-bank"
                    hide-details
                  />
                </VCol>

                <VCol cols="12">
                  <div class="d-flex align-center justify-space-between mb-1">
                    <span class="text-super-xs font-weight-black text-disabled uppercase">Referencia</span>
                    <span v-if="uploading" class="text-super-xs font-weight-bold text-primary animate-pulse">
                      <VIcon icon="tabler-scan" size="12" class="me-1" />
                      Extrayendo referencia...
                    </span>
                  </div>
                  <VTextField
                    v-model="form.reference"
                    placeholder="# Transacción o Lote..."
                    variant="outlined"
                    density="compact"
                    prepend-inner-icon="tabler-hash"
                    class="premium-input mb-1"
                    hide-details="auto"
                  />
                </VCol>

                <VCol cols="12">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">
                    Comprobante (Pegar o Subir)
                  </span>
                  <VFileInput
                    variant="outlined"
                    density="compact"
                    class="premium-input"
                    prepend-icon=""
                    prepend-inner-icon="tabler-camera"
                    placeholder="Adjuntar o pegar recibo..."
                    accept="image/*,application/pdf"
                    :error="form.payment_method !== 'cash' && form.reference && !form.photo_url"
                    :error-messages="form.payment_method !== 'cash' && form.reference && !form.photo_url ? ['Requerido'] : []"
                    hide-details="auto"
                    :loading="uploading"
                    @update:model-value="handleFileUpload"
                  />
                </VCol>
              </VRow>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <!-- Botones de Acción -->
      <VCardActions class="pa-3 bg-light border-t">
        <VRow
          dense
          class="w-100 ma-0"
        >
          <VCol
            cols="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="outlined"
              height="44"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeModal"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="44"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="loading || uploading"
              :disabled="!isFormValid"
              @click="processPayment"
            >
              <VIcon
                start
                :icon="uploading ? 'tabler-loader' : 'tabler-device-floppy'"
                size="18"
                class="me-2"
              />
              {{ uploading ? 'Subiendo...' : 'Confirmar Pago' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.bg-light-hint {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.invoice-tag {
  background-color: white;
  transition: all 0.2s ease;
}

.invoice-tag:hover {
  background-color: rgb(var(--v-theme-primary));
  color: white !important;
  border-color: rgb(var(--v-theme-primary)) !important;
}
</style>
