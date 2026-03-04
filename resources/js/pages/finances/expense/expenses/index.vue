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

  <!-- === STATS CARDS === -->
  <VRow class="mb-4">
    <VCol
      v-for="card in statsCards"
      :key="card.key"
      cols="12"
      sm="6"
      md="4"
    >
      <VCard :border="`start ${card.color} thin`" rounded="lg" elevation="0">
        <VCardText class="d-flex align-center gap-4 pa-5">
          <VAvatar
            :color="card.color"
            variant="tonal"
            size="52"
            rounded="lg"
          >
            <VIcon :icon="card.icon" size="26" />
          </VAvatar>
          <div class="flex-grow-1">
            <div class="text-caption text-medium-emphasis mb-1">{{ card.description }}</div>
            <div v-if="stats.loading" class="d-flex align-center">
              <VSkeletonLoader type="text" width="60" class="me-2" />
            </div>
            <div v-else class="text-h5 font-weight-bold" :class="`text-${card.color}`">
              {{ stats[card.key] ?? 0 }}
            </div>
            <div class="text-body-2 font-weight-medium mt-1">{{ card.label }}</div>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

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
  <VCard rounded="lg" elevation="0">
    <!-- Tabs de estado -->
    <VTabs
      v-model="activeTab"
      color="primary"
      class="px-4 pt-1"
      density="comfortable"
    >
      <VTab
        v-for="tab in tabItems"
        :key="tab.label"
        :value="tab.value"
        class="text-body-2"
      >
        <VIcon :icon="tab.icon" size="16" class="me-1" />
        {{ tab.label }}
        <VBadge
          v-if="tab.value === 'Pending' && stats.totalPending > 0"
          :content="stats.totalPending"
          color="warning"
          floating
          offset-x="-8"
          offset-y="-8"
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
