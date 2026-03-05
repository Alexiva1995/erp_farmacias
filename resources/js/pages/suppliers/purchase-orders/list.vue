<script setup>
import PurchaseOrderManagementDialog from "@/components/dialogs/PurchaseOrderManagementDialog.vue";
import PurchaseOrdersFilter from "@/components/PurchaseOrdersFilter.vue";
import PurchaseOrdersTable from "@/components/PurchaseOrdersTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const activeTab = ref(null);

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
const { isAdmin } = useAuthStore();

const fetchSuppliers = async () => {
  try {
    const response = await axios.get("/available-suppliers");
    suppliers.value = response.data.data;
  } catch (error) {
    console.error("Error al obtener proveedores:", error);
  }
};

const fetchStats = async () => {
  try {
    const params = { selectedSupplier: selectedSupplier.value };
    const { data } = await axios.get("/suppliers/purchase-orders/stats", { params });
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
    status: activeTab.value !== null ? activeTab.value : undefined,
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
    text: "No podrás revertir la eliminación de esta orden de compra.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    customClass: {
      confirmButton: 'v-btn v-btn--elevated v-theme--light bg-error v-btn--density-default v-btn--size-default v-btn--variant-elevated w-100',
      cancelButton: 'v-btn v-btn--elevated v-theme--light bg-secondary v-btn--density-default v-btn--size-default v-btn--variant-elevated w-100'
    }
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

onMounted(() => {
  fetchSuppliers();
  fetchPurchaseOrders();
  fetchStats();
});

watch([page, itemsPerPage, selectedSupplier, activeTab], () => {
  fetchPurchaseOrders();
  fetchStats();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

const handleClearFilters = () => {
  selectedSupplier.value = null;
};
</script>

<template>
  <VContainer fluid class="pa-6">
    <!-- Filtros -->
    <PurchaseOrdersFilter
      v-model:selectedSupplier="selectedSupplier"
      :suppliers="suppliers"
      @clear="handleClearFilters"
    />

    <!-- Tabla con TABS (Estilo Gastos) -->
    <VCard elevation="0" class="rounded-lg border overflow-hidden mt-6">
      <VTabs
        v-model="activeTab"
        color="primary"
        class="px-2"
        align-tabs="start"
        density="comfortable"
      >
        <VTab
          v-for="tab in tabItems"
          :key="tab.label"
          :value="tab.value"
          class="py-2"
        >
          <span class="d-inline-flex align-center gap-2 text-body-2 font-weight-bold">
            <VIcon :icon="tab.icon" size="18" />
            {{ tab.label }}
            <VChip
              v-if="stats[tab.totalKey] > 0 || tab.value === null"
              size="x-small"
              variant="tonal"
              :color="tab.color"
              class="font-weight-black"
            >
              {{ stats[tab.totalKey] }}
            </VChip>
          </span>
        </VTab>
      </VTabs>
      
      <VDivider />

      <PurchaseOrdersTable
        :purchaseOrders="purchaseOrders"
        :loading="loading"
        :total-purchaseOrders="totalPurchaseOrders"
        :items-per-page="itemsPerPage"
        :page="page"
        :is-admin="isAdmin"
        @update:options="updateTableOptions"
        @manage="handleManage"
        @delete-purchaseOrder="handleDeleteOrder"
        @refresh="fetchPurchaseOrders"
      />
    </VCard>

    <!-- Diálogo Unificado de Gestión -->
    <PurchaseOrderManagementDialog
      v-model="isManagementDialogVisible"
      :purchaseOrder="currentPurchaseOrder"
      :is-admin="isAdmin"
      @refresh="fetchPurchaseOrders"
    />
  </VContainer>
</template>

<style scoped>
.kpi-card {
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 8px !important;
  transition: all 0.2s ease;
}

.kpi-card:hover {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 8%) !important;
  transform: translateY(-4px);
}

.bg-var-theme-background {
  background-color: rgb(var(--v-theme-surface));
}

.gap-2 {
  gap: 8px;
}
</style>
