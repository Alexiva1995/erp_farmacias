<script setup>
// Filtros Pagos Pendientes (PendingPayments / Diferente de PendingPayment)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:      String,
  selectedSupplier: [Number, String, null],
  startDate:        [String, null],
  endDate:          [String, null],
  showOverdueOnly:  { type: Boolean, default: false },
  suppliers:        { type: Array,   default: () => [] },
  loading:          { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedSupplier",
  "update:startDate",
  "update:endDate",
  "update:showOverdueOnly",
  "clear",
]);

const hasAdvancedFilters = computed(() =>
  !!(props.selectedSupplier || props.startDate || props.endDate || props.showOverdueOnly)
);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar por Proveedor..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <template #advanced-filters>
      <!-- Proveedor -->
      <VCol cols="12" sm="6" md="3">
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
          prepend-inner-icon="tabler-building-factory-2"
          @update:model-value="emit('update:selectedSupplier', $event)"
        />
      </VCol>

      <!-- Fecha de Pago Desde -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Pdgo Desde"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha de Pago Hasta -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Pago Hasta"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>

      <!-- Solo Vencidos -->
      <VCol cols="12" sm="6" md="3" class="d-flex align-center">
        <VCheckbox
          :model-value="props.showOverdueOnly"
          label="Pagos vencidos"
          hide-details
          density="compact"
          color="error"
          @update:model-value="emit('update:showOverdueOnly', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
