<script setup>
// Filtros para laboratorios asignados a empleados de productividad
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:        String,
  selectedLaboratory: [Number, null],
  laboratories:       { type: Array,   default: () => [] },
  loading:            { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "clear",
  "add-assignment",
  "sort",
]);

const sortOptions = [
  { title: "Empleado A-Z",      icon: "tabler-sort-ascending-letters",  key: "employee_name",      order: "asc"  },
  { title: "Empleado Z-A",      icon: "tabler-sort-descending-letters", key: "employee_name",      order: "desc" },
  { title: "Más Laboratorios",  icon: "tabler-sort-descending",         key: "laboratories_count", order: "desc" },
  { title: "Menos Laboratorios",icon: "tabler-sort-ascending",          key: "laboratories_count", order: "asc"  },
  { title: "Más Recientes",     icon: "tabler-clock",                   key: "created_at",         order: "desc" },
];

const hasAdvancedFilters = computed(() => !!props.selectedLaboratory);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-add="true"
    add-button-text="Asignar Laboratorio"
    search-placeholder="Buscar por nombre de empleado..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-assignment')"
    @sort="emit('sort', $event)"
  >
    <template #advanced-filters>
      <!-- Laboratorio -->
      <VCol cols="12" sm="6" md="4">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          :loading="props.loading"
          placeholder="Laboratorio"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-flask"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
