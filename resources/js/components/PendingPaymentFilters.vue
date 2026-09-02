<script setup>
// Filtros Cuentas por Pagar — Diseño horizontal unificado en una sola fila para Desktop
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
  isSyncingBots:      { type: Boolean, default: false },
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
  "sync-bots",
]);

const hasActiveFilters = computed(() =>
  !!(props.searchQuery || props.selectedSupplier || props.startDate || props.endDate || props.showOverdueOnly)
);
</script>

<template>
  <VCard class="rounded-lg border shadow-sm mb-4 app-filter-single-row">
    <VCardText class="pa-2 px-3">
      <VRow align="center" dense class="d-flex align-center flex-wrap ga-2">
        <!-- Buscador N° Factura -->
        <VCol cols="12" sm="6" md="3" lg="2" class="d-flex align-center">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar N° Factura..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <!-- Selector Proveedor -->
        <VCol cols="12" sm="6" md="3" lg="2.5" class="d-flex align-center">
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
        <VCol cols="6" sm="4" md="2" lg="1.5" class="d-flex align-center">
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
        <VCol cols="6" sm="4" md="2" lg="1.5" class="d-flex align-center">
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

        <!-- Checkbox Solo Vencidos -->
        <VCol cols="auto" class="d-flex align-center">
          <VCheckbox
            :model-value="props.showOverdueOnly"
            label="Solo Vencidos"
            hide-details
            density="compact"
            color="error"
            class="text-no-wrap"
            @update:model-value="emit('update:showOverdueOnly', $event)"
          />
        </VCol>

        <VSpacer class="d-none d-md-flex" />

        <!-- Acciones a la derecha -->
        <VCol cols="12" sm="auto" class="d-flex align-center justify-end gap-1 flex-wrap ms-auto">
          <slot name="selection-actions" />

          <!-- Botón Limpiar Filtros -->
          <VBtn
            v-if="hasActiveFilters"
            icon
            variant="tonal"
            color="error"
            size="38"
            rounded="circle"
            class="shadow-sm"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-filter-x" size="20" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>

          <!-- Botón Sincronizar Bots -->
          <VBtn
            icon
            variant="tonal"
            color="info"
            size="38"
            rounded="circle"
            class="shadow-sm"
            :loading="props.isSyncingBots || props.isSyncingDronena"
            @click="emit('sync-bots')"
          >
            <VIcon icon="tabler-robot" size="20" />
            <VTooltip activator="parent" location="top">Sincronizar Facturas con Droguerías</VTooltip>
          </VBtn>

          <!-- Botón Refrescar -->
          <VBtn
            icon
            variant="tonal"
            color="primary"
            size="38"
            rounded="circle"
            class="shadow-sm"
            :loading="props.loading"
            @click="emit('refresh')"
          >
            <VIcon icon="tabler-refresh" size="20" />
            <VTooltip activator="parent" location="top">Actualizar Datos</VTooltip>
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.app-filter-single-row {
  background-color: rgb(var(--v-theme-surface));
}
</style>
