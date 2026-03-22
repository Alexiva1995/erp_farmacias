<script setup>
import PurchaseOrderManagementDialog from "@/components/dialogs/PurchaseOrderManagementDialog.vue";
import PurchaseOrdersFilter from "@/components/PurchaseOrdersFilter.vue";
import PurchaseOrdersTable from "@/components/PurchaseOrdersTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const searchQuery = ref("");
const startDate = ref("");
const endDate = ref("");
const activeTab = ref(0); // Cambiado a entero para coincidir con el diseño común

// Convierte el valor del tab al entero que espera el backend
const tabStatusMap = { 0: 0, 1: 1, 2: 2 };

const tabItems = [
  { label: 'Pendientes',  value: 0, color: 'warning', icon: 'tabler-clock',        totalKey: 'pending_orders' },
  { label: 'Enviadas',    value: 1, color: 'info',    icon: 'tabler-send',         totalKey: 'sent_orders' },
  { label: 'Completadas', value: 2, color: 'success', icon: 'tabler-circle-check', totalKey: 'completed_orders' },
];

const currentPurchaseOrder = ref({});
const purchaseOrders = ref([]);
const suppliers = ref([]);
const selectedSupplier = ref(null);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const totalPurchaseOrders = ref(0);
const stats = ref({
  total_orders: 0,
  total_amount: 0,
  pending_orders: 0,
  sent_orders: 0,
  completed_orders: 0,
});

const isManagementDialogVisible = ref(false);
const isAdmin = ref(false);

const checkAdmin = async () => {
  try {
    const { data } = await axios.get("/auth/user");
    isAdmin.value = data.role === "admin";
  } catch (error) {
    isAdmin.value = false;
  }
};

const fetchSuppliers = async () => {
  try {
    const response = await axios.get("/suppliers/list-simple"); // Endpoint estandarizado
    suppliers.value = response.data;
  } catch (error) {
    console.error("Error al obtener proveedores:", error);
  }
};

const fetchStats = async () => {
  try {
    const params = { 
      supplier_id: selectedSupplier.value,
      search: searchQuery.value,
      start_date: startDate.value,
      end_date: endDate.value
    };
    const { data } = await axios.get("/suppliers/purchase-orders/stats", { params });
    stats.value = data;
  } catch (error) {
    console.error("Error al obtener estadísticas:", error);
  }
};

const fetchPurchaseOrders = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    perPage: itemsPerPage.value,
    supplier_id: selectedSupplier.value,
    search: searchQuery.value,
    start_date: startDate.value,
    end_date: endDate.value,
    status: activeTab.value,
  };

  try {
    const { data } = await axios.get("/suppliers/purchase-orders", { params });
    purchaseOrders.value = data.data;
    totalPurchaseOrders.value = data.total;
  } catch (error) {
    toast.error("Error al obtener las órdenes de compra.");
  } finally {
    loading.value = false;
  }
};

const handleManage = (purchaseOrder) => {
  currentPurchaseOrder.value = { ...purchaseOrder };
  isManagementDialogVisible.value = true;
};

const handleDeleteOrder = async (id) => {
  if (confirm("¿Estás seguro de eliminar esta orden? No podrás deshacer esta acción.")) {
    try {
      await axios.delete(`/suppliers/purchase-orders/${id}`);
      toast.success("Orden eliminada correctamente.");
      fetchPurchaseOrders();
      fetchStats();
    } catch (error) {
      toast.error("Error al eliminar la orden.");
    }
  }
};

onMounted(() => {
  checkAdmin();
  fetchSuppliers();
  fetchPurchaseOrders();
  fetchStats();
});

// Cambiar de pestaña o filtro => reset a página 1 y recargar
watch([selectedSupplier, activeTab, searchQuery, startDate, endDate], () => {
  page.value = 1;
  fetchPurchaseOrders();
  fetchStats();
});

// Paginar sin resetear la página
watch([page, itemsPerPage], () => {
  fetchPurchaseOrders();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

const handleClearFilters = () => {
  selectedSupplier.value = null;
  searchQuery.value = "";
  startDate.value = "";
  endDate.value = "";
};

</script>

<template>
  <VContainer fluid class="purchase-orders-list pa-0 pa-sm-4 bg-var-theme-background min-h-screen">
    <!-- Encabezado Premium con KPIs -->
    <div class="premium-header mb-6 rounded-xl-mobile overflow-hidden shadow-sm">
      <div class="header-gradient pa-6 text-white position-relative">
        <div class="d-flex align-center gap-3 mb-6">
          <VAvatar color="white" variant="tonal" size="48" class="rounded-lg shadow-sm">
            <VIcon icon="tabler-truck-delivery" color="white" size="28" />
          </VAvatar>
          <div>
            <h1 class="text-h4 font-weight-black leading-none mb-1">Órdenes de Compra</h1>
            <p class="text-subtitle-2 text-white opacity-80 font-weight-medium uppercase tracking-wide">Gestión de Abastecimiento</p>
          </div>
        </div>

        <VRow>
          <VCol v-for="(kpi, index) in [
            { label: 'En Espera', val: stats.pending_orders, color: 'warning', icon: 'tabler-clock', suffix: 'ord.' },
            { label: 'En Camino', val: stats.sent_orders, color: 'info', icon: 'tabler-send', suffix: 'ord.' },
            { label: 'Completadas', val: stats.completed_orders, color: 'success', icon: 'tabler-circle-check', suffix: 'ord.' },
            { label: 'Inversión Total', val: Number(stats.total_amount).toLocaleString('es-ES', { maximumFractionDigits: 0 }), color: 'primary', icon: 'tabler-coin', prefix: '$ ' }
          ]" :key="index" cols="6" sm="6" md="3">
            <VCard variant="flat" color="rgba(255,255,255,0.1)" class="rounded-xl border-white-opacity px-4 py-3 h-full overflow-hidden position-relative">
              <div class="d-flex align-center gap-3 position-relative z-10">
                <VAvatar :color="kpi.color" variant="flat" size="36" class="rounded-lg shadow-inner">
                  <VIcon :icon="kpi.icon" size="20" />
                </VAvatar>
                <div>
                  <div class="text-xxs text-uppercase opacity-70 font-weight-black">{{ kpi.label }}</div>
                  <div class="text-h6 font-weight-black leading-none mt-1">
                    {{ kpi.prefix || '' }}{{ kpi.val }}{{ kpi.suffix || '' }}
                  </div>
                </div>
              </div>
              <div class="kpi-decoration" :class="`bg-${kpi.color}`"></div>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </div>

    <!-- Filtros -->
    <PurchaseOrdersFilter
      v-model:selected-supplier="selectedSupplier"
      v-model:search-query="searchQuery"
      v-model:start-date="startDate"
      v-model:end-date="endDate"
      :suppliers="suppliers"
      @clear="handleClearFilters"
    />

    <!-- Tabs de Estado Premium -->
    <VTabs
      v-slot:content
      v-model="activeTab"
      color="primary"
      grow
      class="premium-tabs mb-6 rounded-xl border bg-surface overflow-hidden shadow-xs"
      density="comfortable"
    >
      <VTab 
        v-for="tab in tabItems" 
        :key="tab.value" 
        :value="tab.value" 
        class="font-weight-black text-xs py-4"
      >
        <VIcon start :icon="tab.icon" size="18" /> {{ tab.label.toUpperCase() }}
        <VChip
          v-if="stats[tab.totalKey] > 0"
          size="x-small"
          variant="tonal"
          :color="activeTab === tab.value ? 'primary' : 'secondary'"
          class="ms-2 font-weight-black"
        >
          {{ stats[tab.totalKey] }}
        </VChip>
      </VTab>
    </VTabs>

      <VDivider />

      <PurchaseOrdersTable
        :purchase-orders="purchaseOrders"
        :loading="loading"
        :total-purchase-orders="totalPurchaseOrders"
        :items-per-page="itemsPerPage"
        :page="page"
        :is-admin="isAdmin"
        @update:options="updateTableOptions"
        @manage="handleManage"
        @delete-purchaseOrder="handleDeleteOrder"
        @refresh="fetchPurchaseOrders"
      />
    </VCard>

    <!-- Diálogo de Gestión -->
    <PurchaseOrderManagementDialog
      v-model="isManagementDialogVisible"
      :purchase-order="currentPurchaseOrder"
      :is-admin="isAdmin"
      @refresh="fetchPurchaseOrders(); fetchStats();"
    />
  </VContainer>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e293b 100%);
}

.border-white-opacity {
  border: 1px solid rgba(255, 255, 255, 20%) !important;
}

.shadow-inner {
  box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 10%);
}

.text-xxs {
  font-size: 0.65rem !important;
}

.leading-none {
  line-height: 1;
}

.leading-tight {
  line-height: 1.25;
}

.min-h-screen {
  min-block-size: 100vh;
}

.premium-tabs :deep(.v-tab--selected) {
  background-color: rgba(var(--v-theme-primary), 5%);
}

.kpi-decoration {
  position: absolute;
  border-radius: 0 0 0 100%;
  block-size: 60px;
  inline-size: 60px;
  inset-block-start: 0;
  inset-inline-end: 0;
  opacity: 0.1;
  pointer-events: none;
}

.bg-warning { background: linear-gradient(135deg, rgb(var(--v-theme-warning)) 0%, transparent 100%); }
.bg-info { background: linear-gradient(135deg, rgb(var(--v-theme-info)) 0%, transparent 100%); }
.bg-success { background: linear-gradient(135deg, rgb(var(--v-theme-success)) 0%, transparent 100%); }
.bg-primary { background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, transparent 100%); }

.shadow-xs {
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 5%);
}

@media (max-width: 600px) {
  .rounded-xl-mobile {
    border-radius: 0 !important;
  }
}
</style>

