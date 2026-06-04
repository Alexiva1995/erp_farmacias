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
    BANK: { value: "transfer", label: "Banco", icon: "tabler-building-bank" },
    MOBILE: { value: "mobile", label: "Pago móvil", icon: "tabler-device-mobile" },
    BINANCE: { value: "binance", label: "Binance", icon: "tabler-brand-binance" },
    PAYPAL: { value: "paypal", label: "PayPal", icon: "tabler-brand-paypal" },
    CREDIT: { value: "credit", label: "Crédito", icon: "tabler-hand-finger" },
  };

  const allowed = currency === "VES" || currency === "BS" 
    ? ["CASH", "BANK", "MOBILE"]
    : currency === "COP" 
    ? ["CASH", "BANK"]
    : ["CASH", "BINANCE", "PAYPAL", "CREDIT"];

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

  // Si hay referencia, DEBE haber foto (comprobante)
  const referenceValidation = !form.value.reference || (form.value.reference && form.value.photo_url);

  return basicValidation && referenceValidation && !uploading.value;
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
      payment_type: form.value.is_partial ? "partial" : "full",
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
    max-width="700"
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

      <VCardText class="pa-3 pa-sm-4 bg-light overflow-y-auto" style="max-block-size: 70vh;">
        <!-- Resumen de Deuda Destacado -->
        <VCard
          variant="flat"
          class="rounded-xl border shadow-sm mb-3 bg-white overflow-hidden"
        >
          <div class="pa-3 d-flex align-center justify-space-between flex-wrap gap-2">
            <div class="d-flex align-center gap-3">
              <VAvatar
                color="primary"
                variant="tonal"
                size="36"
                class="rounded-lg text-primary"
              >
                <VIcon
                  icon="tabler-receipt-2"
                  size="20"
                />
              </VAvatar>
              <div class="d-flex flex-column">
                <div class="d-flex align-center gap-2">
                  <span class="text-h5 font-weight-black text-primary leading-none">{{ formatCurrency(totalInUSD, 'USD') }}</span>
                  <VDivider
                    vertical
                    class="opacity-10"
                    style="block-size: 16px;"
                  />
                  <span class="text-h6 font-weight-bold text-success leading-none">Bs. {{ formatCurrency(totalInBS, 'Bs', true) }}</span>
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
              <span class="text-super-xs text-disabled uppercase font-weight-bold">Sujeto a Tasa: {{ exchangeRate }} Bt/USD</span>
            </div>
          </div>

          <!-- Facturas Incluidass (Sutil) -->
          <VDivider class="border-dashed opacity-10 mx-3" />
          <div class="px-3 py-2 d-flex flex-wrap gap-2 align-center bg-light-hint">
            <span class="text-super-xs font-weight-black text-disabled uppercase">Detalle:</span>
            <div class="d-flex flex-wrap gap-1">
              <span 
                v-for="invoice in props.invoices" 
                :key="invoice.id"
                class="invoice-tag text-xs font-weight-bold px-2 py-0.5 rounded border text-medium-emphasis"
              >
                #{{ invoice.invoice_number }}
              </span>
            </div>
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
                    :items="[ {title: 'USD - Dólar', value: 'USD'}, {title: 'VES - Bolívar', value: 'VES'}, {title: 'COP - Peso', value: 'COP'} ]"
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
                    variant="outlined"
                    density="compact"
                    class="premium-input mb-3"
                    hide-details
                    prefix="$"
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

                <VCol cols="12">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Referencia</span>
                  <VTextField
                    v-model="form.reference"
                    placeholder="# Transacción o Lote..."
                    variant="outlined"
                    density="compact"
                    class="premium-input mb-3"
                    hide-details
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
                    :error="form.reference && !form.photo_url"
                    :error-messages="form.reference && !form.photo_url ? ['Si hay referencia, el comprobante es obligatorio'] : []"
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
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
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
