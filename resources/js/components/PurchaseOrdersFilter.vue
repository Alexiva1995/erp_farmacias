<script setup>
const props = defineProps({
  selectedSupplier: [Number, String, null],
  suppliers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:selectedSupplier", "clear"]);
</script>

<template>
  <VCard class="mb-6 rounded-lg border">
    <VCardText class="pa-5">
      <VRow>
        <VCol cols="12" md="4" lg="3">
          <AppAutocomplete
            :model-value="props.selectedSupplier"
            :items="props.suppliers"
            :loading="props.loading"
            placeholder="Seleccionar Proveedor"
            item-title="name"
            item-value="id"
            clearable
            hide-details="auto"
            prepend-inner-icon="tabler-filter"
            @update:model-value="emit('update:selectedSupplier', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VDivider />
    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn 
        color="secondary" 
        variant="outlined" 
        @click="emit('clear')"
      >
        <VIcon icon="tabler-filter-off" class="me-1" size="18" />
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>

<style scoped>
.v-card {
  border: 1px solid rgba(var(--v-border-color), 0.1) !important;
}
</style>
