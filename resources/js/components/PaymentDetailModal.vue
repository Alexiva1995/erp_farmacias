<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  payment: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

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

      <VCardActions class="pa-4 bg-light border-t">
        <VBtn
          block
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
