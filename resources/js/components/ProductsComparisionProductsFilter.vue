<script setup>
const props = defineProps({
  selectedSupplier: [Number, String, null],
  selectedLaboratory: [Number, String, null],
  selectedOrigin: [Number, String, null],
  suppliers: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  laboratories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  enableDiscounts: { type: Boolean, default: false },
  enableUsdAmountCol: { type: Boolean, default: false },
  enableDiscountCol: { type: Boolean, default: false },
  searchQuery: { type: String, default: "" },
  stockStatusFilter: { type: [Boolean, null], default: null },
  isStrictSearch: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:selectedSupplier",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:searchQuery",
  "update:stockStatusFilter",
  "update:isStrictSearch",
  "clear",
]);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
  { title: "Todos", value: null },
];
</script>

<template>
  <VCardText>
    <VRow>
      <VCol cols="12" sm="6" md="3">
        <AppTextField
          :model-value="props.searchQuery"
          placeholder="Buscar por Producto, Cód. Barra, C. Activo..."
          clearable
          @update:model-value="emit('update:searchQuery', $event)"
        />
        <VCheckbox
          label="Búsqueda Estricta"
          :model-value="props.isStrictSearch"
          @update:model-value="emit('update:isStrictSearch', $event)"
        />
      </VCol>
      <VCol cols="12" sm="3">
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
      <VCol cols="12" sm="4" md="2">
        <VSwitch
          :model-value="props.enableDiscounts"
          label="Activar Descuento"
          :inset="true"
          @update:model-value="emit('update:enableDiscounts', $event)"
        />
      </VCol>
      <VCol cols="12" sm="4" md="2">
        <VSwitch
          :model-value="props.enableUsdAmountCol"
          label="Monto en Divisas"
          :inset="true"
          @update:model-value="emit('update:enableUsdAmountCol', $event)"
        />
      </VCol>
      <VCol cols="12" sm="4" md="3">
        <VSwitch
          :model-value="props.enableDiscountCol"
          label="Monto en descuento"
          :inset="true"
          @update:model-value="emit('update:enableDiscountCol', $event)"
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
