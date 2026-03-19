<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  searchQuery: String,
  idSearchQuery: String,
  loading: { type: Boolean, default: false },
  mode: { type: String, default: "packs" },
  showAddButton: { type: Boolean, default: true },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:idSearchQuery",
  "clear",
  "add-pack",
]);

const searchQuery = computed({
  get: () => props.searchQuery,
  set: (value) => emit('update:searchQuery', value)
});

const idSearchQuery = computed({
  get: () => props.idSearchQuery,
  set: (value) => emit('update:idSearchQuery', value)
});

const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return !!props.idSearchQuery;
});
</script>

<template>
  <VCard class="elevation-1 rounded-lg border-0 mb-6 overflow-hidden">
    <VCardText class="pa-3 bg-white">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-2 px-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="6" lg="5">
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar por Nombre del Pack..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            hide-details
            :disabled="props.loading"
            class="premium-input shadow-sm"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-2">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            class="rounded-lg shadow-sm"
            @click="toggleAdvancedFilters"
          >
            <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="3"
              offset-y="-3"
            />
          </VBtn>

          <!-- Añadir Pack -->
          <VBtn
            v-if="props.showAddButton"
            icon
            color="primary"
            variant="flat"
            size="38"
            class="rounded-lg shadow-primary-sm"
            @click="emit('add-pack')"
            :disabled="props.loading"
          >
            <VIcon icon="tabler-plus" />
            <VTooltip activator="parent" location="top">Añadir Nuevo Pack</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Filtros -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            class="rounded-lg"
            @click="emit('clear')"
            :disabled="props.loading"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10 border-dashed" />
          
          <VRow dense class="px-2">
            <VCol cols="12" sm="6" md="3">
              <div class="d-flex flex-column gap-1">
                <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 ms-1">ID del Pack</span>
                <AppTextField
                  v-model="idSearchQuery"
                  placeholder="Ej: #123"
                  clearable
                  density="compact"
                  variant="outlined"
                  :disabled="props.loading"
                  class="premium-input shadow-sm"
                  prepend-inner-icon="tabler-hash"
                  hide-details
                />
              </div>
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.68rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1.5px !important;
}

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 5%) !important;
}

.shadow-primary-sm {
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 20%) !important;
}

.border-dashed {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.gap-2 { gap: 8px !important; }

.premium-input :deep(.v-field__outline__start),
.premium-input :deep(.v-field__outline__end),
.premium-input :deep(.v-field__outline__notch) {
  border-color: rgba(var(--v-border-color), 0.5) !important;
}

.premium-input :deep(.v-field--focused .v-field__outline__start),
.premium-input :deep(.v-field--focused .v-field__outline__end),
.premium-input :deep(.v-field--focused .v-field__outline__notch) {
  border-width: 2px !important;
  border-color: rgb(var(--v-theme-primary)) !important;
}
</style>
