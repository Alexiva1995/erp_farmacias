<script setup>
// Filtro del Comparador por Proveedores (simplificado libre)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  selectedSupplier: { type: String, default: "" }, // Texto Libre
});

const emit = defineEmits(["update:selectedSupplier", "clear"]);

const hasAdvancedFilters = computed(() => false);
</script>

<template>
  <AppFilterBase
    :search="props.selectedSupplier"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Escriba el nombre del proveedor..."
    @update:search="emit('update:selectedSupplier', $event)"
    @clear="emit('clear')"
  />
</template>
