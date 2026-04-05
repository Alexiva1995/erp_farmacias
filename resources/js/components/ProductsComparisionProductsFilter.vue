<script setup>
// Filtros Comparador de Productos
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:        { type: String,  default: "" },
  selectedSupplier:   [Number, String, null],
  selectedLaboratory: [Number, String, null],
  selectedOrigin:     [Number, String, null],
  suppliers:          { type: Array,   default: () => [] },
  origins:            { type: Array,   default: () => [] },
  laboratories:       { type: Array,   default: () => [] },
  loading:            { type: Boolean, default: false },
  enableDiscounts:    { type: Boolean, default: false },
  enableUsdAmountCol: { type: Boolean, default: false },
  enableDiscountCol:  { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedSupplier",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:enableDiscounts",
  "update:enableUsdAmountCol",
  "update:enableDiscountCol",
  "clear",
  "open-delete-dialog",
  "update-all-api",
]);

const hasAdvancedFilters = computed(() =>
  !!(props.selectedSupplier || props.selectedLaboratory || props.selectedOrigin)
);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Nombre, ID, C. Activo..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <template #actions-extra>
      <!-- Botones principales del comparador -->
      <VBtn
        icon
        color="info"
        variant="tonal"
        size="38"
        class="ml-1"
        @click="emit('update-all-api')"
      >
        <VIcon icon="tabler-cloud-download" />
        <VTooltip activator="parent" location="top">Actualizar Vía API</VTooltip>
      </VBtn>

      <VBtn
        icon
        color="error"
        variant="tonal"
        size="38"
        class="ml-1"
        @click="emit('open-delete-dialog')"
      >
        <VIcon icon="tabler-trash" />
        <VTooltip activator="parent" location="top">Eliminar Productos</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Laboratorio -->
      <VCol cols="12" sm="4">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          :loading="props.loading"
          placeholder="Laboratorio"
          item-title="name"
          item-value="id"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-flask"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

      <!-- Origen -->
      <VCol cols="12" sm="4">
        <VSelect
          :model-value="props.selectedOrigin"
          :items="props.origins"
          item-title="name"
          item-value="id"
          placeholder="Origen"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-world"
          @update:model-value="emit('update:selectedOrigin', $event)"
        />
      </VCol>

      <!-- Proveedor -->
      <VCol cols="12" sm="4">
        <VAutocomplete
          :model-value="props.selectedSupplier"
          :items="props.suppliers"
          :loading="props.loading"
          placeholder="Proveedor"
          item-title="name"
          item-value="id"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-building-factory-2"
          @update:model-value="emit('update:selectedSupplier', $event)"
        />
      </VCol>

      <!-- Panel de Switches (Opciones Visuales) -->
      <VCol cols="12">
        <div class="d-flex flex-wrap align-center gap-4 bg-surface-variant-opacity-2 pa-3 rounded-lg border-dashed">
          <span class="text-xs font-weight-black uppercase text-disabled mr-2">Visualización:</span>
          
          <VSwitch
            :model-value="props.enableDiscounts"
            label="Aplicar Descuento"
            density="compact"
            color="primary"
            hide-details
            @update:model-value="emit('update:enableDiscounts', $event)"
          />

          <VSwitch
            :model-value="props.enableUsdAmountCol"
            label="Divisas ($)"
            density="compact"
            color="success"
            hide-details
            @update:model-value="emit('update:enableUsdAmountCol', $event)"
          />

          <VSwitch
            :model-value="props.enableDiscountCol"
            label="Ver % Desc."
            density="compact"
            color="info"
            hide-details
            @update:model-value="emit('update:enableDiscountCol', $event)"
          />
        </div>
      </VCol>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}
.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.4);
}
</style>
