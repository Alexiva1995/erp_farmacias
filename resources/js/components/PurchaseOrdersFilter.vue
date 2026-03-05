<script setup>
const props = defineProps({
  selectedSupplier: [Number, String, null],
  suppliers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:selectedSupplier", "clear"]);
</script>

<template>
  <VCard class="mb-6 shadow-sm border">
    <VCardText class="py-4">
      <VRow align="center" dense>
        <VCol cols="12" md="10">
          <AppAutocomplete
            :model-value="props.selectedSupplier"
            :items="props.suppliers"
            :loading="props.loading"
            placeholder="Filtrar por proveedor"
            item-title="name"
            item-value="id"
            clearable
            hide-details="auto"
            prepend-inner-icon="tabler-filter"
            @update:model-value="emit('update:selectedSupplier', $event)"
          />
        </VCol>
        <VCol cols="12" md="2" class="d-flex justify-end">
          <VBtn 
            color="secondary" 
            variant="tonal" 
            block
            @click="emit('clear')"
          >
            <VIcon icon="tabler-filter-off" class="me-1" size="18" />
            Limpiar
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
