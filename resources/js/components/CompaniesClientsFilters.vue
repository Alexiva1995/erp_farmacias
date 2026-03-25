<script setup>
// Filtros para clientes asociados a empresas (CRM)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  buscador:                  String,
  tipo_identificacion_filtro:[String, null],
  fechaHasta_filtro:         [String, null],
  fechaDesde_filtro:         [String, null],
});

const emit = defineEmits([
  "update:buscador",
  "update:tipo_identificacion_filtro",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "add-client",
  "add-existing-client",
  "export-pdf",
  "export-excel",
]);

// Indicador de filtros avanzados activos
const hasAdvancedFilters = computed(() =>
  !!(props.tipo_identificacion_filtro || props.fechaDesde_filtro || props.fechaHasta_filtro)
);
</script>

<template>
  <AppFilterBase
    :search="props.buscador"
    :has-advanced-filters="hasAdvancedFilters"
    :show-export="true"
    :show-add="true"
    add-button-text="Añadir Cliente"
    search-placeholder="Buscar cliente por nombre o ID..."
    @update:search="emit('update:buscador', $event)"
    @clear="emit('clear')"
    @add="emit('add-existing-client')"
    @export="(fmt) => fmt === 'pdf' ? emit('export-pdf') : emit('export-excel', fmt)"
  >
    <template #advanced-filters>
      <!-- Tipo de Identificación -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.tipo_identificacion_filtro"
          :items="['V-', 'J-', 'G-', 'E-']"
          placeholder="Tipo ID"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-id"
          @update:model-value="emit('update:tipo_identificacion_filtro', $event)"
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
          prepend-inner-icon="tabler-calendar"
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
          prepend-inner-icon="tabler-calendar"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:fechaHasta_filtro', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
