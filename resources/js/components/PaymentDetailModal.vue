<script setup>
import axios from "@/plugins/axios";
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
  const map = { BS: "VES", USS: "USD" };
  const normalized = currency.toUpperCase().trim();
  return map[normalized] || normalized;
};

const formatCurrency = (amount, currency) => {
  if (!amount) return "N/A";
  const normalized = normalizeCurrencyCode(currency);
  const decimals = normalized === "COP" ? 0 : 2;
  return `${normalized} ${formatNumber(amount, decimals)}`;
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
        <div class="premium-dialog-header pa-4 d-flex align-center shadow-sm">
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
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="isVisible = false"
          />
        </div>
      </VCardTitle>

      <VCardText v-if="props.payment" class="pa-4 pa-sm-6 bg-light">
        <VRow>
          <!-- Resumen Financiero -->
          <VCol cols="12" md="5">
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Resumen del Pago</span>
            </div>

            <VCard class="rounded-xl border shadow-sm bg-white overflow-hidden mb-6">
              <div class="pa-6 d-flex flex-column align-center text-center">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2">Total Pagado</span>
                <span class="text-h3 font-weight-black text-primary mb-1">
                  {{ formatCurrency(props.payment.amount, props.payment.currency) }}
                </span>
                <div class="d-flex align-center gap-2 bg-white px-4 py-1 rounded-pill shadow-sm border">
                  <VIcon icon="tabler-currency-dollar" size="18" color="success" />
                  <span class="text-base font-weight-black text-success">
                    USD {{ formatNumber(props.payment.amount_usd) }}
                  </span>
                </div>
              </div>

              <div class="pa-5 pt-0">
                <VDivider class="border-dashed opacity-20 mb-5" />

                <div v-if="savingsPercentage > 0" class="savings-card pa-4 rounded-lg d-flex align-center">
                  <VAvatar color="white" size="44" class="me-4 shadow-sm" variant="elevated">
                    <VIcon icon="tabler-trending-down" color="success" size="24" />
                  </VAvatar>
                  <div>
                    <div class="text-h5 font-weight-black text-success">{{ savingsPercentage }}%</div>
                    <div class="text-super-xs font-weight-black text-disabled uppercase">Ahorro Detectado</div>
                  </div>
                </div>
                <div v-else class="pa-4 bg-white rounded-lg border border-dashed d-flex align-center text-center justify-center min-h-60">
                  <span class="text-xs font-weight-bold text-disabled uppercase">Sin descuentos registrados</span>
                </div>
              </div>
            </VCard>

            <!-- Detalles de Registro -->
            <VCard class="rounded-lg border shadow-sm bg-white pa-5">
              <div class="d-flex align-center mb-5">
                <VIcon icon="tabler-user-check" size="22" class="me-3 text-primary" />
                <div>
                  <span class="text-super-xs font-weight-black text-disabled uppercase d-block">Registrado por</span>
                  <span class="text-sm font-weight-black">{{ props.payment.user?.name || "Sistema" }}</span>
                </div>
              </div>
              <div class="d-flex align-center">
                <VIcon icon="tabler-wallet" size="22" class="me-3 text-primary" />
                <div>
                  <span class="text-super-xs font-weight-black text-disabled uppercase d-block">Método de Pago</span>
                  <span class="text-sm font-weight-black text-capitalize">{{ props.payment.payment_method || "Transferencia" }}</span>
                </div>
              </div>
            </VCard>
          </VCol>

          <!-- Facturas -->
          <VCol cols="12" md="7">
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Facturas Asociadas</span>
            </div>

            <VCard class="rounded-lg border shadow-sm overflow-hidden bg-white">
              <VList lines="two" class="pa-0">
                <VListItem
                  v-for="invoice in props.payment.invoices"
                  :key="invoice.id"
                  class="border-b py-4"
                >
                  <template #prepend>
                    <VAvatar color="secondary" variant="tonal" rounded size="40" class="rounded-lg">
                      <VIcon icon="tabler-hash" size="22" />
                    </VAvatar>
                  </template>
                  <VListItemTitle class="font-weight-black text-base">
                    #{{ invoice.invoice_number }}
                  </VListItemTitle>
                  <VListItemSubtitle class="text-xs font-weight-bold text-disabled uppercase mt-1">
                    {{ invoice.supplier?.name }}
                  </VListItemSubtitle>
                  <template #append>
                    <div class="text-right">
                      <div class="text-base font-weight-black">
                        {{ formatNumber(invoice.total_amount, normalizeCurrencyCode(invoice.currency) === "COP" ? 0 : 2) }}
                        <span class="text-super-xs font-weight-black ms-1">{{ normalizeCurrencyCode(invoice.currency) }}</span>
                      </div>
                      <div class="text-xs font-weight-black text-success uppercase">
                        USD {{ formatNumber(invoice.total_usd) }}
                      </div>
                    </div>
                  </template>
                </VListItem>
              </VList>

              <div class="bg-surface-variant-light pa-4 d-flex justify-space-between align-center border-t">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Total Facturado</span>
                <span class="text-h6 font-weight-black text-high-emphasis">USD {{ formatNumber(props.payment.invoice_total_usd) }}</span>
              </div>
            </VCard>

            <!-- Notas -->
            <div v-if="props.payment.notes" class="mt-6">
              <div class="d-flex align-center gap-2 mb-3 ms-2">
                <VIcon icon="tabler-message-2" size="20" color="disabled" />
                <span class="font-weight-black text-uppercase text-xs text-disabled">Observaciones</span>
              </div>
              <div class="pa-4 bg-surface-variant-light rounded-lg border border-dashed text-sm italic text-medium-emphasis">
                "{{ props.payment.notes }}"
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-4 bg-light border-t d-flex gap-3">
        <VBtn
          v-if="hasPortalIntegration"
          variant="flat"
          color="warning"
          height="50"
          class="rounded-lg font-weight-black shadow-sm text-button uppercase flex-grow-1"
          prepend-icon="tabler-send"
          @click="openResendDialog"
        >
          Reenviar Pago a Portal
        </VBtn>
        <VBtn
          :class="hasPortalIntegration ? 'flex-grow-1' : 'w-100'"
          variant="flat"
          color="secondary"
          height="50"
          class="rounded-lg font-weight-black shadow-sm text-button uppercase"
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

.savings-card {
  background: rgba(var(--v-theme-success), 0.08);
  border: 1px dashed rgba(var(--v-theme-success), 0.3);
}

.border-dashed {
  border-style: dashed !important;
}

.premium-dialog-header {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>

