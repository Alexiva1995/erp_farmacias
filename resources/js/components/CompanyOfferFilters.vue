<script setup>
// Filtros para ofertas por empresa
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  idSearchQuery:  { type: String,  default: "" },
  searchQuery:    { type: String,  default: "" },
  addOfferLoading:{ type: Boolean, default: false },
});

const emit = defineEmits([
  "update:idSearchQuery",
  "update:searchQuery",
  "clear",
  "add-companies",
]);

const hasAdvancedFilters = computed(() => !!(props.idSearchQuery));
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="true"
    add-button-text="Nueva Oferta"
    search-placeholder="Buscar empresa por nombre..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-companies')"
  >
    <template #advanced-filters>
      <!-- ID de empresa -->
      <VCol cols="12" sm="6" md="3">
        <AppTextField
          :model-value="props.idSearchQuery"
          placeholder="ID de Empresa (ej: 45)"
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
