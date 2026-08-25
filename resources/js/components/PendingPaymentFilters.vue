<script setup>
// Filtros Cuentas por Pagar (Pending Payments con layout completo)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:        String,
  selectedSupplier:   [Number, String, null],
  startDate:          String,
  endDate:            String,
  showOverdueOnly:    Boolean,
  suppliers:          { type: Array,   default: () => [] },
  loading:            { type: Boolean, default: false },
  isLoadingFilters:   { type: Boolean, default: false },
  isSyncingDronena:   { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedSupplier",
  "update:startDate",
  "update:endDate",
  "update:showOverdueOnly",
  "clear",
  "refresh",
  "sync-dronena",
]);

const hasAdvancedFilters = computed(() =>
  !!(props.selectedSupplier || props.startDate || props.endDate || props.showOverdueOnly)
);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar N° Factura..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <template #actions-extra>
      <slot name="selection-actions" />

      <VBtn
        icon
        variant="tonal"
        color="info"
        size="38"
        class="ml-1"
        :loading="props.isSyncingDronena"
        @click="emit('sync-dronena')"
      >
        <VIcon icon="tabler-robot" size="20" />
        <VTooltip activator="parent" location="top">Sincronizar Facturas Dronena</VTooltip>
      </VBtn>

      <VBtn
        icon
        variant="tonal"
        color="primary"
        size="38"
        class="ml-1"
        :loading="props.loading"
        @click="emit('refresh')"
      >
        <VIcon icon="tabler-refresh" size="20" />
        <VTooltip activator="parent" location="top">Actualizar Datos</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Proveedor -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          :model-value="props.selectedSupplier"
          :items="props.suppliers"
          :loading="props.isLoadingFilters"
          item-title="name"
          item-value="id"
          placeholder="Proveedor"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-building-factory-2"
          @update:model-value="emit('update:selectedSupplier', $event)"
        />
      </VCol>

      <!-- Fecha Desde -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Desde"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha Hasta -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Hasta"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>

      <!-- Solo Vencidos -->
      <VCol cols="12" sm="6" md="3" class="d-flex align-center">
        <VCheckbox
          :model-value="props.showOverdueOnly"
          label="Solo Vencidos"
          hide-details
          density="compact"
          color="error"
          @update:model-value="emit('update:showOverdueOnly', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
