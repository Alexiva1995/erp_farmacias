<script setup>
// Filtros Productos sin Lotes
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: String,
});

const emit = defineEmits(["update:searchQuery", "clear", "add-lot"]);

const hasAdvancedFilters = computed(() => false);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="true"
    add-button-text="Agregar Lote"
    search-placeholder="Buscar Producto, Proveedor, Lote..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-lot')"
  >
  </AppFilterBase>
</template>
