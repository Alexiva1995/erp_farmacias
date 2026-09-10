<script setup>
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: { type: [String, null], default: "" },
  startDate: { type: [String, null], default: null },
  endDate: { type: [String, null], default: null },
  selectedMovementType: { type: [Array, String, null], default: () => [] },
  excludeMovementTypes: { type: [Array, String, null], default: () => [] },
  isExporting: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "update:selectedMovementType",
  "update:excludeMovementTypes",
  "clear",
  "export",
]);

const movementTypes = [
  { title: "Venta", value: "sale" },
  { title: "Compra", value: "purchase" },
  { title: "Devolución", value: "return" },
  { title: "Verificado", value: "verification" },
  { title: "Ajuste", value: "adjustment" },
  { title: "Pérdida", value: "loss" },
  { title: "Caducado", value: "expired" },
];

const hasAdvancedFilters = computed(() => {
  const hasIncluded = Array.isArray(props.selectedMovementType)
    ? props.selectedMovementType.length > 0
    : !!props.selectedMovementType;
  const hasExcluded = Array.isArray(props.excludeMovementTypes)
    ? props.excludeMovementTypes.length > 0
    : !!props.excludeMovementTypes;

  return !!(hasIncluded || hasExcluded || props.startDate || props.endDate);
});
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-export="true"
    :export-loading="props.isExporting"
    search-placeholder="Buscar por ID, Producto, Laboratorio..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @export="(ext) => emit('export', ext)"
  >
    <template #advanced-filters>
      <!-- Tipos de Movimiento (Incluir) -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.selectedMovementType"
          :items="movementTypes"
          placeholder="Incluir Movimientos"
          clearable
          multiple
          chips
          closable-chips
          density="compact"
          hide-details
          prepend-inner-icon="tabler-arrows-diff"
          @update:model-value="emit('update:selectedMovementType', $event)"
        />
      </VCol>

      <!-- Tipos de Movimiento (Excluir) -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.excludeMovementTypes"
          :items="movementTypes"
          placeholder="Excluir Movimientos"
          clearable
          multiple
          chips
          closable-chips
          density="compact"
          hide-details
          color="error"
          prepend-inner-icon="tabler-filter-x"
          @update:model-value="emit('update:excludeMovementTypes', $event)"
        />
      </VCol>

      <!-- Fecha Desde -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Fecha Inicial"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha Hasta -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Fecha Final"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
