<script setup>
import PurchaseOrderKpis from "@/components/PurchaseOrderKpis.vue";
import PurchaseOrderManagementDialog from "@/components/dialogs/PurchaseOrderManagementDialog.vue";
import PurchaseOrdersFilter from "@/components/PurchaseOrdersFilter.vue";
import PurchaseOrdersTable from "@/components/PurchaseOrdersTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import Swal from "sweetalert2";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

const authStore = useAuthStore();
const searchQuery = ref("");
const startDate = ref("");
const endDate = ref("");
const activeTab = ref(0);

const tabItems = [
  {
    label: "Pendientes",
    value: 0,
    color: "warning",
    icon: "tabler-clock",
    totalKey: "pending_orders",
  },
  {
    label: "Enviadas",
    value: 1,
    color: "info",
    icon: "tabler-send",
    totalKey: "sent_orders",
  },
  {
    label: "Completadas",
    value: 2,
    color: "success",
    icon: "tabler-circle-check",
    totalKey: "completed_orders",
  },
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
const isAdmin = computed(() => authStore.isAdmin);

const fetchSuppliers = async () => {
  try {
    const response = await axios.get("/product-lots/available-suppliers");
    suppliers.value = response.data.data;
  } catch (error) {
    console.error("Error al obtener proveedores:", error);
  }
};

const fetchStats = async () => {
  try {
    const params = {
      selectedSupplier: selectedSupplier.value,
      search: searchQuery.value,
      start_date: startDate.value,
      end_date: endDate.value,
    };
    const { data } = await axios.get("/suppliers/purchase-orders/stats", {
      params,
    });
    stats.value = data.data;
  } catch (error) {
    console.error("Error al obtener estadísticas:", error);
  }
};

const fetchPurchaseOrders = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    selectedSupplier: selectedSupplier.value,
    search: searchQuery.value,
    start_date: startDate.value,
    end_date: endDate.value,
    status: activeTab.value,
  };

  try {
    const { data } = await axios.get("/suppliers/purchase-orders", { params });
    purchaseOrders.value = data.data.data;
    totalPurchaseOrders.value = data.data.total;
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
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "No podrás deshacer la eliminación de esta orden de compra.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
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

const handleRevertOrder = async (order) => {
  try {
    await axios.post(`/suppliers/purchase-orders/${order.id}/revert-to-sent`);
    toast.success("Orden devuelta a estado 'Enviada' correctamente.");
    fetchPurchaseOrders();
    fetchStats();
  } catch (error) {
    toast.error("Error al devolver la orden a enviada.");
  }
};

onMounted(() => {
  fetchSuppliers();
  fetchPurchaseOrders();
  fetchStats();
});

let debounceTimer = null;
let ignoreNextPageWatch = false;

watch([selectedSupplier, activeTab, searchQuery, startDate, endDate], () => {
  if (page.value !== 1) {
    ignoreNextPageWatch = true;
    page.value = 1;
  }
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchPurchaseOrders();
    fetchStats();
  }, 300);
});

watch([page, itemsPerPage], () => {
  if (ignoreNextPageWatch) {
    ignoreNextPageWatch = false;
    return;
  }
  fetchPurchaseOrders();
});

onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer);
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
  <div class="purchase-orders-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Filtros -->
      <PurchaseOrdersFilter
        class="mb-4"
        v-model:selected-supplier="selectedSupplier"
        v-model:search-query="searchQuery"
        v-model:start-date="startDate"
        v-model:endDate="endDate"
        :suppliers="suppliers"
        @clear="handleClearFilters"
      />

      <!-- KPIs Estilo SupplierStatsCards Desacoplado -->
      <PurchaseOrderKpis v-if="isAdmin" :stats="stats" :loading="loading" />

      <!-- Pestañas de Navegación por Estado -->
      <VTabs
        v-model="activeTab"
        color="primary"
        grow
        class="premium-tabs rounded-lg border bg-surface overflow-hidden shadow-sm"
        density="comfortable"
      >
        <VTab
          v-for="tab in (isAdmin ? tabItems : tabItems.filter(t => t.value === 0 || t.value === 1))"
          :key="tab.value"
          :value="tab.value"
          class="font-weight-black text-xs py-4"
        >
          <VIcon start :icon="tab.icon" size="18" />
          {{ tab.label.toUpperCase() }}
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

      <!-- Tabla Premium -->
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
        @revert="handleRevertOrder"
        @refresh="
          fetchPurchaseOrders();
          fetchStats();
        "
      />
    </div>

    <!-- Diálogo de Gestión -->
    <PurchaseOrderManagementDialog
      v-model="isManagementDialogVisible"
      :purchase-order="currentPurchaseOrder"
      :is-admin="isAdmin"
      @refresh="
        fetchPurchaseOrders();
        fetchStats();
      "
    />
  </div>
</template>

<style scoped>
.purchase-orders-view {
  min-block-size: 100vh;
}

.premium-tabs :deep(.v-tab--selected) {
  background-color: rgba(var(--v-theme-primary), 5%);
}
</style>
