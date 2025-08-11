<script setup>
import ApproveInvoiceModal from "@/components/dialogs/ApproveInvoiceModal.vue";
import InvoiceFilters from "@/components/InvoiceFilters.vue";
import InvoiceTable from "@/components/InvoiceTable.vue";
import InvoiceDetailView from "@/pages/invoice/invoiceDetails.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

// ... (las refs se mantienen igual) ...
const currentView = ref("list");
const selectedInvoiceId = ref(null);
const invoices = ref([]);
const totalInvoices = ref(0);
const loading = ref(false);
const suppliers = ref([]);
const isLoadingFilters = ref(false);
const searchQuery = ref("");
const selectedSupplier = ref(null);
const selectedStatus = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();
const isApproveModalVisible = ref(false);
const invoiceToApprove = ref(null);
const availableDiscounts = ref([]);
const isApproving = ref(false);

const fetchSuppliers = async () => {
  isLoadingFilters.value = true;
  try {
    const response = await axios.get("/suppliers");
    suppliers.value = response.data.data ?? response.data;
  } catch (error) {
    console.error("Error al cargar proveedores:", error);
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
    status: selectedStatus.value,
    startDate: startDate.value,
    endDate: endDate.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] == null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/invoices/for-order", { params });
    invoices.value = response.data.data;
    totalInvoices.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener las facturas:", error);
    toast.error("Error al obtener las facturas.");
  } finally {
    loading.value = false;
  }
};

const fetchDiscountsForSupplier = async (supplierId) => {
  if (!supplierId) {
    availableDiscounts.value = [];
    return;
  }
  try {
    const response = await axios.get(`/suppliers/${supplierId}/discounts`);
    availableDiscounts.value = response.data.data || [];
  } catch (error) {
    console.error("Error al obtener los descuentos del proveedor:", error);
    toast.error("No se pudieron cargar los descuentos del proveedor.");
    availableDiscounts.value = [];
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
    selectedStatus,
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

watch(
  [searchQuery, selectedSupplier, selectedStatus, startDate, endDate],
  () => {
    if (page.value !== 1) {
      page.value = 1;
    }
  }
);

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
  selectedStatus.value = null;
  startDate.value = null;
  endDate.value = null;
};

const handleViewDetails = (invoice) => {
  console.log("Cambiando a la vista de detalle para la factura:", invoice.id);
  selectedInvoiceId.value = invoice.id;
  currentView.value = "detail";
};

const handleReturnToList = () => {
  console.log("Volviendo a la lista de facturas");
  selectedInvoiceId.value = null;
  currentView.value = "list";
  fetchInvoices();
};

const handleApproveInvoice = async (invoice) => {
  invoiceToApprove.value = invoice;
  await fetchDiscountsForSupplier(invoice.supplier_id);
  isApproveModalVisible.value = true;
};

const confirmApproval = async ({ invoiceId, discountId }) => {
  isApproving.value = true;
  console.log("Enviando al backend:", { invoiceId, discountId });

  const payload = {
    discount_rule_id: discountId,
  };

  try {
    await axios.post(`/invoices/${invoiceId}/approve`, payload);

    toast.success("Factura aprobada con éxito.");
    isApproveModalVisible.value = false;
    fetchInvoices();
  } catch (error) {
    console.error(`Error al aprobar la factura ${invoiceId}:`, error);
    toast.error(
      error.response?.data?.message || "No se pudo aprobar la factura."
    );
  } finally {
    isApproving.value = false;
  }
};

const handleRejectInvoice = async (invoice) => {
  const { value: reason } = await Swal.fire({
    title: "Rechazar Factura",
    text: `Estás a punto de RECHAZAR la factura N° ${invoice.invoice_number}.`,
    input: "textarea",
    inputLabel: "Motivo del rechazo",
    inputPlaceholder: "Escribe el motivo aquí...",
    inputAttributes: {
      "aria-label": "Escribe el motivo aquí",
    },
    showCancelButton: true,
    confirmButtonText: "Sí, rechazar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    inputValidator: (value) => {
      if (!value) {
        return "¡Necesitas escribir un motivo para el rechazo!";
      }
    },
  });

  if (reason) {
    try {
      await axios.post(`/invoices/${invoice.id}/reject`, { reason });
      toast.success("Factura rechazada con éxito.");
      fetchInvoices();
    } catch (error) {
      console.error(`Error al rechazar la factura ${invoice.id}:`, error);
      toast.error(
        error.response?.data?.message || "No se pudo rechazar la factura."
      );
    }
  }
};

const closeApproveModal = () => {
  isApproveModalVisible.value = false;
  invoiceToApprove.value = null;
  availableDiscounts.value = [];
};
</script>

<template>
  <div>
    <div v-if="currentView === 'list'">
      <InvoiceFilters
        v-model:searchQuery="searchQuery"
        v-model:selectedSupplier="selectedSupplier"
        v-model:selectedStatus="selectedStatus"
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
        actions-mode="approval"
        @update:options="updateTableOptions"
        @edit-invoice="handleViewDetails"
        @approve-invoice="handleApproveInvoice"
        @reject-invoice="handleRejectInvoice"
      />
    </div>

    <div v-else-if="currentView === 'detail'">
      <InvoiceDetailView
        :invoice-id="selectedInvoiceId"
        @back-to-list="handleReturnToList"
      />
    </div>
    <ApproveInvoiceModal
      v-model="isApproveModalVisible"
      :invoice="invoiceToApprove"
      :discounts="availableDiscounts"
      :loading="isApproving"
      @confirm="confirmApproval"
    />
  </div>
</template>
