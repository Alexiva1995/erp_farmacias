<script setup>
// Filtros para cierre de caja
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: String,
  startDate:   [String, null],
  endDate:     [String, null],
  users:       { type: Array,   default: () => [] },
  loading:     { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "clear",
]);

// Indicador de filtros avanzados activos
const hasAdvancedFilters = computed(() => !!(props.startDate || props.endDate));
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar por producto, usuario..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <template #advanced-filters>
      <!-- Fecha desde -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Desde"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-calendar-event"
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha hasta -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Hasta"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-calendar-event"
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
