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
      <div class="filters-container">
        <!-- Buscador N° Factura -->
        <div class="filter-item filter-search">
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
        </div>

        <!-- Selector Proveedor -->
        <div class="filter-item filter-supplier">
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
        </div>

        <!-- Fecha Desde -->
        <div class="filter-item filter-date">
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
        </div>

        <!-- Fecha Hasta -->
        <div class="filter-item filter-date">
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
        </div>

        <!-- Checkbox Solo Vencidos -->
        <div class="filter-item filter-checkbox">
          <VCheckbox
            :model-value="props.showOverdueOnly"
            label="Solo Vencidos"
            hide-details
            density="compact"
            color="error"
            class="text-no-wrap"
            @update:model-value="emit('update:showOverdueOnly', $event)"
          />
        </div>

        <!-- Acciones a la derecha -->
        <div class="filter-actions">
          <slot name="selection-actions" />

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
            <VTooltip activator="parent" location="top">Sincronizar Facturas (Dronena, Drocerca, Mafarta, Cristmedicals, Dromega y Drosymca)</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-1" style="height: 24px;" />

          <!-- Botón Borrar / Limpiar Filtros -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            rounded="circle"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-eraser" size="22" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.app-filter-single-row {
  background-color: rgb(var(--v-theme-surface));
}

.filters-container {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  flex-wrap: nowrap;
}

.filter-search {
  flex: 1 1 180px;
  min-width: 140px;
}

.filter-supplier {
  flex: 1.2 1 200px;
  min-width: 150px;
}

.filter-date {
  flex: 0 0 130px;
  width: 130px;
  min-width: 120px;
}

.filter-checkbox {
  flex: 0 0 auto;
  margin-left: 4px;
}

.filter-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-left: auto;
  flex-shrink: 0;
}

@media (max-width: 960px) {
  .filters-container {
    flex-wrap: wrap;
  }

  .filter-search,
  .filter-supplier {
    flex: 1 1 100%;
    min-width: 100%;
  }

  .filter-date {
    flex: 1 1 calc(50% - 8px);
    width: auto;
  }

  .filter-actions {
    width: 100%;
    justify-content: flex-end;
    margin-top: 4px;
  }
}
</style>
