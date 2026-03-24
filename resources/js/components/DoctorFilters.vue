<script setup>
// Filtros para doctores (CRM)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  buscador:          String,
  fechaHasta_filtro: [String, null],
  fechaDesde_filtro: [String, null],
});

const emit = defineEmits([
  "update:buscador",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "add-doctor",
  "export-pdf",
  "export-excel",
]);

const hasAdvancedFilters = computed(() =>
  !!(props.fechaDesde_filtro || props.fechaHasta_filtro)
);
</script>

<template>
  <AppFilterBase
    :search="props.buscador"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="true"
    :show-export="true"
    add-button-text="Agregar Doctor"
    search-placeholder="Buscar por nombre, identificación o dirección..."
    @update:search="emit('update:buscador', $event)"
    @clear="emit('clear')"
    @add="emit('add-doctor')"
    @export="(fmt) => fmt === 'pdf' ? emit('export-pdf') : emit('export-excel', fmt)"
  >
    <template #advanced-filters>
      <!-- Fecha desde -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.fechaDesde_filtro"
          placeholder="Fecha inicial"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-calendar-event"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:fechaDesde_filtro', $event)"
        />
      </VCol>

      <!-- Fecha hasta -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.fechaHasta_filtro"
          placeholder="Fecha final"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-calendar-event"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:fechaHasta_filtro', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
