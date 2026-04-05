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
    :show-advanced="false"
    search-placeholder="Buscar por nombre, cédula o correo..."
    class="py-1"
    @update:search="emit('update:search', $event)"
    @clear="emit('clear')"
  />
</template>
