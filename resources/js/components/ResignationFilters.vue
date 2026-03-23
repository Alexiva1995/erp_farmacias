<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  search: { type: String, default: "" },
  filters: {
    type: Object,
    default: () => ({
      resignation_type: null,
      date_from: null,
      date_to: null,
      status: null,
    }),
  },
});

const emit = defineEmits(["update:search", "update:filters", "clear"]);

const isAdvancedFilterVisible = ref(false);

const hasActiveAdvancedFilters = computed(() => {
  return (
    props.filters.resignation_type ||
    props.filters.date_from ||
    props.filters.date_to
  );
});

const resignationTypes = [
  { title: "REUNCIA VOLUNTARIA", value: "voluntary" },
  { title: "DESPIDO INJUSTIFICADO", value: "unjustified_dismissal" },
];
</script>

<template>
  <VCard class="mb-6 border-0 shadow-sm overflow-visible">
    <VCardText class="pa-3">
      <VRow align="center" no-gutters class="gap-2 px-2">
        <!-- Búsqueda Principal -->
        <VCol cols="12" sm="5" md="4" lg="4">
          <AppTextField
            :model-value="props.search"
            placeholder="BUSCAR EMPLEADO..."
            variant="outlined"
            density="compact"
            hide-details
            clearable
            prepend-inner-icon="tabler-search"
            class="premium-input shadow-sm"
            @update:model-value="emit('update:search', $event)"
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
              dot
              color="error"
              offset-x="3"
              offset-y="-3"
            />
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
            :disabled="!props.search && !hasActiveAdvancedFilters"
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
          <VRow dense class="px-2 pb-2">
            <!-- Tipo de Egreso -->
            <VCol cols="12" sm="4">
              <VSelect
                :model-value="props.filters.resignation_type"
                :items="resignationTypes"
                placeholder="TIPO DE EGRESO"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="premium-input shadow-sm"
                @update:model-value="emit('update:filters', { ...props.filters, resignation_type: $event })"
              />
            </VCol>

            <!-- Fecha Desde -->
            <VCol cols="12" sm="4">
              <AppTextField
                :model-value="props.filters.date_from"
                type="date"
                placeholder="FECHA DESDE"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="premium-input shadow-sm"
                @update:model-value="emit('update:filters', { ...props.filters, date_from: $event })"
              />
            </VCol>

            <!-- Fecha Hasta -->
            <VCol cols="12" sm="4">
              <AppTextField
                :model-value="props.filters.date_to"
                type="date"
                placeholder="FECHA HASTA"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="premium-input shadow-sm"
                @update:model-value="emit('update:filters', { ...props.filters, date_to: $event })"
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
