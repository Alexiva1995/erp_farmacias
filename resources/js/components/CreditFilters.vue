<script setup>
// Filtros para créditos a clientes
import AppFilterBase from "@/components/AppFilterBase.vue";

const props = defineProps({
  searchQuery: String,
});

const emit = defineEmits(["update:searchQuery", "clear"]);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    search-placeholder="Buscar por documento o nombre del cliente..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  />
</template>
