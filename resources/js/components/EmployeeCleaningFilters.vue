<script setup>
// Filtros para actividades de limpieza por empleado (supervisor)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:    String,
  selectedStatus: [String, null],
  loading:        { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedStatus",
  "clear",
  "add-assignment",
  "sort",
]);

const statusOptions = [
  { title: "Pendiente",   value: "Pendiente"  },
  { title: "Completada",  value: "Completada" },
  { title: "Cancelada",   value: "Cancelada"  },
];

const sortOptions = [
  { title: "Empleado A-Z",     icon: "tabler-sort-ascending-letters",  key: "employee_name",    order: "asc"  },
  { title: "Empleado Z-A",     icon: "tabler-sort-descending-letters", key: "employee_name",    order: "desc" },
  { title: "Más Actividades",  icon: "tabler-sort-descending",         key: "activities_count", order: "desc" },
  { title: "Menos Actividades",icon: "tabler-sort-ascending",          key: "activities_count", order: "asc"  },
];

const hasAdvancedFilters = computed(() => !!props.selectedStatus);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-add="true"
    add-button-text="Asignar Actividades"
    search-placeholder="Buscar empleado por nombre o ID..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-assignment')"
    @sort="emit('sort', $event)"
  >
    <template #advanced-filters>
      <!-- Estado de actividad -->
      <VCol cols="12" sm="6" md="4">
        <VSelect
          :model-value="props.selectedStatus"
          :items="statusOptions"
          :loading="props.loading"
          placeholder="Estado de actividad"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-list-check"
          @update:model-value="emit('update:selectedStatus', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
