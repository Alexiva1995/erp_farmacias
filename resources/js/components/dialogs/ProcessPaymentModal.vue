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
  payment_currency: "USD",
  payment_amount: 0,
  payment_date: new Date().toISOString().split("T")[0],
  photo_url: null,
  reference: "",
  payment_method: null,
});

const loading = ref(false);
const uploading = ref(false);
const exchangeRates = ref({});
const errors = ref({});

const availablePaymentMethods = computed(() => {
  const currency = form.value.payment_currency;
  const methodMap = {
    CASH: { value: "cash", label: "Efectivo", icon: "tabler-cash" },
    CARD: { value: "card", label: "Tarjeta", icon: "tabler-credit-card" },
    MOBILE: { value: "mobile", label: "Pago móvil", icon: "tabler-device-mobile" },
    TRANSFER: { value: "transfer", label: "Transferencia", icon: "tabler-building-bank" },
    BINANCE: { value: "binance", label: "Binance", icon: "tabler-brand-binance" },
    PAYPAL: { value: "paypal", label: "PayPal", icon: "tabler-brand-paypal" },
    CREDIT: { value: "credit", label: "Crédito", icon: "tabler-hand-finger" },
  };

  const allowed = currency === "VES" || currency === "BS" 
    ? ["CASH", "CARD", "MOBILE", "TRANSFER"]
    : currency === "COP" 
    ? ["CASH", "TRANSFER"]
    : ["CASH", "BINANCE", "PAYPAL", "CREDIT"];

  return allowed.map((key) => methodMap[key]);
});

const validatePaymentAmount = (value) => {
  if (!value || isNaN(parseFloat(value)) || parseFloat(value) <= 0) return ["Monto inválido"];
  return [];
};

const isFormValid = computed(() => {
  return props.invoices.length > 0 && 
         validatePaymentAmount(form.value.payment_amount).length === 0 && 
         form.value.payment_date && 
         form.value.payment_method;
});

const totalInUSD = computed(() => {
  return props.invoices.reduce((sum, invoice) => sum + (parseFloat(invoice.total_usd) || 0), 0);
});

const totalInBS = computed(() => {
  return props.invoices.reduce((sum, invoice) => {
    // Si la factura está indexada, el usuario quiere usar la "tasa de hoy"
    if (invoice.is_indexed) {
      return sum + ((parseFloat(invoice.total_usd) || 0) * props.exchangeRate);
    }
    
    // Si no está indexada, el usuario quiere el "precio de la factura" (original en BS)
    if (invoice.currency === "Bs" || invoice.currency === "VES") {
      return sum + (parseFloat(invoice.total_amount) || 0);
    }
    
    // Si la factura es en moneda extranjera y NO está indexada, 
    // su valor en BS es el que se registró originalmente (total_amount_bs)
    return sum + (parseFloat(invoice.total_amount_bs) || 0);
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
      payment_type: "full", // Forzado a full por requerimiento del usuario (aunque sea menor)
      payment_method: frontendToEnumMap[form.value.payment_method],
      invoice_ids: props.invoices.map(i => i.id)
    });

    if (response.data.status === "success") {
      toast.success("Pago procesado");
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

const formatCurrency = (amount, currency, omitCurrency = false) => {
  if (!amount && amount !== 0) return "0,00";
  const code = currency === "Bs" ? "VES" : currency === "COP" ? "COP" : "USD";
  const formatted = new Intl.NumberFormat("es-VE", {
    style: omitCurrency ? "decimal" : "currency",
    currency: code,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
  return formatted;
};

watch(() => props.modelValue, (val) => { if (val) fetchExchangeRates(); });
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    max-width="800"
    persistent
    @update:model-value="closeModal"
  >
    <VCard class="rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Toolbar Premium (Móvil) -->
      <VToolbar v-if="mobile" color="primary" flat>
        <VBtn icon @click="closeModal">
          <VIcon icon="tabler-x" />
        </VBtn>
        <VToolbarTitle class="text-sm font-weight-black uppercase">Registrar Pago</VToolbarTitle>
        <VSpacer />
        <VBtn
          color="white"
          variant="text"
          class="font-weight-black text-xs"
          :loading="loading"
          :disabled="!isFormValid"
          @click="processPayment"
        >
          CONFIRMAR
        </VBtn>
      </VToolbar>

      <!-- Cabecera Premium (Escritorio) -->
      <VCardTitle v-else class="pa-6 pb-2 d-flex align-center">
        <VAvatar color="primary" variant="tonal" size="44" class="me-4 rounded-lg">
          <VIcon icon="tabler-currency-dollar" size="24" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-lg font-weight-black uppercase leading-none mb-1">Procesar Pago</span>
          <span class="text-xs text-disabled font-weight-medium">Registro de transacción financiera</span>
        </div>
        <VSpacer />
        <VBtn icon="tabler-x" variant="tonal" color="secondary" size="32" class="rounded-lg" @click="closeModal" />
      </VCardTitle>

      <VCardText class="pa-6">
        <VRow>
          <!-- Resumen de Deuda -->
          <VCol cols="12">
            <VCard variant="tonal" color="primary" class="rounded-xl border-0 mb-4 overflow-hidden">
              <div class="pa-5 d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-3">
                  <VAvatar color="primary" variant="flat" size="40" class="rounded-lg shadow-sm text-white">
                    <VIcon icon="tabler-sum" size="20" />
                  </VAvatar>
                  <div class="d-flex flex-column">
                    <span class="text-super-xs font-weight-black text-disabled uppercase leading-none mb-1">Total a Procesar</span>
                    <div class="d-flex align-center gap-2">
                       <span class="text-h6 font-weight-black text-primary leading-none">{{ formatCurrency(totalInUSD, 'USD') }}</span>
                       <VDivider vertical class="mx-1" />
                       <span class="text-xs font-weight-black text-success leading-none">Bs. {{ formatCurrency(totalInBS, 'Bs', true) }}</span>
                    </div>
                  </div>
                </div>
                <VChip size="small" variant="flat" color="primary" class="font-weight-black rounded">
                  {{ props.invoices.length }} FACTURAS
                </VChip>
              </div>
            </VCard>
          </VCol>

          <!-- Formulario -->
          <VCol cols="12" md="6">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Moneda de Pago</span>
            <VSelect
              v-model="form.payment_currency"
              :items="[ {title: 'USD - Dólar', value: 'USD'}, {title: 'VES - Bolívar', value: 'VES'}, {title: 'COP - Peso', value: 'COP'} ]"
              variant="outlined"
              density="comfortable"
              class="premium-input mb-4"
              hide-details
            />

            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Monto del Pago</span>
            <VTextField
              v-model="form.payment_amount"
              type="number"
              variant="outlined"
              density="comfortable"
              class="premium-input mb-4"
              hide-details
              prefix="$"
            />

            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Fecha del Pago</span>
            <AppDateTimePicker
              v-model="form.payment_date"
              variant="outlined"
              density="comfortable"
              class="premium-input"
              hide-details
            />
          </VCol>

          <VCol cols="12" md="6">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Método de Pago</span>
            <VSelect
              v-model="form.payment_method"
              :items="availablePaymentMethods"
              item-title="label"
              item-value="value"
              variant="outlined"
              density="comfortable"
              class="premium-input mb-4"
              hide-details
            >
              <template #item="{ props, item }">
                <VListItem v-bind="props" :prepend-icon="item.raw.icon" />
              </template>
            </VSelect>

            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Referencia</span>
            <VTextField
              v-model="form.reference"
              placeholder="# Transacción..."
              variant="outlined"
              density="comfortable"
              class="premium-input mb-4"
              hide-details
            />

            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Comprobante (Archivo)</span>
            <VFileInput
              variant="outlined"
              density="comfortable"
              class="premium-input"
              prepend-icon="tabler-camera"
              placeholder="Subir recibo..."
              hide-details
              @update:model-value="handleFileUpload"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions v-if="!mobile" class="pa-6 pt-0 gap-3">
        <VBtn color="secondary" variant="tonal" class="rounded-lg font-weight-black text-xs flex-grow-1" @click="closeModal">CANCELAR</VBtn>
        <VBtn
          color="primary"
          variant="flat"
          class="rounded-lg font-weight-black text-xs flex-grow-1 shadow-md"
          :loading="loading"
          :disabled="!isFormValid"
          @click="processPayment"
        >
          NOTIFICAR PAGO
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.leading-none {
  line-height: 1;
}

:deep(.premium-input) {
  .v-field__outline {
    --v-field-border-opacity: 0.1;
  }
}
</style>
