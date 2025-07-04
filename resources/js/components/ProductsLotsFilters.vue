<script setup>
defineProps({
  searchQuery: { type: String, required: true },
  itemsPerPage: { type: Number, required: true },
});

const emit = defineEmits(["update:searchQuery", "update:itemsPerPage"]);
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12">
          <AppTextField
            :model-value="searchQuery"
            placeholder="Buscar por Producto, Proveedor..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <!-- Puedes añadir un botón para limpiar si lo deseas en el futuro -->
      <VSpacer />

      <VBtn color="primary" prepend-icon="tabler-plus" @click="emit('add-lot')">
        Agregar Lote
      </VBtn>
    </VCardActions>
  </VCard>
</template>
