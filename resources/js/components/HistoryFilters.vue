<script setup>
// Filtros de Historial Fiscal
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: String,
  startDate:   [String, null],
  endDate:     [String, null],
  origins:     { type: Array,   default: () => [] },
  loading:     { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "clear",
  "export",
  "sort",
]);

const sortOptions = [
  { title: "Precio Mayor",   icon: "tabler-arrow-up",      key: "total_amount", order: "desc" },
  { title: "Precio Menor",   icon: "tabler-arrow-down",    key: "total_amount", order: "asc"  },
  { title: "Fecha Reciente", icon: "tabler-calendar-up",   key: "invoice_date", order: "desc" },
  { title: "Fecha Antigua",  icon: "tabler-calendar-down", key: "invoice_date", order: "asc"  },
];

const hasAdvancedFilters = computed(() => !!(props.startDate || props.endDate));
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-export="true"
    search-placeholder="Buscar por ID, Razón, Factura..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @export="(fmt) => emit('export', fmt)"
    @sort="emit('sort', $event)"
  >
    <template #advanced-filters>
      <!-- Fecha Desde -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Desde"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha Hasta -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Hasta"
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
