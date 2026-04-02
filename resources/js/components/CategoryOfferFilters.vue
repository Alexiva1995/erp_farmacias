<script setup>
// Filtros para ofertas por categoría
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  idSearchQuery:  { type: String,                      default: "" },
  searchQuery:    { type: String,                      default: "" },
  isActive:       { type: [String, Number, Boolean],   default: "" },
  addOfferLoading:{ type: Boolean,                     default: false },
});

const emit = defineEmits([
  "update:idSearchQuery",
  "update:searchQuery",
  "update:isActive",
  "clear",
  "add-categories",
]);

// Indicador de filtros avanzados activos
const hasAdvancedFilters = computed(() =>
  (props.idSearchQuery && props.idSearchQuery !== "") ||
  (props.isActive !== "" && props.isActive !== null)
);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="true"
    :add-button-text="'Nueva Oferta'"
    search-placeholder="Buscar categoría por nombre..."
    search-icon="tabler-search"
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-categories')"
  >
    <template #advanced-filters>
      <!-- ID de Oferta -->
      <VCol cols="12" sm="6" md="3">
        <AppTextField
          :model-value="props.idSearchQuery"
          placeholder="ID de Oferta (ej: 125)"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-hash"
          @update:model-value="emit('update:idSearchQuery', $event)"
        />
      </VCol>

      <!-- Estado -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.isActive"
          :items="[
            { value: '', title: 'Todos los estados' },
            { value: 1,  title: 'Activas' },
            { value: 0,  title: 'Inactivas' },
          ]"
          item-title="title"
          item-value="value"
          placeholder="Estado"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-circle-dot"
          @update:model-value="emit('update:isActive', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
