<script setup>
// Filtros para gestión de récipes (Prescriptions)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:   { type: String,  default: "" },
  idSearchQuery: { type: String,  default: "" },
  mode:          { type: String,  default: "all" },
  loading:       { type: Boolean, default: false },
  showAddButton: { type: Boolean, default: true },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:idSearchQuery",
  "update:mode",
  "clear",
  "add-prescription",
]);

const hasAdvancedFilters = computed(() =>
  !!(props.idSearchQuery || (props.mode && props.mode !== "all"))
);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="props.showAddButton"
    add-button-text="Nueva Oferta"
    search-placeholder="Buscar oferta por nombre..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-prescription')"
  >
    <template #advanced-filters>
      <!-- ID de Oferta -->
      <VCol cols="12" sm="6" md="4">
        <AppTextField
          :model-value="props.idSearchQuery"
          placeholder="ID (ej: 89)"
          prepend-inner-icon="tabler-hash"
          clearable
          density="compact"
          hide-details
          :disabled="props.loading"
          @update:model-value="emit('update:idSearchQuery', $event)"
        />
      </VCol>

      <!-- Estado de Oferta -->
      <VCol cols="12" sm="6" md="4">
        <VSelect
          :model-value="props.mode"
          :items="[
            { title: 'Todas las ofertas', value: 'all' },
            { title: 'Solo activas', value: 'active' },
            { title: 'Solo inactivas', value: 'inactive' },
          ]"
          item-title="title"
          item-value="value"
          placeholder="Estado"
          density="compact"
          hide-details
          :disabled="props.loading"
          @update:model-value="emit('update:mode', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
