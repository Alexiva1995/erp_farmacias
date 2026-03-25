<script setup lang="js">
import { ref } from 'vue';

const props = defineProps({
  buscardor_filtro: { type: String, required: true, default: () => "" },
  currency: { type: String, required: true },
  category_id_filtro: { type: String, required: true },
  categorias: { type: Array, required: true, default: () => [] },
  fechaHasta_filtro: { type: String, required: true, default: () => "" },
  fechaDesde_filtro: { type: String, required: true, default: () => "" },
  isDeductible: Boolean,
  showAddButton: { type: Boolean, required: false, default: true },
  loading: { type: Boolean, default: false },
});

const currencies = ["BS", "USD", "COP"];
const isFiltersVisible = ref(false);

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
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Header: Búsqueda y Botón de Colapso -->
    <div class="pa-4 d-flex align-center flex-wrap gap-4">
      <VTextField
        :model-value="props.buscardor_filtro"
        placeholder="Buscar gasto por nombre o ID..."
        variant="solo"
        density="compact"
        class="search-input rounded-lg flex-grow-1"
        prepend-inner-icon="tabler-search"
        hide-details
        clearable
        @update:model-value="emit('update:buscardor_filtro', $event)"
      />

      <div class="d-flex align-center gap-2">
        <VBtn
          variant="tonal"
          :color="isFiltersVisible ? 'primary' : 'secondary'"
          size="small"
          class="rounded-lg font-weight-black"
          @click="isFiltersVisible = !isFiltersVisible"
        >
          <VIcon :icon="isFiltersVisible ? 'tabler-chevron-up' : 'tabler-adjustments-horizontal'" start size="18" />
          {{ isFiltersVisible ? 'Ocultar' : 'Filtros' }}
        </VBtn>

        <VMenu>
          <template #activator="{ props: menuProps }">
            <VBtn
              color="success"
              variant="tonal"
              icon="tabler-download"
              size="small"
              class="rounded-lg"
              v-bind="menuProps"
            />
          </template>
          <VList class="rounded-lg shadow-soft py-1">
            <VListItem @click="emit('export-excel', 'xlsx')" density="compact">
              <template #prepend><VIcon icon="tabler-file-spreadsheet" color="success" size="18" /></template>
              <VListItemTitle class="text-xs font-weight-bold">Excel</VListItemTitle>
            </VListItem>
            <VListItem @click="emit('export-pdf')" density="compact">
              <template #prepend><VIcon icon="tabler-file-type-pdf" color="error" size="18" /></template>
              <VListItemTitle class="text-xs font-weight-bold">PDF</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>

        <VBtn
          v-if="props.showAddButton"
          color="primary"
          variant="elevated"
          size="small"
          class="rounded-lg font-weight-black px-4"
          prepend-icon="tabler-plus"
          @click="emit('add')"
        >
          Nuevo
        </VBtn>
      </div>
    </div>

    <!-- Panel Expandible -->
    <VExpandTransition>
      <div v-show="isFiltersVisible">
        <VDivider />
        <div class="pa-5 bg-white">
          <VRow>
            <VCol cols="12" sm="6" md="3">
              <span class="text-super-xs font-weight-black text-primary uppercase d-block mb-2">Clasificación</span>
              <VAutocomplete
                :model-value="props.category_id_filtro"
                :items="props.categorias"
                :loading="props.loading"
                placeholder="Todas las categorías"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @update:model-value="emit('update:category_id_filtro', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <span class="text-super-xs font-weight-black text-primary uppercase d-block mb-2">Moneda de Pago</span>
              <VSelect
                :model-value="props.currency"
                :items="currencies"
                placeholder="Cualquier moneda"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @update:model-value="emit('update:currency', $event)"
              />
            </VCol>

            <VCol cols="12" sm="12" md="4">
              <span class="text-super-xs font-weight-black text-primary uppercase d-block mb-2">Rango de Fecha</span>
              <div class="d-flex align-center gap-2">
                <AppDateTimePicker
                  :model-value="props.fechaDesde_filtro"
                  placeholder="Desde"
                  clearable
                  class="premium-input-compact"
                  :config="{ altInput: true, altFormat: 'd/m/Y', dateFormat: 'Y-m-d' }"
                  @update:model-value="emit('update:fechaDesde_filtro', $event)"
                />
                <span class="text-disabled text-caption">—</span>
                <AppDateTimePicker
                  :model-value="props.fechaHasta_filtro"
                  placeholder="Hasta"
                  clearable
                  class="premium-input-compact"
                  :config="{ altInput: true, altFormat: 'd/m/Y', dateFormat: 'Y-m-d' }"
                  @update:model-value="emit('update:fechaHasta_filtro', $event)"
                />
              </div>
            </VCol>

            <VCol cols="12" sm="6" md="2" class="d-flex align-end">
              <div class="d-flex flex-column w-100">
                <VBtn
                  variant="text"
                  color="error"
                  size="small"
                  class="rounded-lg font-weight-bold text-caption"
                  prepend-icon="tabler-trash-x"
                  @click="emit('clear')"
                >
                  Limpiar
                </VBtn>
                <VSwitch
                  label="Deducibles"
                  :model-value="props.isDeductible"
                  color="primary"
                  density="compact"
                  hide-details
                  inset
                  class="mt-2 ml-2"
                  @update:model-value="emit('update:isDeductible', $event)"
                />
              </div>
            </VCol>
          </VRow>
        </div>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 5%);
}

.shadow-soft {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 8%) !important;
}

:deep(.search-input) {
  .v-field {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: none !important;
  }
}

:deep(.premium-input-compact) {
  .v-field__input {
    font-size: 0.85rem !important;
    min-block-size: 38px !important;
    padding-block: 0 !important;
  }
}
</style>
