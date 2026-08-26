<script setup>
import PendingPaymentFilters from "@/components/PendingPaymentFilters.vue";
import PendingPaymentTable from "@/components/PendingPaymentTable.vue";
import PendingPaymentModal from "@/components/dialogs/PendingPaymentModal.vue";
import ProcessPaymentModal from "@/components/dialogs/ProcessPaymentModal.vue";
import DronenaDiscrepanciesModal from "@/components/dialogs/DronenaDiscrepanciesModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useDisplay } from "vuetify";
import { useAuthStore } from "@/stores/auth";

const { mobile } = useDisplay();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const loading = ref(false);
const pendingPayments = ref([]);
const totalInvoices = ref(0);
const suppliers = ref([]);
const isLoadingFilters = ref(false);
const exchangeRate = ref(1);
const updatingIndexed = ref({});
const updatingDates = ref({});

// Filtros
const searchQuery = ref("");
const selectedSupplier = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const showOverdueOnly = ref(false);

// Paginación
const page = ref(1);
const itemsPerPage = ref(10);

// Modales
const showPaymentModal = ref(false);
const showProcessModal = ref(false);
const showDiscrepanciesModal = ref(false);
const syncDiscrepancies = ref({});
const syncSummary = ref({});
const selectedPaymentGroup = ref(null);
const selectedInvoices = ref([]);
const selectedTableInvoices = ref([]);


const fetchExchangeRates = async () => {
  try {
    const { data } = await axios.get("/public/exchange-rates");
    const rate = data.find((r) => r.currency_code === "BS")?.rate || 1;
    exchangeRate.value = parseFloat(rate);
  } catch (error) {
    console.error("Error tasa:", error);
  }
};

const fetchSuppliers = async () => {
  isLoadingFilters.value = true;
  try {
    const { data } = await axios.get("/finances/pending-payments/suppliers");
    suppliers.value = data.data;
  } catch (error) {
    console.error("Error proveedores:", error);
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchPendingPayments = async () => {
  loading.value = true;
  try {
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      q: searchQuery.value,
      supplier_id: selectedSupplier.value,
      start_date: startDate.value,
      end_date: endDate.value,
      show_overdue_only: showOverdueOnly.value,
    };

    const { data } = await axios.get("/finances/pending-payments", { params });

    const allInvoices = [];
    data.data.pending_payments.forEach((group) => {
      group.invoices.forEach((invoice) => {
        allInvoices.push({
          ...invoice,
          supplier_name: group.supplier_name,
          payment_date: invoice.payment_date || invoice.exp_date || group.payment_date,
          group_id: `${group.supplier_id}_${group.payment_date}`,
          supplier_total_bs: group.total_in_supplier_currency,
          supplier_total_usd: group.total_amount_usd,
        });
      });
    });

    pendingPayments.value = allInvoices;
    totalInvoices.value = data.data.total_suppliers || allInvoices.length;

    // Sincronizar las facturas seleccionadas con los datos más recientes (para reflejar cambios de indexación)
    selectedTableInvoices.value = selectedTableInvoices.value.map(
      (selected) => {
        const updated = allInvoices.find((inv) => inv.id === selected.id);
        return updated || selected;
      },
    );
  } catch (error) {
    console.error("Error pagos:", error);
    toast.error("Error al cargar pagos pendientes");
  } finally {
    loading.value = false;
  }
};

const handleTableUpdate = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  fetchPendingPayments();
};

const toggleIndexedStatus = async (item) => {
  updatingIndexed.value[item.id] = true;
  const previousState = !item.is_indexed;
  try {
    const { data } = await axios.put(`/finances/invoices/${item.id}/toggle-indexed`, {
      is_indexed: item.is_indexed,
    });
    if (data?.data) {
      item.is_indexed = data.data.is_indexed;
    }

    // Actualizar reactivamente los datos de indexación del item local
    if (item.is_indexed) {
      const currentRate = exchangeRate.value > 0 ? exchangeRate.value : 1;
      const originalUsd = parseFloat(item.original_amount_usd || item.total_usd) || 0;
      item.indexed_data = {
        is_indexed: true,
        indexed_amount: originalUsd * currentRate,
        exchange_rate: currentRate,
        original_amount: item.original_amount || item.total_amount,
        original_amount_usd: originalUsd,
      };
    } else if (item.indexed_data) {
      item.indexed_data.is_indexed = false;
    }

    // Sincronizar si la factura está en la selección de lotes
    const selIdx = selectedTableInvoices.value.findIndex((inv) => inv.id === item.id);
    if (selIdx !== -1) {
      selectedTableInvoices.value[selIdx] = { ...item };
    }

    toast.success("Estado de indexación actualizado");
  } catch (error) {
    item.is_indexed = previousState;
    toast.error("Error al actualizar indexación");
  } finally {
    updatingIndexed.value[item.id] = false;
  }
};

const processPayment = (invoice) => {
  selectedInvoices.value = [invoice];
  selectedPaymentGroup.value = {
    supplier_name: invoice.supplier_name,
    payment_date: invoice.payment_date,
    currency: invoice.currency,
    invoice_count: 1,
  };
  showProcessModal.value = true;
};

const processMultiplePayments = () => {
  if (selectedTableInvoices.value.length === 0) return;

  selectedInvoices.value = [...selectedTableInvoices.value];
  selectedPaymentGroup.value = {
    supplier_name: "Múltiples Proveedores",
    payment_date: "Varias Fechas",
    currency: "USD",
    invoice_count: selectedTableInvoices.value.length,
  };
  showProcessModal.value = true;
};

const handlePaymentProcessed = () => {
  // CORRECCIÓN SOLICITADA: Limpiar selección tras pago exitoso
  selectedTableInvoices.value = [];
  fetchPendingPayments();
};

const handleUpdateDate = async (item, newDate) => {
  updatingDates.value[item.id] = true;
  try {
    await axios.patch(`/finances/pending-payments/invoices/${item.id}/update-date`, {
      payment_date: newDate,
    });
    toast.success("Fecha de pago actualizada correctamente");
    await fetchPendingPayments();
  } catch (error) {
    console.error("Error al actualizar fecha:", error);
    toast.error("Error al actualizar la fecha de pago");
  } finally {
    updatingDates.value[item.id] = false;
  }
};

const handleMarkAsPaid = async (item) => {
  toast.confirm(
    `¿Marcar Factura #${item.invoice_number} como Pagada?`,
    async () => {
      try {
        await axios.patch(
          `/finances/pending-payments/invoices/${item.id}/mark-as-paid`,
        );
        toast.success("Factura marcada como pagada directamente");
        fetchPendingPayments();
      } catch (error) {
        console.error("Error al marcar como pagada:", error);
        toast.error("No se pudo marcar la factura como pagada");
      }
    },
  );
};

const clearFilters = () => {
  searchQuery.value = "";
  selectedSupplier.value = null;
  startDate.value = null;
  endDate.value = null;
  showOverdueOnly.value = false;
};

const toggleSelection = (invoice) => {
  const index = selectedTableInvoices.value.findIndex(
    (i) => i.id === invoice.id,
  );
  if (index > -1) selectedTableInvoices.value.splice(index, 1);
  else selectedTableInvoices.value.push(invoice);
};

onMounted(async () => {
  if (authStore.isVendedor) {
    toast.error("Acceso denegado: No tienes permisos para ver esta sección.");
    router.push("/invoice/invoices");
    return;
  }
  await fetchExchangeRates();
  await fetchSuppliers();
  if (route.query.supplierId)
    selectedSupplier.value = Number(route.query.supplierId);
  fetchPendingPayments();
});

watch(
  [searchQuery, selectedSupplier, startDate, endDate, showOverdueOnly],
  () => {
    page.value = 1;
    fetchPendingPayments();
  },
);

const isSyncingDronena = ref(false);

const handleSyncDronena = async () => {
  const result = await Swal.fire({
    title: "¿Sincronizar Cuentas por Pagar con Dronena?",
    text: "Se consultará el portal de Dronena para actualizar las fechas de vencimiento, fechas de pago, indexación (FA$) y contrastar el estado de las facturas.",
    icon: "info",
    showCancelButton: true,
    confirmButtonText: "Sincronizar",
    cancelButtonText: "Cancelar",
  });

  if (!result.isConfirmed) return;

  isSyncingDronena.value = true;
  try {
    const payload = selectedSupplier.value ? { supplier_id: selectedSupplier.value } : {};
    const response = await axios.post("/invoices/sync-dronena", payload);
    const data = response.data?.data || {};

    syncSummary.value = {
      updated: data.updated || 0,
      skipped: data.skipped || 0,
      total_extracted: data.total_extracted || 0,
    };
    syncDiscrepancies.value = data.discrepancies || {
      paid_in_erp_pending_in_dronena: [],
      pending_in_erp_paid_in_dronena: [],
      total_discrepancies: 0,
    };

    showDiscrepanciesModal.value = true;
    await fetchPendingPayments();
  } catch (error) {
    console.error("Error al sincronizar con Dronena:", error);
    toast.error(error.response?.data?.message || "Ocurrió un error al sincronizar con Dronena.");
  } finally {
    isSyncingDronena.value = false;
  }
};

</script>

<template>
  <div class="pending-payments-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Filtros Premium -->
      <PendingPaymentFilters
        v-model:search-query="searchQuery"
        v-model:selected-supplier="selectedSupplier"
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        v-model:show-overdue-only="showOverdueOnly"
        :suppliers="suppliers"
        :loading="loading"
        :is-loading-filters="isLoadingFilters"
        :is-syncing-dronena="isSyncingDronena"
        @clear="clearFilters"
        @refresh="fetchPendingPayments"
        @sync-dronena="handleSyncDronena"
        class="mb-0"
      >
        <template #selection-actions>
          <div v-if="!mobile" class="d-flex align-center gap-1 me-1">
            <VBtn
              v-if="selectedTableInvoices.length > 0"
              icon
              variant="tonal"
              color="secondary"
              size="38"
              class="rounded-circle shadow-sm"
              @click="selectedTableInvoices = []"
            >
              <VBadge color="error" :content="selectedTableInvoices.length" offset-x="-2" offset-y="-2">
                <VIcon icon="tabler-deselect" size="20" />
              </VBadge>
              <VTooltip activator="parent" location="top">Deseleccionar Todo</VTooltip>
            </VBtn>

            <VBtn
              :disabled="selectedTableInvoices.length === 0"
              icon
              color="success"
              variant="flat"
              size="38"
              class="rounded-circle shadow-sm"
              @click="processMultiplePayments"
            >
              <VIcon icon="tabler-credit-card" size="20" />
              <VTooltip activator="parent" location="top">Pagar Seleccionados ({{ selectedTableInvoices.length }})</VTooltip>
            </VBtn>
          </div>
        </template>
      </PendingPaymentFilters>


      <!-- Tabla y Cards Premium -->
      <PendingPaymentTable
        :pending-payments="pendingPayments"
        :loading="loading"
        :selected-table-invoices="selectedTableInvoices"
        :items-per-page="itemsPerPage"
        :page="page"
        :updating-indexed="updatingIndexed"
        :updating-dates="updatingDates"
        :exchange-rate="exchangeRate"
        @update:options="handleTableUpdate"
        @toggle-indexed="toggleIndexedStatus"
        @process-payment="processPayment"
        @process-multiple="processMultiplePayments"
        @update-date="handleUpdateDate"
        @mark-as-paid="handleMarkAsPaid"
        @toggle-selection="toggleSelection"
        @select-all="selectedTableInvoices = [...pendingPayments]"
        @deselect-all="selectedTableInvoices = []"
        class="ma-0"
      />

      <!-- Modales -->
      <PendingPaymentModal
        v-model="showPaymentModal"
        :payment-group="selectedPaymentGroup"
        :invoices="selectedInvoices"
        @close="showPaymentModal = false"
      />

      <ProcessPaymentModal
        v-model="showProcessModal"
        :invoices="selectedInvoices"
        :exchange-rate="exchangeRate"
        @payment-processed="handlePaymentProcessed"
      />

      <DronenaDiscrepanciesModal
        v-model="showDiscrepanciesModal"
        :discrepancies="syncDiscrepancies"
        :sync-summary="syncSummary"
        @invoices-marked-as-paid="fetchPendingPayments"
        @close="showDiscrepanciesModal = false"
      />

    </div>
  </div>
</template>


<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}
</style>
