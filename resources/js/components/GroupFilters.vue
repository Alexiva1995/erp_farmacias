<script setup>
// Filtros para Grupos (Products/Attributes)
import AppFilterBase from "@/components/AppFilterBase.vue";

const props = defineProps({
  searchQuery:    String,
  isStrictSearch: Boolean,
  loading:        { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:isStrictSearch",
  "clear",
  "add-group",
]);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :show-add="true"
    add-button-text="Añadir Grupo"
    search-placeholder="Buscar por nombre, descripción..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-group')"
  >
    <template #search-extra>
      <!-- Búsqueda Estricta -->
      <VCol cols="auto" class="d-none d-sm-flex">
        <VCheckbox
          :model-value="props.isStrictSearch"
          label="Estricta"
          color="primary"
          density="compact"
          hide-details
          @update:model-value="emit('update:isStrictSearch', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
