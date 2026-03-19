<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  idSearchQuery: { type: String, default: "" },
  searchQuery: { type: String, default: "" },
  addOfferLoading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:idSearchQuery",
  "update:searchQuery",
  "clear",
  "add-product",
]);

const idSearchQuery = computed({
  get: () => props.idSearchQuery,
  set: (value) => emit("update:idSearchQuery", value),
});

const searchQuery = computed({
  get: () => props.searchQuery,
  set: (value) => emit("update:searchQuery", value),
});

const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return props.idSearchQuery;
});
</script>

<template>
  <VCard class="mb-4 elevation-1 border-0 rounded-lg overflow-hidden">
    <VCardText class="pa-3">
      <!-- Fila Principal: Búsqueda y Botones de Acción -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="6" lg="5">
          <AppTextField
            v-model="searchQuery"
            placeholder="BUSCAR OFERTA POR NOMBRE DE PRODUCTO..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
            class="premium-input-compact"
            :disabled="props.addOfferLoading"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            class="rounded-lg"
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

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Filtros (Solo Icono) -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Añadir Oferta -->
          <VBtn
            color="primary"
            variant="flat"
            height="38"
            class="rounded-lg font-weight-black px-4"
            prepend-icon="tabler-plus"
            :loading="props.addOfferLoading"
            @click="emit('add-product')"
          >
            NUEVA OFERTA
          </VBtn>
        </div>
      </VRow>

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          
          <VRow dense>
            <VCol cols="12" sm="6" md="4">
              <AppTextField
                v-model="idSearchQuery"
                placeholder="BUSCAR POR ID DE OFERTA"
                prepend-inner-icon="tabler-hash"
                clearable
                density="compact"
                hide-details
                class="premium-input-compact"
                :disabled="props.addOfferLoading"
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

/* Forzar altura y alineación en todos los campos */
.premium-input-compact :deep(.v-field) {
  border-radius: 8px !important;
  min-block-size: 38px !important;
  padding-inline-start: 12px !important;
}

.premium-input-compact :deep(.v-field__input) {
  display: flex !important;
  align-items: center !important;
  padding-block: 0 !important;
  font-size: 0.75rem !important;
  font-weight: 700;
  min-block-size: 38px !important;
  text-transform: uppercase;
}

.premium-input-compact :deep(.v-select__selection),
.premium-input-compact :deep(.v-select__selection-text) {
  font-size: 0.75rem !important;
  font-weight: 700;
  text-transform: uppercase;
}

.gap-1 { gap: 4px !important; }

.gap-2 { gap: 8px !important; }
</style>
