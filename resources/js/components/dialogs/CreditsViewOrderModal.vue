<script setup>
import { formatCurrency, formatAmountOnly } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import { translateMethod } from "@/utils/paymentMethods";
import axios from "@/plugins/axios";
import { computed, defineEmits, defineProps, ref, watch } from "vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  creditsData: {
    type: Array,
    default: () => [],
  },
  selectedCurrency: {
    type: String,
    default: "USD",
  },
});

const emit = defineEmits(["update:isDialogVisible", "modal-closed"]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const payments = ref([]);
const loadingPayments = ref(false);
const filterClient = ref("");
const filterDate = ref("");
const filterCurrency = ref("");
const exchangeRates = ref({});
const pageOrders = ref(1);
const pagePayments = ref(1);
const itemsPerPage = 10;

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
};

// Ordenar créditos por fecha de orden: más reciente primero
const sortedCredits = computed(() => {
  if (!Array.isArray(props.creditsData) || props.creditsData.length === 0) {
    return [];
  }
  return [...props.creditsData].sort((a, b) => {
    const dateA = a.order?.created_at || a.credit_date || "";
    const dateB = b.order?.created_at || b.credit_date || "";
    return new Date(dateB) - new Date(dateA);
  });
});

const clientInfo = computed(() => {
  const first = sortedCredits.value[0];
  if (!first) return null;
  return first.client || first.order?.client;
});

const totalCredits = computed(() => {
  return sortedCredits.value.reduce((sum, credit) => {
    return sum + (parseFloat(credit.credit_amount) || 0);
  }, 0);
});

const totalPendingAmount = computed(() => {
  return sortedCredits.value.reduce((sum, credit) => {
    return sum + (parseFloat(credit.pending_amount) || 0);
  }, 0);
});

const getCurrencyChipColor = (currency) => {
  if (!currency) return "secondary";
  switch (String(currency).toUpperCase()) {
    case "COP":
      return "primary";
    case "BS":
      return "success";
    case "USD":
      return "warning";
    default:
      return "info";
  }
};

const getItemPriceByCurrency = (detail, currency) => {
  const unitPrice =
    parseFloat(detail.unit_price_usd) ??
    parseFloat(detail.price) ??
    0;
  return unitPrice;
};

const getLineTotal = (detail) => {
  const qty = parseInt(detail.quantity) || 0;
  const unitPrice = getItemPriceByCurrency(detail, props.selectedCurrency);
  return unitPrice * qty;
};

const fetchPayments = async () => {
  const clientId = clientInfo.value?.id ?? props.creditsData?.[0]?.client_id ?? props.creditsData?.[0]?.client?.id;
  if (!clientId) return;

  loadingPayments.value = true;
  try {
    const response = await axios.post("/tpv/credits/payments", {
      client_id: clientId,
    });
    payments.value = Array.isArray(response.data) ? response.data : [];
  } catch (error) {
    console.error("Error al cargar los pagos:", error);
    payments.value = [];
  } finally {
    loadingPayments.value = false;
  }
};

const flattenedPaymentRows = computed(() => {
  const data = payments.value;
  if (!Array.isArray(data) || data.length === 0) return [];
  return data.map((row) => ({
    date: row.payment_date ?? row.date,
    amount: row.amount ?? 0,
    currency: row.currency ?? "USD",
    method: row.method ?? "",
    reference: row.reference ?? "",
    seller: row.seller ?? "N/A",
  }));
});

const filteredPaymentRows = computed(() => {
  let filtered = flattenedPaymentRows.value;

  if (filterClient.value) {
    const search = filterClient.value.toLowerCase();
    filtered = filtered.filter((r) =>
      (r.seller || "").toLowerCase().includes(search)
    );
  }
  if (filterDate.value) {
    filtered = filtered.filter((r) => {
      const d = r.date?.split?.(" ")?.[0] ?? r.date;
      return d === filterDate.value;
    });
  }
  if (filterCurrency.value) {
    filtered = filtered.filter((r) => r.currency === filterCurrency.value);
  }
  return filtered;
});

const fetchExchangeRates = async () => {
  try {
    const response = await axios.get("/public/exchange-rates");
    if (response.status !== 200) return;
    const apiRates = response.data || [];
    const formatted = {};
    apiRates.forEach((item) => {
      const code = item.currency_code;
      const rate = parseFloat(item.rate) || 0;
      if (!formatted["USD"]) formatted["USD"] = {};
      formatted["USD"][code] = rate;
      if (!formatted[code]) formatted[code] = {};
      formatted[code]["USD"] = rate !== 0 ? 1 / rate : 0;
    });
    exchangeRates.value = formatted;
  } catch (e) {
    console.warn("Error cargando tasas:", e);
  }
};

const totalPaidUSD = computed(() => {
  const rates = exchangeRates.value;
  return filteredPaymentRows.value.reduce((sum, r) => {
    const amount = parseFloat(r.amount) || 0;
    const cur = (r.currency || "USD").toUpperCase();
    if (cur === "USD") return sum + amount;
    const rateToUsd = rates?.[cur]?.["USD"];
    return sum + (rateToUsd ? amount * rateToUsd : amount);
  }, 0);
});

const paginatedCredits = computed(() => {
  const list = sortedCredits.value;
  const start = (pageOrders.value - 1) * itemsPerPage;
  return list.slice(start, start + itemsPerPage);
});

const totalPagesOrders = computed(() =>
  Math.ceil((sortedCredits.value.length || 0) / itemsPerPage)
);

const paginatedPaymentRows = computed(() => {
  const list = filteredPaymentRows.value;
  const start = (pagePayments.value - 1) * itemsPerPage;
  return list.slice(start, start + itemsPerPage);
});

const totalPagesPayments = computed(() =>
  Math.ceil((filteredPaymentRows.value.length || 0) / itemsPerPage)
);

const formatPaymentAmount = (amount, currency) =>
  formatAmountOnly(parseFloat(amount) || 0, (currency || "USD").toUpperCase());

const prevPageOrders = () => {
  pageOrders.value = Math.max(1, pageOrders.value - 1);
};
const nextPageOrders = () => {
  pageOrders.value = Math.min(totalPagesOrders.value, pageOrders.value + 1);
};
const prevPagePayments = () => {
  pagePayments.value = Math.max(1, pagePayments.value - 1);
};
const nextPagePayments = () => {
  pagePayments.value = Math.min(totalPagesPayments.value, pagePayments.value + 1);
};

const paymentHeaders = [
  { title: "Fecha", key: "date", sortable: false },
  { title: "Monto", key: "amount", sortable: false },
  { title: "Moneda", key: "currency", sortable: false },
  { title: "Método", key: "method", sortable: false },
  { title: "Vendedor", key: "seller", sortable: false },
];

watch(
  () => [props.isDialogVisible, props.creditsData],
  ([visible, credits]) => {
    const hasClient = credits?.length > 0 && credits[0]?.client?.id;
    if (visible && hasClient) {
      fetchPayments();
      fetchExchangeRates();
      pageOrders.value = 1;
      pagePayments.value = 1;
    }
  },
  { immediate: true, deep: true }
);

watch([filterClient, filterDate, filterCurrency], () => {
  pagePayments.value = 1;
});
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="700"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    class="premium-dialog"
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-report-money" color="primary" size="24" />
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">Estado de Cuenta</h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold letter-spacing-1">
                Detalle de Créditos y Pagos
              </span>
            </div>
          </div>

          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            @click="closeModal"
            class="rounded-lg"
          >
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-0 bg-light">
        <div class="pa-4 pa-sm-6 overflow-y-auto" style="max-height: 75vh;">
          <!-- Información del Cliente -->
          <VCard variant="flat" class="border pa-4 bg-white rounded-lg elevation-1 mb-6">
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary"></div>
              <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Información del Tarjetahabiente</span>
            </div>

            <VRow dense>
              <VCol cols="12" sm="7">
                <div class="d-flex flex-column">
                  <span class="text-super-xs text-disabled font-weight-bold uppercase mb-1">Nombre Completo</span>
                  <span class="text-subtitle-1 font-weight-black text-high-emphasis">
                    {{ clientInfo?.name || "" }} {{ clientInfo?.last_name || "" }}
                  </span>
                </div>
              </VCol>
              <VCol cols="12" sm="5">
                <div class="d-flex flex-column">
                  <span class="text-super-xs text-disabled font-weight-bold uppercase mb-1">Identificación</span>
                  <span class="text-subtitle-1 font-weight-black text-high-emphasis">
                    {{ clientInfo?.identification_type || "" }}{{ clientInfo?.identification || "—" }}
                  </span>
                </div>
              </VCol>
            </VRow>
          </VCard>

          <!-- Resumen de Saldos -->
          <VRow dense class="mb-6">
            <VCol cols="12" sm="6">
              <VCard variant="flat" class="border-l-primary border pa-3 bg-white rounded-lg elevation-2 h-100 d-flex flex-column justify-center overflow-hidden">
                <div class="d-flex align-center justify-space-between w-100">
                  <div class="d-flex flex-column">
                    <span class="text-super-xs text-disabled font-weight-black uppercase letter-spacing-1">Total Crédito</span>
                    <span class="text-h6 font-weight-950 text-primary leading-none mt-1">
                      {{ formatCurrency(totalCredits, selectedCurrency) }}
                    </span>
                  </div>
                  <VIcon icon="tabler-currency-dollar" size="32" color="primary" class="opacity-10 position-absolute" style="inset-inline-end: -8px; inset-block-end: -8px;" />
                </div>
              </VCard>
            </VCol>
            <VCol cols="12" sm="6">
              <VCard variant="flat" class="border-l-error border pa-3 bg-white rounded-lg elevation-2 h-100 d-flex flex-column justify-center overflow-hidden">
                <div class="d-flex align-center justify-space-between w-100">
                  <div class="d-flex flex-column">
                    <span class="text-super-xs text-disabled font-weight-black uppercase letter-spacing-1">Total Pendiente</span>
                    <span class="text-h6 font-weight-950 text-error leading-none mt-1">
                      {{ formatCurrency(totalPendingAmount, selectedCurrency) }}
                    </span>
                  </div>
                  <VIcon icon="tabler-alert-circle" size="32" color="error" class="opacity-10 position-absolute" style="inset-inline-end: -8px; inset-block-end: -8px;" />
                </div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Órdenes -->
          <div class="d-flex align-center gap-2 mb-4">
            <div class="header-indicator secondary"></div>
            <span class="text-xs font-weight-black text-secondary uppercase letter-spacing-1">Historial de Ventas a Crédito</span>
          </div>

          <div v-for="(credit, idx) in paginatedCredits" :key="credit.id || credit.order?.id || idx" class="mb-4">
            <VCard variant="flat" class="border overflow-hidden rounded-lg bg-white elevation-1">
              <div class="bg-var-theme-background-secondary pa-3 d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-2">
                  <VChip size="x-small" color="secondary" variant="flat" class="font-weight-black uppercase">
                    ORDEN #{{ credit.order?.id ?? "—" }}
                  </VChip>
                  <span class="text-super-xs font-weight-bold text-disabled">
                    {{ formatDateTime(credit.order?.created_at || credit.credit_date, "datetime") }}
                  </span>
                </div>
                <VChip size="x-small" :color="getCurrencyChipColor(credit.order?.currency)" variant="tonal" class="font-weight-black">
                  {{ credit.order?.currency || "USD" }}
                </VChip>
              </div>

              <!-- Tabla de productos -->
              <VTable density="compact" class="premium-table-compact">
                <thead>
                  <tr>
                    <th class="text-super-xs font-weight-black text-disabled uppercase">Producto</th>
                    <th class="text-super-xs font-weight-black text-disabled uppercase text-end">Unit.</th>
                    <th class="text-super-xs font-weight-black text-disabled uppercase text-center">Cant.</th>
                    <th class="text-super-xs font-weight-black text-disabled uppercase text-end">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(detail, dIdx) in credit.order?.details || []" :key="detail.id || dIdx">
                    <td>
                      <div class="d-flex flex-column py-1">
                        <div class="d-flex align-center gap-1">
                          <span class="text-primary font-weight-black" style="font-size: 0.65rem;">#{{ detail.product?.id || detail.product_id }}</span>
                          <span class="text-xs font-weight-black text-uppercase truncate" style="max-width: 180px;">{{ detail.product?.name || '—' }}</span>
                        </div>
                        <span class="text-super-xs text-disabled font-weight-medium">{{ detail.product?.laboratory?.name || detail.product?.laboratory || '—' }}</span>
                      </div>
                    </td>
                    <td class="text-end text-xs font-weight-bold">
                      {{ formatAmountOnly(getItemPriceByCurrency(detail, credit.order?.currency), credit.order?.currency) }}
                    </td>
                    <td class="text-center text-xs font-weight-black">{{ detail.quantity }}</td>
                    <td class="text-end text-xs font-weight-black text-primary">
                      {{ formatAmountOnly(getLineTotal(detail), credit.order?.currency) }}
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </VCard>
          </div>

          <!-- Paginación Órdenes -->
          <div v-if="totalPagesOrders > 1" class="d-flex justify-center align-center gap-3 mt-4 mb-8">
            <VBtn icon variant="tonal" color="secondary" size="x-small" :disabled="pageOrders <= 1" @click="prevPageOrders" class="rounded-lg shadow-sm">
              <VIcon icon="tabler-chevron-left" size="18" />
            </VBtn>
            <span class="text-super-xs font-weight-black text-disabled uppercase">Página {{ pageOrders }} de {{ totalPagesOrders }}</span>
            <VBtn icon variant="tonal" color="secondary" size="x-small" :disabled="pageOrders >= totalPagesOrders" @click="nextPageOrders" class="rounded-lg shadow-sm">
              <VIcon icon="tabler-chevron-right" size="18" />
            </VBtn>
          </div>

          <VDivider class="my-6" />

          <!-- Historial de Pagos -->
          <div class="d-flex align-center justify-space-between mb-4">
            <div class="d-flex align-center gap-2">
              <div class="header-indicator success"></div>
              <span class="text-xs font-weight-black text-success uppercase letter-spacing-1">Historial de Pagos Recibidos</span>
            </div>
            <VChip color="success" size="small" variant="flat" class="font-weight-black shadow-sm">
              TOTAL PAGADO: {{ formatCurrency(totalPaidUSD, "USD") }}
            </VChip>
          </div>

          <!-- Filtros de Pagos -->
          <VCard variant="flat" class="pa-3 bg-white border border-dashed rounded-lg mb-4">
            <VRow dense>
              <VCol cols="12" md="5">
                <AppTextField v-model="filterClient" placeholder="Filtrar por vendedor..." density="compact" prepend-inner-icon="tabler-search" class="shadow-sm" />
              </VCol>
              <VCol cols="12" md="4">
                <AppTextField v-model="filterDate" type="date" density="compact" class="shadow-sm" />
              </VCol>
              <VCol cols="12" md="3">
                <AppSelect v-model="filterCurrency" :items="['USD', 'COP', 'BS']" placeholder="Divisa" density="compact" class="shadow-sm" />
              </VCol>
            </VRow>
          </VCard>

          <VCard variant="flat" class="border rounded-lg overflow-hidden bg-white elevation-1">
            <VTable density="compact" class="premium-table">
              <thead>
                <tr>
                  <th v-for="h in paymentHeaders" :key="h.key" class="text-super-xs font-weight-black text-disabled uppercase">
                    {{ h.title }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, rIdx) in paginatedPaymentRows" :key="rIdx" class="row-hover-effect">
                  <td class="text-xs font-weight-medium">{{ row.date ? formatDateTime(row.date, "date") : "N/A" }}</td>
                  <td class="text-xs font-weight-black">{{ formatPaymentAmount(row.amount, row.currency) }}</td>
                  <td>
                    <VChip size="x-small" :color="row.currency === 'USD' ? 'success' : row.currency === 'COP' ? 'info' : 'primary'" variant="tonal" class="font-weight-black">
                      {{ row.currency }}
                    </VChip>
                  </td>
                  <td class="text-xs font-weight-medium uppercase">{{ translateMethod(row.method) }}</td>
                  <td class="text-xs font-weight-bold text-disabled">{{ row.seller || "N/A" }}</td>
                </tr>
                <tr v-if="filteredPaymentRows.length === 0">
                  <td :colspan="paymentHeaders.length" class="text-center py-6 text-disabled text-super-xs font-weight-bold uppercase italic">
                    No se han registrado pagos para este cliente
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>

          <!-- Paginación Pagos -->
          <div v-if="totalPagesPayments > 1" class="d-flex justify-center align-center gap-3 mt-4">
            <VBtn icon variant="tonal" color="success" size="x-small" :disabled="pagePayments <= 1" @click="prevPagePayments" class="rounded-lg shadow-sm">
              <VIcon icon="tabler-chevron-left" size="18" />
            </VBtn>
            <span class="text-super-xs font-weight-black text-disabled uppercase">Página {{ pagePayments }} de {{ totalPagesPayments }}</span>
            <VBtn icon variant="tonal" color="success" size="x-small" :disabled="pagePayments >= totalPagesPayments" @click="nextPagePayments" class="rounded-lg shadow-sm">
              <VIcon icon="tabler-chevron-right" size="18" />
            </VBtn>
          </div>
        </div>
      </VCardText>

      <VDivider />
      <VCardActions class="pa-4 bg-light border-t">
        <VBtn
          color="secondary"
          variant="tonal"
          size="large"
          block
          height="50"
          class="font-weight-black rounded-lg text-button uppercase"
          @click="closeModal"
        >
          Cerrar Expediente
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.detail-dialog-card {
  border-radius: 16px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }
.header-indicator.success { background-color: rgb(var(--v-theme-success)); }

.border-l-primary { border-inline-start: 4px solid rgb(var(--v-theme-primary)) !important; }
.border-l-error { border-inline-start: 4px solid rgb(var(--v-theme-error)) !important; }

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

.leading-tight { line-height: 1.25 !important; }
.leading-none { line-height: 1 !important; }

.font-weight-950 { font-weight: 950 !important; }

.bg-var-theme-background-secondary {
  background-color: rgba(var(--v-theme-secondary), 0.05);
}

.premium-table-compact :deep(th) {
  height: 32px !important;
  padding-inline: 12px !important;
}

.premium-table-compact :deep(td) {
  height: 48px !important;
  padding-inline: 12px !important;
}

.premium-table :deep(th) {
  background-color: #fafbfc !important;
  height: 40px !important;
  padding-inline: 12px !important;
}

.premium-table :deep(td) {
  height: 48px !important;
  padding-inline: 12px !important;
}

.row-hover-effect:hover {
  background-color: rgba(var(--v-theme-success), 0.02);
}

.border-dashed {
  border-style: dashed !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
