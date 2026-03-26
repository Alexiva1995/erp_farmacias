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
import { onMounted, ref, watch } from "vue";
import { useRouter } from 'vue-router';

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
const pendingInvoices = ref({});
const discountRules = ref([]);
const paymentRules = ref([]);
const supplierDiscount = ref([]);

const isLoadingFilters = ref(false);

const currentSupplier = ref({});
const supplierFormErrors = ref({});
const isEditDialogVisible = ref(false);
const isCommercialPanelVisible = ref(false);
const isPendingInvoicesDialogVisible = ref(false);

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
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
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
      `/supplier-laboratories/${currentSupplier.value.id}/discount-rules`
    );
    discountRules.value = data.discount_rules;
  } catch (error) {
    toast.error("Error al cargar las reglas de descuento");
  } finally {
    loading.value = false;
  }
};

const fetchPendingInvoices = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(
      `/suppliers/${currentSupplier.value.id}/pending-invoices`
    );
    pendingInvoices.value = data.pending_invoices;
  } catch (error) {
    toast.error("Error al cargar facturas pendientes");
  } finally {
    loading.value = false;
  }
};

const fetchPaymentRules = async () => {
  try {
    const { data } = await axios.get(
      `/suppliers/${currentSupplier.value.id}/payment-rules`
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
      `/suppliers/${currentSupplier.value.id}/discounts`
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
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const handleAddSupplier = () => {
  currentSupplier.value = {};
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
      `Proveedor ${isNewSupplier ? "creado" : "actualizado"} con éxito`
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
      `Procesando los datos de ${supplier.name}, le notificaremos al finalizar`
    );
    await axios.get(`/suppliers/${supplier.id}/connection`);
    supplierConnectionStore.startConnection();
  } catch (error) {
    toast.error(`No se pudo iniciar la conexión con ${supplier.name}`);
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
      fetchDiscountRules()
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
    name: 'finances-pending-payments', 
    query: { supplierId: supplier.id } 
  });
};

const clearFormErrors = () => {
  supplierFormErrors.value = {};
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, debtFilter, minScore],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchSuppliers(), 300);
  },
  { deep: true }
);

watch([searchQuery, debtFilter, minScore], () => {
  page.value = 1;
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};
</script>

<template>
  <div class="suppliers-view px-6 mt-6 pb-12">
    <!-- === HEADER Y KPIS === -->
    <VCard class="rounded-lg border shadow-sm mb-6 overflow-hidden">
      <div class="header-bg pa-6">
        <div class="d-flex align-center gap-4">
          <VAvatar size="54" color="white" variant="flat" class="rounded-lg shadow-soft">
            <VIcon icon="tabler-truck-delivery" color="primary" size="28" />
          </VAvatar>
          <div class="d-flex flex-column">
            <h1 class="text-h4 font-weight-black text-white letter-spacing-tight">
              Gestión de Proveedores
            </h1>
            <span class="text-sm font-weight-bold text-white opacity-80 uppercase letter-spacing-widest">
              Directorio y Control Comercial de Abastecimiento
            </span>
          </div>
        </div>
      </div>
    </VCard>

    <div class="d-flex flex-column gap-6">
      <SupplierFilters
        v-model:searchQuery="searchQuery"
        v-model:debtFilter="debtFilter"
        v-model:minScore="minScore"
        @clear="handleClearFilters"
        @sort="handleSort"
        @add-supplier="handleAddSupplier"
      />

    <SupplierStatsCards :stats="stats" :loading="isLoadingStats" />

    <SupplierTable
      :suppliers="suppliers"
      :loading="loading"
      :total-supplier="totalSupplier"
      :items-per-page="itemsPerPage"
      :page="page"
      :checking-api-id="checkingApiSupplierId"
      @update:options="updateTableOptions"
      @edit-supplier="handleEditSupplier"
      @delete-supplier="handleDeleteSupplier"
      @commercial-panel="handleCommercialPanel"
      @supplier-pending-invoices="handleSupplierPendingInvoices"
      @check-supplier-api="handleCheckSupplierApi"
      @config-connection="handleConfigConnection"
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
.header-bg {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #4a90e2 100%);
  border-block-end: 1px solid rgba(255, 255, 255, 10%);
}

.letter-spacing-tight { letter-spacing: -0.02em; }
.letter-spacing-widest { letter-spacing: 0.1em !important; }
.shadow-soft { box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important; }
</style>
