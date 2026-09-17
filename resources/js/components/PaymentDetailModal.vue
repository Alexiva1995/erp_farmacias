<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  payment: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "payment-resent"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

// Bancos de Dronena
const dronenaBanks = [
  { title: "Banesco - 01340326153261014466", value: "0134:01340326153261014466" },
  { title: "100% Banco - 01560001850000305886", value: "0156:01560001850000305886" },
  { title: "Bancaribe - 01140159081590059954", value: "0114:01140159081590059954" },
  { title: "Banco Activo - 01710001916000572877", value: "0171:01710001916000572877" },
  { title: "Banco de Venezuela - 01020108860000213197", value: "0102:01020108860000213197" },
  { title: "Banco Exterior - 01150010231002347209", value: "0115:01150010231002347209" },
  { title: "Banco Plaza - 01380001980010196885", value: "0138:01380001980010196885" },
  { title: "BANPLUS - 01740101161014167664", value: "0174:01740101161014167664" },
  { title: "BBVA Provincial - 01080031540100001188", value: "0108:01080031540100001188" },
  { title: "BFC Banco Fondo Común - 01510078044078018318", value: "0151:01510078044078018318" },
  { title: "BNC - 01910052942152066804", value: "0191:01910052942152066804" },
  { title: "Mercantil - 01050014841014299388", value: "0105:01050014841014299388" },
  { title: "Venezolano de Crédito - 01040001590001358054", value: "0104:01040001590001358054" },
];

// Bancos de Cobeca / Mafarta
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

// Bancos de Cristmedicals
const cristmedicalsBanks = [
  { title: "BANPLUS (MovilPay) - 0174 0144 1214 4440 2133", value: "30" },
  { title: "BANCO PROVINCIAL - 0108 0014 4401 0034 7852", value: "01080014440100347852" },
  { title: "BANESCO - 0134 0435 6943 5102 6986", value: "01340435694351026986" },
  { title: "BANCO DE VENEZUELA - 0102 0219 1900 0117 6179", value: "01020219190001176179" },
  { title: "BANCO NACIONAL DE CREDITO (BNC) - 0191 0040 5621 4008 9488", value: "01910040562140089488" },
];

// Bancos de Dromega
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

// Detección de proveedor
const supplierNameUpper = computed(() => {
  if (!props.payment?.invoices?.length) return "";
  return String(props.payment.invoices[0]?.supplier?.name || "").toUpperCase();
});

const isDronena = computed(() => supplierNameUpper.value.includes("NENA") || supplierNameUpper.value.includes("DRONENA"));
const isMafarta = computed(() => supplierNameUpper.value.includes("MAFARTA") || supplierNameUpper.value.includes("COBECA"));
const isCristmedicals = computed(() => supplierNameUpper.value.includes("CRIST") || supplierNameUpper.value.includes("CRISTALMEDICALS"));
const isDromega = computed(() => supplierNameUpper.value.includes("DROMEGA") || supplierNameUpper.value.includes("MEGA"));

const hasPortalIntegration = computed(() => isDronena.value || isMafarta.value || isCristmedicals.value || isDromega.value);

const supplierEmail = computed(() => {
  if (!props.payment?.invoices?.length) return "";
  const inv = props.payment.invoices[0];
  return inv?.supplier?.payment_email || inv?.supplier?.email || "";
});

const hasEmailIntegration = computed(() => Boolean(supplierEmail.value));

const portalBankOptions = computed(() => {
  if (isMafarta.value) return mafartaBanks;
  if (isDronena.value) return dronenaBanks;
  if (isCristmedicals.value) return cristmedicalsBanks;
  if (isDromega.value) return dromegaBanks;
  return [];
});

// Estado de reenvío
const showResendDialog = ref(false);
const resending = ref(false);
const sendingEmail = ref(false);
const resendForm = ref({
  destination_bank: null,
  reference: "",
  id_type: "V",
  id_number: "24150980",
});
const alertMessage = ref("");
const alertType = ref("info");
const showAlert = ref(false);

const openResendDialog = () => {
  resendForm.value.reference = props.payment?.reference || "";
  const defaultBank = portalBankOptions.value[0]?.value || null;
  resendForm.value.destination_bank = defaultBank;
  resendForm.value.id_type = "V";
  resendForm.value.id_number = "24150980";
  showAlert.value = false;
  showResendDialog.value = true;
};

const executeResendEmail = async () => {
  if (!props.payment?.id || !supplierEmail.value) return;
  sendingEmail.value = true;
  try {
    const res = await axios.post("/finances/pending-payments/resend-email", {
      payment_id: props.payment.id,
      email: supplierEmail.value,
    });
    if (res.data?.status === "success" || res.data?.success) {
      toast.success(res.data.message || `Comprobante reenviado exitosamente a ${supplierEmail.value}`);
      emit("payment-resent");
    } else {
      toast.error(res.data?.message || "No se pudo reenviar el correo.");
    }
  } catch (err) {
    console.error("Error reenviando correo de pago:", err);
    toast.error(err.response?.data?.message || "Error al conectar con el servidor.");
  } finally {
    sendingEmail.value = false;
  }
};

const executeResend = async () => {
  if (!props.payment?.id) return;
  resending.value = true;
  showAlert.value = false;

  try {
    const payload = {
      payment_id: props.payment.id,
      destination_bank: resendForm.value.destination_bank,
      reference: resendForm.value.reference,
      id_type: resendForm.value.id_type,
      id_number: resendForm.value.id_number,
    };

    const response = await axios.post("/finances/pending-payments/resend-to-portal", payload);

    if (response.data.status === "success" || response.data.success) {
      alertType.value = "success";
      alertMessage.value = response.data.message || "Pago reenviado exitosamente al portal del proveedor.";
      showAlert.value = true;
      if (resendForm.value.reference && props.payment) {
        props.payment.reference = resendForm.value.reference;
      }
      emit("payment-resent");
      setTimeout(() => {
        showResendDialog.value = false;
      }, 2500);
    } else {
      alertType.value = "error";
      alertMessage.value = response.data.message || "Error reportando el pago en el portal.";
      showAlert.value = true;
    }
  } catch (error) {
    alertType.value = "error";
    alertMessage.value = error.response?.data?.message || "Error de comunicación con el servidor.";
    showAlert.value = true;
  } finally {
    resending.value = false;
  }
};

const formatDate = (date) =>
  date ? new Date(date).toLocaleDateString("es-ES") : "N/A";

const formatNumber = (num, decimals = 2) => {
  if (num === null || num === undefined) return "0.00";
  return new Intl.NumberFormat("es-ES", {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(num);
};

const normalizeCurrencyCode = (currency) => {
  if (!currency) return "";
  const map = { BS: "Bs.", VES: "Bs.", USS: "USD" };
  const normalized = currency.toUpperCase().trim();
  return map[normalized] || normalized;
};

const formatCurrency = (amount, currency) => {
  if (amount === null || amount === undefined || amount === "") return "N/A";
  const normalized = normalizeCurrencyCode(currency);
  const decimals = normalized === "COP" ? 0 : 2;
  const formatted = formatNumber(amount, decimals);
  return `${formatted} ${normalized}`;
};

const savingsPercentage = computed(() => {
  if (!props.payment) return 0;
  const paidUSD = parseFloat(props.payment.amount_usd) || 0;
  const invoiceTotalUSD = parseFloat(props.payment.invoice_total_usd) || 0;
  if (invoiceTotalUSD <= 0) return 0;
  const savingsUSD = invoiceTotalUSD - paidUSD;
  const percentage = (savingsUSD / invoiceTotalUSD) * 100;
  return Math.max(0, Math.round(percentage * 100) / 100);
});

// Total facturado en la moneda original de las facturas o en Bs.
const invoiceBilledAmount = computed(() => {
  if (!props.payment?.invoices?.length) return 0;
  return props.payment.invoices.reduce((acc, inv) => acc + (parseFloat(inv.total_amount) || 0), 0);
});

const invoiceBilledCurrency = computed(() => {
  if (!props.payment?.invoices?.length) return "Bs.";
  return props.payment.invoices[0]?.currency || "Bs.";
});
</script>

<template>
  <VDialog
    v-model="isVisible"
    max-width="1000"
    persistent
    scrollable
    :fullscreen="$vuetify.display.smAndDown"
    :transition="$vuetify.display.smAndDown ? 'dialog-bottom-transition' : 'scale-transition'"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            size="40"
            color="white"
            variant="flat"
            class="me-3 shadow-sm rounded-lg elevation-1"
          >
            <VIcon icon="tabler-receipt-2" color="primary" size="24" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h3 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Detalles del Pago
            </h3>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Ref: {{ props.payment?.reference || 'N/A' }} · {{ formatDate(props.payment?.payment_date) }}
              </span>
            </div>
          </div>
          <VSpacer />
          <IconBtn
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="isVisible = false"
          >
            <VIcon icon="tabler-x" size="20" />
            <VTooltip activator="parent" location="top">Cerrar</VTooltip>
          </IconBtn>
        </div>
      </VCardTitle>

      <VCardText v-if="props.payment" class="pa-6 pa-md-8 bg-light">
        <VRow class="gx-md-6 gy-6">
          <!-- Resumen Financiero -->
          <VCol cols="12" md="5">
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Resumen del Pago</span>
            </div>

            <VCard class="rounded-xl border shadow-sm bg-white overflow-hidden mb-5">
              <div class="pa-5">
                <VRow no-gutters class="align-stretch">
                  <!-- Izquierda: Monto Real Pagado -->
                  <VCol cols="6" class="pe-3 border-e d-flex flex-column justify-center align-center text-center">
                    <span class="text-caption text-medium-emphasis mb-1 font-weight-medium">Monto Pagado</span>
                    <span class="summary-amount font-weight-black text-high-emphasis mb-1">
                      {{ formatCurrency(props.payment.amount, props.payment.currency) }}
                    </span>
                    <span class="text-xs font-weight-bold text-success">
                      ({{ formatNumber(props.payment.amount_usd) }} USD)
                    </span>
                  </VCol>

                  <!-- Derecha: Total Facturado Original -->
                  <VCol cols="6" class="ps-3 d-flex flex-column justify-center align-center text-center">
                    <span class="text-caption text-medium-emphasis mb-1 font-weight-medium">Total Facturado</span>
                    <span class="summary-amount font-weight-black text-high-emphasis mb-1">
                      {{ formatCurrency(invoiceBilledAmount, invoiceBilledCurrency) }}
                    </span>
                    <span class="text-xs font-weight-bold text-medium-emphasis">
                      ({{ formatNumber(props.payment.invoice_total_usd) }} USD)
                    </span>
                  </VCol>
                </VRow>
              </div>

              <div class="pa-5 pt-0">
                <VDivider class="opacity-10 mb-4" />

                <div v-if="savingsPercentage > 0" class="savings-card-soft pa-3 pa-sm-4 rounded-lg d-flex align-center gap-3">
                  <VAvatar color="success" size="36" variant="tonal" class="rounded-lg">
                    <VIcon icon="tabler-trending-down" size="20" />
                  </VAvatar>
                  <div class="d-flex flex-column">
                    <span class="text-subtitle-1 font-weight-bold text-success leading-tight">{{ savingsPercentage }}%</span>
                    <span class="text-caption text-medium-emphasis">Ahorro detectado</span>
                  </div>
                </div>
                <div v-else class="pa-3 bg-surface-variant-light rounded-lg border border-dashed d-flex align-center justify-center text-center">
                  <span class="text-caption text-disabled">Sin descuentos registrados</span>
                </div>
              </div>
            </VCard>

            <!-- Detalles de Registro -->
            <VCard class="rounded-xl border shadow-sm bg-white pa-5">
              <div class="d-flex align-center mb-4">
                <VAvatar size="36" color="secondary" variant="tonal" class="me-3 rounded-lg">
                  <VIcon icon="tabler-user-check" size="20" class="text-medium-emphasis" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-caption text-medium-emphasis leading-tight mb-1">Registrado por</span>
                  <span class="text-sm font-weight-bold text-high-emphasis">{{ props.payment.user?.name || "Sistema" }}</span>
                </div>
              </div>
              <div class="d-flex align-center">
                <VAvatar size="36" color="secondary" variant="tonal" class="me-3 rounded-lg">
                  <VIcon icon="tabler-wallet" size="20" class="text-medium-emphasis" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-caption text-medium-emphasis leading-tight mb-1">Método de Pago</span>
                  <span class="text-sm font-weight-bold text-high-emphasis text-capitalize">{{ props.payment.payment_method || "Transferencia" }}</span>
                </div>
              </div>
            </VCard>
          </VCol>

          <!-- Facturas -->
          <VCol cols="12" md="7">
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Facturas Asociadas</span>
            </div>

            <VCard class="rounded-xl border shadow-sm overflow-hidden bg-white">
              <div class="invoice-list">
                <div
                  v-for="(invoice, idx) in props.payment.invoices"
                  :key="invoice.id"
                  class="d-flex align-center justify-space-between py-3 px-4 invoice-item"
                  :class="{ 'border-b': idx < props.payment.invoices.length - 1 }"
                >
                  <div class="d-flex align-center gap-3">
                    <VAvatar color="secondary" variant="tonal" size="32" class="rounded-lg">
                      <VIcon icon="tabler-hash" size="16" class="text-medium-emphasis" />
                    </VAvatar>
                    <div class="d-flex flex-column">
                      <span class="text-sm font-weight-bold text-high-emphasis">
                        #{{ invoice.invoice_number }}
                      </span>
                      <span class="text-caption text-medium-emphasis">
                        {{ invoice.supplier?.name }}
                      </span>
                    </div>
                  </div>
                  <div class="text-end d-flex flex-column">
                    <span class="text-sm font-weight-bold text-high-emphasis">
                      {{ formatNumber(invoice.total_amount, normalizeCurrencyCode(invoice.currency) === "COP" ? 0 : 2) }} {{ normalizeCurrencyCode(invoice.currency) }}
                    </span>
                    <span class="text-caption text-medium-emphasis">
                      {{ formatNumber(invoice.total_usd) }} USD
                    </span>
                  </div>
                </div>
              </div>

              <div class="total-billed-row pa-4 d-flex justify-space-between align-center">
                <span class="text-body-2 font-weight-medium text-medium-emphasis">Total Facturado</span>
                <span class="text-subtitle-1 font-weight-bold text-high-emphasis">{{ formatNumber(props.payment.invoice_total_usd) }} USD</span>
              </div>
            </VCard>

            <!-- Notas -->
            <div v-if="props.payment.notes" class="mt-4">
              <div class="d-flex align-center gap-2 mb-2">
                <VIcon icon="tabler-message-2" size="18" color="medium-emphasis" />
                <span class="text-caption font-weight-bold text-medium-emphasis">Observaciones</span>
              </div>
              <div class="pa-3 bg-surface-variant-light rounded-lg border text-sm text-medium-emphasis">
                "{{ props.payment.notes }}"
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-6 bg-light border-t d-flex flex-wrap gap-3">
        <VBtn
          v-if="hasPortalIntegration"
          variant="flat"
          color="warning"
          height="44"
          class="rounded-lg font-weight-bold shadow-sm flex-grow-1"
          prepend-icon="tabler-send"
          @click="openResendDialog"
        >
          Reenviar Pago al Portal
        </VBtn>

        <VBtn
          v-if="hasEmailIntegration"
          variant="flat"
          color="info"
          height="44"
          class="rounded-lg font-weight-bold shadow-sm flex-grow-1"
          prepend-icon="tabler-mail-forward"
          :loading="sendingEmail"
          @click="executeResendEmail"
        >
          Reenviar al Correo
          <VTooltip activator="parent" location="top">Enviar comprobante a {{ supplierEmail }}</VTooltip>
        </VBtn>

        <VBtn
          :class="(hasPortalIntegration || hasEmailIntegration) ? 'flex-grow-1' : 'w-100'"
          variant="outlined"
          color="secondary"
          height="44"
          class="rounded-lg font-weight-bold"
          @click="isVisible = false"
        >
          Cerrar Detalles
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Diálogo de Confirmación y Banco Destino para Reenvío -->
    <VDialog v-model="showResendDialog" max-width="560" persistent>
      <VCard class="rounded-xl shadow-xl overflow-hidden bg-surface">
        <VCardTitle class="pa-4 bg-warning d-flex align-center text-white">
          <VIcon icon="tabler-refresh" size="24" class="me-2 text-white" />
          <span class="font-weight-black text-h6 text-white">Reenviar Pago al Portal</span>
          <VSpacer />
          <VBtn icon="tabler-x" variant="text" color="white" size="small" @click="showResendDialog = false" />
        </VCardTitle>

        <VCardText class="pa-5">
          <VAlert
            v-if="showAlert"
            :type="alertType"
            variant="tonal"
            class="mb-4 rounded-lg"
            closable
            @click:close="showAlert = false"
          >
            {{ alertMessage }}
          </VAlert>

          <p class="text-sm text-medium-emphasis mb-4">
            Selecciona la cuenta bancaria de destino y verifica la referencia para reportar el pago directamente en el portal oficial de <strong>{{ supplierNameUpper }}</strong>.
          </p>

          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="resendForm.destination_bank"
                :items="portalBankOptions"
                item-title="title"
                item-value="value"
                label="Cuenta Bancaria Destino *"
                density="compact"
                variant="outlined"
                prepend-inner-icon="tabler-building-bank"
                class="rounded-lg"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="resendForm.reference"
                label="Número de Referencia *"
                density="compact"
                variant="outlined"
                prepend-inner-icon="tabler-hash"
                placeholder="Ej. 059133884722"
              />
            </VCol>

            <template v-if="isMafarta">
              <VCol cols="4">
                <VSelect
                  v-model="resendForm.id_type"
                  :items="['V', 'E', 'J', 'G']"
                  label="Tipo Doc"
                  density="compact"
                  variant="outlined"
                />
              </VCol>
              <VCol cols="8">
                <VTextField
                  v-model="resendForm.id_number"
                  label="Cédula / RIF Titular"
                  density="compact"
                  variant="outlined"
                  placeholder="24150980"
                />
              </VCol>
            </template>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4 bg-light border-t d-flex gap-2">
          <VBtn
            variant="outlined"
            color="secondary"
            class="rounded-lg font-weight-bold"
            :disabled="resending"
            @click="showResendDialog = false"
          >
            Cancelar
          </VBtn>
          <VSpacer />
          <VBtn
            variant="flat"
            color="warning"
            class="rounded-lg font-weight-black px-5"
            :loading="resending"
            :disabled="resending || !resendForm.destination_bank"
            prepend-icon="tabler-send"
            @click="executeResend"
          >
            Transmitir al Portal
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VDialog>
</template>

<style scoped>
.bg-light {
  background-color: #f8faff !important;
}

.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.leading-none {
  line-height: 1 !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.04);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.min-h-60 {
  min-block-size: 60px;
}

.hero-amount {
  font-size: 1.85rem !important;
  line-height: 1.2 !important;
}

.summary-amount {
  font-size: 1.15rem !important;
  line-height: 1.2 !important;
  letter-spacing: -0.01em;
}

.savings-card-soft {
  background-color: #f0fdf4 !important;
  border: 1px solid #dcfce7 !important;
}

.invoice-item {
  border-color: rgba(var(--v-border-color), 0.08) !important;
}

.total-billed-row {
  background-color: #fafafa;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-e {
  border-inline-end: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>

