<script setup>
const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  selectedOrigin: [Number, String, null],
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  selectedSortOption: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:stockStatusFilter",
  "update:selectedSortOption",
  "clear",
]);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
];

const sortOptions = [
  { title: 'Precio (Menor a Mayor)', value: { sortBy: 'sale_price', orderBy: 'asc' } },
  { title: 'Precio (Mayor a Menor)', value: { sortBy: 'sale_price', orderBy: 'desc' } },
  { title: 'Unidades (Menor a Mayor)', value: { sortBy: 'lots_sum_quantity', orderBy: 'asc' } },
  { title: 'Unidades (Mayor a Menor)', value: { sortBy: 'lots_sum_quantity', orderBy: 'desc' } },
  { title: 'Más Vendidos', value: { sortBy: 'sales_average', orderBy: 'desc' } },
  { title: 'Menos Vendidos', value: { sortBy: 'sales_average', orderBy: 'asc' } },
  { title: 'Expiración (Próxima)', value: { sortBy: 'next_expiration', orderBy: 'asc' } },
];
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="3">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por Producto, Cód. Barra, C. Activo..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.selectedLaboratory"
            label="Laboratorio"
            :items="props.laboratories"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedLaboratory', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.selectedOrigin"
            label="Origen"
            :items="props.origins"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedOrigin', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.stockStatusFilter"
            label="Estado de Stock"
            :items="stockOptions"
            clearable
            @update:model-value="emit('update:stockStatusFilter', $event)"
          />
        </VCol>
           <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.selectedSortOption"
            label="Ordenar por"
            :items="sortOptions"
            placeholder="Selecciona una opción de ordenamiento"
            clearable
            @update:model-value="emit('update:selectedSortOption', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
      <VSpacer />
    </VCardActions>
  </VCard>
</template>
