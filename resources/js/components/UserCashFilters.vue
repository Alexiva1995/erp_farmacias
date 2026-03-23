<script setup>
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:filters", "refresh"]);

const { mobile } = useDisplay();
const isFiltersExpanded = ref(false);

const filters = ref({
  search: "",
  date_start: null,
  date_end: null,
});

const activeFiltersCount = computed(() => {
  let count = 0;
  if (filters.value.date_start) count++;
  if (filters.value.date_end) count++;
  return count;
});

const clearFilters = () => {
  filters.value.date_start = null;
  filters.value.date_end = null;
  filters.value.search = "";
};

watch(
  filters,
  (val) => {
    emit("update:filters", val);
  },
  { deep: true }
);

const handleRefresh = () => {
  emit("refresh");
};
</script>

<template>
  <VCard class="mb-6 rounded-lg border-0 shadow-sm overflow-visible">
    <VCardText class="pa-3">
      <VRow align="center" no-gutters class="gap-2 px-2">
        <!-- Buscador Principal -->
        <VCol cols="12" sm="5" md="4" lg="4">
          <AppTextField
            v-model="filters.search"
            placeholder="BUSCAR POR ID O CLIENTE..."
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
            :color="isFiltersExpanded ? 'primary' : 'secondary'"
            size="38"
            class="shadow-sm"
            @click="isFiltersExpanded = !isFiltersExpanded"
          >
            <VIcon :icon="isFiltersExpanded ? 'tabler-calendar-off' : 'tabler-calendar-stats'" />
            <VTooltip activator="parent" location="top">Filtros de Fecha</VTooltip>
            <VBadge
              v-if="activeFiltersCount > 0 && !isFiltersExpanded"
              dot
              color="error"
              offset-x="3"
              offset-y="-3"
            />
          </VBtn>

          <!-- Refresh -->
          <VBtn
            icon
            variant="tonal"
            color="info"
            size="38"
            class="shadow-sm"
            :loading="props.loading"
            @click="handleRefresh"
          >
            <VIcon icon="tabler-refresh" />
            <VTooltip activator="parent" location="top">Refrescar Datos</VTooltip>
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
            :disabled="!filters.search && !activeFiltersCount"
            @click="clearFilters"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Panel de Filtros Avanzados (Expandible) -->
      <VExpandTransition>
        <div v-show="isFiltersExpanded">
          <VDivider class="my-3 border-opacity-10" />
          <VRow dense class="px-2 pb-2">
            <VCol cols="12" sm="6">
              <AppTextField
                v-model="filters.date_start"
                type="date"
                placeholder="FECHA INICIO"
                variant="outlined"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar-event"
                class="premium-input shadow-sm"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppTextField
                v-model="filters.date_end"
                type="date"
                placeholder="FECHA FIN"
                variant="outlined"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar-plus"
                class="premium-input shadow-sm"
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
