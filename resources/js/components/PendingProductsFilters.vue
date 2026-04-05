<script setup>
// Filtros Productos Pendientes (PendingProducts)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:        String,
  selectedLaboratory: [Number, String, null],
  selectedOrigin:     [Number, String, null],
  laboratories:       { type: Array,   default: () => [] },
  origins:            { type: Array,   default: () => [] },
  loading:            { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "clear",
]);

const hasAdvancedFilters = computed(() =>
  !!(props.selectedLaboratory || props.selectedOrigin)
);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar ID, Producto, C. Activo..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <template #advanced-filters>
      <!-- Laboratorio -->
      <VCol cols="12" sm="6" md="4">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          :loading="props.loading"
          item-title="name"
          item-value="id"
          placeholder="Laboratorio"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-flask"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

      <!-- Origen -->
      <VCol cols="12" sm="6" md="4">
        <VAutocomplete
          :model-value="props.selectedOrigin"
          :items="props.origins"
          :loading="props.loading"
          item-title="name"
          item-value="id"
          placeholder="Origen"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-map-pin"
          @update:model-value="emit('update:selectedOrigin', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
