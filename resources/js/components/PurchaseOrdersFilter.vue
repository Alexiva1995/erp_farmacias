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
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <VAutocomplete
            :model-value="props.selectedSupplier"
            :items="props.suppliers"
            :loading="props.loading"
            label="Proveedor"
            placeholder="Escribe para buscar un proveedor"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedSupplier', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VBtn color="secondary" variant="outlined" @click="emit('clear')">
            Limpiar Filtros
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
