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

const productLineLabel = (detail) => {
  const product = detail.product || {};
  const id = product.id ?? product.product_id ?? null;
  const name = product.name ?? product.title ?? "—";
  const lab = product.laboratory?.name ?? product.laboratory ?? "";
  const parts = [id != null ? String(id) : null, name, lab || null].filter(
    Boolean
  );
  return parts.join(" - ");
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

const totalPaid = computed(() => {
  return filteredPaymentRows.value.reduce(
    (sum, r) => sum + (parseFloat(r.amount) || 0),
    0
  );
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
    max-width="560"
    persistent
    content-class="order-view-dialog"
  >
    <VCard class="order-view-card rounded-lg">
      <!-- Header con info del cliente -->
      <VCardTitle
        class="order-view-header d-flex align-center flex-wrap gap-2 pt-3 px-3 pb-2"
      >
        <span class="text-subtitle-1 font-weight-bold section-title">
          Detalle de Créditos
        </span>
        <VChip
          color="primary"
          size="x-small"
          variant="tonal"
          density="compact"
        >
          Crédito
        </VChip>
        <VSpacer />
        <VBtn icon variant="text" size="x-small" @click="closeModal">
          <VIcon size="18">tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="px-4 pb-4 pt-3">
        <!-- Cajero | Cliente (en créditos no hay cajero de la venta original) -->
        <div class="order-view-data mb-4">
          <div class="data-block-unified rounded pa-3 d-flex">
            <div class="data-half flex-grow-1">
              <span class="data-label d-block">Cliente</span>
              <span class="data-value">
                {{ clientInfo?.name || "" }}
                {{ clientInfo?.last_name || "" }}
              </span>
            </div>
            <div class="data-divider" />
            <div class="data-half flex-grow-1">
              <span class="data-label d-block">Documento</span>
              <span class="data-value">
                {{ clientInfo?.identification_type || "" }}
                {{ clientInfo?.identification || "—" }}
              </span>
            </div>
          </div>
        </div>

        <!-- Órdenes agrupadas (últimas 10 por página) -->
        <div
          v-for="(credit, idx) in paginatedCredits"
          :key="credit.id || credit.order?.id || idx"
          class="mb-4"
        >
          <div class="order-block rounded overflow-hidden">
            <div
              class="order-block-header d-flex align-center flex-wrap gap-2 py-2 px-3"
            >
              <span class="text-subtitle-2 font-weight-bold section-title">
                Orden #{{ credit.order?.id ?? "—" }}
              </span>
              <VChip
                size="x-small"
                variant="tonal"
                :color="getCurrencyChipColor(credit.order?.currency)"
                density="compact"
              >
                {{ credit.order?.currency || "USD" }}
              </VChip>
              <span class="header-date text-caption">
                {{
                  formatDateTime(
                    credit.order?.created_at || credit.credit_date,
                    "datetime"
                  )
                }}
              </span>
            </div>

            <!-- Tabla de productos -->
            <div class="order-view-products">
              <div class="products-table-wrapper rounded overflow-hidden">
                <table class="products-table">
                  <thead>
                    <tr>
                      <th>Producto</th>
                      <th class="text-end">Unit.</th>
                      <th class="quantity-col">Cant.</th>
                      <th class="text-end">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(detail, dIdx) in credit.order?.details || []"
                      :key="detail.id || detail.product_id || dIdx"
                      class="products-table-row"
                    >
                      <td class="product-cell">
                        <span class="product-line-full">
                          {{ productLineLabel(detail) }}
                        </span>
                      </td>
                      <td class="text-end table-amount">
                        {{
                          formatAmountOnly(
                            getItemPriceByCurrency(
                              detail,
                              credit.order?.currency
                            ),
                            credit.order?.currency
                          )
                        }}
                      </td>
                      <td class="quantity-cell">{{ detail.quantity }}</td>
                      <td class="text-end table-amount font-weight-medium">
                        {{
                          formatAmountOnly(
                            getLineTotal(detail),
                            credit.order?.currency
                          )
                        }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div v-if="totalPagesOrders > 1" class="d-flex justify-center align-center gap-2 my-3">
          <VBtn
            icon
            variant="tonal"
            size="small"
            :disabled="pageOrders <= 1"
            @click="prevPageOrders"
          >
            <VIcon icon="tabler-chevron-left" />
          </VBtn>
          <span class="text-caption">Página {{ pageOrders }} de {{ totalPagesOrders }}</span>
          <VBtn
            icon
            variant="tonal"
            size="small"
            :disabled="pageOrders >= totalPagesOrders"
            @click="nextPageOrders"
          >
            <VIcon icon="tabler-chevron-right" />
          </VBtn>
        </div>

        <!-- Totales globales -->
        <div class="order-view-summary rounded pa-3 mb-4">
          <div class="summary-row">
            <span class="summary-label">Total Crédito</span>
            <span class="summary-value">
              {{ formatCurrency(totalCredits, selectedCurrency) }}
            </span>
          </div>
          <div class="summary-row">
            <span class="summary-label">Total Pendiente</span>
            <span class="summary-value font-weight-bold text-error">
              {{ formatCurrency(totalPendingAmount, selectedCurrency) }}
            </span>
          </div>
        </div>

        <VDivider class="my-4" />

        <!-- Historial de Pagos -->
        <div class="mb-2">
          <div class="d-flex justify-space-between align-center mb-3">
            <span class="font-weight-bold text-subtitle-1 section-label">
              Historial de Pagos
            </span>
            <VChip color="success" size="small" variant="tonal">
              Total Pagado: {{ formatCurrency(totalPaidUSD, "USD") }}
            </VChip>
          </div>

          <VRow class="mb-3">
            <VCol cols="12" md="4">
              <VTextField
                v-model="filterClient"
                label="Buscar Vendedor"
                density="compact"
                clearable
                prepend-inner-icon="tabler-search"
                hide-details
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model="filterDate"
                label="Fecha"
                type="date"
                density="compact"
                clearable
                hide-details
              />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="filterCurrency"
                :items="['USD', 'COP', 'BS']"
                label="Moneda"
                density="compact"
                clearable
                hide-details
              />
            </VCol>
          </VRow>

          <div class="products-table-wrapper rounded overflow-hidden">
            <table class="products-table">
              <thead>
                <tr>
                  <th v-for="h in paymentHeaders" :key="h.key">
                    {{ h.title }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, rIdx) in paginatedPaymentRows"
                  :key="rIdx"
                  class="products-table-row"
                >
                  <td>
                    {{ row.date ? formatDateTime(row.date, "date") : "N/A" }}
                  </td>
                  <td class="font-weight-medium">
                    {{ formatPaymentAmount(row.amount, row.currency) }}
                  </td>
                  <td>
                    <VChip
                      size="x-small"
                      :color="
                        row.currency === 'USD'
                          ? 'success'
                          : row.currency === 'COP'
                            ? 'info'
                            : 'primary'
                      "
                    >
                      {{ row.currency }}
                    </VChip>
                  </td>
                  <td>{{ translateMethod(row.method) }}</td>
                  <td>{{ row.seller || "N/A" }}</td>
                </tr>
                <tr v-if="filteredPaymentRows.length === 0">
                  <td :colspan="paymentHeaders.length" class="text-center py-4">
                    No hay pagos registrados
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="totalPagesPayments > 1" class="d-flex justify-center align-center gap-2 mt-3">
            <VBtn
              icon
              variant="tonal"
              size="small"
              :disabled="pagePayments <= 1"
              @click="prevPagePayments"
            >
              <VIcon icon="tabler-chevron-left" />
            </VBtn>
            <span class="text-caption">Página {{ pagePayments }} de {{ totalPagesPayments }}</span>
            <VBtn
              icon
              variant="tonal"
              size="small"
              :disabled="pagePayments >= totalPagesPayments"
              @click="nextPagePayments"
            >
              <VIcon icon="tabler-chevron-right" />
            </VBtn>
          </div>
        </div>
      </VCardText>

      <VDivider />
      <VCardActions class="pa-3">
        <VBtn color="secondary" variant="outlined" block @click="closeModal">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.order-view-dialog :deep(.v-overlay__content) {
  align-items: flex-start;
  padding-block: 0.75rem;
  padding-inline: 0;
}

.order-view-card {
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 2px 12px rgba(0, 0, 0, 8%);
}

.order-view-card :deep(.v-chip:not(.v-chip--pill).v-chip--size-x-small) {
  --v-chip-height: 30px;

  font-size: 13px;
  min-block-size: 30px;
  padding-block: 4px;
  padding-inline: 10px;
}

.order-view-card :deep(.v-chip__underlay) {
  border-radius: 6px;
  margin: 2px;
}

.order-view-header {
  background: rgba(var(--v-theme-primary), 0.08);
  border-block-end: none;
}

.section-title {
  color: rgb(var(--v-theme-primary));
}

.section-label {
  color: rgb(var(--v-theme-primary));
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.data-label {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.8125rem;
  font-weight: 500;
  margin-block-end: 2px;
}

.data-value {
  color: rgba(var(--v-theme-on-surface), 0.92);
  font-size: 0.9375rem;
  font-weight: 500;
}

.data-block-unified {
  background: rgba(var(--v-theme-primary), 0.06);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 5%);
}

.data-half {
  min-inline-size: 0;
  padding-block: 0;
  padding-inline: 12px;
}

.data-divider {
  flex-shrink: 0;
  background: rgba(var(--v-theme-primary), 0.18);
  inline-size: 1px;
}

.order-block {
  background: rgba(var(--v-theme-primary), 0.04);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 5%);
}

.order-block-header {
  background: rgba(var(--v-theme-primary), 0.1);
}

.products-table-wrapper {
  background: rgba(var(--v-theme-primary), 0.04);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 5%);
  max-block-size: 280px;
  overflow-y: auto;
}

.products-table {
  border-collapse: collapse;
  font-size: 0.8125rem;
  inline-size: 100%;
}

.products-table th {
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1.2;
  padding-block: 4px;
  padding-inline: 8px;
  text-align: start;
}

.products-table th.text-end {
  text-align: end;
}

.products-table td {
  border-block-end:
 1px solid
    rgba(var(--v-border-color), var(--v-border-opacity, 0.12));
  line-height: 1.25;
  padding-block: 4px;
  padding-inline: 8px;
  vertical-align: top;
}

.products-table-row:nth-child(even) {
  background: rgba(var(--v-theme-primary), 0.04);
}

.products-table-row:last-child td {
  border-block-end: none;
}

.quantity-col {
  inline-size: 52px;
}

.quantity-cell {
  inline-size: 52px;
  text-align: center;
}

.product-cell {
  min-inline-size: 0;
}

.product-line-full {
  color: rgba(var(--v-theme-on-surface), 0.92);
  font-size: 0.8125rem;
  font-weight: 500;
  word-break: break-word;
}

.table-amount {
  color: rgba(var(--v-theme-on-surface), 0.9);
  font-size: 0.8125rem;
}

.order-view-summary {
  background: rgba(var(--v-theme-primary), 0.08);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 5%);
}

.summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-block-size: 1.5rem;
  padding-block: 4px;
  padding-inline: 0;
}

.summary-label {
  color: rgba(var(--v-theme-on-surface), 0.78);
  font-size: 0.8125rem;
}

.summary-value {
  color: rgba(var(--v-theme-on-surface), 0.92);
  font-size: 0.8125rem;
  font-weight: 500;
}

.summary-divider {
  margin-block: 6px !important;
  margin-inline: 0 !important;
}

.total-row {
  padding-block-start: 2px;
}

.total-label {
  color: rgb(var(--v-theme-primary));
  font-size: 0.9375rem;
  font-weight: 700;
}

.total-amount {
  color: rgb(var(--v-theme-primary));
  font-size: 1.125rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.header-date {
  color: rgba(var(--v-theme-on-surface), 0.75);
  font-size: 0.8125rem;
}

/* Dark mode */
.v-theme--dark .order-view-card {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 35%);
}

.v-theme--dark .order-view-header {
  background: rgba(255, 255, 255, 6%);
}

.v-theme--dark .section-title,
.v-theme--dark .section-label,
.v-theme--dark .header-date {
  color: rgba(255, 255, 255, 90%);
}

.v-theme--dark .data-label {
  color: rgba(255, 255, 255, 60%);
}

.v-theme--dark .data-value {
  color: rgba(255, 255, 255, 92%);
}

.v-theme--dark .data-block-unified {
  background: rgba(255, 255, 255, 6%);
}

.v-theme--dark .data-divider {
  background: rgba(255, 255, 255, 12%);
}

.v-theme--dark .order-block {
  background: rgba(255, 255, 255, 5%);
}

.v-theme--dark .order-block-header {
  background: rgba(255, 255, 255, 8%);
}

.v-theme--dark .products-table-wrapper {
  background: rgba(255, 255, 255, 5%);
}

.v-theme--dark .products-table th {
  background: rgba(255, 255, 255, 8%);
  color: rgba(255, 255, 255, 90%);
}

.v-theme--dark .product-line-full {
  color: rgba(255, 255, 255, 92%);
}

.v-theme--dark .table-amount {
  color: rgba(255, 255, 255, 90%);
}

.v-theme--dark .products-table-row:nth-child(even) {
  background: rgba(255, 255, 255, 3%);
}

.v-theme--dark .order-view-summary {
  background: rgba(255, 255, 255, 7%);
}

.v-theme--dark .summary-label {
  color: rgba(255, 255, 255, 70%);
}

.v-theme--dark .summary-value {
  color: rgba(255, 255, 255, 92%);
}

.v-theme--dark .total-label,
.v-theme--dark .total-amount {
  color: rgba(255, 255, 255, 100%);
}
</style>
