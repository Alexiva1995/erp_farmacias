<script setup>
import InvoiceFilters from "@/components/InvoiceFilters.vue";
import InvoiceTable from "@/components/InvoiceTable.vue";
import InvoiceDetailView from "@/pages/invoice/invoiceDetails.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const currentView = ref("list");
const selectedInvoiceId = ref(null);
const selectedInvoice = ref(null);
const invoices = ref([]);
const totalInvoices = ref(0);
const loading = ref(false);
const suppliers = ref([]);
const isLoadingFilters = ref(false);
const searchQuery = ref("");
const selectedSupplier = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();
const availablePaymentRules = ref([]);
const isApproving = ref(false);

const { isAdmin } = useAuthStore();

const fetchSuppliers = async () => {
  isLoadingFilters.value = true;
  try {
    const response = await axios.get("/suppliers");
    suppliers.value = response.data.data ?? response.data;
  } catch (error) {
    toast.error("No se pudieron cargar los proveedores.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchInvoices = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    q: searchQuery.value,
    supplierId: selectedSupplier.value,
    startDate: startDate.value,
    endDate: endDate.value,
    status: "loaded",
  };

  Object.keys(params).forEach(
    (key) => (params[key] == null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/invoices", { params });
    invoices.value = response.data.data;
    totalInvoices.value = response.data.total;
  } catch (error) {
    toast.error("Error al obtener las facturas.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    searchQuery,
    selectedSupplier,
    startDate,
    endDate,
  ],
  () => {
    if (currentView.value === "list") {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => fetchInvoices(), 300);
    }
  },
  { deep: true }
);

watch([searchQuery, selectedSupplier, startDate, endDate], () => {
  if (page.value !== 1) {
    page.value = 1;
  }
});

onMounted(() => {
  fetchSuppliers();
  fetchInvoices();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedSupplier.value = null;
  startDate.value = null;
  endDate.value = null;
};

const handleReviewInvoice = async (invoice) => {
  selectedInvoiceId.value = invoice.id;
  selectedInvoice.value = invoice;

  try {
    const response = await axios.get(
      `/suppliers/${invoice.supplier_id}/payment-rules`
    );
    availablePaymentRules.value = response.data.data ?? response.data ?? [];
  } catch (error) {
    availablePaymentRules.value = [];
  }

  currentView.value = "detail";
};

const handleReturnToList = () => {
  selectedInvoiceId.value = null;
  currentView.value = "list";
  fetchInvoices();
};

const handleApprovalApiCall = async ({ paymentRuleId }) => {
  isApproving.value = true;
  try {
    const payload = {
      payment_rule_id: paymentRuleId,
    };
    await axios.post(`/invoices/${selectedInvoiceId.value}/approve`, payload);
    toast.success("Factura aprobada con éxito.");
    handleReturnToList();
  } catch (error) {
    toast.error(
      error.response?.data?.message || "No se pudo aprobar la factura."
    );
  } finally {
    isApproving.value = false;
  }
};

const handleRejectApiCall = async () => {
  isApproving.value = true;
  try {
    await axios.post(`/invoices/${selectedInvoiceId.value}/reject`);
    toast.success("Factura rechazada con éxito y devuelta a la carga.");
    handleReturnToList();
  } catch (error) {
    toast.error(
      error.response?.data?.message || "No se pudo rechazar la factura."
    );
  } finally {
    isApproving.value = false;
  }
};

const handleReturnInvoice = async (invoiceId) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `La factura #${invoiceId} junto a todos los productos aceptados serán regresados al estado pendiente.`,
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Aprobar",
    confirmButtonColor: "#28a745",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
  });

  if (result.isConfirmed) {
    try {
      const { data } = await axios.put(`/invoices/${invoiceId}/return-pending`);

      if (data.status) {
        toast.success(data.message);
        fetchInvoices();
      } else {
        toast.error(data.message);
      }
    } catch (error) {
      toast.error("No se pudo devolver la factura a estado pendiente");
    }
  }
};
</script>

<template>
  <div>
    <div v-if="currentView === 'list'">
      <InvoiceFilters
        v-model:searchQuery="searchQuery"
        v-model:selectedSupplier="selectedSupplier"
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        :suppliers="suppliers"
        :loading="isLoadingFilters"
        @clear="handleClearFilters"
      />

      <InvoiceTable
        :invoices="invoices"
        :loading="loading"
        :total-invoices="totalInvoices"
        :items-per-page="itemsPerPage"
        :page="page"
        :is-admin="isAdmin"
        :highlighted-id="selectedInvoiceId"
        actions-mode="approval"
        @update:options="updateTableOptions"
        @edit-invoice="handleReviewInvoice"
        @return-invoice="handleReturnInvoice"
      />
    </div>

    <div v-else-if="currentView === 'detail'">
      <InvoiceDetailView
        :invoice-id="selectedInvoiceId"
        :initial-invoice="selectedInvoice"
        :payment-rules="availablePaymentRules"
        :is-saving="isApproving"
        mode="approval"
        @back-to-list="handleReturnToList"
        @confirm-approval="handleApprovalApiCall"
        @reject-invoice="handleRejectApiCall"
      />
    </div>
  </div>
</template>
