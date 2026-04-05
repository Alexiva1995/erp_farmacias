<script setup>
// Filtros Reporte Trazabilidad
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:          [String, null],
  startDate:            [String, null],
  endDate:              [String, null],
  selectedMovementType: [String, null],
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "update:selectedMovementType",
  "clear",
  "export",
]);

const movementTypes = [
  { title: "Venta",      value: "sale"       },
  { title: "Compra",     value: "purchase"   },
  { title: "Devolución", value: "return"     },
  { title: "Ajuste",     value: "adjustment" },
  { title: "Pérdida",    value: "loss"       },
  { title: "Caducado",   value: "expired"    },
];

const hasAdvancedFilters = computed(() =>
  !!(props.selectedMovementType || props.startDate || props.endDate)
);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-export="true"
    search-placeholder="Buscar por ID, Producto, Laboratorio..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @export="(ext) => emit('export', ext)"
  >
    <template #advanced-filters>
      <!-- Tipo de Movimiento -->
      <VCol cols="12" sm="4">
        <VSelect
          :model-value="props.selectedMovementType"
          :items="movementTypes"
          placeholder="Tipo de Movimiento"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-arrows-diff"
          @update:model-value="emit('update:selectedMovementType', $event)"
        />
      </VCol>

      <!-- Fecha Desde -->
      <VCol cols="12" sm="4">
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
      <VCol cols="12" sm="4">
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
