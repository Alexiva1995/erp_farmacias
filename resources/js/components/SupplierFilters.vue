<script setup>
// Filtros de Proveedores
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: String,
});

const emit = defineEmits([
  "update:searchQuery",
  "clear",
  "sort",
  "add-supplier",
]);

const sortOptions = [
  { title: "Deuda mayor",      icon: "tabler-arrow-up",   key: "debt",              order: "desc" },
  { title: "Deuda menor",      icon: "tabler-arrow-down", key: "debt",              order: "asc"  },
  { title: "Más Calificación", icon: "tabler-plus",       key: "latestScore.score", order: "desc" },
  { title: "Menos Calificación", icon: "tabler-minus",    key: "latestScore.score", order: "asc"  },
];

const hasAdvancedFilters = computed(() => false);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-add="true"
    add-button-text="Añadir Proveedor"
    search-placeholder="Buscar por ID, Nombre o RIF del Proveedor..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @sort="(sortFilter) => emit('sort', sortFilter)"
    @add="emit('add-supplier')"
  >
  </AppFilterBase>
</template>
