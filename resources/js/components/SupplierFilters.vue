<script setup>
// Filtros de Proveedores
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: String,
});

const emit = defineEmits([
  "update:searchQuery",
  "clear",
  "sort",
  "add-supplier",
]);

const sortOptions = [
  { title: "Deuda mayor",      icon: "tabler-arrow-up",   key: "debt",              order: "desc" },
  { title: "Deuda menor",      icon: "tabler-arrow-down", key: "debt",              order: "asc"  },
  { title: "Más Calificación", icon: "tabler-plus",       key: "latestScore.score", order: "desc" },
  { title: "Menos Calificación", icon: "tabler-minus",    key: "latestScore.score", order: "asc"  },
];

const hasAdvancedFilters = computed(() => false);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    search-placeholder="Buscar por ID, Nombre o RIF del Proveedor..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @sort="(sortFilter) => emit('sort', sortFilter)"
  >
    <template #actions-extra>
      <VBtn
        color="primary"
        variant="flat"
        size="38"
        class="rounded-lg ml-1 font-weight-black"
        @click="emit('add-supplier')"
      >
        <VIcon icon="tabler-plus" size="20" class="d-sm-none" />
        <span class="d-none d-sm-flex px-2">
          <VIcon icon="tabler-plus" size="18" class="mr-2" />
          Añadir Proveedor
        </span>
        <VTooltip activator="parent" location="top" class="d-sm-none">Añadir Proveedor</VTooltip>
      </VBtn>
    </template>
  </AppFilterBase>
</template>
