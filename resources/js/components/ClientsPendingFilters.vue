<script setup>
const props = defineProps({
  status: [String, Number, null],
});

const emit = defineEmits(["update:status", "clear"]);

const options = [
  { value: 0, title: "PENDIENTE" },
  { value: 1, title: "PARCIAL" },
  { value: 2, title: "COMPLETO" },
];
</script>

<template>
  <VCard class="mb-4">
    <VCardText class="pa-3">
      <div class="d-flex align-center gap-3">
        <!-- Filtro de Estado -->
        <div class="flex-grow-1" style="max-inline-size: 300px;">
          <VSelect
            :model-value="props.status"
            label="FILTRAR POR ESTADO"
            :items="options"
            clearable
            density="compact"
            variant="outlined"
            hide-details
            prepend-inner-icon="tabler-filter-cog"
            class="premium-select"
            @update:model-value="emit('update:status', $event)"
          />
        </div>

        <VSpacer />

        <!-- Botón Limpiar -->
        <VBtn
          variant="tonal"
          color="secondary"
          size="38"
          icon
          @click="emit('clear')"
        >
          <VIcon icon="tabler-eraser" />
          <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.premium-select :deep(.v-field__input) {
  font-size: 0.75rem !important;
  font-weight: 800;
  text-transform: uppercase;
}

.premium-select :deep(.v-label) {
  font-size: 0.7rem !important;
  font-weight: 900;
  letter-spacing: 0.5px;
}
</style>
