<script setup>
import OrderViewModal from "@/components/dialogs/OrderViewModal.vue";
import IncomeStatementFilters from "@/components/IncomeStatementFilters.vue";
import IncomeStatementMobileList from "@/components/IncomeStatementMobileList.vue";
import IncomeStatementSummaryCards from "@/components/IncomeStatementSummaryCards.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

// Helpers para fechas por defecto (Mes actual)
const getCurrentMonthStart = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
};

const getToday = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

// Estado reactivo principal
const loadingSummary = ref(false);
const loadingDetails = ref(false);

const summary = ref({});
const transactions = ref([]);
const pageTotals = ref({});
const totalItems = ref(0);
const itemsPerPage = ref(50);
const page = ref(1);

const startDate = ref(getCurrentMonthStart());
const endDate = ref(getToday());
const searchQuery = ref("");
const selectedType = ref(null);

// Diálogo de Ver Orden
const viewModal = ref(false);
const orderData = ref(null);
const orderItems = ref([]);
const paymentsForPrint = ref([]);
const changeAmountForPrint = ref(0);
const amountForPrint = ref(0);
const creditAmountForPrint = ref(0);
const creditForPrint = ref(false);
const selectedOrderCurrency = ref("USD");

const openOrderModal = async (orderId) => {
  if (!orderId) return;
  try {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    if (response.data?.data?.order) {
      const order = response.data.data.order;
      orderData.value = order;
      selectedOrderCurrency.value = order.currency ? order.currency.toUpperCase() : "USD";
      orderItems.value = (order.details || []).map((detail) => ({
        title: detail.product?.name || detail.dish?.name || 'Producto',
        selectedQuantity: detail.quantity,
        taxRate: 0,
        unit_price: detail.quantity > 0 ? parseFloat(detail.price) / detail.quantity : parseFloat(detail.price),
        price_bs: parseFloat(detail.price),
        price_cop: parseFloat(detail.price),
        price: parseFloat(detail.price),
        price_before_discount: parseFloat(detail.price_before_discount || detail.price),
      }));
      paymentsForPrint.value = order.payment_methods || [];
      changeAmountForPrint.value = parseFloat(order.money_returns || 0);
      amountForPrint.value = parseFloat(order.total_amount || 0);
      creditAmountForPrint.value = response.data.data.hasCreditPayment
        ? parseFloat(order.total_amount || 0)
        : 0;
      creditForPrint.value = !!response.data.data.hasCreditPayment;
      viewModal.value = true;
    }
  } catch (error) {
    console.error("Error al obtener los detalles de la orden:", error);
    toast.error("Error al abrir los detalles de la orden");
  }
};

const handleCloseViewModal = () => {
  viewModal.value = false;
  orderData.value = null;
  orderItems.value = [];
  paymentsForPrint.value = [];
  changeAmountForPrint.value = 0;
  amountForPrint.value = 0;
  creditAmountForPrint.value = 0;
  creditForPrint.value = false;
};

const headers = [
  { title: "FECHA", key: "date", sortable: true, width: "110px" },
  { title: "COMPROBANTE", key: "voucher_number", sortable: false, width: "140px" },
  { title: "TIPO", key: "type", sortable: false, align: "center", width: "95px" },
  { title: "DESCRIPCIÓN", key: "description", sortable: true },
  { title: "CLIENTE / CANAL", key: "client", sortable: true },
  { title: "VENTA", key: "amount", sortable: true, align: "end", width: "135px" },
  { title: "COSTO", key: "costs", sortable: true, align: "end", width: "125px" },
  { title: "MARGEN ($)", key: "profit", sortable: true, align: "end", width: "130px" },
  { title: "MARGEN (%)", key: "margin_percentage", sortable: false, align: "center", width: "115px" },
];

const loadSummary = async () => {
  loadingSummary.value = true;
  try {
    const params = new URLSearchParams();
    if (startDate.value) params.append("start_date", startDate.value);
    if (endDate.value) params.append("end_date", endDate.value);
    if (searchQuery.value) params.append("search", searchQuery.value);

    const response = await axios.get(`/finances/income-statement/summary?${params}`);
    if (response.data?.success) {
      summary.value = response.data.data;
    }
  } catch (error) {
    toast.error("Error al cargar el resumen del estado de resultados");
  } finally {
    loadingSummary.value = false;
  }
};

const loadDetails = async () => {
  loadingDetails.value = true;
  try {
    const params = new URLSearchParams();
    if (startDate.value) params.append("start_date", startDate.value);
    if (endDate.value) params.append("end_date", endDate.value);
    if (searchQuery.value) params.append("search", searchQuery.value);
    if (selectedType.value) params.append("type", selectedType.value);
    params.append("page", page.value);
    params.append("per_page", itemsPerPage.value);

    const response = await axios.get(`/finances/income-statement/details?${params}`);
    if (response.data?.success) {
      transactions.value = response.data.data.transactions || [];
      pageTotals.value = response.data.data.page_totals || {};
      totalItems.value = response.data.data.pagination?.total || 0;
    }
  } catch (error) {
    toast.error("Error al cargar los detalles del estado de resultados");
  } finally {
    loadingDetails.value = false;
  }
};

const loadData = async () => {
  page.value = 1;
  await Promise.all([loadSummary(), loadDetails()]);
};

const updateOptions = (opts) => {
  page.value = opts.page;
  itemsPerPage.value = opts.itemsPerPage;
  loadDetails();
};

const clearFilters = () => {
  startDate.value = getCurrentMonthStart();
  endDate.value = getToday();
  searchQuery.value = "";
  selectedType.value = null;
  loadData();
};

const formatNumber = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount || 0);
};

const getMarginChipClass = (pct) => {
  const num = Number(pct || 0);
  if (num < 0) return "margin-chip-error";
  if (num < 15) return "margin-chip-warning";
  return "margin-chip-success";
};

const formatDate = (date) => {
  if (!date) return "—";
  return new Date(date).toLocaleDateString("es-VE");
};

const exportToCsv = () => {
  if (!transactions.value.length) {
    toast.warning("No hay transacciones para exportar");
    return;
  }

  const csvRows = [];
  csvRows.push([
    "FECHA",
    "COMPROBANTE",
    "TIPO",
    "DESCRIPCIÓN",
    "CLIENTE / PROVEEDOR",
    "CANAL",
    "VENTA",
    "COSTO",
    "MARGEN ($)",
    "MARGEN (%)"
  ].join(";"));

  transactions.value.forEach(item => {
    csvRows.push([
      `"${item.date || ''}"`,
      `"${item.voucher_number || ''}"`,
      `"${item.type === 'sale' ? 'INGRESO' : 'EGRESO'}"`,
      `"${(item.description || '').replace(/"/g, '""')}"`,
      `"${(item.client || '').replace(/"/g, '""')}"`,
      `"${(item.channel || '').replace(/"/g, '""')}"`,
      `"${item.amount || 0}"`,
      `"${item.costs || 0}"`,
      `"${item.profit || 0}"`,
      `"${item.margin_percentage || 0}%"`
    ].join(";"));
  });

  const csvContent = "\uFEFF" + csvRows.join("\r\n");
  const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `registro_transaccional_${startDate.value || 'inicio'}_${endDate.value || 'fin'}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  toast.success("Reporte CSV exportado correctamente");
};

const printReport = () => {
  window.print();
};

const handleReset = async () => {
  const result = await toast.fire({
    title: "¿Estás seguro?",
    text: "El reporte se reiniciará para tomar datos desde hoy en adelante por defecto. Podrás volver a ver datos antiguos seleccionando el rango de fechas manualmente.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, reiniciar",
    cancelButtonText: "Cancelar",
    customClass: {
      confirmButton: "v-btn v-btn--elevated v-theme--light bg-error v-btn--density-default v-btn--size-default v-btn--variant-elevated",
      cancelButton: "v-btn v-btn--flat v-theme--light bg-secondary v-btn--density-default v-btn--size-default v-btn--variant-text",
    },
  });

  if (result.isConfirmed) {
    try {
      await axios.post("/finances/income-statement/reset");
      toast.success("Reporte reiniciado con éxito");
      clearFilters();
    } catch (error) {
      toast.error("Error al reiniciar el reporte");
    }
  }
};

let debounceTimeout = null;
const debouncedLoadData = () => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    loadData();
  }, 400);
};

onMounted(() => {
  loadData();
});

watch([startDate, endDate, selectedType], () => loadData());
watch(searchQuery, () => debouncedLoadData());
</script>

<template>
  <div class="income-statement-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Componente Filtros -->
      <IncomeStatementFilters
        v-model:searchQuery="searchQuery"
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        v-model:selectedType="selectedType"
        class="mb-5 print-hidden"
        @clear="clearFilters"
        @reset="handleReset"
        @export-excel="exportToCsv"
        @export-pdf="printReport"
      />

      <!-- Componente Tarjetas de Resumen KPI -->
      <IncomeStatementSummaryCards
        :summary="summary"
        :loading="loadingSummary"
      />

      <!-- Tabla / Tarjetas de Detalles -->
      <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
        <div class="px-6 py-4 bg-white border-b d-flex justify-space-between align-center flex-wrap gap-4 print-header">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="elevated" size="38" class="rounded-lg shadow-sm">
              <VIcon icon="tabler-report-analytics" color="white" size="22" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-h6 font-weight-black leading-none">Registro Transaccional</span>
              <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1">
                Auxiliar Transaccional de Ventas y Márgenes
              </span>
            </div>
          </div>
        </div>

        <VCardText class="pa-0">
          <!-- Vista Desktop -->
          <template v-if="!$vuetify.display.smAndDown">
            <VProgressLinear
              v-if="loadingDetails"
              indeterminate
              color="primary"
              height="3"
              class="position-absolute w-100 progress-overlay"
            />

            <VDataTableServer
              v-model:items-per-page="itemsPerPage"
              v-model:page="page"
              :headers="headers"
              :items="transactions"
              :items-length="totalItems"
              :loading="loadingDetails"
              class="premium-table"
              @update:options="updateOptions"
            >
              <!-- FECHA -->
              <template #item.date="{ item }">
                <span class="text-body-2 font-weight-bold text-medium-emphasis">
                  {{ formatDate(item.date) }}
                </span>
              </template>

              <!-- COMPROBANTE -->
              <template #item.voucher_number="{ item }">
                <VChip
                  v-if="item.type === 'sale'"
                  size="small"
                  color="primary"
                  variant="tonal"
                  class="font-weight-black cursor-pointer clickable-order"
                  @click="openOrderModal(item.id)"
                >
                  <VIcon start icon="tabler-receipt" size="14" />
                  {{ item.voucher_number || `Order #${item.id}` }}
                </VChip>
                <span
                  v-else
                  class="font-mono text-xs font-weight-bold text-secondary bg-secondary-subtle px-2 py-1 rounded"
                >
                  {{ item.voucher_number || `EGR-#${item.id}` }}
                </span>
              </template>

              <!-- TIPO -->
              <template #item.type="{ item }">
                <VChip
                  :color="item.type === 'sale' ? 'success' : 'error'"
                  size="x-small"
                  variant="tonal"
                  class="font-weight-black px-2 rounded"
                >
                  {{ item.type === "sale" ? "INGRESO" : "EGRESO" }}
                </VChip>
              </template>

              <!-- DESCRIPCIÓN -->
              <template #item.description="{ item }">
                <span class="text-body-2 font-weight-medium text-high-emphasis">
                  {{ item.description }}
                </span>
              </template>

              <!-- CLIENTE / CANAL -->
              <template #item.client="{ item }">
                <div class="d-flex flex-column">
                  <span class="text-body-2 font-weight-bold text-high-emphasis">
                    {{ item.client }}
                  </span>
                  <span class="text-super-xs text-medium-emphasis">
                    {{ item.channel }}
                  </span>
                </div>
              </template>

              <!-- MONTO VENTA -->
              <template #item.amount="{ item }">
                <span class="text-body-2 font-weight-black font-mono text-high-emphasis">
                  {{ formatNumber(item.amount) }}
                </span>
              </template>

              <!-- COSTOS -->
              <template #item.costs="{ item }">
                <span class="text-body-2 font-weight-black font-mono text-high-emphasis">
                  {{ item.costs > 0 ? formatNumber(item.costs) : "0,00" }}
                </span>
              </template>

              <!-- MARGEN ($) -->
              <template #item.profit="{ item }">
                <span
                  class="text-body-2 font-weight-black font-mono"
                  :class="item.profit >= 0 ? 'text-money-green' : 'text-error'"
                >
                  {{ formatNumber(item.profit) }}
                </span>
              </template>

              <!-- MARGEN (%) -->
              <template #item.margin_percentage="{ item }">
                <template v-if="item.type === 'sale'">
                  <VChip
                    size="small"
                    variant="tonal"
                    class="font-weight-black font-mono px-2"
                    :class="getMarginChipClass(item.margin_percentage)"
                  >
                    {{ Number(item.margin_percentage || 0).toFixed(2) }}%
                  </VChip>
                </template>
                <template v-else>
                  <span class="text-disabled font-weight-bold">—</span>
                </template>
              </template>

              <!-- FILA DE TOTALES EN EL PIE DE TABLA -->
              <template #body.append>
                <tr v-if="transactions.length > 0" class="summary-footer-row bg-light font-weight-black">
                  <td colspan="5" class="text-subtitle-2 font-weight-black text-uppercase text-medium-emphasis ps-4 py-3">
                    <div class="d-flex align-center gap-2">
                      <VIcon icon="tabler-calculator" size="18" class="text-primary" />
                      <span>TOTALES DE LA PÁGINA ({{ transactions.length }} REGISTROS)</span>
                    </div>
                  </td>
                  <td class="text-end font-mono text-body-2 font-weight-black text-high-emphasis pe-4 py-3">
                    {{ formatNumber(pageTotals.amount) }}
                  </td>
                  <td class="text-end font-mono text-body-2 font-weight-black text-high-emphasis pe-4 py-3">
                    {{ pageTotals.costs > 0 ? formatNumber(pageTotals.costs) : '0,00' }}
                  </td>
                  <td
                    class="text-end font-mono text-body-2 font-weight-black pe-4 py-3"
                    :class="pageTotals.profit >= 0 ? 'text-money-green' : 'text-error'"
                  >
                    {{ formatNumber(pageTotals.profit) }}
                  </td>
                  <td class="text-center font-mono font-weight-black py-3">
                    <VChip
                      size="small"
                      variant="tonal"
                      class="font-weight-black font-mono px-2"
                      :class="getMarginChipClass(pageTotals.margin_percentage)"
                    >
                      {{ Number(pageTotals.margin_percentage || 0).toFixed(2) }}%
                    </VChip>
                  </td>
                </tr>
              </template>
            </VDataTableServer>
          </template>

          <!-- Vista Móvil Refactorizada -->
          <IncomeStatementMobileList
            v-else
            v-model:page="page"
            :transactions="transactions"
            :loading="loadingDetails"
            :total-items="totalItems"
            :items-per-page="itemsPerPage"
            @update:page="loadDetails"
            @view-order="openOrderModal"
          />
        </VCardText>
      </VCard>
    </div>

    <!-- Modal de Detalles de Orden -->
    <OrderViewModal
      v-model:isDialogVisible="viewModal"
      :order-data="orderData"
      :order-products="orderItems"
      :total-amount="amountForPrint"
      :selected-currency="selectedOrderCurrency"
      :payments="paymentsForPrint"
      :change-amount="changeAmountForPrint"
      :credit-amount="creditAmountForPrint"
      :credit="creditForPrint"
      @close="handleCloseViewModal"
    />
  </div>
</template>

<style scoped>
.income-statement-view {
  background-color: #f8fafc;
  min-block-size: 100vh;
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.bg-primary-subtle {
  background-color: rgba(var(--v-theme-primary), 0.08);
}

.bg-secondary-subtle {
  background-color: rgba(var(--v-theme-secondary), 0.08);
}

.bg-light {
  background-color: #f8fafc;
}

.progress-overlay {
  z-index: 1;
}

.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.text-money-green {
  color: #16a34a !important;
}

.margin-chip-success {
  background-color: #dcfce7 !important;
  color: #166534 !important;
  border: 1px solid #86efac !important;
}

.margin-chip-warning {
  background-color: #fef3c7 !important;
  color: #92400e !important;
  border: 1px solid #fcd34d !important;
}

.margin-chip-error {
  background-color: #fee2e2 !important;
  color: #991b1b !important;
  border: 1px solid #fca5a5 !important;
}

.clickable-order {
  cursor: pointer;
  transition: all 0.2s ease-in-out;
}

.clickable-order:hover {
  transform: translateY(-1px);
  filter: brightness(0.95);
  box-shadow: 0 2px 6px rgba(var(--v-theme-primary), 0.25);
}

:deep(.v-data-table.premium-table) {
  background: white;
}

:deep(.v-data-table.premium-table th) {
  background-color: #f8fafc !important;
  block-size: 48px !important;
  border-block-end: 2px solid rgba(var(--v-border-color), 0.12) !important;
  color: rgba(var(--v-theme-on-surface), 0.85) !important;
  font-size: 0.78rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

:deep(.v-data-table.premium-table td) {
  block-size: 56px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.06) !important;
  padding-block: 10px !important;
}

.summary-footer-row {
  border-top: 2px solid rgba(var(--v-border-color), 0.18) !important;
  background-color: #f1f5f9 !important;
}

@media print {
  .print-hidden {
    display: none !important;
  }
  .income-statement-view {
    background-color: white !important;
    padding: 0 !important;
  }
}
</style>
