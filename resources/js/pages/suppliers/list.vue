<script setup>
import SupplierCommercialPanel from "@/components/dialogs/SupplierCommercialPanel.vue";
import SupplierConnectionDialog from "@/components/dialogs/SupplierConnectionDialog.vue";
import SupplierEditDialog from "@/components/dialogs/SupplierEditDialog.vue";
import SupplierFilters from "@/components/SupplierFilters.vue";
import SupplierStatsCards from "@/components/SupplierStatsCards.vue";
import SupplierTable from "@/components/SupplierTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useSupplierConnectionStore } from "@/stores/supplierConnection";
import Swal from "sweetalert2";
import { onMounted, onUnmounted, ref, watch, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
const authStore = useAuthStore();
const suppliers = ref([]);
const totalSupplier = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();
const searchQuery = ref("");
const debtFilter = ref(null);
const minScore = ref(null);
const typeFilter = ref(null);

const stats = ref({
  total_debt: 0,
  active_suppliers_count: 0,
  connection_success_rate: 100,
});
const isLoadingStats = ref(false);

// Estado para el diálogo de configuración de conexión FTP/API
const isConnectionDialogVisible = ref(false);
const connectionSupplier = ref({});

const laboratories = ref([]);
const discountRules = ref([]);
const paymentRules = ref([]);
const supplierDiscount = ref([]);

const isLoadingFilters = ref(false);

const currentSupplier = ref({});
const supplierFormErrors = ref({});
const isEditDialogVisible = ref(false);
const isCommercialPanelVisible = ref(false);

const checkingApiSupplierId = ref(null);

const router = useRouter();

const fetchStats = async () => {
  isLoadingStats.value = true;
  try {
    const response = await axios.get("/suppliers/stats");
    stats.value = response.data;
  } catch (error) {
    console.error("Error al cargar estadísticas:", error);
  } finally {
    isLoadingStats.value = false;
  }
};

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse] = await Promise.all([axios.get("/laboratories")]);
    laboratories.value = labResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchSuppliers = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    debtStatus: debtFilter.value,
    minScore: minScore.value,
    type: typeFilter.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/suppliers", { params });
    suppliers.value = response.data.data;
    totalSupplier.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los proveedores:", error);
    toast.error("Error al obtener los proveedores.");
  } finally {
    loading.value = false;
  }
};

const fetchDiscountRules = async () => {
  try {
    const { data } = await axios.get(
      `/supplier-laboratories/${currentSupplier.value.id}/discount-rules`,
    );
    discountRules.value = data.discount_rules;
  } catch (error) {
    toast.error("Error al cargar las reglas de descuento");
  } finally {
    loading.value = false;
  }
};

const fetchPaymentRules = async () => {
  try {
    const { data } = await axios.get(
      `/suppliers/${currentSupplier.value.id}/payment-rules`,
    );
    paymentRules.value = data.payment_rules;
  } catch (error) {
    toast.error("Error al cargar las reglas de pronto pago");
  } finally {
    loading.value = false;
  }
};

const fetchSupplierDiscount = async () => {
  try {
    const { data } = await axios.get(
      `/suppliers/${currentSupplier.value.id}/discounts`,
    );
    supplierDiscount.value = data.supplier_discount;
  } catch (error) {
    toast.error("Error al cargar los descuentos");
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchStats();
  fetchSelectOptions();
  fetchSuppliers();
});

const handleClearFilters = () => {
  searchQuery.value = "";
  debtFilter.value = null;
  minScore.value = null;
  typeFilter.value = null;
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const handleAddSupplier = (type = "drogueria") => {
  currentSupplier.value = { type };
  supplierFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleSaveSupplier = async (supplierFormData) => {
  const isNewSupplier = !currentSupplier.value.id;
  const url = isNewSupplier
    ? "/suppliers"
    : `/suppliers/${currentSupplier.value.id}`;

  const payloadKeys = Object.keys(supplierFormData);
  if (!isNewSupplier && payloadKeys.length === 0) {
    toast.info("No se realizaron cambios en el proveedor.");
    return;
  }

  try {
    const payload = { ...supplierFormData };

    if (!isNewSupplier) {
      payload._method = "PUT";
    }

    await axios.post(url, payload);

    toast.success(
      `Proveedor ${isNewSupplier ? "creado" : "actualizado"} con éxito`,
    );
    isEditDialogVisible.value = false;
    await fetchSuppliers();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      supplierFormErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar/crear el proveedor:", error);
      toast.error("Hubo un error al guardar el proveedor.");
    }
  }
};

const handleEditSupplier = (supplier) => {
  currentSupplier.value = { ...supplier };
  supplierFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleDeleteSupplier = async (id) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de este proveedor!",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/suppliers/${id}`);
      toast.success("Proveedor eliminado con éxito.");
      fetchSuppliers();
    } catch (error) {
      console.error(`Error al borrar el proveedor ${id}:`, error);
      toast.error("No se pudo eliminar el proveedor.");
    }
  }
};

const supplierConnectionStore = useSupplierConnectionStore();

const handleCheckSupplierApi = async (supplier) => {
  checkingApiSupplierId.value = supplier.id;

  try {
    toast.info(
      `Procesando los datos de ${supplier.name}, le notificaremos al finalizar`,
    );
    await axios.get(`/suppliers/${supplier.id}/connection`);
    supplierConnectionStore.startConnection();
  } catch (error) {
    const errorDetail = error?.response?.data?.message || error?.message || "";
    toast.error(`No se pudo iniciar la conexión con ${supplier.name}${errorDetail ? `: ${errorDetail}` : ""}`);
  } finally {
    checkingApiSupplierId.value = null;
  }
};

const handleSyncDronenaBot = async (supplier) => {
  const result = await Swal.fire({
    title: "¿Sincronizar Facturas con Dronena?",
    text: `Se extraerán las facturas, fechas de vencimiento, fechas de pago e indexación desde Dronena para ${supplier.name}.`,
    icon: "info",
    showCancelButton: true,
    confirmButtonText: "Sincronizar Ahora",
    cancelButtonText: "Cancelar",
  });

  if (!result.isConfirmed) return;

  checkingApiSupplierId.value = supplier.id;
  try {
    const response = await axios.post("/invoices/sync-dronena", {
      supplier_id: supplier.id,
    });
    toast.success(response.data.message || "Sincronización de facturas completada.");
  } catch (error) {
    console.error("Error al sincronizar con Dronena:", error);
    toast.error(error.response?.data?.message || "Ocurrió un error durante la sincronización.");
  } finally {
    checkingApiSupplierId.value = null;
  }
};

// Abre el diálogo de configuración de conexión FTP/API
const handleConfigConnection = (supplier) => {
  connectionSupplier.value = { ...supplier };
  isConnectionDialogVisible.value = true;
};

const handleCommercialPanel = async (supplier) => {
  currentSupplier.value = { ...supplier };
  supplierFormErrors.value = {};
  isCommercialPanelVisible.value = true;
  loading.value = true;

  try {
    await Promise.all([
      fetchPaymentRules(),
      fetchSupplierDiscount(),
      fetchDiscountRules(),
    ]);
  } catch (error) {
    console.error("Error al cargar datos comerciales:", error);
    toast.error("Error al cargar la configuración comercial.");
  } finally {
    loading.value = false;
  }
};

const handleSavePaymentRule = async (paymentRuleFormData) => {
  const url = `/suppliers/${currentSupplier.value.id}/payment-rules`;
  try {
    const payload = { rules: paymentRuleFormData };
    await axios.post(url, payload);
    toast.success(`Reglas de pronto pago guardadas`);
    await fetchPaymentRules();
  } catch (error) {
    console.error("Error al guardar reglas de pago:", error);
    toast.error("Error al guardar reglas de pago.");
  }
};

const handleSaveDiscountRules = async (formData) => {
  const url = `/supplier-laboratories/${currentSupplier.value.id}/discount-rules`;
  try {
    const payload = { rules: formData };
    await axios.post(url, payload);
    toast.success("Escalas de descuento guardadas");
    await fetchDiscountRules();
  } catch (error) {
    console.error("Error al guardar escalas:", error);
    toast.error("Error al guardar escalas.");
  }
};

const handleSaveSupplierDiscount = async (supplierDiscountFormData) => {
  const url = `/suppliers/${currentSupplier.value.id}/discounts`;
  try {
    const payload = { discounts: supplierDiscountFormData };
    await axios.post(url, payload);
    toast.success(`Descuentos guardados con éxito`);
    await fetchSupplierDiscount();
  } catch (error) {
    console.error("Error al guardar descuentos:", error);
    toast.error("Error al guardar descuentos.");
  }
};

const handleSupplierPendingInvoices = async (supplier) => {
  await router.push({
    name: "finances-pending-payments",
    query: { supplierId: supplier.id },
  });
};

const clearFormErrors = () => {
  supplierFormErrors.value = {};
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, debtFilter, minScore, typeFilter],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchSuppliers(), 300);
  },
  { deep: true },
);

watch([searchQuery, debtFilter, minScore, typeFilter], () => {
  page.value = 1;
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;

  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  }
};

onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
  }
});
</script>

<template>
  <div class="suppliers-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <SupplierFilters
        v-model:searchQuery="searchQuery"
        v-model:debtFilter="debtFilter"
        v-model:minScore="minScore"
        v-model:type="typeFilter"
        @clear="handleClearFilters"
        @sort="handleSort"
        @add-supplier="handleAddSupplier"
      />

      <SupplierStatsCards
        v-if="authStore.isAdmin"
        :stats="stats"
        :loading="isLoadingStats"
        class="mt-0"
      />

      <SupplierTable
        :suppliers="suppliers"
        :loading="loading"
        :total-supplier="totalSupplier"
        :items-per-page="itemsPerPage"
        :page="page"
        :sort-by="sortBy"
        :order-by="orderBy"
        :checking-api-id="checkingApiSupplierId"
        @update:options="updateTableOptions"
        @edit-supplier="handleEditSupplier"
        @delete-supplier="handleDeleteSupplier"
        @commercial-panel="handleCommercialPanel"
        @supplier-pending-invoices="handleSupplierPendingInvoices"
        @check-supplier-api="handleCheckSupplierApi"
        @config-connection="handleConfigConnection"
        @sync-dronena-bot="handleSyncDronenaBot"
      />

      <SupplierEditDialog
        v-model="isEditDialogVisible"
        :supplier="currentSupplier"
        :errors="supplierFormErrors"
        @save="handleSaveSupplier"
        @clear-errors="clearFormErrors"
      />

      <SupplierCommercialPanel
        v-model="isCommercialPanelVisible"
        :supplier="currentSupplier"
        :laboratories="laboratories"
        :supplier-discount="supplierDiscount"
        :discount-rules="discountRules"
        :loading="loading"
        :errors="supplierFormErrors"
        @save-payment-rules="handleSavePaymentRule"
        @save-discounts="handleSaveSupplierDiscount"
        @save-discount-rules="handleSaveDiscountRules"
        @clear-errors="clearFormErrors"
      />

      <!-- Diálogo de Configuración de Conexión FTP/API -->
      <SupplierConnectionDialog
        v-model="isConnectionDialogVisible"
        :supplier="connectionSupplier"
        @saved="fetchSuppliers"
      />
    </div>
  </div>
</template>

<style scoped>
.letter-spacing-tight {
  letter-spacing: -0.02em;
}
.letter-spacing-widest {
  letter-spacing: 0.1em !important;
}
.shadow-soft {
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important;
}
</style>
