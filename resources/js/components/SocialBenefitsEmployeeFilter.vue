<script setup>
const props = defineProps({
  search: { type: String, default: "" },
});

const emit = defineEmits(["update:search", "clear"]);
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="4">
          <AppTextField
            :model-value="props.search"
            placeholder="Buscar por nombre, apellido, cédula o correo"
            clearable
            @update:model-value="emit('update:search', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
