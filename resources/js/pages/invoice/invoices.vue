<script setup>
import { onMounted, ref, watch } from "vue";

import InvoiceFilters from "@/components/InvoiceFilters.vue";
import InvoiceTable from "@/components/InvoiceTable.vue";
import InvoiceDetailView from "@/pages/invoice/invoiceDetails.vue";
import InvoiceForm from "@/pages/invoice/register.vue";

import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";

const currentView = ref("list");
const selectedInvoiceId = ref(null);
const selectedInvoiceSupplierId = ref(null);

const invoices = ref([]);
const totalInvoices = ref(0);
const loading = ref(false);
const suppliers = ref([]);
const isLoadingFilters = ref(false);

const supplierDiscounts = ref([]);
const loadingDiscounts = ref(false);

const searchQuery = ref("");
const selectedSupplier = ref(null);
const startDate = ref(null);
const endDate = ref(null);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

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
    status: "pending",
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

const fetchSupplierDiscounts = async (supplierId) => {
  if (!supplierId) {
    supplierDiscounts.value = [];
    return;
  }
  loadingDiscounts.value = true;
  try {
    const response = await axios.get(`/suppliers/${supplierId}/discounts`);
    supplierDiscounts.value = response.data.supplier_discount ?? [];
  } catch (error) {
    supplierDiscounts.value = [];
  } finally {
    loadingDiscounts.value = false;
  }
};

const handleEditInvoice = async (invoice) => {
  selectedInvoiceId.value = invoice.id;
  selectedInvoiceSupplierId.value = invoice.supplier_id;

  await fetchSupplierDiscounts(invoice.supplier_id);

  currentView.value = "detail";
};

const handleEditInvoiceForm = (invoice) => {
  selectedInvoiceId.value = invoice.id;
  currentView.value = "edit-form";
};

const handleCreateInvoice = () => {
  selectedInvoiceId.value = null;
  currentView.value = "create-form";
};

const handleReturnToList = () => {
  selectedInvoiceId.value = null;
  selectedInvoiceSupplierId.value = null;
  supplierDiscounts.value = [];
  currentView.value = "list";
  fetchInvoices();
};

const handleDeleteInvoice = async (id) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de esta factura!",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Sí, eliminar",
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
      await axios.delete(`/invoices/${id}`);
      toast.success("Factura eliminada con éxito.");
      fetchInvoices();
    } catch (error) {
      toast.error(
        error.response?.data?.message || "No se pudo eliminar la factura."
      );
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
        @create-invoice="handleCreateInvoice"
      />

      <InvoiceTable
        :invoices="invoices"
        :loading="loading"
        :total-invoices="totalInvoices"
        :items-per-page="itemsPerPage"
        :page="page"
        @update:options="updateTableOptions"
        @edit-invoice="handleEditInvoice"
        @edit-invoice-form="handleEditInvoiceForm"
        @delete-invoice="handleDeleteInvoice"
      />
    </div>

    <div v-else-if="currentView === 'detail'">
      <InvoiceDetailView
        :invoice-id="selectedInvoiceId"
        :supplier-discounts="supplierDiscounts"
        mode="editable"
        @back-to-list="handleReturnToList"
      />
    </div>

    <!-- Vista unificada para crear y editar -->
    <div v-else-if="currentView === 'create-form'">
      <InvoiceForm
        @back-to-list="handleReturnToList"
        @invoice-saved="handleReturnToList"
      />
    </div>

    <div v-else-if="currentView === 'edit-form'">
      <InvoiceForm
        :invoice-id="selectedInvoiceId"
        :is-edit-mode="true"
        @back-to-list="handleReturnToList"
        @invoice-saved="handleReturnToList"
      />
    </div>
  </div>
</template>
