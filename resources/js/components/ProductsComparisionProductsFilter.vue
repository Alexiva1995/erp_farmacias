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
});

const emit = defineEmits([
  "update:selectedSupplier",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:enableDiscounts",
  "update:enableUsdAmountCol",
  "update:enableDiscountCol",
  "clear",
]);
</script>

<template>
  <VCardText class="pa-3">
    <VRow dense>
      <!-- Fila 1: Selectores Principales (3 columnas en pantallas medianas) -->
      <VCol cols="12" sm="4">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          :loading="props.loading"
          label="Laboratorio"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

      <VCol cols="12" sm="4">
        <VSelect
          :model-value="props.selectedOrigin"
          label="Origen"
          :items="props.origins"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="emit('update:selectedOrigin', $event)"
        />
      </VCol>

      <VCol cols="12" sm="4">
        <VAutocomplete
          :model-value="props.selectedSupplier"
          :items="props.suppliers"
          :loading="props.loading"
          label="Proveedor"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="emit('update:selectedSupplier', $event)"
        />
      </VCol>

      <!-- Fila 2: Switches y Botón de Limpiar (Flexbox para ahorrar espacio) -->
      <VCol cols="12" class="d-flex flex-wrap align-center gap-4 pt-4">
        <div class="d-flex align-center gap-2">
          <VSwitch
            :model-value="props.enableDiscounts"
            label="Aplicar Descuento"
            density="compact"
            hide-details
            color="primary"
            class="ms-2"
            @update:model-value="emit('update:enableDiscounts', $event)"
          />
        </div>

        <div class="d-flex align-center gap-2">
          <VSwitch
            :model-value="props.enableUsdAmountCol"
            label="Divisas ($)"
            density="compact"
            hide-details
            color="success"
            @update:model-value="emit('update:enableUsdAmountCol', $event)"
          />
        </div>

        <div class="d-flex align-center gap-2">
          <VSwitch
            :model-value="props.enableDiscountCol"
            label="Ver % Desc."
            density="compact"
            hide-details
            color="info"
            @update:model-value="emit('update:enableDiscountCol', $event)"
          />
        </div>

        <VSpacer />

        <VBtn
          color="secondary"
          variant="text"
          size="small"
          prepend-icon="tabler-filter-off"
          @click="emit('clear')"
        >
          Limpiar
        </VBtn>
      </VCol>
    </VRow>
  </VCardText>
</template>
