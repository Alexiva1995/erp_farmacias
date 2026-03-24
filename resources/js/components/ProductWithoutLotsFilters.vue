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
    search-placeholder="Buscar Producto, Proveedor, Lote..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <template #actions-extra>
      <VBtn
        color="primary"
        variant="flat"
        size="38"
        class="rounded-lg ml-1 font-weight-black"
        @click="emit('add-lot')"
      >
        <VIcon icon="tabler-plus" size="20" class="d-sm-none" />
        <span class="d-none d-sm-flex px-2">
          <VIcon icon="tabler-plus" size="18" class="mr-2" />
          Agregar Lote
        </span>
        <VTooltip activator="parent" location="top" class="d-sm-none">Agregar Lote</VTooltip>
      </VBtn>
    </template>
  </AppFilterBase>
</template>
