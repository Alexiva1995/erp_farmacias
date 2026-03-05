<script setup>
import PurchaseOrderManagementDialog from "@/components/dialogs/PurchaseOrderManagementDialog.vue";
import PurchaseOrdersFilter from "@/components/PurchaseOrdersFilter.vue";
import PurchaseOrdersTable from "@/components/PurchaseOrdersTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const activeTab = ref('all');

// Convierte el valor del tab al entero que espera el backend
const tabStatusMap = { all: undefined, pending: 0, sent: 1, completed: 2 };

const tabItems = [
  { label: 'Todas',       value: 'all',       color: 'primary', icon: 'tabler-list',        totalKey: 'total_orders' },
  { label: 'Pendientes',  value: 'pending',   color: 'warning', icon: 'tabler-clock',        totalKey: 'pending_orders' },
  { label: 'Enviadas',    value: 'sent',      color: 'info',    icon: 'tabler-send',         totalKey: 'sent_orders' },
  { label: 'Completadas', value: 'completed', color: 'success', icon: 'tabler-circle-check', totalKey: 'completed_orders' },
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
    status: tabStatusMap[activeTab.value],
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

// Cambiar de pestaña o filtro => reset a página 1 y recargar
watch([selectedSupplier, activeTab], () => {
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
};
</script>

<template>
  <VContainer fluid class="pa-6">
    <!-- KPIs (4 cards con altura uniforme) -->
    <VRow class="mb-6" no-gutters>
      <!-- Órdenes Totales -->
      <VCol cols="12" sm="6" lg="3" class="pa-2">
        <VCard class="kpi-card h-100" elevation="0">
          <VCardText class="pa-5 d-flex flex-column justify-space-between h-100">
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar color="primary" variant="tonal" size="44" rounded>
                <VIcon icon="tabler-clipboard-list" size="22" />
              </VAvatar>
              <VChip color="primary" size="small" variant="tonal" label>Total</VChip>
            </div>
            <div>
              <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Órdenes Totales</div>
              <div class="text-h4 font-weight-black text-primary">{{ stats.total_orders }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Monto Total -->
      <VCol cols="12" sm="6" lg="3" class="pa-2">
        <VCard class="kpi-card h-100" elevation="0">
          <VCardText class="pa-5 d-flex flex-column justify-space-between h-100">
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar color="success" variant="tonal" size="44" rounded>
                <VIcon icon="tabler-currency-dollar" size="22" />
              </VAvatar>
              <VChip color="success" size="small" variant="tonal" label>Inversión</VChip>
            </div>
            <div>
              <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Monto Total (USD)</div>
              <div class="text-h4 font-weight-black">
                {{ Number(stats.total_amount).toLocaleString('es-ES', { minimumFractionDigits: 2 }) }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Pendientes -->
      <VCol cols="12" sm="6" lg="3" class="pa-2">
        <VCard class="kpi-card h-100" elevation="0">
          <VCardText class="pa-5 d-flex flex-column justify-space-between h-100">
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar color="warning" variant="tonal" size="44" rounded>
                <VIcon icon="tabler-alert-circle" size="22" />
              </VAvatar>
              <VChip color="warning" size="small" variant="tonal" label>Por Recibir</VChip>
            </div>
            <div>
              <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Órdenes Pendientes</div>
              <div class="text-h4 font-weight-black text-warning">{{ stats.pending_orders }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Completadas -->
      <VCol cols="12" sm="6" lg="3" class="pa-2">
        <VCard class="kpi-card h-100" elevation="0">
          <VCardText class="pa-5 d-flex flex-column justify-space-between h-100">
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar color="info" variant="tonal" size="44" rounded>
                <VIcon icon="tabler-circle-check" size="22" />
              </VAvatar>
              <VChip color="info" size="small" variant="tonal" label>Completadas</VChip>
            </div>
            <div>
              <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Órdenes Completadas</div>
              <div class="text-h4 font-weight-black text-info">{{ stats.completed_orders }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filtros -->
    <PurchaseOrdersFilter
      v-model:selectedSupplier="selectedSupplier"
      :suppliers="suppliers"
      @clear="handleClearFilters"
    />

    <!-- Tabla con Pestañas (Estilo Gastos) -->
    <VCard variant="outlined" class="rounded-lg bg-surface mt-4">
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
          class="tab-with-badge py-2"
        >
          <span class="d-inline-flex align-center gap-2 text-body-2 font-weight-bold">
            <VIcon :icon="tab.icon" size="18" />
            {{ tab.label }}
            <VChip
              v-if="stats[tab.totalKey] > 0 || tab.value === null"
              size="x-small"
              variant="tonal"
              :color="tab.color"
              class="tab-count font-weight-black"
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

    <!-- Diálogo de Gestión -->
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
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 8px !important;
  transition: all 0.2s ease;
}

.kpi-card:hover {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 8%) !important;
  transform: translateY(-2px);
}

/* stylelint-disable-next-line selector-pseudo-class-no-unknown */
:deep(.v-tabs) {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.gap-2 { gap: 8px; }
</style>
