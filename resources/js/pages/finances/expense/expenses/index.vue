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
  { label: 'Todos',      value: null,        color: 'primary',  icon: 'tabler-list',          totalKey: 'totalApproved' }, // Aproximación
  { label: 'Pendientes', value: 'Pending',   color: 'warning',  icon: 'tabler-clock',         totalKey: 'totalPending' },
  { label: 'Aprobados',  value: 'Approved',  color: 'success',  icon: 'tabler-circle-check',   totalKey: 'totalApproved' },
  { label: 'Cancelados', value: 'Cancelled', color: 'error',    icon: 'tabler-circle-x',       totalKey: 'totalCancelled' },
];
</script>

<template>
  <LoaderComponent :loadingApp="statuModule.loadingApp" />

  <!-- === FILTROS === -->
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

  <!-- === TABLA CON TABS === -->
  <VCard variant="outlined" class="rounded-lg bg-surface mt-4">
    <!-- Tabs de estado (Diseño inspirado en orderGeneral) -->
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
            {{ tab.value === null ? (stats.totalApproved + stats.totalPending + stats.totalCancelled) : stats[tab.totalKey] }}
          </VChip>
        </span>
      </VTab>
    </VTabs>
    <VDivider />

    <!-- Tabla -->
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
</template>

<style scoped>
.border-success { border: 1px solid rgba(40, 199, 111, 50%) !important; }
.border-warning { border: 1px solid rgba(255, 159, 67, 50%) !important; }
.border-error { border: 1px solid rgba(234, 84, 85, 50%) !important; }

/* stylelint-disable-next-line selector-pseudo-class-no-unknown */
:deep(.v-tabs) {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
