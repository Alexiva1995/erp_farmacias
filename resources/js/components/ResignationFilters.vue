<script setup>
// Filtros para renuncias (Resignations)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  search: { type: String, default: "" },
  filters: {
    type: Object,
    default: () => ({
      resignation_type: null,
      date_from: null,
      date_to: null,
      status: null,
    }),
  },
});

const emit = defineEmits(["update:search", "update:filters", "clear"]);

const hasActiveAdvancedFilters = computed(() => {
  return (
    props.filters.resignation_type ||
    props.filters.date_from ||
    props.filters.date_to
  );
});

const resignationTypes = [
  { title: "Renuncia Voluntaria", value: "voluntary" },
  { title: "Despido Injustificado", value: "unjustified_dismissal" },
];
</script>

<template>
  <AppFilterBase
    :search="props.search"
    :has-advanced-filters="hasActiveAdvancedFilters"
    search-placeholder="Buscar empleado..."
    @update:search="emit('update:search', $event)"
    @clear="emit('clear')"
  >
    <template #advanced-filters>
      <!-- Tipo de Egreso -->
      <VCol cols="12" sm="4">
        <VSelect
          :model-value="props.filters.resignation_type"
          :items="resignationTypes"
          placeholder="Tipo de egreso"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-list-details"
          @update:model-value="emit('update:filters', { ...props.filters, resignation_type: $event })"
        />
      </VCol>

      <!-- Fecha Desde -->
      <VCol cols="12" sm="4">
        <AppDateTimePicker
          :model-value="props.filters.date_from"
          placeholder="Desde"
          prepend-inner-icon="tabler-calendar"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:filters', { ...props.filters, date_from: $event })"
        />
      </VCol>

      <!-- Fecha Hasta -->
      <VCol cols="12" sm="4">
        <AppDateTimePicker
          :model-value="props.filters.date_to"
          placeholder="Hasta"
          prepend-inner-icon="tabler-calendar"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:filters', { ...props.filters, date_to: $event })"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
