<script setup>
const props = defineProps({
  selectedSupplier: [Number, String, null],
  selectedLaboratory: [Number, String, null],
  suppliers: { type: Array, default: () => [] },
  laboratories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  enableDiscounts: { type: Boolean, default: false },
});

const emit = defineEmits(["update:selectedSupplier", "update:selectedLaboratory", "clear"]);
</script>

<template>
  <VCard title="Filtros" class="mb-6">
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
          <VAutocomplete
            :model-value="props.selectedLaboratory"
            :items="props.laboratories"
            :loading="props.loading"
            label="Laboratorios"
            placeholder="Escribe para buscar un laboratorio"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedLaboratory', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VSwitch
            :model-value="props.enableDiscounts"
            label="Activar Descuento"
            :inset="true"
            @update:model-value="emit('update:enableDiscounts', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')"> Limpiar Filtros </VBtn>
    </VCardActions>
  </VCard>
</template>
