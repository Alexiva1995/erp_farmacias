<script setup>
// Filtros Reporte ABC
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  filters: {
    type: Object,
    required: true,
  },
  abcOptions: {
    type: Array,
    required: true,
  },
  coverageOptions: {
    type: Array,
    required: true,
  },
});

const emit = defineEmits(["update:filters", "clear"]);

const updateFilter = (key, value) => {
  emit("update:filters", { ...props.filters, [key]: value });
};

// Como no hay búsqueda textual, obligamos a abrir
const hasAdvancedFilters = computed(() => true);
</script>

<template>
  <AppFilterBase
    :search="''"
    :force-advanced="true"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="..."
    @clear="emit('clear')"
  >
    <!-- Sustituir buscador primario por selectores base -->
    <template #search>
      <div class="d-flex align-center gap-2 flex-grow-1 min-width-0 w-100">
        <!-- Fecha Inicial -->
        <AppDateTimePicker
          :model-value="props.filters.startDate"
          placeholder="Fecha Inicial"
          clearable
          density="compact"
          hide-details
          class="flex-grow-1"
          style="min-width: 130px;"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar"
          @update:model-value="updateFilter('startDate', $event)"
        />

        <span class="text-disabled d-none d-sm-inline">—</span>

        <!-- Fecha Final -->
        <AppDateTimePicker
          :model-value="props.filters.endDate"
          placeholder="Fecha Final"
          clearable
          density="compact"
          hide-details
          class="flex-grow-1"
          style="min-width: 130px;"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="updateFilter('endDate', $event)"
        />
      </div>
    </template>

    <template #advanced-filters>
      <!-- Cobertura -->
      <VCol cols="12" sm="6">
        <VSelect
          :model-value="props.filters.coverage_range"
          :items="props.coverageOptions"
          placeholder="Filtro de Cobertura"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-chart-pie"
          @update:model-value="updateFilter('coverage_range', $event)"
        />
      </VCol>

      <!-- Clase ABC -->
      <VCol cols="12" sm="6">
        <VSelect
          :model-value="props.filters.classification"
          :items="props.abcOptions"
          placeholder="Clase (A, B, C...)"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-tag"
          @update:model-value="updateFilter('classification', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
