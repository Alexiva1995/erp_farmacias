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

const fetchInvoicesForLocation = async () => {
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
    status: "to_order",
  };

  Object.keys(params).forEach(
    (key) => (params[key] == null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/invoices", { params });
    invoices.value = response.data.data;
    totalInvoices.value = response.data.total;
  } catch (error) {
    toast.error("Error al obtener las facturas para ubicar.");
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
      debounceTimer = setTimeout(() => fetchInvoicesForLocation(), 300);
    }
  },
  { deep: true },
);

onMounted(() => {
  fetchSuppliers();
  fetchInvoicesForLocation();
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

const handleLocateProducts = (invoice) => {
  selectedInvoiceId.value = invoice.id;
  selectedInvoice.value = invoice;
  currentView.value = "detail";
};

const handleReturnToList = () => {
  selectedInvoiceId.value = null;
  currentView.value = "list";
  fetchInvoicesForLocation();
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
        fetchInvoicesForLocation();
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
        class="mb-6"
      />

      <InvoiceTable
        :invoices="invoices"
        :loading="loading"
        :total-invoices="totalInvoices"
        :items-per-page="itemsPerPage"
        :page="page"
        :is-admin="isAdmin"
        :highlighted-id="selectedInvoiceId"
        actions-mode="location"
        @update:options="updateTableOptions"
        @locate-products="handleLocateProducts"
        @return-invoice="handleReturnInvoice"
      />
    </div>

    <div v-else-if="currentView === 'detail'">
      <InvoiceDetailView
        :invoice-id="selectedInvoiceId"
        :initial-invoice="selectedInvoice"
        mode="location"
        @back-to-list="handleReturnToList"
      />
    </div>
  </div>
</template>
