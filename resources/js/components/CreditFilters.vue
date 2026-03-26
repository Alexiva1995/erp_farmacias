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
    :show-advanced="false"
    search-placeholder="Buscar por cliente, identificación o documento..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  />
</template>
