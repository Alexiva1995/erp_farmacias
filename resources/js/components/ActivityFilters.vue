<script setup>
// Filtros para actividades de limpieza/productividad
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:       String,
  selectedFrequency: [String, null],
  frequencies:       { type: Array,   default: () => [] },
  loading:           { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedFrequency",
  "clear",
  "add-activity",
  "sort",
]);

// Opciones de ordenamiento del estándar de oro
const sortOptions = [
  { title: "Nombre A-Z",    icon: "tabler-sort-ascending-letters",  key: "activity",   order: "asc"  },
  { title: "Nombre Z-A",    icon: "tabler-sort-descending-letters", key: "activity",   order: "desc" },
  { title: "Frecuencia A-Z",icon: "tabler-calendar",               key: "frequency",  order: "asc"  },
  { title: "Más Recientes", icon: "tabler-clock",                  key: "created_at", order: "desc" },
];

// Indicador de filtros avanzados activos
const hasAdvancedFilters = computed(() => !!props.selectedFrequency);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-add="true"
    add-button-text="Nueva Actividad"
    search-placeholder="Buscar por actividad o descripción..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-activity')"
    @sort="emit('sort', $event)"
  >
    <template #advanced-filters>
      <!-- Filtro por frecuencia -->
      <VCol cols="12" sm="6" md="4">
        <VSelect
          :model-value="props.selectedFrequency"
          :items="props.frequencies"
          :loading="props.loading"
          placeholder="Frecuencia"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-calendar-repeat"
          @update:model-value="emit('update:selectedFrequency', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
