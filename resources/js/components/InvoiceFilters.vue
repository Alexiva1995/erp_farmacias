<script setup>
const props = defineProps({
  searchQuery: String,
  selectedSupplier: [Number, String, null],
  startDate: [String, null],
  endDate: [String, null],
  suppliers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedSupplier",
  "update:startDate",
  "update:endDate",
  "clear",
]);
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" md="3">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por N° Factura, Control..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
        <VCol cols="12" md="3">
          <VAutocomplete
            :model-value="props.selectedSupplier"
            :items="props.suppliers"
            :loading="props.loading"
            label="Proveedor"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedSupplier', $event)"
          />
        </VCol>
        <VCol cols="12" md="3">
          <AppDateTimePicker
            :model-value="props.startDate"
            placeholder="Fecha de Recibo Desde"
            clearable
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>
        <VCol cols="12" md="3">
          <AppDateTimePicker
            :model-value="props.endDate"
            placeholder="Fecha de Recibo Hasta"
            clearable
            @update:model-value="emit('update:endDate', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VDivider />
    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
