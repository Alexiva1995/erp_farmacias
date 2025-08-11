<script setup>
const props = defineProps({
  searchQuery: String,
  selectedSupplier: [Number, String, null],
  selectedStatus: [String, null],
  startDate: [String, null],
  endDate: [String, null],
  suppliers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedSupplier",
  "update:selectedStatus",
  "update:startDate",
  "update:endDate",
  "clear",
]);

const statusOptions = [
  { title: "Pendiente", value: "Pendiente" },
  { title: "Pagada", value: "Pagada" },
  { title: "Vencida", value: "Vencida" },
  { title: "Anulada", value: "Anulada" },
];
</script>

<template>
  <VCard title="Filtros de Facturas" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por N° Factura, Control..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
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
        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.selectedStatus"
            label="Estado de la Factura"
            :items="statusOptions"
            clearable
            @update:model-value="emit('update:selectedStatus', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <AppDateTimePicker
            :model-value="props.startDate"
            placeholder="Vencimiento Desde"
            clearable
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <AppDateTimePicker
            :model-value="props.endDate"
            placeholder="Vencimiento Hasta"
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
