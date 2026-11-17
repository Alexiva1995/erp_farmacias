<script setup>
import SupplierCommercialPanel from "@/components/dialogs/SupplierCommercialPanel.vue";
import SupplierConnectionDialog from "@/components/dialogs/SupplierConnectionDialog.vue";
import SupplierConnectionHistoryDialog from "@/components/dialogs/SupplierConnectionHistoryDialog.vue";
import SupplierEditDialog from "@/components/dialogs/SupplierEditDialog.vue";
import SupplierMergeDialog from "@/components/dialogs/SupplierMergeDialog.vue";
import MasterSupplierDirectoryDialog from "@/components/dialogs/MasterSupplierDirectoryDialog.vue";
import ShowSupplierProductsDialog from "@/components/dialogs/ShowSupplierProductsDialog.vue";
import SupplierFilters from "@/components/SupplierFilters.vue";
import SupplierStatsCards from "@/components/SupplierStatsCards.vue";
import SupplierTable from "@/components/SupplierTable.vue";
import axios from "@/plugins/axios";
import { toast, confirmDialog } from "@/plugins/sweetalert";
import { useSupplierConnectionStore } from "@/stores/supplierConnection";
import Swal from "sweetalert2";
import { onMounted, onUnmounted, ref, watch, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const authStore = useAuthStore();

const props = defineProps({
  isMaster: {
    type: Boolean,
    default: false,
  },
});

const route = useRoute();
const isMasterMode = computed(() => props.isMaster || (route.path && route.path.startsWith("/master")));
const masterRequestConfig = computed(() => (isMasterMode.value ? { headers: { "X-Master-Catalog-Context": "1" } } : {}));

const isSyncingAll = ref(false);
const suppliers = ref([]);
const totalSupplier = ref(0);
const loading = ref(false);
const commercialLoading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();
const searchQuery = ref("");
const debtFilter = ref(null);
const minScore = ref(null);
const typeFilter = ref("drogueria");
const businessTypeFilter = ref("all");
if (isMasterMode.value) {
  sortBy.value = "id";
  orderBy.value = "asc";
}

const stats = ref({
  total_debt: 0,
  active_suppliers_count: 0,
  connection_success_rate: 100,
});
const isLoadingStats = ref(false);

// Estado para diálogos técnicos y catálogo
const isConnectionDialogVisible = ref(false);
const connectionSupplier = ref({});
const isConnectionHistoryDialogVisible = ref(false);
const connectionHistorySupplier = ref({});
const isProductsDialogVisible = ref(false);
const productsDialogSupplier = ref({});

// Estado para fusión y directorio maestro
const isMergeDialogVisible = ref(false);
const isMasterDirectoryDialogVisible = ref(false);
const mergeSupplierTarget = ref({});

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
    const response = await axios.get("/suppliers/stats", masterRequestConfig.value);
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
    type: isMasterMode.value ? "drogueria" : (typeFilter.value || undefined),
    business_type: isMasterMode.value ? (businessTypeFilter.value || "all") : undefined,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value || (isMasterMode.value ? "id" : undefined),
    orderBy: orderBy.value || (isMasterMode.value ? "asc" : undefined),
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );

  try {
    const config = {
      params,
      ...masterRequestConfig.value,
    };
    const response = await axios.get("/suppliers", config);
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
  typeFilter.value = "drogueria";
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const handleStatsCardClick = (action) => {
  if (action === "debt") {
    debtFilter.value = debtFilter.value === "with_debt" ? null : "with_debt";
  } else if (action === "sync") {
    sortBy.value = "last_sync_at";
    orderBy.value = "asc";
  } else if (action === "score") {
    sortBy.value = "latest_score_value";
    orderBy.value = "asc";
  } else if (action === "catalog") {
    debtFilter.value = null;
    minScore.value = null;
    typeFilter.value = "drogueria";
  }
};

const handleAddSupplier = (type = null) => {
  const defaultType = type || typeFilter.value || "drogueria";
  currentSupplier.value = { type: defaultType };
  supplierFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const buildSupplierRequestData = (supplierFormData, isNewSupplier) => {
  if (!(supplierFormData.logo instanceof File)) {
    const payload = { ...supplierFormData };
    if (!isNewSupplier) {
      payload._method = "PUT";
    }
    return payload;
  }

  const fd = new FormData();
  Object.entries(supplierFormData).forEach(([key, value]) => {
    if (value === undefined || value === null) return;

    if (key === "logo") {
      fd.append("logo", value);
    } else if (typeof value === "boolean") {
      fd.append(key, value ? "1" : "0");
    } else if (Array.isArray(value)) {
      value.forEach((item, idx) => {
        fd.append(`${key}[${idx}]`, typeof item === "object" ? JSON.stringify(item) : String(item));
      });
    } else if (typeof value === "object") {
      Object.entries(value).forEach(([subKey, subVal]) => {
        if (Array.isArray(subVal)) {
          subVal.forEach((sv, idx) => fd.append(`${key}[${subKey}][${idx}]`, String(sv)));
        } else if (subVal !== undefined && subVal !== null) {
          fd.append(`${key}[${subKey}]`, String(subVal));
        }
      });
    } else {
      fd.append(key, String(value));
    }
  });

  if (!isNewSupplier) {
    fd.append("_method", "PUT");
  }

  return fd;
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
    const requestData = buildSupplierRequestData(supplierFormData, isNewSupplier);
    const config = { ...masterRequestConfig.value };
    if (requestData instanceof FormData) {
      config.headers = {
        ...(config.headers || {}),
        "Content-Type": "multipart/form-data",
      };
    }
    await axios.post(url, requestData, config);

    toast.success(
      `Proveedor ${isNewSupplier ? "creado" : "actualizado"} con éxito`,
    );
    isEditDialogVisible.value = false;
    await Promise.all([fetchSuppliers(), fetchStats()]);
  } catch (error) {
    if (error.response && error.response.status === 422) {
      supplierFormErrors.value = error.response.data.errors || {};
      toast.error(error.response?.data?.message || "Por favor, corrige los errores en el formulario.");
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

const handleEditExistingFromMaster = async (existingInfo) => {
  if (!existingInfo?.id) return;

  const localMatch = suppliers.value.find((s) => Number(s.id) === Number(existingInfo.id));
  if (localMatch) {
    handleEditSupplier(localMatch);
    return;
  }

  try {
    const response = await axios.get("/suppliers", {
      params: {
        q: existingInfo.rif || existingInfo.name || "",
        itemsPerPage: 25,
      },
      ...masterRequestConfig.value,
    });
    const list = response.data?.data || [];
    const fetchedMatch = list.find((s) => Number(s.id) === Number(existingInfo.id));
    if (fetchedMatch) {
      handleEditSupplier(fetchedMatch);
      return;
    }
  } catch (error) {
    console.warn("No se pudo cargar el proveedor existente:", error);
  }

  isEditDialogVisible.value = false;
  searchQuery.value = existingInfo.rif || existingInfo.name || "";
};

const handleDeleteSupplier = async (id) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "Si eliminas este proveedor y deseas recuperarlo luego, podrás volver a vincularlo desde el buscador del catálogo.",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/suppliers/${id}`, masterRequestConfig.value);
      toast.success("Proveedor eliminado con éxito.");
      await Promise.all([fetchSuppliers(), fetchStats()]);
    } catch (error) {
      console.error(`Error al borrar el proveedor ${id}:`, error);
      const serverMsg =
        error.response?.data?.errors?.supplier?.[0] ||
        error.response?.data?.message ||
        "No se pudo eliminar el proveedor.";
      toast.error(serverMsg);
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
    await axios.get(`/suppliers/${supplier.id}/connection`, masterRequestConfig.value);
    supplierConnectionStore.startConnection();
  } catch (error) {
    const errorDetail = error?.response?.data?.message || error?.message || "";
    toast.error(`No se pudo iniciar la conexión con ${supplier.name}${errorDetail ? `: ${errorDetail}` : ""}`);
  } finally {
    checkingApiSupplierId.value = null;
  }
};

// Abre el diálogo de configuración de conexión FTP/API/Excel
const handleConfigConnection = (supplier) => {
  connectionSupplier.value = { ...supplier };
  isConnectionDialogVisible.value = true;
};

// Abre el historial de conexiones y facturas
const handleViewConnectionHistory = (supplier) => {
  connectionHistorySupplier.value = { ...supplier };
  isConnectionHistoryDialogVisible.value = true;
};

// Abre el diálogo del catálogo de productos del proveedor
const handleViewProducts = (supplier) => {
  productsDialogSupplier.value = { ...supplier };
  isProductsDialogVisible.value = true;
};

const handleCommercialPanel = async (supplier) => {
  currentSupplier.value = { ...supplier };
  supplierFormErrors.value = {};
  isCommercialPanelVisible.value = true;
  commercialLoading.value = true;

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
    commercialLoading.value = false;
  }
};

const handleSavePaymentRule = async (paymentRuleFormData) => {
  const url = `/suppliers/${currentSupplier.value.id}/payment-rules`;
  commercialLoading.value = true;
  try {
    await axios.post(url, { rules: paymentRuleFormData });
    toast.success("Reglas de pronto pago guardadas");
    await Promise.all([fetchPaymentRules(), fetchSuppliers(), fetchStats()]);
  } catch (error) {
    console.error("Error al guardar reglas de pago:", error);
    toast.error(error.response?.data?.message || "Error al guardar reglas de pago.");
  } finally {
    commercialLoading.value = false;
  }
};

const handleSaveDiscountRules = async (formData) => {
  const url = `/supplier-laboratories/${currentSupplier.value.id}/discount-rules`;
  commercialLoading.value = true;
  try {
    await axios.post(url, { rules: formData });
    toast.success("Escalas de descuento guardadas");
    await Promise.all([fetchDiscountRules(), fetchSuppliers(), fetchStats()]);
  } catch (error) {
    console.error("Error al guardar escalas:", error);
    toast.error(error.response?.data?.message || "Error al guardar escalas.");
  } finally {
    commercialLoading.value = false;
  }
};

const handleSaveSupplierDiscount = async (supplierDiscountFormData) => {
  const url = `/suppliers/${currentSupplier.value.id}/discounts`;
  commercialLoading.value = true;
  try {
    await axios.post(url, { discounts: supplierDiscountFormData });
    toast.success("Descuentos guardados con éxito");
    await Promise.all([fetchSupplierDiscount(), fetchSuppliers(), fetchStats()]);
  } catch (error) {
    console.error("Error al guardar descuentos:", error);
    toast.error(error.response?.data?.message || "Error al guardar descuentos.");
  } finally {
    commercialLoading.value = false;
  }
};

const handleSaveAllCommercial = async ({ paymentRules: pr, discountRules: dr, discounts: d, supplierSettings }) => {
  commercialLoading.value = true;
  supplierFormErrors.value = {};
  const tasks = [];

  if (pr !== undefined) {
    tasks.push(axios.post(`/suppliers/${currentSupplier.value.id}/payment-rules`, { rules: pr }));
  }
  if (dr !== undefined && Array.isArray(dr) && dr.length > 0) {
    tasks.push(axios.post(`/supplier-laboratories/${currentSupplier.value.id}/discount-rules`, { rules: dr }));
  }
  if (d !== undefined) {
    tasks.push(axios.post(`/suppliers/${currentSupplier.value.id}/discounts`, { discounts: d }));
  }
  if (supplierSettings !== undefined) {
    tasks.push(axios.post(`/suppliers/${currentSupplier.value.id}`, { ...supplierSettings, _method: "PUT" }));
  }

  if (tasks.length === 0) {
    commercialLoading.value = false;
    toast.info("No hay cambios pendientes para guardar.");
    return;
  }

  try {
    await Promise.all(tasks);
    if (supplierSettings !== undefined) {
      currentSupplier.value = {
        ...currentSupplier.value,
        ...supplierSettings,
      };
    }
    toast.success("Configuración comercial guardada con éxito");
    await Promise.all([
      fetchPaymentRules(),
      fetchSupplierDiscount(),
      fetchDiscountRules(),
      fetchSuppliers(),
      fetchStats(),
    ]);
    const refreshed = suppliers.value.find((s) => Number(s.id) === Number(currentSupplier.value.id));
    if (refreshed) {
      currentSupplier.value = { ...refreshed };
    }
  } catch (error) {
    if (error.response && error.response.status === 422) {
      supplierFormErrors.value = error.response.data.errors || {};
      toast.error(error.response?.data?.message || "Verifica los campos marcados en la configuración comercial.");
    } else {
      console.error("Error al guardar configuración comercial:", error);
      toast.error("Error al guardar la configuración comercial.");
    }
  } finally {
    commercialLoading.value = false;
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

const handleMergeSupplier = (supplier) => {
  if (!isMasterMode.value) return;
  mergeSupplierTarget.value = { ...supplier };
  isMergeDialogVisible.value = true;
};

const handleSyncFromAllTenants = async () => {
  const isConfirmed = await confirmDialog({
    title: "¿Consolidar Proveedores al Master?",
    text: "Se extraerán e integrarán los proveedores y estructuras técnicas de Excel/conexiones de todos los tenants hacia tovaerp_master.",
    confirmButtonText: "Sí, Sincronizar Todo",
    cancelButtonText: "Cancelar",
  });

  if (!isConfirmed) return;

  isSyncingAll.value = true;
  try {
    const response = await axios.post("/master-suppliers/sync-from-all-tenants", {
      overwrite: true,
      propagate: false,
    });

    toast.success(response.data?.message || "Catálogo maestro consolidado exitosamente.");
    await fetchSuppliers();
  } catch (error) {
    console.error("Error al sincronizar tenants al master:", error);
    toast.error(error.response?.data?.message || "Ocurrió un error al consolidar el catálogo maestro.");
  } finally {
    isSyncingAll.value = false;
  }
};

const handleSupplierActivatedFromMaster = async () => {
  await Promise.all([fetchSuppliers(), fetchStats()]);
};

const handleSupplierMerged = async () => {
  await Promise.all([fetchSuppliers(), fetchStats()]);
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, debtFilter, minScore, typeFilter, businessTypeFilter],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchSuppliers(), 300);
  },
  { deep: true },
);

watch([searchQuery, debtFilter, minScore, typeFilter, businessTypeFilter], () => {
  page.value = 1;
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;

  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  } else {
    sortBy.value = undefined;
    orderBy.value = undefined;
  }
};

onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
  }
});
defineExpose({ fetchSuppliers, fetchStats });
</script>

<template>
  <div class="suppliers-view pb-12">
    <div class="d-flex flex-column gap-3 mt-1">
      <SupplierFilters
        v-model:searchQuery="searchQuery"
        v-model:debtFilter="debtFilter"
        v-model:minScore="minScore"
        v-model:type="typeFilter"
        v-model:businessType="businessTypeFilter"
        @clear="handleClearFilters"
        @sort="handleSort"
        @add-supplier="handleAddSupplier"
        :is-master="isMasterMode"
        :is-syncing-master="isSyncingAll"
        @open-master-catalog="isMasterDirectoryDialogVisible = true"
        @sync-tenants-to-master="handleSyncFromAllTenants"
      />

      <SupplierStatsCards
        v-if="authStore.isAdmin"
        :stats="stats"
        :loading="isLoadingStats"
        class="mt-0"
        @card-click="handleStatsCardClick"
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
        :is-master="isMasterMode"
        @update:options="updateTableOptions"
        @edit-supplier="handleEditSupplier"
        @delete-supplier="handleDeleteSupplier"
        @merge-supplier="handleMergeSupplier"
        @commercial-panel="handleCommercialPanel"
        @supplier-pending-invoices="handleSupplierPendingInvoices"
        @view-products="handleViewProducts"
        @check-supplier-api="handleCheckSupplierApi"
        @config-connection="handleConfigConnection"
        @view-connection-history="handleViewConnectionHistory"
      />

      <SupplierEditDialog
        v-model="isEditDialogVisible"
        :supplier="currentSupplier"
        :errors="supplierFormErrors"
        :is-master="isMasterMode"
        @save="handleSaveSupplier"
        @clear-errors="clearFormErrors"
        @edit-existing="handleEditExistingFromMaster"
        @open-master-directory="() => { isEditDialogVisible = false; isMasterDirectoryDialogVisible = true; }"
      />

      <SupplierCommercialPanel
        v-model="isCommercialPanelVisible"
        :supplier="currentSupplier"
        :laboratories="laboratories"
        :payment-rules="paymentRules"
        :supplier-discount="supplierDiscount"
        :discount-rules="discountRules"
        :loading="commercialLoading"
        :errors="supplierFormErrors"
        @save-payment-rules="handleSavePaymentRule"
        @save-discounts="handleSaveSupplierDiscount"
        @save-discount-rules="handleSaveDiscountRules"
        @save-all="handleSaveAllCommercial"
        @clear-errors="clearFormErrors"
      />

      <!-- Diálogo de Configuración de Conexión FTP/API/Excel -->
      <SupplierConnectionDialog
        v-model="isConnectionDialogVisible"
        :supplier="connectionSupplier"
        :is-master="isMasterMode"
        @saved="() => { fetchSuppliers(); fetchStats(); }"
      />

      <!-- Diálogo de Historial de Conexiones y Auditoría de Facturas -->
      <SupplierConnectionHistoryDialog
        v-model="isConnectionHistoryDialogVisible"
        :supplier="connectionHistorySupplier"
      />

      <!-- Diálogo del Catálogo de Productos del Proveedor -->
      <ShowSupplierProductsDialog
        v-model="isProductsDialogVisible"
        :selected-supplier="productsDialogSupplier"
      />

      <!-- Diálogo Directorio de Droguerías del Catálogo Maestro -->
      <MasterSupplierDirectoryDialog
        v-model="isMasterDirectoryDialogVisible"
        @supplier-activated="handleSupplierActivatedFromMaster"
      />

      <!-- Diálogo de Fusión de Proveedores Duplicados (Exclusivo Master) -->
      <SupplierMergeDialog
        v-if="isMasterMode"
        v-model="isMergeDialogVisible"
        :supplier="mergeSupplierTarget"
        @merged="handleSupplierMerged"
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
