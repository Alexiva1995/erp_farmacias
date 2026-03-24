<script setup>
// Filtros Evaluación Social / Beneficios (RRHH)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  search: { type: String, default: "" },
});

const emit = defineEmits(["update:search", "clear"]);

const hasAdvancedFilters = computed(() => false);
</script>

<template>
  <AppFilterBase
    :search="props.search"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar por Nombre, Cédula o Correo..."
    @update:search="emit('update:search', $event)"
    @clear="emit('clear')"
  />
</template>
