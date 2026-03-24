<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  idSearchQuery: { type: String, default: "" },
  searchQuery: { type: String, default: "" },
  addOfferLoading: { type: Boolean, default: false },
  showAddButton: { type: Boolean, default: true },
});

const emit = defineEmits([
  "update:idSearchQuery",
  "update:searchQuery",
  "clear",
  "add-doctors",
]);

const isAdvancedFilterVisible = ref(false);

const idSearchQuery = computed({
  get: () => props.idSearchQuery,
  set: (value) => emit("update:idSearchQuery", value),
});

const searchQuery = computed({
  get: () => props.searchQuery,
  set: (value) => emit("update:searchQuery", value),
});

const hasActiveAdvancedFilters = computed(() => {
  return (idSearchQuery.value && idSearchQuery.value !== "");
});
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm overflow-visible">
    <VCardText class="pa-3">
      <VRow align="center" no-gutters class="gap-2 px-2">
        <!-- Búsqueda Principal -->
        <VCol cols="12" sm="5" md="4" lg="4">
          <AppTextField
            v-model="searchQuery"
            placeholder="BUSCAR MÉDICO POR NOMBRE..."
            variant="outlined"
            density="compact"
            hide-details
            prepend-inner-icon="tabler-search"
            class="premium-input shadow-sm"
            clearable
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFilterVisible ? 'primary' : 'secondary'"
            size="38"
            class="shadow-sm"
            @click="isAdvancedFilterVisible = !isAdvancedFilterVisible"
          >
            <VIcon :icon="isAdvancedFilterVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFilterVisible"
              color="error"
              dot
              offset-x="3"
              offset-y="-3"
            />
          </VBtn>

          <!-- Añadir Oferta -->
          <VBtn
            v-if="props.showAddButton"
            icon
            color="primary"
            variant="flat"
            size="38"
            class="shadow-primary-sm"
            :loading="props.addOfferLoading"
            @click="emit('add-doctors')"
          >
            <VIcon icon="tabler-plus" />
            <VTooltip activator="parent" location="top">Añadir Nueva Oferta</VTooltip>
          </VBtn>

          <VDivider
            vertical
            class="mx-1 my-2"
          />

          <!-- Limpiar Filtros -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            :disabled="!searchQuery && !hasActiveAdvancedFilters"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Filtros Avanzados Colapsables -->
      <VExpandTransition>
        <div v-show="isAdvancedFilterVisible">
          <VDivider class="my-3 border-opacity-10" />
          <VRow dense class="px-2">
            <VCol cols="12" sm="6" md="4">
              <AppTextField
                v-model="idSearchQuery"
                placeholder="ID DEL MÉDICO..."
                variant="outlined"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-hash"
                class="premium-input shadow-sm"
                clearable
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

.shadow-primary-sm {
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.2) !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

.premium-input :deep(.v-field__outline) {
  --v-field-border-opacity: 0.15 !important;
  color: rgba(var(--v-border-color), 1) !important;
}

.premium-input :deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1 !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.premium-input :deep(.v-field) {
  border-radius: 8px !important;
  background-color: white !important;
}

.premium-input :deep(.v-field__input),
.premium-input :deep(.v-select__selection),
.premium-input :deep(.v-select__selection-text) {
  font-size: 0.75rem !important;
  font-weight: 700;
  text-transform: uppercase;
}
</style>
