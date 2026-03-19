<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  searchQuery: { type: String, default: "" },
  idSearchQuery: { type: String, default: "" },
  mode: { type: String, default: "all" },
  loading: { type: Boolean, default: false },
  showAddButton: { type: Boolean, default: true },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:idSearchQuery",
  "update:mode",
  "clear",
  "add-prescription",
]);

const isAdvancedFilterVisible = ref(false);

const searchQuery = computed({
  get: () => props.searchQuery,
  set: (value) => emit("update:searchQuery", value),
});

const idSearchQuery = computed({
  get: () => props.idSearchQuery,
  set: (value) => emit("update:idSearchQuery", value),
});

const mode = computed({
  get: () => props.mode,
  set: (value) => emit("update:mode", value),
});

const hasActiveAdvancedFilters = computed(() => {
  return (idSearchQuery.value && idSearchQuery.value !== "") || 
         (mode.value !== "all" && mode.value !== "");
});
</script>

<template>
  <VCard class="mb-6 rounded-xl border-0 shadow-sm overflow-visible">
    <VCardText class="pa-4">
      <VRow align="center" no-gutters>
        <!-- Búsqueda Principal -->
        <VCol cols="12" md="6" class="pe-md-4 mb-3 mb-md-0">
          <AppTextField
            v-model="searchQuery"
            placeholder="BUSCAR POR DESCUENTO O ESTADO..."
            variant="outlined"
            density="compact"
            hide-details
            prepend-inner-icon="tabler-search"
            class="premium-input-compact"
            clearable
            :disabled="props.loading"
          />
        </VCol>

        <!-- Botones de Acción -->
        <VCol cols="12" md="6" class="d-flex align-center gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            class="rounded-lg h-38"
            :class="{ 'bg-primary-lighten-5 text-primary': isAdvancedFilterVisible }"
            @click="isAdvancedFilterVisible = !isAdvancedFilterVisible"
            :disabled="props.loading"
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
            v-if="searchQuery || hasActiveAdvancedFilters"
            variant="tonal"
            color="secondary"
            icon="tabler-eraser"
            class="rounded-lg h-38"
            @click="emit('clear')"
            :disabled="props.loading"
          />

          <VSpacer />

          <VBtn
            v-if="props.showAddButton"
            color="primary"
            variant="flat"
            prepend-icon="tabler-plus"
            :loading="props.loading"
            class="rounded-lg h-38 shadow-primary font-weight-black"
            @click="emit('add-prescription')"
          >
            NUEVA OFERTA
          </VBtn>
        </VCol>
      </VRow>

      <!-- Filtros Avanzados Colapsables -->
      <VExpandTransition>
        <div v-show="isAdvancedFilterVisible">
          <VDivider class="my-4 border-dashed" />
          <VRow dense>
            <VCol cols="12" sm="6" md="4">
              <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">ID de Oferta</span>
              <AppTextField
                v-model="idSearchQuery"
                placeholder="EJ: 89"
                variant="outlined"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-hash"
                class="premium-input-compact"
                clearable
                :disabled="props.loading"
              />
            </VCol>
            
            <VCol cols="12" sm="6" md="4">
              <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Estado de Oferta</span>
              <VSelect
                v-model="mode"
                :items="[
                  { title: 'TODAS LAS OFERTAS', value: 'all' },
                  { title: 'SOLO ACTIVAS', value: 'active' },
                  { title: 'SOLO INACTIVAS', value: 'inactive' },
                ]"
                item-title="title"
                item-value="value"
                variant="outlined"
                density="compact"
                hide-details
                class="premium-input-compact"
                :disabled="props.loading"
              />
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
  border-radius: 8px !important;
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
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

.shadow-primary {
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.2) !important;
}

.border-dashed {
  border-style: dashed !important;
  opacity: 0.4;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}
</style>