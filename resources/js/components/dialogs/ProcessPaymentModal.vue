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

const form = ref({
  payment_type: "full",
  is_partial: false,
  payment_currency: "USD",
  payment_amount: 0,
  payment_date: new Date().toISOString().split("T")[0],
  photo_url: null,
  reference: "",
  payment_method: "transfer",
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

const destinationBankOptions = computed(() => {
  if (isSumiandesPayment.value || isDrosymcaPayment.value) return [];
  if (isDromegaPayment.value) return dromegaBanks;
  if (isCristmedicalsPayment.value) return cristmedicalsBanks;
  if (isMafartaPayment.value) return mafartaBanks;
  if (isDronenaPayment.value) return dronenaBanks;
  return [...dromegaBanks, ...cristmedicalsBanks, ...mafartaBanks, ...dronenaBanks];
});


const loading = ref(false);
const uploading = ref(false);
const exchangeRates = ref({});
const errors = ref({});

watch(() => form.value.payment_method, (newMethod) => {
  if (newMethod === 'cash') {
    const dateStr = form.value.payment_date || new Date().toISOString().split("T")[0];
    form.value.reference = `EFECTIVO-${dateStr}`;
  } else if (form.value.reference.startsWith('EFECTIVO-')) {
    form.value.reference = '';
  }
});

watch(() => form.value.payment_date, (newDate) => {
  if (form.value.payment_method === 'cash') {
    form.value.reference = `EFECTIVO-${newDate}`;
  }
});

const availablePaymentMethods = computed(() => {
  const currency = form.value.payment_currency;
  const methodMap = {
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
    ? ["BANK", "CASH"]
    : ["BANK", "CASH", "BINANCE", "PAYPAL", "CREDIT"];

  return allowed.map((key) => methodMap[key]);
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

  // Si el método es efectivo (cash), no es obligatoria la referencia con comprobante
  if (form.value.payment_method === 'cash') {
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
  form.value = {
    payment_type: "full",
    payment_currency: "USD",
    payment_amount: 0,
    payment_date: new Date().toISOString().split("T")[0],
    photo_url: null,
    reference: "",
    payment_method: null,
    destination_bank: null,
  };
  errors.value = {};
};


const processPayment = async () => {
  loading.value = true;
  try {
    const frontendToEnumMap = {
      cash: "CASH", card: "CARD", mobile: "MOBILE", transfer: "TRANSFER",
      binance: "BINANCE", paypal: "PAYPAL", credit: "CREDIT"
    };

    const response = await axios.post("/finances/pending-payments/process-payment", {
      ...form.value,
      payment_type: form.value.is_partial ? "partial" : "full",
      payment_method: frontendToEnumMap[form.value.payment_method],
      invoice_ids: props.invoices.map(i => i.id)
    });

    if (response.data.status === "success") {
      const msg = response.data.message || "Pago procesado";
      toast.success(msg);
      emit("payment-processed");
      closeModal();
    } else {
      toast.error(response.data.message);
    }

  } catch (error) {
    console.error("Error al procesar:", error);
    toast.error("Error al procesar el pago");
  } finally {
    loading.value = false;
  }
};

const handleFileUpload = async (file) => {
  if (!file) return;
  uploading.value = true;
  const formData = new FormData();
  formData.append("file", file);
  try {
    const { data } = await axios.post("/finances/pending-payments/upload-receipt", formData, {
      headers: { "Content-Type": "multipart/form-data" }
    });
    form.value.photo_url = data.data.url;
    toast.success("Comprobante subido");
  } catch (error) {
    toast.error("Error al subir archivo");
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

const formatNumber = (value) => {
  const num = Number(value) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
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
    form.value.payment_currency = 'VES';
    form.value.payment_method = 'transfer';
    form.value.payment_amount = Number(totalInBS.value.toFixed(2));

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
    }
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
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-3 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="32"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-currency-dollar"
              size="24"
              class="text-primary"
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
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeModal"
          />
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
                  <span class="text-h5 font-weight-black text-primary leading-none">${{ formatNumber(totalInUSD) }} USD</span>
                  <VDivider
                    vertical
                    class="opacity-10"
                    style="block-size: 16px;"
                  />
                  <span class="text-h6 font-weight-black text-success leading-none">{{ formatNumber(totalInBS) }} Bs</span>
                </div>
              </div>
            </div>
            <div class="d-flex flex-column align-end">
              <VChip
                size="small"
                variant="flat"
                color="primary"
                class="font-weight-black rounded mb-1"
              >
                {{ props.invoices.length }} {{ props.invoices.length === 1 ? 'FACTURA' : 'FACTURAS' }}
              </VChip>
              <span class="text-super-xs text-disabled uppercase font-weight-bold">Tasa BCV Referencial: {{ formatNumber(exchangeRate) }} Bs/USD</span>
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
                  <td class="text-xs font-weight-bold text-end">${{ formatNumber(getInvoiceUsdAmount(inv)) }}</td>
                  <td class="text-xs font-weight-bold text-success text-end">{{ formatNumber(getInvoiceBsAmount(inv)) }} Bs</td>
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

        <VRow>
          <!-- Datos del Pago -->
          <VCol
            cols="12"
            md="6"
          >
            <div class="d-flex align-center gap-2 mb-2">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Origen del Pago</span>
            </div>

            <VCard
              variant="flat"
              class="pa-3 bg-white rounded-lg elevation-1 border"
            >
              <VRow dense>
                <VCol cols="12">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Moneda de Pago</span>
                  <VSelect
                    v-model="form.payment_currency"
                    :items="[ {title: 'VES - Bolívar', value: 'VES'}, {title: 'USD - Dólar', value: 'USD'}, {title: 'COP - Peso', value: 'COP'} ]"
                    variant="outlined"
                    density="compact"
                    class="premium-input mb-3"
                    hide-details
                  />
                </VCol>

                <VCol cols="12">
                  <div class="d-flex align-center justify-space-between mb-1">
                    <span class="text-super-xs font-weight-black text-disabled uppercase">Monto</span>
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
                  <VTextField
                    v-model="form.payment_amount"
                    type="number"
                    step="0.01"
                    variant="outlined"
                    density="compact"
                    class="premium-input mb-3"
                    hide-details
                    :prefix="form.payment_currency === 'USD' ? '$' : (form.payment_currency === 'VES' ? 'Bs' : '$')"
                  />
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

          <!-- Detalles y Comprobante -->
          <VCol
            cols="12"
            md="6"
          >
            <div class="d-flex align-center gap-2 mb-2">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Verificación</span>
            </div>

            <VCard
              variant="flat"
              class="pa-3 bg-white rounded-lg elevation-1 border"
            >
              <VRow dense>
                <VCol cols="12">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Método de Pago</span>
                  <VSelect
                    v-model="form.payment_method"
                    :items="availablePaymentMethods"
                    item-title="label"
                    item-value="value"
                    variant="outlined"
                    density="compact"
                    class="premium-input mb-3"
                    hide-details
                  >
                    <template #item="{ props, item }">
                      <VListItem
                        v-bind="props"
                        :prepend-icon="item.raw.icon"
                      />
                    </template>
                  </VSelect>
                </VCol>

                <VCol v-if="!isSumiandesPayment && !isDrosymcaPayment" cols="12">
                  <div class="d-flex align-center justify-space-between mb-1">
                    <span class="text-super-xs font-weight-black text-primary uppercase">
                      {{ isCristmedicalsPayment ? 'Banco Destino Cristmedicals' : (isMafartaPayment ? 'Banco Destino Cobeca / Mafarta' : (isDronenaPayment ? 'Banco Destino Dronena' : (isDromegaPayment ? 'Banco Destino Droguería Mega' : 'Banco Destino'))) }}
                    </span>
                    <VChip
                      v-if="isCristmedicalsPayment || isMafartaPayment || isDronenaPayment || isDromegaPayment"
                      size="x-small"
                      color="primary"
                      variant="tonal"
                    >
                      {{ isCristmedicalsPayment ? 'Portal Cristmedicals' : (isMafartaPayment ? 'Portal Cobeca (SIC)' : (isDronenaPayment ? 'Portal Dronena' : 'Portal Droguería Mega')) }}
                    </VChip>
                  </div>
                  <VSelect
                    v-model="form.destination_bank"
                    :items="destinationBankOptions"
                    item-title="title"
                    item-value="value"
                    :placeholder="isCristmedicalsPayment ? 'SELECCIONE BANCO CRISTMEDICALS' : (isMafartaPayment ? 'SELECCIONE BANCO COBECA' : (isDronenaPayment ? 'SELECCIONE BANCO DRONENA' : (isDromegaPayment ? 'SELECCIONE BANCO DROGUERÍA MEGA' : 'SELECCIONE BANCO DESTINO')))"
                    variant="outlined"
                    density="compact"
                    class="premium-input mb-3"
                    clearable
                    prepend-inner-icon="tabler-building-bank"
                    hide-details
                  />
                </VCol>

                <VCol cols="12">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Referencia</span>
                  <VTextField
                    v-model="form.reference"
                    placeholder="# Transacción o Lote..."
                    variant="outlined"
                    density="compact"
                    class="premium-input mb-3"
                    :hint="isCristmedicalsPayment ? 'Para Cristmedicals se validará automáticamente la referencia en el banco vía MovilPay' : (isDronenaPayment ? 'Para Dronena se tomarán automáticamente los últimos 10 dígitos' : (isMafartaPayment ? 'Para Cobeca/Mafarta se tomarán automáticamente los últimos 9 dígitos' : undefined))"
                    :persistent-hint="isCristmedicalsPayment || isDronenaPayment || isMafartaPayment"
                    hide-details="auto"
                  />
                </VCol>


                <VCol cols="12">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Comprobante</span>
                  <VFileInput
                    variant="outlined"
                    density="compact"
                    class="premium-input"
                    prepend-icon="tabler-camera"
                    placeholder="Adjuntar recibo..."
                    :error="form.payment_method !== 'cash' && form.reference && !form.photo_url"
                    :error-messages="form.payment_method !== 'cash' && form.reference && !form.photo_url ? ['Si hay referencia, el comprobante es obligatorio'] : []"
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
          no-gutters
          class="w-100"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="44"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeModal"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
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
              {{ uploading ? 'Subiendo Imagen...' : 'Confirmar Pago' }}
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
