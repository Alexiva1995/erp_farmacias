<template>
  <div class="payment-history-page pb-12">
    <div class="d-flex flex-column gap-3 mt-1">
      <!-- Tarjetas KPI Gerenciales -->
      <VRow>
        <VCol cols="12" sm="6" md="3">
          <VCard class="pa-4 rounded-lg border-0 shadow-sm bg-surface">
            <div class="d-flex align-center gap-3">
              <VAvatar size="44" color="primary" variant="tonal" class="rounded-lg">
                <VIcon icon="tabler-currency-dollar" size="24" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Total Pagado (USD)</span>
                <span class="text-h6 font-weight-black text-primary">USD {{ formatNumber(summaryStats.total_usd) }}</span>
              </div>
            </div>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard class="pa-4 rounded-lg border-0 shadow-sm bg-surface">
            <div class="d-flex align-center gap-3">
              <VAvatar size="44" color="warning" variant="tonal" class="rounded-lg">
                <VIcon icon="tabler-coin" size="24" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Total Pagado (Bs.)</span>
                <span class="text-h6 font-weight-black text-warning">{{ formatNumber(summaryStats.total_ves) }} Bs.</span>
              </div>
            </div>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard class="pa-4 rounded-lg border-0 shadow-sm bg-surface">
            <div class="d-flex align-center gap-3">
              <VAvatar size="44" color="info" variant="tonal" class="rounded-lg">
                <VIcon icon="tabler-receipt-2" size="24" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Transacciones</span>
                <span class="text-h6 font-weight-black text-info">{{ summaryStats.total_transactions }} Pagos</span>
              </div>
            </div>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard class="pa-4 rounded-lg border-0 shadow-sm bg-surface">
            <div class="d-flex align-center gap-3">
              <VAvatar size="44" color="success" variant="tonal" class="rounded-lg">
                <VIcon icon="tabler-chart-bar" size="24" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Promedio por Pago</span>
                <span class="text-h6 font-weight-black text-success">USD {{ formatNumber(summaryStats.average_usd) }}</span>
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Filtros Premium -->
      <PaymentHistoryFilters
        v-model:search-query="searchQuery"
        v-model:selected-supplier="selectedSupplier"
        v-model:selected-currency="selectedCurrency"
        v-model:selected-payment-method="selectedPaymentMethod"
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        :suppliers="suppliers"
        :loading="loading"
        class="mb-0"
        @clear="clearFilters"
        @refresh="fetchPaymentHistory"
        @export="exportToExcel"
      />

      <!-- Tabla Desktop -->
      <div v-if="!$vuetify.display.smAndDown">
        <VCard class="ma-0 rounded-lg border shadow-sm overflow-hidden bg-surface">
          <VDataTableServer
            v-model:items-per-page="itemsPerPage"
            :headers="headers"
            :items="payments"
            :items-length="totalPayments"
            :loading="loading"
            :page="page"
            class="premium-table text-no-wrap"
            @update:options="updateOptions"
          >
            <!-- Fecha -->
            <template #item.payment_date="{ item }">
              <span class="text-xs text-medium-emphasis">{{ formatDate(item.payment_date) }}</span>
            </template>

            <!-- Proveedor -->
            <template #item.supplier="{ item }">
              <span class="text-sm font-weight-black supplier-title">
                {{ item.invoices?.[0]?.supplier?.name || "N/A" }}
              </span>
            </template>

            <!-- Egreso de Caja (Monto Pagado Real) -->
            <template #item.amount="{ item }">
              <div class="d-flex flex-column text-end align-end py-1">
                <span class="text-sm font-weight-black text-high-emphasis">
                  {{ formatCurrency(item.amount, item.currency) }}
                </span>
                <span
                  v-if="normalizeCurrencyCode(item.currency) !== 'USD' && item.amount_usd"
                  class="text-xs text-success font-weight-bold"
                >
                  {{ formatNumber(item.amount_usd) }} USD
                </span>
              </div>
            </template>

            <!-- Liquidado al Proveedor (Monto de Facturas) -->
            <template #item.billed_amount="{ item }">
              <div class="d-flex flex-column text-end align-end py-1">
                <span class="text-sm font-weight-black text-high-emphasis">
                  {{ formatCurrency(getInvoicesBilledTotal(item), getInvoicesBilledCurrency(item)) }}
                </span>
                <span
                  v-if="item.invoice_total_usd"
                  class="text-xs text-success font-weight-bold"
                >
                  {{ formatNumber(item.invoice_total_usd) }} USD
                </span>
              </div>
            </template>

            <!-- Referencia -->
            <template #item.reference="{ item }">
              <span
                v-if="item.reference"
                class="text-sm font-weight-bold text-primary"
              >
                {{ item.reference }}
              </span>
              <span v-else class="text-disabled text-xs font-italic">Sin referencia</span>
            </template>

            <!-- Usuario Registro -->
            <template #item.user="{ item }">
              <span class="text-sm font-weight-medium text-high-emphasis">{{ item.user?.name || "Sistema" }}</span>
            </template>

            <!-- Acciones -->
            <template #item.actions="{ item }">
              <div class="d-flex gap-1 justify-center align-center">
                <IconBtn
                  size="small"
                  color="primary"
                  @click="viewPaymentDetails(item)"
                >
                  <VIcon icon="tabler-eye" size="18" />
                  <VTooltip activator="parent">Ver Detalles</VTooltip>
                </IconBtn>
                <IconBtn
                  v-if="item.photo_url"
                  size="small"
                  color="success"
                  @click="viewReceipt(item.photo_url)"
                >
                  <VIcon icon="tabler-file-dollar" size="18" />
                  <VTooltip activator="parent">Ver Comprobante</VTooltip>
                </IconBtn>
              </div>
            </template>
          </VDataTableServer>
        </VCard>
      </div>

      <!-- Vista Móvil: Cards -->
      <div v-else class="d-flex flex-column gap-4">
        <template v-if="loading">
          <VSkeletonLoader
            v-for="n in 3"
            :key="n"
            type="card-avatar, actions"
            class="rounded-lg border shadow-sm"
          />
        </template>
        <template v-else-if="payments.length === 0">
          <VCard class="rounded-lg border shadow-sm pa-8 text-center bg-surface">
            <VIcon icon="tabler-receipt-off" size="48" color="disabled" class="mb-2" />
            <h4 class="text-h6 font-weight-black text-medium-emphasis">No se encontraron pagos</h4>
            <p class="text-xs text-disabled">Prueba cambiando los filtros de búsqueda</p>
          </VCard>
        </template>
        <template v-else>
          <VCard
            v-for="item in payments"
            :key="item.id"
            class="rounded-lg border shadow-sm overflow-hidden"
          >
            <div class="pa-4 bg-surface-variant-light d-flex align-center gap-3">
              <VAvatar size="48" color="primary" variant="tonal" class="rounded-lg shadow-sm">
                <VIcon icon="tabler-receipt" size="24" />
              </VAvatar>
              <div class="d-flex flex-column flex-grow-1">
                <span class="text-base font-weight-bold leading-tight truncate supplier-title" style="max-inline-size: 180px">
                  {{ item.invoices?.[0]?.supplier?.name || "Proveedor N/A" }}
                </span>
                <span class="text-xs text-medium-emphasis font-weight-medium">{{ formatDate(item.payment_date) }}</span>
              </div>
              <VChip
                :color="getCurrencyColor(item.currency)"
                variant="elevated"
                class="font-weight-black px-4 rounded-lg shadow-sm"
              >
                {{ normalizeCurrencyCode(item.currency) }}
              </VChip>
            </div>

            <VDivider class="opacity-10" />

            <div class="pa-4 pt-4">
              <div class="d-flex justify-space-between align-center mb-4">
                <div class="d-flex flex-column">
                  <span class="text-super-xs text-disabled font-weight-black uppercase">Egreso de Caja</span>
                  <span class="text-lg font-weight-black text-primary">
                    {{ formatCurrency(item.amount, item.currency) }}
                  </span>
                  <span v-if="normalizeCurrencyCode(item.currency) !== 'USD' && item.amount_usd" class="text-xs font-weight-bold text-success">
                    {{ formatNumber(item.amount_usd) }} USD
                  </span>
                </div>
                <div class="text-right d-flex flex-column">
                  <span class="text-super-xs text-disabled font-weight-black uppercase">Liquidado</span>
                  <span class="text-lg font-weight-black text-high-emphasis">
                    {{ formatCurrency(getInvoicesBilledTotal(item), getInvoicesBilledCurrency(item)) }}
                  </span>
                  <span v-if="item.invoice_total_usd" class="text-xs text-success font-weight-bold">
                    {{ formatNumber(item.invoice_total_usd) }} USD
                  </span>
                </div>
              </div>

              <div class="d-flex gap-2">
                <VBtn
                  block
                  variant="tonal"
                  color="primary"
                  class="rounded-lg font-weight-black flex-grow-1"
                  prepend-icon="tabler-eye"
                  @click="viewPaymentDetails(item)"
                >
                  Detalles
                </VBtn>
                <VBtn
                  v-if="item.photo_url"
                  variant="tonal"
                  color="success"
                  class="rounded-lg px-4"
                  @click="viewReceipt(item.photo_url)"
                >
                  <VIcon icon="tabler-file-dollar" />
                </VBtn>
              </div>
            </div>
          </VCard>

          <VCard class="rounded-lg border shadow-sm pa-3 d-flex justify-center align-center bg-surface">
            <VPagination
              v-model="page"
              :length="Math.ceil(totalPayments / itemsPerPage)"
              total-visible="3"
              density="comfortable"
              active-color="primary"
              @update:model-value="fetchPaymentHistory"
            />
          </VCard>
        </template>
      </div>

      <!-- Modales Desacoplados -->
      <PaymentDetailModal
        v-model="showPaymentModal"
        :payment="selectedPayment"
        @payment-resent="fetchPaymentHistory"
      />
      <ReceiptModal v-model="showReceiptModal" :receipt-url="receiptUrl" />
      
      <!-- Snackbar de error/UX -->
      <VSnackbar v-model="showErrorSnackbar" color="error" location="top end">
        {{ errorMessage }}
      </VSnackbar>

      <VSnackbar v-model="showSuccessSnackbar" color="success" location="top end">
        {{ successMessage }}
      </VSnackbar>
    </div>
  </div>
</template>

<script setup>
import PaymentDetailModal from "@/components/PaymentDetailModal.vue";
import PaymentHistoryFilters from "@/components/PaymentHistoryFilters.vue";
import ReceiptModal from "@/components/ReceiptModal.vue";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";

// Estado de UI
const loading = ref(false);
const showErrorSnackbar = ref(false);
const showSuccessSnackbar = ref(false);
const errorMessage = ref("");
const successMessage = ref("");

// Datos y KPIs
const payments = ref([]);
const suppliers = ref([]);
const summaryStats = ref({
  total_transactions: 0,
  total_usd: 0,
  total_ves: 0,
  average_usd: 0,
});

const totalPayments = ref(0);
const page = ref(1);
const itemsPerPage = ref(15);
const sortBy = ref();
const orderBy = ref();

// Filtros
const searchQuery = ref("");
const selectedSupplier = ref(null);
const selectedCurrency = ref(null);
const selectedPaymentMethod = ref(null);
const startDate = ref(null);
const endDate = ref(null);

// Modales
const showPaymentModal = ref(false);
const showReceiptModal = ref(false);
const selectedPayment = ref(null);
const receiptUrl = ref("");

// Headers
const headers = [
  { title: "Fecha", key: "payment_date", sortable: true, align: "start" },
  { title: "Proveedor", key: "supplier", sortable: false, align: "start" },
  { title: "Egreso de Caja", key: "amount", sortable: true, align: "end" },
  { title: "Liquidado al Proveedor", key: "billed_amount", sortable: false, align: "end" },
  { title: "Referencia", key: "reference", sortable: true, align: "start" },
  { title: "Registrado por", key: "user", sortable: false, align: "start" },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

// Fetching
const fetchPaymentHistory = async () => {
  loading.value = true;
  try {
    const params = {
      search: searchQuery.value,
      supplier_id: selectedSupplier.value,
      currency: selectedCurrency.value,
      payment_method: selectedPaymentMethod.value,
      start_date: startDate.value,
      end_date: endDate.value,
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
    };

    Object.keys(params).forEach(
      (key) => (params[key] === null || params[key] === "") && delete params[key]
    );

    const response = await axios.get("/finances/payment-history", { params });

    if (response.data.status === "success" || response.data.success) {
      payments.value = response.data.data.data || [];
      totalPayments.value = response.data.data.total || 0;
      if (response.data.data.summary_stats) {
        summaryStats.value = response.data.data.summary_stats;
      }
    }
  } catch (error) {
    errorMessage.value = "Error al obtener el historial de pagos de la API";
    showErrorSnackbar.value = true;
  } finally {
    loading.value = false;
  }
};

const fetchSuppliers = async () => {
  try {
    const response = await axios.get("/finances/pending-payments/suppliers");
    if (response.data.status === "success" || response.data.success) {
      suppliers.value = response.data.data || [];
    }
  } catch (error) {
    console.error("Error al cargar proveedores:", error);
  }
};

// Exportación a Excel/CSV
const exportToExcel = () => {
  if (payments.value.length === 0) {
    errorMessage.value = "No hay datos para exportar";
    showErrorSnackbar.value = true;
    return;
  }

  const csvRows = [
    ["ID", "Fecha", "Proveedor", "Monto", "Moneda", "Monto USD", "Referencia", "Registrado Por"],
  ];

  payments.value.forEach((p) => {
    csvRows.push([
      p.id,
      p.payment_date || "",
      `"${p.invoices?.[0]?.supplier?.name || 'N/A'}"`,
      p.amount,
      p.currency || "",
      p.amount_usd || 0,
      `"${p.reference || ''}"`,
      `"${p.user?.name || 'Sistema'}"`,
    ]);
  });

  const csvContent = "data:text/csv;charset=utf-8," + csvRows.map((e) => e.join(",")).join("\n");
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `Historial_Pagos_${new Date().toISOString().slice(0,10)}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  successMessage.value = "Reporte exportado en formato CSV exitosamente";
  showSuccessSnackbar.value = true;
};

// Handlers
let debouncer;
const handleSearch = () => {
  clearTimeout(debouncer);
  debouncer = setTimeout(() => {
    page.value = 1;
    fetchPaymentHistory();
  }, 500);
};

const updateOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
  fetchPaymentHistory();
};

const clearFilters = () => {
  searchQuery.value = "";
  selectedSupplier.value = null;
  selectedCurrency.value = null;
  selectedPaymentMethod.value = null;
  startDate.value = null;
  endDate.value = null;
  page.value = 1;
  fetchPaymentHistory();
};

const viewPaymentDetails = (payment) => {
  selectedPayment.value = payment;
  showPaymentModal.value = true;
};

const viewReceipt = (url) => {
  receiptUrl.value = url;
  showReceiptModal.value = true;
};

// Watchers
watch([selectedSupplier, selectedCurrency, selectedPaymentMethod, startDate, endDate], () => {
  page.value = 1;
  fetchPaymentHistory();
});

watch(searchQuery, () => {
  handleSearch();
});

// Formateadores
const formatDate = (date) => (date ? new Date(date).toLocaleDateString("es-ES") : "N/A");

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

const getInvoicesBilledTotal = (item) => {
  if (!item?.invoices?.length) return 0;
  return item.invoices.reduce((acc, inv) => acc + (parseFloat(inv.total_amount) || 0), 0);
};

const getInvoicesBilledCurrency = (item) => {
  if (!item?.invoices?.length) return "Bs.";
  return item.invoices[0]?.currency || "Bs.";
};

const getUserInitials = (name) => {
  if (!name) return "S";
  return name
    .split(" ")
    .map((w) => w[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
};

const getCurrencyColor = (currency) => {
  const norm = normalizeCurrencyCode(currency);
  if (norm === "Bs." || norm === "VES" || norm === "BS") return "warning";
  if (norm === "USD") return "success";
  if (norm === "COP") return "info";
  return "secondary";
};

onMounted(() => {
  fetchPaymentHistory();
  fetchSuppliers();
});
</script>

<style scoped>
.payment-history-page {
  background-color: rgb(var(--v-theme-background));
  min-block-size: 100vh;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.04);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

:deep(.premium-table) {
  .v-data-table-header th {
    background: rgb(var(--v-theme-surface)) !important;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    text-transform: none !important;
    letter-spacing: 0.02rem !important;
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
    padding-inline: 12px !important;
  }

  .v-data-table__td {
    padding-block: 8px !important;
    padding-inline: 12px !important;
    font-size: 0.8125rem !important;
    border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  }

  tbody tr:hover {
    background-color: rgba(var(--v-theme-primary), 0.02) !important;
  }
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.supplier-title {
  color: #111827 !important;
  font-weight: 600 !important;
}
</style>
