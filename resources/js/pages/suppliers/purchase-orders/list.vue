<script setup>
import PurchaseOrderManagementDialog from "@/components/dialogs/PurchaseOrderManagementDialog.vue";
import PurchaseOrdersFilter from "@/components/PurchaseOrdersFilter.vue";
import PurchaseOrdersTable from "@/components/PurchaseOrdersTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

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

watch([page, itemsPerPage, selectedSupplier], () => {
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
    <!-- Encabezado con Estilo -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-black d-flex align-center gap-2 mb-1">
          <VAvatar color="primary" variant="tonal" size="48" rounded>
            <VIcon icon="tabler-shopping-cart" size="28" />
          </VAvatar>
          Órdenes de Compra
        </h1>
        <p class="text-body-2 text-disabled mb-0">Gestión y seguimiento de pedidos a proveedores</p>
      </div>
      
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        variant="elevated"
        class="rounded-lg shadow-sm"
        to="/suppliers/generar-pedido"
      >
        Nuevo Pedido
      </VBtn>
    </div>

    <!-- KPIs Premium (Estilo CashAverage) -->
    <VRow class="mb-6">
      <!-- Órdenes Totales -->
      <VCol cols="12" sm="6" lg="3">
        <VCard class="kpi-card" elevation="0">
          <VCardText class="pa-5">
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar color="primary" variant="tonal" size="44" rounded>
                <VIcon icon="tabler-clipboard-list" size="22" />
              </VAvatar>
              <VChip color="primary" size="small" variant="tonal" label>
                Total
              </VChip>
            </div>
            <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Órdenes Totales</div>
            <div class="text-h4 font-weight-black text-primary">{{ stats.total_orders }}</div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Monto Total -->
      <VCol cols="12" sm="6" lg="3">
        <VCard class="kpi-card" elevation="0">
          <VCardText class="pa-5">
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar color="success" variant="tonal" size="44" rounded>
                <VIcon icon="tabler-currency-dollar" size="22" />
              </VAvatar>
              <VChip color="success" size="small" variant="tonal" label>
                Inversión
              </VChip>
            </div>
            <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Monto Total (USD)</div>
            <div class="text-h4 font-weight-black">
              {{ Number(stats.total_amount).toLocaleString('es-ES', { minimumFractionDigits: 2 }) }}
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Pendientes -->
      <VCol cols="12" sm="6" lg="3">
        <VCard class="kpi-card" elevation="0">
          <VCardText class="pa-5">
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar color="warning" variant="tonal" size="44" rounded>
                <VIcon icon="tabler-alert-circle" size="22" />
              </VAvatar>
              <VChip color="warning" size="small" variant="tonal" label>
                Por Recibir
              </VChip>
            </div>
            <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Órdenes Pendientes</div>
            <div class="text-h4 font-weight-black text-warning">{{ stats.pending_orders }}</div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Estado General -->
      <VCol cols="12" sm="6" lg="3">
        <VCard class="kpi-card" color="info" variant="tonal" elevation="0">
          <VCardText class="pa-5">
            <div class="d-flex align-center mb-3">
              <VAvatar color="info" variant="elevated" size="44" rounded>
                <VIcon icon="tabler-checkup-list" size="22" class="text-white" />
              </VAvatar>
            </div>
            <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Estatus Operativo</div>
            <div class="text-h6 font-weight-black text-info">
              {{ stats.pending_orders > 0 ? '📦 Pedidos en camino' : '✅ Todo al día' }}
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

    <!-- Tabla con Contenedor Premium -->
    <VCard elevation="0" class="rounded-xl border overflow-hidden">
      <VCardItem class="pa-4 pb-2 bg-var-theme-background">
        <template #prepend>
          <VAvatar color="primary" variant="tonal" size="38" rounded>
            <VIcon icon="tabler-list" size="20" />
          </VAvatar>
        </template>
        <VCardTitle class="text-subtitle-1 font-weight-bold">Listado de Órdenes</VCardTitle>
        <VCardSubtitle class="text-caption">Historial completo de pedidos realizados</VCardSubtitle>
      </VCardItem>

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
  border-radius: 16px !important;
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
