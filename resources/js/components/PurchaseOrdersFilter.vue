<script setup>
// Filtros Órdenes de Compra
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  selectedSupplier: [Number, String, null],
  searchQuery:      { type: String,  default: "" },
  startDate:        { type: String,  default: "" },
  endDate:          { type: String,  default: "" },
  suppliers:        { type: Array,   default: () => [] },
  loading:          { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:selectedSupplier",
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "clear",
]);

const hasAdvancedFilters = computed(() =>
  !!(props.selectedSupplier || props.startDate || props.endDate)
);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar por ID de Orden..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <template #advanced-filters>
      <!-- Proveedor -->
      <VCol cols="12" sm="4">
        <VAutocomplete
          :model-value="props.selectedSupplier"
          :items="props.suppliers"
          :loading="props.loading"
          placeholder="Proveedor"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-truck"
          @update:model-value="emit('update:selectedSupplier', $event)"
        />
      </VCol>

      <!-- Fecha Inicio -->
      <VCol cols="12" sm="4">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Desde"
          clearable
          density="compact"
          hide-details
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha Fin -->
      <VCol cols="12" sm="4">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Hasta"
          clearable
          density="compact"
          hide-details
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
