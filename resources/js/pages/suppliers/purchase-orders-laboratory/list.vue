<script setup>
import PurchaseOrderLaboratoryManagementDialog from "@/components/dialogs/PurchaseOrderLaboratoryManagementDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

const authStore = useAuthStore();
const isAdmin = computed(() => authStore.isAdmin);

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

const currentLaboratory = ref(null);
const isManagementDialogVisible = ref(false);

const laboratories = ref([]);
const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const totalLaboratories = ref(0);

const sortBy = ref("total_amount_usd");
const orderBy = ref("desc");

const stats = ref({
  total_laboratories: 0,
  total_skus: 0,
  total_units: 0,
  total_amount: 0,
  pending_orders: 0,
  sent_orders: 0,
  completed_orders: 0,
});

const formatUsd = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount ?? 0);
};

const headers = [
  { title: "LABORATORIO", key: "laboratory_name", sortable: true },
  { title: "SKUS", key: "total_skus", sortable: true, align: "center", width: "130px" },
  { title: "UNIDADES", key: "total_units", sortable: true, align: "center", width: "140px" },
  { title: "TOTAL ($ USD)", key: "total_amount_usd", sortable: true, align: "end", width: "160px" },
  { title: "ACCIÓN", key: "actions", sortable: false, align: "end", width: "130px" },
];

const fetchStats = async () => {
  try {
    const params = {
      search: searchQuery.value,
      start_date: startDate.value,
      end_date: endDate.value,
    };
    const { data } = await axios.get("/suppliers/purchase-orders-laboratory/stats", {
      params,
    });
    stats.value = data.data;
  } catch (error) {
    console.error("Error al obtener estadísticas de laboratorios:", error);
  }
};

const fetchLaboratories = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    search: searchQuery.value,
    start_date: startDate.value,
    end_date: endDate.value,
    status: activeTab.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  try {
    const { data } = await axios.get("/suppliers/purchase-orders-laboratory", { params });
    laboratories.value = data.data.data;
    totalLaboratories.value = data.data.total;
  } catch (error) {
    toast.error("Error al obtener las órdenes por laboratorio.");
  } finally {
    loading.value = false;
  }
};

const handleManage = (lab) => {
  currentLaboratory.value = { ...lab };
  isManagementDialogVisible.value = true;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  startDate.value = "";
  endDate.value = "";
  page.value = 1;
};

const updateTableOptions = (options) => {
  page.value = options.page || 1;
  itemsPerPage.value = options.itemsPerPage || 10;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  }
  fetchLaboratories();
};

let debounceTimer = null;
watch([activeTab, searchQuery, startDate, endDate], () => {
  page.value = 1;
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchLaboratories();
    fetchStats();
  }, 300);
});

onMounted(() => {
  fetchLaboratories();
  fetchStats();
});

onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer);
});
</script>

<template>
  <div class="purchase-orders-laboratory-view pb-12">
    <div class="d-flex flex-column gap-4 mt-2">
      <!-- Header y Filtros -->
      <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
        <VCardText class="pa-4">
          <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
            <div class="d-flex align-center gap-2">
              <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
                <VIcon icon="tabler-flask" size="22" />
              </VAvatar>
              <div>
                <h1 class="text-h6 font-weight-black text-uppercase text-high-emphasis">
                  Órdenes por Laboratorio
                </h1>
                <p class="text-xs text-medium-emphasis mb-0">
                  Consolidado analítico de órdenes de compra agrupadas por laboratorio comercial.
                </p>
              </div>
            </div>

            <VBtn
              v-if="searchQuery || startDate || endDate"
              variant="tonal"
              color="secondary"
              size="small"
              prepend-icon="tabler-filter-off"
              @click="handleClearFilters"
            >
              Limpiar Filtros
            </VBtn>
          </div>

          <VRow dense align="center">
            <!-- Buscador -->
            <VCol cols="12" md="6">
              <VTextField
                v-model="searchQuery"
                placeholder="Buscar por laboratorio o producto..."
                prepend-inner-icon="tabler-search"
                density="compact"
                variant="outlined"
                hide-details
                clearable
              />
            </VCol>

            <!-- Rango Fechas -->
            <VCol cols="12" sm="6" md="3">
              <VTextField
                v-model="startDate"
                type="date"
                label="Desde"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <VTextField
                v-model="endDate"
                type="date"
                label="Hasta"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Tarjetas KPIs Resumen -->
      <VRow dense>
        <VCol cols="12" sm="6" md="3">
          <VCard class="rounded-lg border shadow-sm bg-surface">
            <VCardText class="pa-4 d-flex align-center gap-3">
              <VAvatar color="primary" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-flask" size="26" />
              </VAvatar>
              <div>
                <span class="text-xs text-medium-emphasis font-weight-bold uppercase">Laboratorios</span>
                <div class="text-h6 font-weight-black text-high-emphasis">
                  {{ stats.total_laboratories }}
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard class="rounded-lg border shadow-sm bg-surface">
            <VCardText class="pa-4 d-flex align-center gap-3">
              <VAvatar color="info" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-category" size="26" />
              </VAvatar>
              <div>
                <span class="text-xs text-medium-emphasis font-weight-bold uppercase">SKUs Pedidos</span>
                <div class="text-h6 font-weight-black text-high-emphasis">
                  {{ stats.total_skus }}
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard class="rounded-lg border shadow-sm bg-surface">
            <VCardText class="pa-4 d-flex align-center gap-3">
              <VAvatar color="warning" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-packages" size="26" />
              </VAvatar>
              <div>
                <span class="text-xs text-medium-emphasis font-weight-bold uppercase">Unidades Totales</span>
                <div class="text-h6 font-weight-black text-high-emphasis">
                  {{ stats.total_units }}
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard class="rounded-lg border shadow-sm bg-surface">
            <VCardText class="pa-4 d-flex align-center gap-3">
              <VAvatar color="success" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-currency-dollar" size="26" />
              </VAvatar>
              <div>
                <span class="text-xs text-medium-emphasis font-weight-bold uppercase">Monto Total Estimado</span>
                <div class="text-h6 font-weight-black text-success">
                  ${{ formatUsd(stats.total_amount) }}
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Pestañas de Navegación por Estado -->
      <VTabs
        v-model="activeTab"
        color="primary"
        grow
        class="premium-tabs rounded-lg border bg-surface overflow-hidden shadow-sm"
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

      <!-- Tabla de Laboratorios -->
      <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
        <VDataTableServer
          :headers="headers"
          :items="laboratories"
          :items-length="totalLaboratories"
          :items-per-page="itemsPerPage"
          :page="page"
          :loading="loading"
          density="comfortable"
          hover
          class="text-no-wrap"
          @update:options="updateTableOptions"
        >
          <!-- Laboratorio -->
          <template #item.laboratory_name="{ item }">
            <div class="d-flex align-center gap-2 py-2">
              <VAvatar color="primary" variant="tonal" size="34" class="rounded-lg flex-shrink-0">
                <VIcon icon="tabler-flask" size="18" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase">
                  {{ item.laboratory_name }}
                </span>
                <span v-if="item.laboratory_id" class="text-xs text-disabled">
                  ID: #{{ item.laboratory_id }}
                </span>
              </div>
            </div>
          </template>

          <!-- SKUs -->
          <template #item.total_skus="{ item }">
            <VChip
              size="small"
              color="info"
              variant="tonal"
              class="font-weight-black"
            >
              {{ item.total_skus }} SKUs
            </VChip>
          </template>

          <!-- Unidades -->
          <template #item.total_units="{ item }">
            <VChip
              size="small"
              color="warning"
              variant="tonal"
              class="font-weight-black"
            >
              {{ item.total_units }} uds
            </VChip>
          </template>

          <!-- Total USD -->
          <template #item.total_amount_usd="{ item }">
            <span class="text-subtitle-2 font-weight-black text-success">
              ${{ formatUsd(item.total_amount_usd) }}
            </span>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-settings"
              class="font-weight-bold"
              @click="handleManage(item)"
            >
              Gestionar
            </VBtn>
          </template>

          <template #no-data>
            <div class="text-center py-10 text-disabled">
              <VIcon icon="tabler-flask-off" size="36" class="mb-2 opacity-50" />
              <div class="text-sm font-weight-medium">No hay órdenes de compra registradas para este estado</div>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Modal de Gestión por Laboratorio -->
    <PurchaseOrderLaboratoryManagementDialog
      v-model="isManagementDialogVisible"
      :laboratory="currentLaboratory"
      :status="activeTab"
      :start-date="startDate"
      :end-date="endDate"
      :is-admin="isAdmin"
      @refresh="
        fetchLaboratories();
        fetchStats();
      "
    />
  </div>
</template>

<style scoped>
.purchase-orders-laboratory-view {
  min-block-size: 100vh;
}

.premium-tabs :deep(.v-tab--selected) {
  background-color: rgba(var(--v-theme-primary), 5%);
}
</style>
