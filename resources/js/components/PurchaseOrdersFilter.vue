<script setup>
import { ref } from "vue";

const props = defineProps({
  selectedSupplier: [Number, String, null],
  searchQuery: { type: String, default: "" },
  startDate: { type: String, default: "" },
  endDate: { type: String, default: "" },
  suppliers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:selectedSupplier",
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "clear",
]);

const isAdvancedFiltersVisible = ref(false);
</script>

<template>
  <VCard variant="flat" border class="mb-6 rounded-xl overflow-hidden shadow-sm bg-surface">
    <VCardText class="pa-4">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-3 flex-nowrap">
        <VCol class="flex-grow-1">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID de Orden..."
            prepend-inner-icon="tabler-search"
            clearable
            hide-details
            density="compact"
            class="filter-search-input font-weight-bold"
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VCol cols="auto" class="d-flex gap-2">
          <VTooltip location="top" text="Filtros Avanzados">
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
                variant="tonal"
                density="comfortable"
                class="rounded-lg"
                icon="tabler-filter"
                @click="isAdvancedFiltersVisible = !isAdvancedFiltersVisible"
              />
            </template>
          </VTooltip>

          <VTooltip location="top" text="Limpiar Filtros">
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                color="secondary"
                variant="tonal"
                density="comfortable"
                class="rounded-lg"
                icon="tabler-trash"
                @click="emit('clear')"
              />
            </template>
          </VTooltip>
        </VCol>
      </VRow>

      <!-- Panel de Filtros Avanzados (Colapsable) -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VRow class="mt-4 px-1 gap-y-4">
            <!-- Proveedor -->
            <VCol cols="12" sm="4">
              <AppAutocomplete
                :model-value="props.selectedSupplier"
                :items="props.suppliers"
                :loading="props.loading"
                placeholder="Proveedor"
                item-title="name"
                item-value="id"
                clearable
                hide-details
                density="compact"
                prepend-inner-icon="tabler-truck"
                @update:model-value="emit('update:selectedSupplier', $event)"
              />
            </VCol>

            <!-- Fecha Inicio -->
            <VCol cols="12" sm="4">
              <AppDateTimePicker
                :model-value="props.startDate"
                placeholder="Desde"
                prepend-inner-icon="tabler-calendar-event"
                clearable
                hide-details
                density="compact"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:startDate', $event)"
              />
            </VCol>

            <!-- Fecha Fin -->
            <VCol cols="12" sm="4">
              <AppDateTimePicker
                :model-value="props.endDate"
                placeholder="Hasta"
                prepend-inner-icon="tabler-calendar-check"
                clearable
                hide-details
                density="compact"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:endDate', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.filter-search-input :deep(.v-field__input) {
  font-size: 0.8125rem !important;
}

.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>

