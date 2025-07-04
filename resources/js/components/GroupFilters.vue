<script setup>
defineProps({
  searchQuery: String,
});

defineEmits(["update:searchQuery", "clear", "add-group"]);
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12">
          <AppTextField
            :model-value="searchQuery"
            placeholder="Buscar por nombre de grupo..."
            clearable
            @update:model-value="$emit('update:searchQuery', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="$emit('clear')">
        Limpiar Filtro
      </VBtn>
      <VSpacer />
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="$emit('add-group')"
      >
        Añadir Grupo
      </VBtn>
    </VCardActions>
  </VCard>
</template>
