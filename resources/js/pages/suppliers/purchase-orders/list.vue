<script setup>
import PurchaseOrderManagementDialog from "@/components/dialogs/PurchaseOrderManagementDialog.vue";
import PurchaseOrdersFilter from "@/components/PurchaseOrdersFilter.vue";
import PurchaseOrdersTable from "@/components/PurchaseOrdersTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const activeTab = ref(null);

const tabItems = [
  { label: 'Todas',      value: null,    color: 'primary',  icon: 'tabler-list',          totalKey: 'total_orders' },
  { label: 'Pendientes', value: 0,       color: 'warning',  icon: 'tabler-clock',         totalKey: 'pending_orders' },
  { label: 'Enviadas',   value: 1,       color: 'info',     icon: 'tabler-send',          totalKey: 'sent_orders' },
  { label: 'Completadas',value: 2,       color: 'success',  icon: 'tabler-circle-check',   totalKey: 'completed_orders' },
];

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

onMounted(() => {
  fetchSuppliers();
  fetchPurchaseOrders();
  fetchStats();
});

watch([page, itemsPerPage, selectedSupplier, activeTab], () => {
  fetchPurchaseOrders();
  fetchStats();
});

// ... resto de métodos ...
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
