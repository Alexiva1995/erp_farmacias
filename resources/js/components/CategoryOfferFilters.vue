<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  idSearchQuery: { type: String, default: "" },
  searchQuery: { type: String, default: "" },
  isActive: { type: [String, Number, Boolean], default: "" },
  addOfferLoading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:idSearchQuery",
  "update:searchQuery",
  "update:isActive",
  "clear",
  "add-categories",
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

const isActive = computed({
  get: () => props.isActive,
  set: (value) => emit("update:isActive", value),
});

const hasActiveAdvancedFilters = computed(() => {
  return (idSearchQuery.value && idSearchQuery.value !== "") || 
         (isActive.value !== "" && isActive.value !== null);
});
</script>

<template>
  <VCard class="mb-6 rounded-lg border-0 shadow-sm overflow-visible">
    <VCardText class="pa-4">
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" sm="5" md="4" lg="4">
          <AppTextField
            v-model="searchQuery"
            placeholder="BUSCAR CATEGORÍA POR NOMBRE..."
            variant="outlined"
            density="compact"
            hide-details
            prepend-inner-icon="tabler-search"
            class="premium-input-compact"
            clearable
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            color="secondary"
            size="38"
            :class="{ 'bg-primary-lighten-5 text-primary': isAdvancedFilterVisible }"
            @click="isAdvancedFilterVisible = !isAdvancedFilterVisible"
          >
            <VBadge
              v-if="hasActiveAdvancedFilters"
              dot
              color="error"
              offset-x="-2"
              offset-y="-2"
            >
              <VIcon icon="tabler-filter" />
            </VBadge>
            <VIcon v-else icon="tabler-filter" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
          </VBtn>

          <!-- Añadir Oferta -->
          <VBtn
            icon
            color="primary"
            variant="flat"
            size="38"
            :loading="props.addOfferLoading"
            @click="emit('add-categories')"
          >
            <VIcon icon="tabler-plus" />
            <VTooltip activator="parent" location="top">Nueva Oferta</VTooltip>
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
          <VDivider class="my-4 border-opacity-10" />
          <VRow dense>
            <VCol cols="12" sm="6" md="4">
              <AppTextField
                v-model="idSearchQuery"
                placeholder="ID DE OFERTA..."
                variant="outlined"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-hash"
                class="premium-input-compact"
                clearable
              />
            </VCol>
            
            <VCol cols="12" sm="6" md="4">
              <VSelect
                v-model="isActive"
                :items="[
                  { value: '', title: 'Todos los estados' },
                  { value: 1, title: 'Activas' },
                  { value: 0, title: 'Inactivas' },
                ]"
                placeholder="ESTADO DE OFERTA"
                item-title="title"
                item-value="value"
                variant="outlined"
                density="compact"
                hide-details
                class="premium-input-compact"
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
  background-color: white !important;
}

.premium-input-compact :deep(.v-field__input) {
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  font-size: 0.75rem !important;
  font-weight: 700;
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
</style>
