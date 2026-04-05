<script setup>
// Filtros para ofertas por médico
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  idSearchQuery:   { type: String,  default: "" },
  searchQuery:     { type: String,  default: "" },
  addOfferLoading: { type: Boolean, default: false },
  showAddButton:   { type: Boolean, default: true  },
});

const emit = defineEmits([
  "update:idSearchQuery",
  "update:searchQuery",
  "clear",
  "add-doctors",
]);

const hasAdvancedFilters = computed(() => !!(props.idSearchQuery));
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="props.showAddButton"
    add-button-text="Nueva Oferta"
    search-placeholder="Buscar médico por nombre..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-doctors')"
  >
    <template #advanced-filters>
      <!-- ID del médico -->
      <VCol cols="12" sm="6" md="3">
        <AppTextField
          :model-value="props.idSearchQuery"
          placeholder="ID del Médico (ej: 12)"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-hash"
          @update:model-value="emit('update:idSearchQuery', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
