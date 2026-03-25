<script setup>
// Filtros para empleados RRHH
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  search:              { type: String,  default: "" },
  showActiveEmployees: { type: Boolean, default: true },
});

const emit = defineEmits([
  "update:search",
  "update:showActiveEmployees",
  "clear",
  "add-employee",
]);

const statusOptions = [
  { title: "Activos",               value: true  },
  { title: "Inactivos / Despedidos", value: false },
];

const hasAdvancedFilters = computed(() => props.showActiveEmployees !== true);
</script>

<template>
  <AppFilterBase
    :search="props.search"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="true"
    add-button-text="Nuevo Empleado"
    search-placeholder="Buscar por nombre, identificación o correo..."
    @update:search="emit('update:search', $event)"
    @clear="emit('clear')"
    @add="emit('add-employee')"
  >
    <template #advanced-filters>
      <!-- Estado del empleado -->
      <VCol cols="12" sm="6" md="4">
        <VSelect
          :model-value="props.showActiveEmployees"
          :items="statusOptions"
          placeholder="Estado del Empleado"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-users-group"
          @update:model-value="emit('update:showActiveEmployees', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
