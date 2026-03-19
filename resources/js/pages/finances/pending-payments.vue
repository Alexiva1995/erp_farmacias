<script setup>
import PendingPaymentFilters from "@/components/PendingPaymentFilters.vue";
import PendingPaymentTable from "@/components/PendingPaymentTable.vue";
import PendingPaymentModal from "@/components/dialogs/PendingPaymentModal.vue";
import ProcessPaymentModal from "@/components/dialogs/ProcessPaymentModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();
const route = useRoute();

const loading = ref(false);
const pendingPayments = ref([]);
const totalInvoices = ref(0);
const suppliers = ref([]);
const isLoadingFilters = ref(false);
const exchangeRate = ref(1);

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
const selectedPaymentGroup = ref(null);
const selectedInvoices = ref([]);
const selectedTableInvoices = ref([]);

const fetchExchangeRates = async () => {
  try {
    const { data } = await axios.get("/public/exchange-rates");
    const rate = data.find(r => r.currency_code === "BS")?.rate || 1;
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
          payment_date: group.payment_date,
          group_id: `${group.supplier_id}_${group.payment_date}`,
          supplier_total_bs: group.total_in_supplier_currency,
          supplier_total_usd: group.total_amount_usd
        });
      });
    });

    pendingPayments.value = allInvoices;
    totalInvoices.value = data.data.total_suppliers || allInvoices.length;

    // Sincronizar las facturas seleccionadas con los datos más recientes (para reflejar cambios de indexación)
    selectedTableInvoices.value = selectedTableInvoices.value.map((selected) => {
      const updated = allInvoices.find((inv) => inv.id === selected.id);
      return updated || selected;
    });
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
  try {
    await axios.put(`/finances/invoices/${item.id}/toggle-indexed`, {
      is_indexed: item.is_indexed,
    });
    toast.success("Estado de indexación actualizado");
    fetchPendingPayments();
  } catch (error) {
    item.is_indexed = !item.is_indexed;
    toast.error("Error al actualizar indexación");
  }
};

const processPayment = (invoice) => {
  selectedInvoices.value = [invoice];
  selectedPaymentGroup.value = {
    supplier_name: invoice.supplier_name,
    payment_date: invoice.payment_date,
    currency: invoice.currency,
    invoice_count: 1
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
    invoice_count: selectedTableInvoices.value.length
  };
  showProcessModal.value = true;
};

const handlePaymentProcessed = () => {
  // CORRECCIÓN SOLICITADA: Limpiar selección tras pago exitoso
  selectedTableInvoices.value = [];
  fetchPendingPayments();
};

const clearFilters = () => {
  searchQuery.value = "";
  selectedSupplier.value = null;
  startDate.value = null;
  endDate.value = null;
  showOverdueOnly.value = false;
};

const toggleSelection = (invoice) => {
  const index = selectedTableInvoices.value.findIndex(i => i.id === invoice.id);
  if (index > -1) selectedTableInvoices.value.splice(index, 1);
  else selectedTableInvoices.value.push(invoice);
};

onMounted(async () => {
  await fetchExchangeRates();
  await fetchSuppliers();
  if (route.query.supplierId) selectedSupplier.value = Number(route.query.supplierId);
  fetchPendingPayments();
});

watch([searchQuery, selectedSupplier, startDate, endDate, showOverdueOnly], () => {
  page.value = 1;
  fetchPendingPayments();
});
</script>

<template>
  <div :class="mobile ? 'pa-0 pb-16' : 'pa-4'">
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
      @clear="clearFilters"
      @refresh="fetchPendingPayments"
    />

    <!-- Cabecera de Tabla Premium (Escritorio) -->
    <VCard v-if="!mobile" class="mb-4 rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
      <VCardTitle class="pa-4 px-6 d-flex align-center">
        <VAvatar color="primary" variant="tonal" size="32" class="me-3 rounded-lg">
          <VIcon icon="tabler-list-check" size="18" />
        </VAvatar>
        <span class="text-sm font-weight-black uppercase">Listado de Facturas</span>
        <VSpacer />
        <div class="d-flex align-center gap-2">
           <VBtn
            v-if="selectedTableInvoices.length > 0"
            variant="tonal"
            color="secondary"
            class="rounded-lg text-xs font-weight-black"
            @click="selectedTableInvoices = []"
          >
            DESELECCIONAR ({{ selectedTableInvoices.length }})
          </VBtn>
          <VBtn
            :disabled="selectedTableInvoices.length === 0"
            color="success"
            variant="flat"
            class="rounded-lg text-xs font-weight-black px-6 shadow-sm"
            @click="processMultiplePayments"
          >
            <VIcon start icon="tabler-credit-card" size="18" />
            PAGAR SELECCIONADOS
          </VBtn>
        </div>
      </VCardTitle>
    </VCard>

    <!-- Tabla y Cards Premium -->
    <PendingPaymentTable
      :pending-payments="pendingPayments"
      :loading="loading"
      :selected-table-invoices="selectedTableInvoices"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="handleTableUpdate"
      @toggle-indexed="toggleIndexedStatus"
      @process-payment="processPayment"
      @process-multiple="processMultiplePayments"
      @toggle-selection="toggleSelection"
      @select-all="selectedTableInvoices = [...pendingPayments]"
      @deselect-all="selectedTableInvoices = []"
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
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}
</style>
