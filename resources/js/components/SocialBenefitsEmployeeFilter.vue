<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  search: { type: String, default: "" },
});

const emit = defineEmits(["update:search", "clear"]);

const isAdvancedFiltersVisible = ref(false);

const search = computed({
  get: () => props.search,
  set: (value) => emit("update:search", value),
});

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  // Por ahora solo tenemos búsqueda, pero dejamos la lógica lista
  return false;
});
</script>

<template>
  <VCard class="mb-6 rounded-xl border-1 border-opacity-10 shadow-sm overflow-visible">
    <VCardText class="pa-4">
      <VRow align="center" no-gutters>
        <!-- Buscador Principal -->
        <VCol cols="12" md="6" class="pe-md-4 mb-3 mb-md-0">
          <AppTextField
            v-model="search"
            placeholder="BUSCAR POR NOMBRE, CÉDULA O CORREO..."
            variant="outlined"
            density="compact"
            hide-details
            prepend-inner-icon="tabler-search"
            class="premium-input-compact"
            clearable
          />
        </VCol>

        <!-- Botones de Acción -->
        <VCol cols="12" md="6" class="d-flex align-center gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            class="rounded-lg h-38"
            :class="{ 'bg-primary-lighten-5 text-primary': isAdvancedFiltersVisible }"
            @click="toggleAdvancedFilters"
          >
            <VBadge
              v-if="hasActiveAdvancedFilters"
              dot
              color="error"
              offset-x="-2"
              offset-y="-2"
            >
              <VIcon icon="tabler-filter" class="me-2" />
            </VBadge>
            <VIcon v-else icon="tabler-filter" class="me-2" />
            Filtros
          </VBtn>

          <VBtn
            v-if="search || hasActiveAdvancedFilters"
            variant="tonal"
            color="secondary"
            icon="tabler-eraser"
            class="rounded-lg h-38"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>

          <VSpacer />

          <!-- Aquí podrían ir acciones globales de RRHH si fueran necesarias -->
        </VCol>
      </VRow>

      <!-- Panel de Filtros Avanzados (Expandible) -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-4 border-dashed" />
          <VRow dense>
            <VCol cols="12">
              <div class="text-caption text-disabled italic pa-2">
                Más opciones de filtrado próximamente (por departamento, estado de liquidación, etc.)
              </div>
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.premium-input-compact :deep(.v-field__outline) {
  --v-field-border-opacity: 0.15 !important;
  color: rgba(var(--v-border-color), 1) !important;
}

.premium-input-compact :deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1 !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.premium-input-compact :deep(.v-field) {
  border-radius: 10px !important;
  min-height: 38px !important;
  background-color: white !important;
}

.premium-input-compact :deep(.v-field__input) {
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  font-size: 0.75rem !important;
  font-weight: 700;
  min-height: 38px !important;
  text-transform: uppercase;
}

.h-38 {
  height: 38px !important;
}

.shadow-sm {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
}

.border-dashed {
  border-style: dashed !important;
  opacity: 0.4;
}

.gap-2 {
  gap: 8px !important;
}
</style>
