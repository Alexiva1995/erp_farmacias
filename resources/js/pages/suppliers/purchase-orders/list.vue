<script setup>
import PurchaseOrderManagementDialog from "@/components/dialogs/PurchaseOrderManagementDialog.vue";
import PurchaseOrdersFilter from "@/components/PurchaseOrdersFilter.vue";
import PurchaseOrdersTable from "@/components/PurchaseOrdersTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const searchQuery = ref("");
const startDate = ref("");
const endDate = ref("");
const activeTab = ref(0); // Cambiado a entero para coincidir con el diseño común

// Convierte el valor del tab al entero que espera el backend
const tabStatusMap = { 0: 0, 1: 1, 2: 2 };

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
    const response = await axios.get("/available-suppliers");
    suppliers.value = response.data;
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
  if (
    confirm(
      "¿Estás seguro de eliminar esta orden? No podrás deshacer esta acción.",
    )
  ) {
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
  <div class="purchase-orders-view pb-12">
    <!-- Header Premium (Ahora como tarjeta flotante) -->
    <VCard class="mx-6 mt-6 mb-6 rounded-lg border shadow-sm overflow-hidden">
      <div class="header-bg pa-6">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div class="d-flex align-center gap-4">
            <VAvatar
              size="54"
              color="white"
              variant="flat"
              class="rounded-lg shadow-soft"
            >
              <VIcon icon="tabler-truck-delivery" color="primary" size="28" />
            </VAvatar>
            <div class="d-flex flex-column">
              <h1
                class="text-h4 font-weight-black text-white letter-spacing-tight"
              >
                Órdenes de Compra
              </h1>
              <span
                class="text-sm font-weight-bold text-white opacity-80 uppercase letter-spacing-widest"
              >
                Gestión de Abastecimiento de Inventario
              </span>
            </div>
          </div>
        </div>
      </div>
    </VCard>

    <div class="px-6 d-flex flex-column gap-6">
      <!-- Filtros (Ahora arriba como en proveedores) -->
      <PurchaseOrdersFilter
        v-model:selected-supplier="selectedSupplier"
        v-model:search-query="searchQuery"
        v-model:start-date="startDate"
        v-model:endDate="endDate"
        :suppliers="suppliers"
        @clear="handleClearFilters"
      />

      <!-- KPIs Estilo SupplierStatsCards -->
      <VRow>
        <VCol
          v-for="(kpi, index) in [
            {
              title: 'En Espera',
              value: stats.pending_orders,
              color: 'warning',
              icon: 'tabler-clock',
              desc: 'Órdenes pendientes',
            },
            {
              title: 'En Camino',
              value: stats.sent_orders,
              color: 'info',
              icon: 'tabler-send',
              desc: 'En tránsito',
            },
            {
              title: 'Completadas',
              value: stats.completed_orders,
              color: 'success',
              icon: 'tabler-circle-check',
              desc: 'Recibidas con éxito',
            },
            {
              title: 'Inversión Total',
              value: `$ ${Number(stats.total_amount).toLocaleString('es-ES', { maximumFractionDigits: 0 })}`,
              color: 'primary',
              icon: 'tabler-coin',
              desc: 'Monto total acumulado',
            },
          ]"
          :key="index"
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative"
          >
            <!-- Decoración de fondo -->
            <div
              class="card-bg-decoration"
              :style="{
                background: `linear-gradient(45deg, rgba(var(--v-theme-${kpi.color}), 0.1), transparent)`,
              }"
            ></div>

            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-4">
                <VAvatar
                  :color="kpi.color"
                  variant="tonal"
                  size="48"
                  rounded="lg"
                  class="elevation-1"
                >
                  <VIcon :icon="kpi.icon" size="26" />
                </VAvatar>

                <div class="text-right">
                  <span
                    class="text-overline font-weight-bold text-disabled"
                    style="letter-spacing: 1px !important"
                    >{{ kpi.title }}</span
                  >
                  <h4 class="text-h4 font-weight-black mt-1">
                    {{ kpi.value }}
                  </h4>
                </div>
              </div>

              <VDivider class="mb-3 opacity-20" />

              <div class="d-flex align-center justify-space-between">
                <span
                  class="text-caption font-weight-medium text-medium-emphasis"
                >
                  {{ kpi.desc }}
                </span>
                <VIcon
                  icon="tabler-trending-up"
                  size="16"
                  :color="kpi.color"
                  class="opacity-50"
                />
              </div>
            </VCardText>

            <!-- Borde de acento lateral -->
            <div
              class="accent-border"
              :style="{ backgroundColor: `rgb(var(--v-theme-${kpi.color}))` }"
            ></div>
          </VCard>
        </VCol>
      </VRow>

      <VTabs
        v-model="activeTab"
        color="primary"
        grow
        class="premium-tabs rounded-lg border bg-surface overflow-hidden shadow-xs"
        density="comfortable"
      >
        <VTab
          v-for="tab in tabItems"
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
.stats-card {
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 80%) !important;
  transition: all 0.3s ease;
}

.header-bg {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #4a90e2 100%
  );
}

.letter-spacing-tight {
  letter-spacing: -0.02em;
}
.letter-spacing-widest {
  letter-spacing: 0.1em !important;
}

.shadow-soft {
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important;
}

.stats-card:hover {
  box-shadow: 0 8px 25px 0 rgba(0, 0, 0, 8%) !important;
  transform: translateY(-5px);
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 100px;
  filter: blur(40px);
  inline-size: 100px;
  inset-block-start: -20px;
  inset-inline-end: -20px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 70%;
  border-end-end-radius: 4px;
  border-start-end-radius: 4px;
  inline-size: 4px;
  inset-block-start: 15%;
  inset-inline-start: 0;
  opacity: 0.8;
}

.text-h4 {
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: -0.5px !important;
}

.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e293b 100%
  );
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

.purchase-orders-view {
  background-color: #f8fafc;
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

.bg-warning {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-warning)) 0%,
    transparent 100%
  );
}
.bg-info {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-info)) 0%,
    transparent 100%
  );
}
.bg-success {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-success)) 0%,
    transparent 100%
  );
}
.bg-primary {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    transparent 100%
  );
}

.shadow-xs {
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 5%);
}

@media (max-width: 600px) {
  .rounded-xl-mobile {
    border-radius: 0 !important;
  }
}
</style>
