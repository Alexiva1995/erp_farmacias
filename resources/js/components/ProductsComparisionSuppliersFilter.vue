<script setup>
const props = defineProps({
  // Ahora es un String porque es texto libre, no un ID
  selectedSupplier: { type: String, default: "" },
});

const emit = defineEmits(["update:selectedSupplier", "clear"]);
</script>

<template>
  <VCardText>
    <VRow>
      <VCol cols="12" sm="6" md="4">
        <!-- Cambiado de VAutocomplete a AppTextField -->
        <AppTextField
          :model-value="props.selectedSupplier"
          label="Buscar Proveedor"
          placeholder="Nombre del proveedor..."
          clearable
          @update:model-value="emit('update:selectedSupplier', $event)"
        />
      </VCol>
    </VRow>
  </VCardText>

  <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
    <VBtn color="secondary" variant="outlined" @click="emit('clear')">
      Limpiar Filtros
    </VBtn>
  </VCardActions>
</template>
