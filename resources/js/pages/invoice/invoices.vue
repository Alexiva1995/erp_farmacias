<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute } from "vue-router";

import InvoiceFilters from "@/components/InvoiceFilters.vue";
import InvoiceTable from "@/components/InvoiceTable.vue";
import InvoiceDetailView from "@/pages/invoice/invoiceDetails.vue";
import InvoiceForm from "@/pages/invoice/register.vue";

import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";

const route = useRoute();

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

const requiredFields = [
  "supplier_id",
  "invoice_number",
  "control_number",
  "exp_date",
  "payment_date",
  "received_date",
  "created_invoice_date",
  "currency",
  "exempt_amount",
  "taxable_base",
  "tax_amount",
  "total_amount",
];

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
    (key) => (params[key] == null || params[key] === "") && delete params[key],
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

let debounceTimer = null;
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
  { deep: true },
);

watch([searchQuery, selectedSupplier, startDate, endDate], () => {
  if (page.value !== 1) {
    page.value = 1;
  }
});

onMounted(async () => {
  fetchSuppliers();
  const queryId = route.query.id || route.query.invoiceId;
  if (queryId) {
    selectedInvoiceId.value = parseInt(queryId, 10) || queryId;
    currentView.value = "detail";
  } else {
    fetchInvoices();
  }
});

onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
  }
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

const validateInvoiceData = (invoice) => {
  const missingFields = requiredFields.filter((field) => {
    const value = invoice[field];
    return value === null || value === undefined || value === "";
  });

  return {
    isValid: missingFields.length === 0,
    missingFields,
  };
};

const handleEditInvoice = async (invoice) => {
  const validation = validateInvoiceData(invoice);

  if (!validation.isValid) {
    const result = await Swal.fire({
      title: "Faltan datos necesarios",
      text: "Por favor editar y completar la factura seleccionada. ¿Desea ir al formulario?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#7367f0",
      cancelButtonColor: "#a8aaae",
      cancelButtonText: "Rechazar",
      confirmButtonText: "Confirmar",
      reverseButtons: true,
    });

    if (result.isConfirmed) {
      handleEditInvoiceForm(invoice);
      return;
    }
    return;
  }

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
    confirmButtonColor: "#ea5455",
    cancelButtonColor: "#a8aaae",
    cancelButtonText: "Cancelar",
    confirmButtonText: "Sí, eliminar",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/invoices/${id}`);
      toast.success("Factura eliminada con éxito.");
      fetchInvoices();
    } catch (error) {
      toast.error(
        error.response?.data?.message || "No se pudo eliminar la factura.",
      );
    }
  }
};

const isBulkDeleteDialogVisible = ref(false);
const bulkDeleteBeforeDate = ref(null);
const isSubmittingBulkDelete = ref(false);

const handleOpenBulkDeleteModal = () => {
  bulkDeleteBeforeDate.value = null;
  isBulkDeleteDialogVisible.value = true;
};

const handleConfirmBulkDelete = async () => {
  if (!bulkDeleteBeforeDate.value) {
    toast.error("Por favor selecciona una fecha límite.");
    return;
  }

  const result = await Swal.fire({
    title: "¿Confirmar eliminación masiva?",
    text: `Todas las facturas registradas hasta el ${bulkDeleteBeforeDate.value} pasarán a estado 'eliminadas' y ya no aparecerán en el sistema.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ea5455",
    cancelButtonColor: "#a8aaae",
    cancelButtonText: "Cancelar",
    confirmButtonText: "Sí, eliminar masivamente",
    reverseButtons: true,
  });

  if (!result.isConfirmed) return;

  isSubmittingBulkDelete.value = true;
  try {
    const response = await axios.post("/invoices/bulk-delete", {
      before_date: bulkDeleteBeforeDate.value,
    });
    toast.success(response.data.message || "Eliminación masiva completada.");
    isBulkDeleteDialogVisible.value = false;
    fetchInvoices();
  } catch (error) {
    toast.error(
      error.response?.data?.message || "Error al ejecutar la eliminación masiva.",
    );
  } finally {
    isSubmittingBulkDelete.value = false;
  }
};

const isSyncingDronena = ref(false);
const isSyncingMafarta = ref(false);
const isSyncingCristmedicals = ref(false);

const handleSyncDronena = async () => {
  const confirmResult = await Swal.fire({
    title: "¿Sincronizar con Dronena?",
    text: "Se consultará el portal de Dronena para actualizar las fechas de vencimiento, fechas de pago e indexación (FA$) de las facturas.",
    icon: "info",
    showCancelButton: true,
    confirmButtonText: "Sincronizar",
    cancelButtonText: "Cancelar",
  });

  if (!confirmResult.isConfirmed) return;

  isSyncingDronena.value = true;
  try {
    const response = await axios.post("/invoices/sync-dronena");
    toast.success(response.data.message || "Sincronización completada exitosamente.");
    await fetchInvoices();
  } catch (error) {
    console.error("Error al sincronizar con Dronena:", error);
    toast.error(error.response?.data?.message || "Ocurrió un error al sincronizar con Dronena.");
  } finally {
    isSyncingDronena.value = false;
  }
};

const handleSyncMafarta = async () => {
  const confirmResult = await Swal.fire({
    title: "¿Sincronizar con Mafarta / Cobeca?",
    text: "Se consultará el portal SIC de Cobeca para actualizar las facturas indexadas (amarillas/vencidas), fechas de vencimiento y números de control oficial.",
    icon: "info",
    showCancelButton: true,
    confirmButtonText: "Sincronizar",
    cancelButtonText: "Cancelar",
  });

  if (!confirmResult.isConfirmed) return;

  isSyncingMafarta.value = true;
  try {
    const response = await axios.post("/invoices/sync-mafarta");
    toast.success(response.data.message || "Sincronización con Mafarta completada exitosamente.");
    await fetchInvoices();
  } catch (error) {
    console.error("Error al sincronizar con Mafarta:", error);
    toast.error(error.response?.data?.message || "Ocurrió un error al sincronizar con Mafarta.");
  } finally {
    isSyncingMafarta.value = false;
  }
};

const handleSyncCristmedicals = async () => {
  const confirmResult = await Swal.fire({
    title: "¿Sincronizar con Cristmedicals?",
    text: "Se consultará el portal de Cristmedicals para actualizar vencimientos, saldos con descuento y montos reales a pagar en Bs.",
    icon: "info",
    showCancelButton: true,
    confirmButtonText: "Sincronizar",
    cancelButtonText: "Cancelar",
  });

  if (!confirmResult.isConfirmed) return;

  isSyncingCristmedicals.value = true;
  try {
    const response = await axios.post("/invoices/sync-cristmedicals");
    toast.success(response.data.message || "Sincronización con Cristmedicals completada exitosamente.");
    await fetchInvoices();
  } catch (error) {
    console.error("Error al sincronizar con Cristmedicals:", error);
    toast.error(error.response?.data?.message || "Ocurrió un error al sincronizar con Cristmedicals.");
  } finally {
    isSyncingCristmedicals.value = false;
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
        :show-bulk-delete="true"
        :show-sync-dronena="true"
        :is-syncing-dronena="isSyncingDronena"
        :show-sync-mafarta="true"
        :is-syncing-mafarta="isSyncingMafarta"
        :show-sync-cristmedicals="true"
        :is-syncing-cristmedicals="isSyncingCristmedicals"
        @clear="handleClearFilters"
        @create-invoice="handleCreateInvoice"
        @bulk-delete="handleOpenBulkDeleteModal"
        @sync-dronena="handleSyncDronena"
        @sync-mafarta="handleSyncMafarta"
        @sync-cristmedicals="handleSyncCristmedicals"
        class="mb-6"
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
        @photo-updated="fetchInvoices"
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

    <!-- Diálogo de Eliminación Masiva por Fecha -->
    <VDialog v-model="isBulkDeleteDialogVisible" max-width="500px">
      <VCard class="rounded-lg">
        <VCardTitle class="d-flex justify-space-between align-center py-3 bg-surface">
          <span class="text-h6 font-weight-bold text-error">Eliminación Masiva por Fecha</span>
          <VBtn icon="tabler-x" variant="text" size="small" @click="isBulkDeleteDialogVisible = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <p class="text-sm text-medium-emphasis mb-4">
            Selecciona la fecha límite. <strong>Todas las facturas registradas o emitidas desde esa fecha hacia atrás</strong> cambiarán su estado a <code>eliminadas</code> y ya no aparecerán en ninguna vista.
          </p>
          <AppDateTimePicker
            v-model="bulkDeleteBeforeDate"
            placeholder="Fecha Límite (Hasta esta fecha)"
            clearable
            density="compact"
            :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            prepend-inner-icon="tabler-calendar-event"
          />
        </VCardText>
        <VDivider />
        <VCardActions class="pa-3 bg-surface">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" class="rounded-lg" @click="isBulkDeleteDialogVisible = false">
            Cancelar
          </VBtn>
          <VBtn
            color="error"
            variant="flat"
            class="rounded-lg"
            :loading="isSubmittingBulkDelete"
            @click="handleConfirmBulkDelete"
          >
            Confirmar Eliminación
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
