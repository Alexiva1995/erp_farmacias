<script setup>
const props = defineProps({
  searchQuery: String,
});

const emit = defineEmits(["update:searchQuery", "clear"]);
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="12" md="12">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por Documento o Nombre del Cliente..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar
      </VBtn>
    </VCardActions>
  </VCard>
</template>
