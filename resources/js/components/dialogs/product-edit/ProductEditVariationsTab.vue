<script setup>
const props = defineProps({
  formData: { type: Object, required: true },
  xs: { type: Boolean, default: false },
});

const emit = defineEmits([
  "add-variant",
  "remove-variant",
]);
</script>

<template>
  <div :class="[xs ? 'gap-4' : 'gap-6', 'd-flex flex-column']">
    <div class="d-flex flex-column gap-3">
      <div class="d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-2">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Variaciones de Tonos y Colores</span>
        </div>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          size="small"
          variant="flat"
          class="font-weight-black rounded-lg shadow-sm"
          @click="emit('add-variant')"
        >
          Añadir Tono
        </VBtn>
      </div>

      <VCard
        variant="flat"
        :class="[xs ? 'pa-3' : 'pa-5', 'bg-surface rounded-xl border shadow-sm']"
      >
        <div v-if="!formData.variants || formData.variants.length === 0" class="text-center py-6">
          <VIcon icon="tabler-palette" size="48" class="text-disabled mb-2" />
          <p class="text-caption font-weight-black text-disabled uppercase mb-0">No se han registrado variaciones de tono para este producto.</p>
        </div>

        <div v-else class="d-flex flex-column gap-4">
          <VRow
            v-for="(variant, index) in formData.variants"
            :key="index"
            dense
            align="center"
            class="border-b pb-4 mb-2"
          >
            <!-- Nombre del Tono -->
            <VCol cols="12" sm="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Nombre del Tono</span>
              <AppTextField
                v-model="variant.attribute_value"
                placeholder="Ej: Bomb Nude 01"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                class="rounded-lg font-weight-black"
              />
            </VCol>

            <!-- Selector Hexadecimal de Color -->
            <VCol cols="12" sm="3">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Color Hexadecimal</span>
              <div class="d-flex align-center gap-2">
                <input
                  type="color"
                  v-model="variant.color_hex"
                  style="width: 40px; height: 40px; border: 1px solid #ccc; border-radius: 8px; cursor: pointer; padding: 0; background: none;"
                />
                <AppTextField
                  v-model="variant.color_hex"
                  placeholder="#E20074"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  class="rounded-lg font-weight-black flex-grow-1"
                  maxlength="7"
                />
              </div>
            </VCol>

            <!-- Modificador de Precio -->
            <VCol cols="12" sm="3">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Ajuste de Precio ($)</span>
              <AppTextField
                v-model="variant.price_modifier"
                type="number"
                step="0.01"
                placeholder="0.00"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                class="rounded-lg font-weight-black"
                prefix="$"
              />
            </VCol>

            <!-- Eliminar Fila de Tono -->
            <VCol cols="12" sm="2" class="d-flex justify-end mt-sm-6">
              <VBtn
                icon="tabler-trash"
                color="error"
                variant="tonal"
                size="small"
                class="rounded-lg"
                @click="emit('remove-variant', index)"
              />
            </VCol>
          </VRow>
        </div>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.header-indicator {
  border-radius: 8px !important;
  block-size: 16px;
  inline-size: 4px;
}
.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}
.letter-spacing-1 {
  letter-spacing: 1px !important;
}
.uppercase {
  text-transform: uppercase;
}
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}
</style>
