<script setup>
import { computed } from "vue";

const props = defineProps({
  searchQuery: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(["update:searchQuery"]);

const internalSearchQuery = computed({
  get: () => props.searchQuery,
  set: (value) => emit("update:searchQuery", value),
});
</script>

<template>
  <VCard title="Filtro de Búsqueda" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12">
          <AppTextField
            v-model="internalSearchQuery"
            placeholder="Buscar por Producto, Principio Activo, Código de Barras..."
            clearable
          />
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
