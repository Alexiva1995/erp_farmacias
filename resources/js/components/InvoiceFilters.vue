<script setup>
// Filtros de facturas de ingresos (Stock/Inventory)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedSupplier: [Number, String, null],
  startDate: [String, null],
  endDate: [String, null],
  suppliers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  showAdd: { type: Boolean, default: true },
  showBulkDelete: { type: Boolean, default: false },
  showSyncDronena: { type: Boolean, default: true },
  isSyncingDronena: { type: Boolean, default: false },
  showSyncMafarta: { type: Boolean, default: true },
  isSyncingMafarta: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedSupplier",
  "update:startDate",
  "update:endDate",
  "clear",
  "create-invoice",
  "bulk-delete",
  "sync-dronena",
  "sync-mafarta",
]);

const hasAdvancedFilters = computed(
  () => !!(props.selectedSupplier || props.startDate || props.endDate),
);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="props.showAdd"
    add-button-text="Registrar Factura"
    search-placeholder="Buscar N° Factura, Control..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('create-invoice')"
  >
    <template #prepend-actions>
      <VBtn
        v-if="props.showSyncDronena"
        icon
        color="info"
        variant="tonal"
        size="38"
        rounded="circle"
        :loading="props.isSyncingDronena"
        @click="emit('sync-dronena')"
      >
        <VIcon icon="tabler-refresh" />
        <VTooltip activator="parent" location="top">Sincronizar Dronena</VTooltip>
      </VBtn>

      <VBtn
        v-if="props.showSyncMafarta"
        icon
        color="warning"
        variant="tonal"
        size="38"
        rounded="circle"
        :loading="props.isSyncingMafarta"
        @click="emit('sync-mafarta')"
      >
        <VIcon icon="tabler-arrows-down-up" />
        <VTooltip activator="parent" location="top">Sincronizar Mafarta / Cobeca (SIC)</VTooltip>
      </VBtn>

      <VBtn
        v-if="props.showBulkDelete"
        icon
        color="error"
        variant="tonal"
        size="38"
        rounded="circle"
        @click="emit('bulk-delete')"
      >
        <VIcon icon="tabler-trash-x" />
        <VTooltip activator="parent" location="top">Eliminación Masiva por Fecha</VTooltip>
      </VBtn>
    </template>
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
