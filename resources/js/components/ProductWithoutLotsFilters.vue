<script setup>
defineProps({
  searchQuery: String,
});

const emit = defineEmits(["update:searchQuery", "clear", "add-lot"]);
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <!-- Sección del campo de búsqueda -->
    <VCardText>
      <VRow>
        <VCol cols="12">
          <AppTextField
            :model-value="searchQuery"
            placeholder="Buscar por Producto, Proveedor, Lote..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <!-- Sección de acciones (botones) -->
    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>

      <VSpacer />

      <VBtn color="primary" prepend-icon="tabler-plus" @click="emit('add-lot')">
        Agregar Lote
      </VBtn>
    </VCardActions>
  </VCard>
</template>
