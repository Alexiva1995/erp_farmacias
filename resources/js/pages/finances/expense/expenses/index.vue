<script setup lang="js">
import ExpenseFormDialoge from "@/components/dialogs/ExpenseFormDialoge.vue";
import ExpenseTable from "@/components/ExpenseTable.vue";
import FiltrosGastos from "@/components/FiltrosGastos.vue";
import LoaderComponent from "@/components/LoaderComponent.vue";
import { useExpenses } from "@/composables/useExpenses";
import { onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";

const authStore = useAuthStore();

const {
  isDeductible,
  activeTab,
  stats,
  modal,
  statuModule,
  formulario,
  formularioError,
  buscardor_filtro,
  category_id_filtro,
  currency,
  fechaDesde_filtro,
  fechaHasta_filtro,
  loading,
  isLoadingFilters,
  page,
  itemsPerPage,
  mostarModal,
  cerrarModal,
  updateTableOptions,
  limpliarFiltros,
  generaPdf,
  exportarExcel,
  enviar,
  cambiarEstadoGasto,
  initialize,
} = useExpenses();

onMounted(() => {
  initialize();
});

const tabItems = [
  {
    label: "Todos",
    value: null,
    color: "primary",
    icon: "tabler-list",
    totalKey: "total",
  },
  {
    label: "Pendientes",
    value: "Pending",
    color: "warning",
    icon: "tabler-clock",
    totalKey: "totalPending",
  },
  {
    label: "Aprobados",
    value: "Approved",
    color: "success",
    icon: "tabler-circle-check",
    totalKey: "totalApproved",
  },
  {
    label: "Cancelados",
    value: "Cancelled",
    color: "error",
    icon: "tabler-circle-x",
    totalKey: "totalCancelled",
  },
];

const kpis = [
  {
    title: "Aprobados",
    key: "totalApproved",
    amountKey: "amountApproved",
    color: "success",
    icon: "tabler-circle-check",
    gradient: "bg-gradient-success",
  },
  {
    title: "Pendientes",
    key: "totalPending",
    amountKey: "amountPending",
    color: "warning",
    icon: "tabler-clock",
    gradient: "bg-gradient-warning",
  },
  {
    title: "Cancelados",
    key: "totalCancelled",
    amountKey: "amountCancelled",
    color: "error",
    icon: "tabler-circle-x",
    gradient: "bg-gradient-error",
  },
];
</script>

<template>
  <div class="expenses-view pb-12">
    <LoaderComponent :loadingApp="statuModule.loadingApp" />

    <div class="d-flex flex-column gap-1 mt-1">
      <!-- === FILTROS === -->
      <div>
        <FiltrosGastos
          v-model:currency="currency"
          v-model:buscardor_filtro="buscardor_filtro"
          v-model:category_id_filtro="category_id_filtro"
          v-model:fechaDesde_filtro="fechaDesde_filtro"
          v-model:fechaHasta_filtro="fechaHasta_filtro"
          v-model:isDeductible="isDeductible"
          :categorias="statuModule.categorias"
          :loading="isLoadingFilters"
          :show-add-button="true"
          class="mb-5"
          @export-excel="exportarExcel"
          @export-pdf="generaPdf"
          @clear="limpliarFiltros"
          @add="mostarModal"
        />
      </div>

      <!-- === KPIS RÁPIDOS === -->
      <VRow v-if="authStore.isAdmin" class="ma-0 mx-n1 mb-5" dense>
        <VCol v-for="kpi in kpis" :key="kpi.key" cols="12" sm="4" class="pa-1">
          <VCard
            class="stats-card h-100 border-0 overflow-hidden shadow-sm position-relative"
          >
            <div
              class="card-bg-decoration"
              :class="`bg-${kpi.color}-opacity-1`"
            ></div>
            <VCardText class="pa-4 relative-content h-100 d-flex flex-column">
              <div class="d-flex align-center gap-3 mb-3">
                <VAvatar
                  :color="kpi.color"
                  variant="tonal"
                  size="38"
                  class="rounded-lg"
                >
                  <VIcon :icon="kpi.icon" size="20" />
                </VAvatar>
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-widest"
                >
                  {{ kpi.title }}
                </span>
              </div>
              <div class="mt-auto">
                <div
                  :class="[
                    'text-h5 font-weight-black leading-none mb-1',
                    `text-${kpi.color}`,
                  ]"
                >
                  ${{ (stats[kpi.amountKey] || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </div>
                <div class="d-flex align-center gap-1">
                  <span class="text-xs font-weight-black text-high-emphasis">
                    {{ stats[kpi.key] || 0 }}
                  </span>
                  <span class="text-super-xs text-disabled font-weight-black uppercase">
                    Gastos
                  </span>
                </div>
              </div>
            </VCardText>
            <div class="accent-border" :class="`bg-${kpi.color}`"></div>
          </VCard>
        </VCol>
      </VRow>

      <!-- === TABLA CON TABS === -->
      <VCard
        variant="flat"
        class="rounded-lg border shadow-sm bg-surface overflow-hidden"
      >
        <!-- Tabs de estado -->
        <VTabs
          v-model="activeTab"
          color="primary"
          class="px-4 bg-surface"
          align-tabs="start"
          density="comfortable"
        >
          <VTab
            v-for="tab in tabItems"
            :key="tab.label"
            :value="tab.value"
            class="tab-with-badge font-weight-black text-xs uppercase letter-spacing-widest"
          >
            <VIcon :icon="tab.icon" size="18" class="me-2" />
            {{ tab.label }}
            <VChip
              v-if="stats[tab.totalKey] >= 0"
              size="x-small"
              variant="tonal"
              :color="tab.color"
              class="ms-2 font-weight-black"
            >
              {{
                tab.value === null
                  ? (Number(stats.totalApproved) || 0) +
                    (Number(stats.totalPending) || 0) +
                    (Number(stats.totalCancelled) || 0)
                  : (stats[tab.totalKey] || 0)
              }}
            </VChip>
          </VTab>
        </VTabs>
        <VDivider />

        <!-- Tabla / Cards Mobile -->
        <ExpenseTable
          :items="statuModule.items"
          :total="statuModule.total"
          :loading="loading"
          :statu-module="statuModule"
          :items-per-page="itemsPerPage"
          :page="page"
          @update:options="updateTableOptions"
          @approve="(id) => cambiarEstadoGasto(id, 'Approved')"
        />
      </VCard>
    </div>

    <!-- === MODAL FORMULARIO === -->
    <ExpenseFormDialoge
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      :categorias="statuModule.categorias"
      @modal-close="cerrarModal"
      @clear-error-form="formularioError = {}"
      @save="enviar"
    />
  </div>
</template>

<style scoped>
.expenses-view {
  background-color: #f8fafc;
  min-block-size: 100vh;
}

.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 90%) !important;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.stats-card:not(.no-hover):hover {
  transform: translateY(-4px);
  background: rgba(var(--v-theme-surface), 98%) !important;
  box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.15) !important;
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 60px;
  filter: blur(35px);
  inline-size: 60px;
  inset-block-start: -10px;
  inset-inline-end: -10px;
  pointer-events: none;
  opacity: 0.5;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 100%;
  inline-size: 4px;
  inset-block-start: 0;
  inset-inline-start: 0;
  opacity: 0.7;
}

.bg-success-opacity-1 {
  background: rgba(var(--v-theme-success), 0.1);
}
.bg-warning-opacity-1 {
  background: rgba(var(--v-theme-warning), 0.1);
}
.bg-error-opacity-1 {
  background: rgba(var(--v-theme-error), 0.1);
}

.letter-spacing-widest {
  letter-spacing: 0.1em !important;
}
.text-super-xs {
  font-size: 0.65rem !important;
}

.shadow-soft {
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important;
}

:deep(.v-tabs) {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.1);
}

:deep(.v-tab) {
  min-block-size: 52px !important;
}
</style>
