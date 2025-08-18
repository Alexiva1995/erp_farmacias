<script setup>
import ApproveInvoiceModal from "@/components/dialogs/ApproveInvoiceModal.vue";
import InvoiceFilters from "@/components/InvoiceFilters.vue";
import InvoiceTable from "@/components/InvoiceTable.vue";
import InvoiceDetailView from "@/pages/invoice/invoiceDetails.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

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
const availablePaymentRules = ref([]);
const isApproving = ref(false);
const invoiceDetails = ref([]);
const exchangeRates = ref([]);

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

const fetchExchangeRates = async () => {
  try {
    const response = await axios.get("/finances/exchange-rates");
    exchangeRates.value = response.data.data ?? response.data ?? [];
  } catch (error) {
    console.error("Error al cargar los tipos de cambio:", error);
    toast.error("No se pudieron cargar los tipos de cambio.");
    exchangeRates.value = [];
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

const fetchPaymentRulesForSupplier = async (supplierId) => {
  if (!supplierId) {
    availablePaymentRules.value = [];
    return;
  }
  try {
    const response = await axios.get(`/suppliers/${supplierId}/payment-rules`);
    availablePaymentRules.value = response.data.data || response.data || [];
  } catch (error) {
    console.error("Error al obtener las reglas de pago del proveedor:", error);
    toast.error("No se pudieron cargar las reglas de pago del proveedor.");
    availablePaymentRules.value = [];
  }
};

const fetchDiscountsForSupplier = async (supplierId) => {
  if (!supplierId) {
    availableDiscounts.value = [];
    return;
  }
  try {
    const response = await axios.get(`/suppliers/${supplierId}/discounts`);
    availableDiscounts.value = response.data.supplier_discount || [];
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
  fetchExchangeRates();
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
  selectedInvoiceId.value = invoice.id;
  currentView.value = "detail";
};

const handleReturnToList = () => {
  selectedInvoiceId.value = null;
  currentView.value = "list";
  fetchInvoices();
};

const handleApproveInvoice = async (invoice) => {
  invoiceToApprove.value = invoice;
  isApproving.value = true;

  availableDiscounts.value = [];
  availablePaymentRules.value = [];
  invoiceDetails.value = [];

  try {
    const promises = [
      axios
        .get(`/suppliers/${invoice.supplier_id}/discounts`)
        .then((response) => {
          availableDiscounts.value = response.data.supplier_discount || [];
        })
        .catch((error) => {
          console.warn(
            "No se pudieron cargar los descuentos del proveedor:",
            error
          );
          availableDiscounts.value = [];
        }),

      axios
        .get(`/suppliers/${invoice.supplier_id}/payment-rules`)
        .then((response) => {
          availablePaymentRules.value =
            response.data.payment_rules ||
            response.data.data ||
            response.data ||
            [];
        })
        .catch((error) => {
          console.warn(
            "No se pudieron cargar las reglas de pago del proveedor:",
            error
          );
          availablePaymentRules.value = [];
        }),

      axios.get(`/invoices/${invoice.id}/details`).then((response) => {
        invoiceDetails.value = response.data.data || [];
      }),
    ];

    await Promise.all(promises);

    isApproveModalVisible.value = true;
  } catch (error) {
    console.error(
      "Error crítico al preparar la aprobación de la factura:",
      error
    );
    toast.error("No se pudieron cargar los detalles de la factura.");
  } finally {
    isApproving.value = false;
  }
};

const confirmApproval = async ({
  invoiceId,
  discountId,
  paymentRuleId,
  returnItems,
}) => {
  isApproving.value = true;

  const payload = {
    supplier_discount_id: discountId,
    payment_rule_id: paymentRuleId,
    return_item_ids: returnItems,
  };

  try {
    await axios.post(`/invoices/${invoiceId}/approve`, payload);

    toast.success("Factura aprobada con éxito (con posibles devoluciones).");
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
  const result = await Swal.fire({
    title: "Rechazar Factura",
    text: `¿Estás seguro de que deseas RECHAZAR la factura N° ${invoice.invoice_number}?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, rechazar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#d33",
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
      await axios.post(`/invoices/${invoice.id}/reject`, {
        reason: "Factura rechazada desde la interfaz",
      });
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
  availablePaymentRules.value = [];
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
        :exchange-rates="exchangeRates"
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
        :exchange-rates="exchangeRates"
        @back-to-list="handleReturnToList"
        mode="read-only"
      />
    </div>

    <ApproveInvoiceModal
      v-model="isApproveModalVisible"
      :invoice="invoiceToApprove"
      :discounts="availableDiscounts"
      :payment-rules="availablePaymentRules"
      :details="invoiceDetails"
      :exchange-rates="exchangeRates"
      :loading="isApproving"
      @confirm="confirmApproval"
    />
  </div>
</template>
