<script setup>
// Filtros para gestión de packs de productos
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:   { type: String,  default: "" },
  idSearchQuery: { type: String,  default: "" },
  loading:       { type: Boolean, default: false },
  showAddButton: { type: Boolean, default: true },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:idSearchQuery",
  "clear",
  "add-pack",
]);

const hasAdvancedFilters = computed(() => !!props.idSearchQuery);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="props.showAddButton"
    add-button-text="Añadir Pack"
    search-placeholder="Buscar por nombre del pack..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-pack')"
  >
    <template #advanced-filters>
      <!-- ID del Pack -->
      <VCol cols="12" sm="6" md="4">
        <AppTextField
          :model-value="props.idSearchQuery"
          placeholder="ID EJ: 12"
          prepend-inner-icon="tabler-hash"
          clearable
          density="compact"
          hide-details
          :disabled="props.loading"
          @update:model-value="emit('update:idSearchQuery', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
