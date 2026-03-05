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
  initialize
} = useExpenses();

onMounted(() => {
  initialize();
});

const tabItems = [
  { label: 'Todos',      value: null,        color: 'primary',  icon: 'tabler-list' },
  { label: 'Pendientes', value: 'Pending',   color: 'warning',  icon: 'tabler-clock' },
  { label: 'Aprobados',  value: 'Approved',  color: 'success',  icon: 'tabler-circle-check' },
  { label: 'Cancelados', value: 'Cancelled', color: 'error',    icon: 'tabler-circle-x' },
];

const statsCards = [
  {
    label: 'Aprobados',
    key: 'totalApproved',
    icon: 'tabler-circle-check',
    color: 'success',
    description: 'Gastos confirmados',
  },
  {
    label: 'Pendientes',
    key: 'totalPending',
    icon: 'tabler-clock',
    color: 'warning',
    description: 'Esperando aprobación',
  },
  {
    label: 'Cancelados',
    key: 'totalCancelled',
    icon: 'tabler-circle-x',
    color: 'error',
    description: 'Gastos rechazados',
  },
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

  <!-- === STATS CARDS === -->
  <VRow class="mb-6" dense>
    <VCol
      v-for="card in statsCards"
      :key="card.key"
      cols="12"
      sm="6"
      md="4"
    >
      <VCard variant="outlined" :class="`border-${card.color}`" class="rounded-lg bg-white h-100">
        <VCardText class="pa-4">
          <div class="d-flex align-center gap-3">
            <VAvatar
              :color="card.color"
              variant="tonal"
              size="48"
              rounded="lg"
            >
              <VIcon :icon="card.icon" size="28" />
            </VAvatar>
            <div v-if="!stats.loading" class="flex-grow-1">
              <span class="text-caption text-medium-emphasis font-weight-bold d-block text-uppercase mb-1">
                {{ card.label }}
              </span>
              <span class="text-h5 font-weight-black" :class="`text-${card.color}`">
                {{ stats[card.key] ?? 0 }}
              </span>
            </div>
            <VSkeletonLoader v-else type="text, text" class="flex-grow-1" />
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

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
  <VCard variant="outlined" class="rounded-lg bg-surface mt-6">
    <!-- Tabs de estado -->
    <VTabs
      v-model="activeTab"
      color="primary"
      class="px-6 py-2"
      align-tabs="start"
      fixed-tabs
    >
      <VTab
        v-for="tab in tabItems"
        :key="tab.label"
        :value="tab.value"
        class="text-body-2 font-weight-bold"
      >
        <VIcon :icon="tab.icon" size="18" class="me-2" />
        {{ tab.label }}
        <VBadge
          v-if="tab.value === 'Pending' && stats.totalPending > 0"
          :content="stats.totalPending"
          color="warning"
          floating
          offset-x="-12"
          offset-y="-12"
        />
      </VTab>
    </VTabs>
    <VDivider />

    <!-- Tabla -->
    <ExpenseTable
      :items="statuModule.items"
      :total="statuModule.total"
      :loading="loading"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
    />
  </VCard>
</template>

<style scoped>
.border-success { border: 1px solid rgba(40, 199, 111, 50%) !important; }
.border-warning { border: 1px solid rgba(255, 159, 67, 50%) !important; }
.border-error { border: 1px solid rgba(234, 84, 85, 50%) !important; }

:deep(.v-tabs) {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
