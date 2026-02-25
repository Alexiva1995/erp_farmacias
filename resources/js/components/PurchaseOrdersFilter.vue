<script setup>
const props = defineProps({
  selectedSupplier: [Number, String, null],
  suppliers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:selectedSupplier", "clear"]);
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow align="center">
        <VCol cols="12" md="12">
          <AppAutocomplete
            :model-value="props.selectedSupplier"
            :items="props.suppliers"
            :loading="props.loading"
            placeholder="Seleccionar un proveedor"
            item-title="name"
            item-value="id"
            clearable
            hide-details="auto"
            @update:model-value="emit('update:selectedSupplier', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
