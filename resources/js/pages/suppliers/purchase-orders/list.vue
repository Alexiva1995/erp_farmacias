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
  <VContainer fluid>
    <VRow>
      <VCol cols="12">
        <h1 class="text-h4 mb-4 font-weight-bold d-flex align-center gap-2">
          <VIcon icon="tabler-shopping-cart" size="32" color="primary" />
          Órdenes de Compra
        </h1>

        <!-- Tarjetas de Resumen Premium -->
        <VRow class="mb-6">
          <VCol cols="12" sm="4">
            <VCard class="stats-card gradient-primary" variant="flat">
              <VCardText class="d-flex align-center pa-6">
                <VAvatar color="white" variant="flat" size="48" class="elevation-2">
                  <VIcon icon="tabler-clipboard-list" color="primary" size="28" />
                </VAvatar>
                <div class="ml-4">
                  <div class="text-h4 font-weight-bold text-white">{{ stats.total_orders }}</div>
                  <div class="text-caption text-white opacity-80 font-weight-medium">Órdenes Totales</div>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12" sm="4">
            <VCard class="stats-card gradient-success" variant="flat">
              <VCardText class="d-flex align-center pa-6">
                <VAvatar color="white" variant="flat" size="48" class="elevation-2">
                  <VIcon icon="tabler-currency-dollar" color="success" size="28" />
                </VAvatar>
                <div class="ml-4">
                  <div class="text-h4 font-weight-bold text-white">
                    {{ Number(stats.total_amount).toLocaleString('es-ES', { minimumFractionDigits: 2 }) }}
                  </div>
                  <div class="text-caption text-white opacity-80 font-weight-medium">Monto Total (USD)</div>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12" sm="4">
            <VCard class="stats-card gradient-warning" variant="flat">
              <VCardText class="d-flex align-center pa-6">
                <VAvatar color="white" variant="flat" size="48" class="elevation-2">
                  <VIcon icon="tabler-alert-circle" color="warning" size="28" />
                </VAvatar>
                <div class="ml-4">
                  <div class="text-h4 font-weight-bold text-white">{{ stats.pending_orders }}</div>
                  <div class="text-caption text-white opacity-80 font-weight-medium">Órdenes Pendientes</div>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <!-- Filtros y Tabla -->
        <VCard border flat class="rounded-xl overflow-hidden shadow-sm">
          <div class="pa-6 border-b bg-var-theme-background">
            <PurchaseOrdersFilter
              v-model:selectedSupplier="selectedSupplier"
              :suppliers="suppliers"
              @clear="handleClearFilters"
            />
          </div>

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
      </VCol>
    </VRow>

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
.stats-card {
  border-radius: 16px !important;
  transition: transform 0.2s ease-in-out;
}

.stats-card:hover {
  transform: translateY(-4px);
}

.gradient-primary {
  background: linear-gradient(135deg, #7367f0 0%, #a8a1f5 100%) !important;
}

.gradient-success {
  background: linear-gradient(135deg, #28c76f 0%, #5af2a0 100%) !important;
}

.gradient-warning {
  background: linear-gradient(135deg, #ff9f43 0%, #ffc994 100%) !important;
}

.opacity-80 {
  opacity: 0.8;
}

.bg-var-theme-background {
  background-color: rgb(var(--v-theme-surface));
}
</style>
