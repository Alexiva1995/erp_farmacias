<script setup>
const props = defineProps({
  searchQuery: { type: String, default: "" },
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
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedSupplier",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:enableDiscounts",
  "update:enableUsdAmountCol",
  "update:enableDiscountCol",
  "clear",
  "open-delete-dialog",
  "update-all-api", // <--- NUEVO EVENTO
]);
</script>

<template>
  <VCardText>
    <VRow>
      <!-- Buscador -->
      <VCol cols="12" sm="3">
        <VTextField
          :model-value="props.searchQuery"
          label="Buscar"
          placeholder="Nombre, ID, C. Activo..."
          clearable
          @update:model-value="emit('update:searchQuery', $event)"
        />
      </VCol>

      <!-- Laboratorio -->
      <VCol cols="12" sm="3">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          :loading="props.loading"
          label="Laboratorio"
          placeholder="Buscar laboratorio"
          item-title="name"
          item-value="id"
          clearable
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

      <!-- Origen -->
      <VCol cols="12" sm="3">
        <VSelect
          :model-value="props.selectedOrigin"
          :items="props.origins"
          label="Origen"
          placeholder="Seleccionar origen"
          item-title="name"
          item-value="id"
          clearable
          @update:model-value="emit('update:selectedOrigin', $event)"
        />
      </VCol>

      <!-- Proveedor -->
      <VCol cols="12" sm="3">
        <VAutocomplete
          :model-value="props.selectedSupplier"
          :items="props.suppliers"
          :loading="props.loading"
          label="Proveedor"
          placeholder="Buscar proveedor"
          item-title="name"
          item-value="id"
          clearable
          @update:model-value="emit('update:selectedSupplier', $event)"
        />
      </VCol>
    </VRow>
  </VCardText>

  <VDivider />

  <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
    <VBtn color="secondary" variant="outlined" @click="emit('clear')">
      Limpiar Filtros
    </VBtn>

    <!-- NUEVO BOTÓN: ACTUALIZAR VÍA API -->
    <VBtn
      color="info"
      variant="tonal"
      prepend-icon="tabler-cloud-download"
      @click="emit('update-all-api')"
    >
      Actualizar Vía API
    </VBtn>

    <!-- BOTÓN: ELIMINAR PRODUCTOS -->
    <VBtn
      color="error"
      variant="tonal"
      prepend-icon="tabler-trash"
      @click="emit('open-delete-dialog')"
    >
      Eliminar Productos
    </VBtn>

    <VSpacer />

    <!-- Switches -->
    <div class="d-flex flex-wrap align-center gap-4">
      <VSwitch
        :model-value="props.enableDiscounts"
        label="Aplicar Descuento"
        density="compact"
        color="primary"
        hide-details
        class="me-2"
        @update:model-value="emit('update:enableDiscounts', $event)"
      />

      <VSwitch
        :model-value="props.enableUsdAmountCol"
        label="Divisas ($)"
        density="compact"
        color="success"
        hide-details
        class="me-2"
        @update:model-value="emit('update:enableUsdAmountCol', $event)"
      />

      <VSwitch
        :model-value="props.enableDiscountCol"
        label="Ver % Desc."
        density="compact"
        color="info"
        hide-details
        @update:model-value="emit('update:enableDiscountCol', $event)"
      />
    </div>
  </VCardActions>
</template>
