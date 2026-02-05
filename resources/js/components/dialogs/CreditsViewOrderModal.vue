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
  const clientId = clientInfo.value?.id;
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
  let rows = [];
  payments.value.forEach((cp) => {
    const pay = cp.payments;
    if (pay) {
      rows.push({
        date: cp.payment_date,
        amount: pay.amount,
        currency: pay.currency,
        method: pay.method,
        reference: pay.reference,
        seller: pay.seller ?? cp.seller?.username ?? "N/A",
      });
    }
  });
  return rows;
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

const paymentHeaders = [
  { title: "Fecha", key: "date", sortable: false },
  { title: "Monto", key: "amount", sortable: false },
  { title: "Moneda", key: "currency", sortable: false },
  { title: "Método", key: "method", sortable: false },
  { title: "Vendedor", key: "seller", sortable: false },
];

watch(
  () => props.isDialogVisible,
  (newVal) => {
    if (newVal) {
      fetchPayments();
    }
  }
);
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

        <!-- Órdenes agrupadas (cada una como OrderViewModal) -->
        <div
          v-for="(credit, idx) in sortedCredits"
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

            <!-- Resumen por orden -->
            <div class="order-view-summary rounded pa-3 mt-2">
              <div class="summary-row">
                <span class="summary-label">Crédito (Orden)</span>
                <span class="summary-value">
                  {{ formatCurrency(credit.credit_amount || 0, credit.order?.currency || "USD") }}
                </span>
              </div>
              <div class="summary-row">
                <span class="summary-label">Pendiente</span>
                <span class="summary-value text-error">
                  {{ formatCurrency(credit.pending_amount || 0, credit.order?.currency || "USD") }}
                </span>
              </div>
              <VDivider class="summary-divider" />
              <div class="summary-row total-row">
                <span class="total-label">Total Orden</span>
                <span class="total-amount">
                  {{ formatCurrency(credit.order?.total_amount || 0, credit.order?.currency || "USD") }}
                </span>
              </div>
            </div>
          </div>
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
              Total Pagado: {{ formatCurrency(totalPaid, "USD") }}
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
                  v-for="(row, rIdx) in filteredPaymentRows"
                  :key="rIdx"
                  class="products-table-row"
                >
                  <td>
                    {{ row.date ? String(row.date).split(" ")[0] : "N/A" }}
                  </td>
                  <td class="font-weight-medium">
                    {{ parseFloat(row.amount || 0).toFixed(2) }}
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
        </div>
      </VCardText>

      <VDivider />
      <VCardActions class="pa-3">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="closeModal">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.order-view-dialog :deep(.v-overlay__content) {
  align-items: flex-start;
  padding: 0.75rem 0;
}
.order-view-card {
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  background: rgb(var(--v-theme-surface));
}
.order-view-card :deep(.v-chip:not(.v-chip--pill).v-chip--size-x-small) {
  --v-chip-height: 30px;
  min-height: 30px;
  font-size: 13px;
  padding: 4px 10px;
}
.order-view-card :deep(.v-chip__underlay) {
  border-radius: 6px;
  margin: 2px;
}
.order-view-header {
  border-bottom: none;
  background: rgba(var(--v-theme-primary), 0.08);
}
.section-title {
  color: rgb(var(--v-theme-primary));
}
.section-label {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  font-size: 0.75rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}
.data-label {
  font-size: 0.8125rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.65);
  margin-bottom: 2px;
}
.data-value {
  font-size: 0.9375rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.92);
}
.data-block-unified {
  background: rgba(var(--v-theme-primary), 0.06);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.data-half {
  min-width: 0;
  padding: 0 12px;
}
.data-divider {
  width: 1px;
  background: rgba(var(--v-theme-primary), 0.18);
  flex-shrink: 0;
}
.order-block {
  background: rgba(var(--v-theme-primary), 0.04);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.order-block-header {
  background: rgba(var(--v-theme-primary), 0.1);
}
.products-table-wrapper {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  background: rgba(var(--v-theme-primary), 0.04);
  max-height: 280px;
  overflow-y: auto;
}
.products-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}
.products-table th {
  text-align: left;
  padding: 4px 8px;
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.1);
  font-size: 0.6875rem;
  line-height: 1.2;
}
.products-table th.text-end {
  text-align: right;
}
.products-table td {
  padding: 4px 8px;
  border-bottom: 1px solid
    rgba(var(--v-border-color), var(--v-border-opacity, 0.12));
  vertical-align: top;
  line-height: 1.25;
}
.products-table-row:nth-child(even) {
  background: rgba(var(--v-theme-primary), 0.04);
}
.products-table-row:last-child td {
  border-bottom: none;
}
.quantity-col {
  width: 52px;
}
.quantity-cell {
  width: 52px;
  text-align: center;
}
.product-cell {
  min-width: 0;
}
.product-line-full {
  font-size: 0.8125rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.92);
  word-break: break-word;
}
.table-amount {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.9);
}
.order-view-summary {
  background: rgba(var(--v-theme-primary), 0.08);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
  min-height: 1.5rem;
}
.summary-label {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.78);
}
.summary-value {
  font-size: 0.8125rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.92);
}
.summary-divider {
  margin: 6px 0 !important;
}
.total-row {
  padding-top: 2px;
}
.total-label {
  font-size: 0.9375rem;
  font-weight: 700;
  color: rgb(var(--v-theme-primary));
}
.total-amount {
  font-size: 1.125rem;
  font-weight: 700;
  color: rgb(var(--v-theme-primary));
  letter-spacing: 0.02em;
}
.header-date {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.75);
}
/* Dark mode */
.v-theme--dark .order-view-card {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
}
.v-theme--dark .order-view-header {
  background: rgba(255, 255, 255, 0.06);
}
.v-theme--dark .section-title,
.v-theme--dark .section-label,
.v-theme--dark .header-date {
  color: rgba(255, 255, 255, 0.9);
}
.v-theme--dark .data-label {
  color: rgba(255, 255, 255, 0.6);
}
.v-theme--dark .data-value {
  color: rgba(255, 255, 255, 0.92);
}
.v-theme--dark .data-block-unified {
  background: rgba(255, 255, 255, 0.06);
}
.v-theme--dark .data-divider {
  background: rgba(255, 255, 255, 0.12);
}
.v-theme--dark .order-block {
  background: rgba(255, 255, 255, 0.05);
}
.v-theme--dark .order-block-header {
  background: rgba(255, 255, 255, 0.08);
}
.v-theme--dark .products-table-wrapper {
  background: rgba(255, 255, 255, 0.05);
}
.v-theme--dark .products-table th {
  color: rgba(255, 255, 255, 0.9);
  background: rgba(255, 255, 255, 0.08);
}
.v-theme--dark .product-line-full {
  color: rgba(255, 255, 255, 0.92);
}
.v-theme--dark .table-amount {
  color: rgba(255, 255, 255, 0.9);
}
.v-theme--dark .products-table-row:nth-child(even) {
  background: rgba(255, 255, 255, 0.03);
}
.v-theme--dark .order-view-summary {
  background: rgba(255, 255, 255, 0.07);
}
.v-theme--dark .summary-label {
  color: rgba(255, 255, 255, 0.7);
}
.v-theme--dark .summary-value {
  color: rgba(255, 255, 255, 0.92);
}
.v-theme--dark .total-label,
.v-theme--dark .total-amount {
  color: rgba(255, 255, 255, 1);
}
</style>
