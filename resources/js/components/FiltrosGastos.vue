<script setup lang="js">
// Filtros de Gastos
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed, ref } from 'vue';

const props = defineProps({
  buscardor_filtro: { type: String, required: true, default: "" },
  currency: { type: String, required: true },
  category_id_filtro: { type: String, required: true },
  categorias: { type: Array, required: true, default: () => [] },
  fechaHasta_filtro: { type: String, required: true, default: "" },
  fechaDesde_filtro: { type: String, required: true, default: "" },
  isDeductible: Boolean,
  showAddButton: { type: Boolean, required: false, default: true },
  loading: { type: Boolean, default: false },
});

const currencies = ["BS", "USD", "COP"];

const emit = defineEmits([
  "update:currency",
  "update:buscardor_filtro",
  "update:category_id_filtro",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "export-excel",
  "export-pdf",
  "add",
  "update:isDeductible",
]);

const hasAdvancedFilters = computed(() => {
  return !!(
    props.category_id_filtro ||
    props.currency ||
    props.fechaDesde_filtro ||
    props.fechaHasta_filtro ||
    props.isDeductible
  );
});
</script>

<template>
  <AppFilterBase
    :search="props.buscardor_filtro"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="props.showAddButton"
    :show-export="true"
    search-placeholder="ID, Nombre o Concepto..."
    class="mb-6"
    @update:search="emit('update:buscardor_filtro', $event)"
    @clear="emit('clear')"
    @add="emit('add')"
    @export="(ext) => ext === 'pdf' ? emit('export-pdf') : emit('export-excel', ext)"
  >
    <template #advanced-filters>
      <!-- Clasificación -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          :model-value="props.category_id_filtro"
          :items="props.categorias"
          :loading="props.loading"
          placeholder="Categoría"
          item-title="name"
          item-value="id"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-category"
          @update:model-value="emit('update:category_id_filtro', $event)"
        />
      </VCol>

      <!-- Moneda -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.currency"
          :items="currencies"
          placeholder="Moneda"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-coin"
          @update:model-value="emit('update:currency', $event)"
        />
      </VCol>

      <!-- Rango de Fechas -->
      <VCol cols="12" sm="6" md="5">
        <div class="d-flex align-center gap-2">
          <AppDateTimePicker
            :model-value="props.fechaDesde_filtro"
            placeholder="Desde"
            clearable
            density="compact"
            hide-details
            class="flex-grow-1"
            :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            prepend-inner-icon="tabler-calendar"
            @update:model-value="emit('update:fechaDesde_filtro', $event)"
          />
          <AppDateTimePicker
            :model-value="props.fechaHasta_filtro"
            placeholder="Hasta"
            clearable
            density="compact"
            hide-details
            class="flex-grow-1"
            :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            prepend-inner-icon="tabler-calendar-check"
            @update:model-value="emit('update:fechaHasta_filtro', $event)"
          />
        </div>
      </VCol>

      <!-- Deducibles -->
      <VCol cols="12" sm="6" md="2" class="d-flex align-center">
        <VSwitch
          :model-value="props.isDeductible"
          label="Deducibles"
          color="primary"
          density="compact"
          hide-details
          inset
          class="ms-2"
          @update:model-value="emit('update:isDeductible', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}
</style>
