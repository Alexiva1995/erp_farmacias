<script setup>
// Filtros de facturas de ingresos (Stock/Inventory)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:      String,
  selectedSupplier: [Number, String, null],
  startDate:        [String, null],
  endDate:          [String, null],
  suppliers:        { type: Array,   default: () => [] },
  loading:          { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedSupplier",
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
    search-placeholder="Buscar N° Factura, Control..."
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

      <!-- Fecha de Recibo Desde -->
      <VCol cols="12" sm="4">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Fecha de Recibo Desde"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha de Recibo Hasta -->
      <VCol cols="12" sm="4">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Fecha de Recibo Hasta"
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
