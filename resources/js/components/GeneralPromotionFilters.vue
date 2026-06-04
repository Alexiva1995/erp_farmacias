<script setup>
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  idSearchQuery:   { type: String,  default: "" },
  searchQuery:     { type: String,  default: "" },
  addOfferLoading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:idSearchQuery",
  "update:searchQuery",
  "clear",
  "add-promotion",
]);

const hasAdvancedFilters = computed(() => !!props.idSearchQuery);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="true"
    add-button-text="Nueva Promoción"
    search-placeholder="Buscar promoción general por tipo..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-promotion')"
  >
    <template #advanced-filters>
      <!-- ID de promoción -->
      <VCol cols="12" sm="6" md="4">
        <AppTextField
          :model-value="props.idSearchQuery"
          placeholder="Buscar por ID de promoción"
          prepend-inner-icon="tabler-hash"
          clearable
          density="compact"
          hide-details
          @update:model-value="emit('update:idSearchQuery', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
