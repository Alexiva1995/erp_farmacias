<script setup>
// Filtros para empresas (CRM)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  buscador:            String,
  tipo_empresa_filtro: String,
  fechaHasta_filtro:   [String, null],
  fechaDesde_filtro:   [String, null],
});

const emit = defineEmits([
  "update:buscador",
  "update:tipo_empresa_filtro",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "add-company",
  "export-pdf",
  "export-excel",
]);

const hasAdvancedFilters = computed(() =>
  !!(props.tipo_empresa_filtro || props.fechaDesde_filtro || props.fechaHasta_filtro)
);
</script>

<template>
  <AppFilterBase
    :search="props.buscador"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="true"
    :show-export="true"
    add-button-text="Nueva Empresa"
    search-placeholder="Buscar por nombre, RIF o dirección..."
    @update:search="emit('update:buscador', $event)"
    @clear="emit('clear')"
    @add="emit('add-company')"
    @export="(fmt) => fmt === 'pdf' ? emit('export-pdf') : emit('export-excel', fmt)"
  >
    <template #advanced-filters>
      <!-- Tipo de organización -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.tipo_empresa_filtro"
          :items="['Empresa', 'Clinica']"
          placeholder="Tipo de organización"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-building-factory-2"
          @update:model-value="emit('update:tipo_empresa_filtro', $event)"
        />
      </VCol>

      <!-- Fecha desde -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.fechaDesde_filtro"
          placeholder="Fecha desde"
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
          placeholder="Fecha hasta"
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
