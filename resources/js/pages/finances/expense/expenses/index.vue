<script setup lang="js">
import ExpenseFormDialoge from '@/components/dialogs/ExpenseFormDialoge.vue';
import ExpenseTable from '@/components/ExpenseTable.vue';
import FiltrosGastos from '@/components/FiltrosGastos.vue';
import LoaderComponent from '@/components/LoaderComponent.vue';
import { useExpenses } from '@/composables/useExpenses';
import { onMounted } from 'vue';

const {
  isDeductible,
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
</script>

<template>
  <LoaderComponent :loadingApp="statuModule.loadingApp" />
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
      @export-excel="exportarExcel"
      @export-pdf="generaPdf"
      @clear="limpliarFiltros"
      @add="mostarModal"
    />
    <ExpenseFormDialoge
      type_of_expense="normal"
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      :categorias="statuModule.categorias"
      @modal-close="cerrarModal"
      @clear-error-form="formularioError = {}"
      @save="enviar"
    />
    <VCard title="Gastos">
      <VDivider />
      <ExpenseTable
        :items="statuModule.items"
        :total="statuModule.total"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @update:options="updateTableOptions"
      />
    </VCard>
  </div>
</template>
