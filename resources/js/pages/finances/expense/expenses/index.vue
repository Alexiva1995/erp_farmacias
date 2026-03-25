<script setup lang="js">
import ExpenseFormDialoge from '@/components/dialogs/ExpenseFormDialoge.vue';
import ExpenseTable from '@/components/ExpenseTable.vue';
import FiltrosGastos from '@/components/FiltrosGastos.vue';
import LoaderComponent from '@/components/LoaderComponent.vue';
import { useExpenses } from '@/composables/useExpenses';
import { onMounted } from 'vue';

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
  initialize
} = useExpenses();

onMounted(() => {
  initialize();
});

const tabItems = [
  { label: 'Todos',      value: null,        color: 'primary',  icon: 'tabler-list',          totalKey: 'total' },
  { label: 'Pendientes', value: 'Pending',   color: 'warning',  icon: 'tabler-clock',         totalKey: 'totalPending' },
  { label: 'Aprobados',  value: 'Approved',  color: 'success',  icon: 'tabler-circle-check',   totalKey: 'totalApproved' },
  { label: 'Cancelados', value: 'Cancelled', color: 'error',    icon: 'tabler-circle-x',       totalKey: 'totalCancelled' },
];

const kpis = [
  { title: 'Aprobados',  key: 'totalApproved',  color: 'success', icon: 'tabler-circle-check', gradient: 'bg-gradient-success' },
  { title: 'Pendientes', key: 'totalPending',   color: 'warning', icon: 'tabler-clock',        gradient: 'bg-gradient-warning' },
  { title: 'Cancelados', key: 'totalCancelled', color: 'error',   icon: 'tabler-circle-x',      gradient: 'bg-gradient-error' },
];
</script>

<template>
  <div class="expenses-view pb-12">
    <LoaderComponent :loadingApp="statuModule.loadingApp" />

    <!-- Header Premium -->
    <div class="header-bg pa-6 mb-6">
      <div class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center gap-4">
          <VAvatar
            size="54"
            color="white"
            variant="flat"
            class="rounded-lg shadow-soft"
          >
            <VIcon icon="tabler-receipt" color="primary" size="28" />
          </VAvatar>
          <div class="d-flex flex-column">
            <h1 class="text-h4 font-weight-black text-white letter-spacing-tight">
              Gestión de Gastos
            </h1>
            <span class="text-sm font-weight-bold text-white opacity-80 uppercase letter-spacing-widest">
              Control Detallado de Egresos Operativos
            </span>
          </div>
        </div>
      </div>

      <!-- KPIs Rápidos -->
      <VRow class="mt-8">
        <VCol
          v-for="kpi in kpis"
          :key="kpi.key"
          cols="12"
          sm="4"
        >
          <VCard
            variant="flat"
            class="rounded-lg border shadow-sm overflow-hidden kpi-card bg-white"
          >
            <div class="d-flex align-center pa-4">
              <VAvatar
                size="48"
                :color="kpi.color"
                variant="tonal"
                class="rounded-lg me-4"
              >
                <VIcon :icon="kpi.icon" size="24" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-xs font-weight-black text-disabled uppercase letter-spacing-widest">{{ kpi.title }}</span>
                <span class="text-h5 font-weight-black leading-tight">
                  {{ stats[kpi.key] || 0 }}
                  <span class="text-xs text-disabled">Items</span>
                </span>
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <!-- === FILTROS === -->
    <div class="px-6">
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
        @export-excel="exportarExcel"
        @export-pdf="generaPdf"
        @clear="limpliarFiltros"
        @add="mostarModal"
      />

      <!-- === TABLA CON TABS === -->
      <VCard variant="flat" class="rounded-lg border shadow-sm bg-surface overflow-hidden">
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
              v-if="stats[tab.totalKey] > 0 || tab.value === null"
              size="x-small"
              variant="tonal"
              :color="tab.color"
              class="ms-2 font-weight-black"
            >
              {{ tab.value === null ? (stats.totalApproved + stats.totalPending + stats.totalCancelled) : stats[tab.totalKey] }}
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

.header-bg {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #4a90e2 100%);
  border-block-end: 1px solid rgba(255, 255, 255, 10%);
}

.letter-spacing-tight { letter-spacing: -0.02em; }
.letter-spacing-widest { letter-spacing: 0.1em !important; }

.shadow-soft { box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important; }

.kpi-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-card:hover {
  box-shadow: 0 8px 24px 0 rgba(0, 0, 0, 10%) !important;
  transform: translateY(-4px);
}

.leading-tight { line-height: 1.2; }

:deep(.v-tabs) {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.1);
}

:deep(.v-tab) {
  min-block-size: 52px !important;
}
</style>
