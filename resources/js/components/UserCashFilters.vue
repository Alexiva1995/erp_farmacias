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
  <VCard class="mb-6 rounded-xl border shadow-sm overlap-overflow">
    <VCardText class="pa-4">
      <div class="d-flex align-center flex-wrap gap-4">
        <!-- Búsqueda Principal -->
        <div class="flex-grow-1 min-w-[200px]">
          <VTextField
            v-model="filters.search"
            prepend-inner-icon="tabler-search"
            placeholder="Buscar por ID o Cliente..."
            variant="outlined"
            density="compact"
            hide-details
            class="bg-surface rounded-lg"
          />
        </div>

        <!-- Acciones Rápidas -->
        <div class="d-flex align-center gap-2">
          <VTooltip text="Filtros de Fecha">
            <template #activator="{ props: tooltip }">
              <VBtn
                v-bind="tooltip"
                variant="tonal"
                :color="isFiltersExpanded ? 'primary' : 'secondary'"
                size="40"
                class="rounded-lg"
                @click="isFiltersExpanded = !isFiltersExpanded"
              >
                <VBadge
                  v-if="activeFiltersCount > 0"
                  :content="activeFiltersCount"
                  color="error"
                  location="top end"
                  offset-x="-2"
                  offset-y="-2"
                >
                  <VIcon icon="tabler-calendar-stats" size="20" />
                </VBadge>
                <VIcon v-else icon="tabler-calendar-stats" size="20" />
              </VBtn>
            </template>
          </VTooltip>

          <VTooltip text="Refrescar Datos">
            <template #activator="{ props: tooltip }">
              <VBtn
                v-bind="tooltip"
                variant="tonal"
                color="info"
                size="40"
                class="rounded-lg"
                :loading="props.loading"
                @click="handleRefresh"
              >
                <VIcon icon="tabler-refresh" size="20" />
              </VBtn>
            </template>
          </VTooltip>

          <VBtn
            v-if="activeFiltersCount > 0 || filters.search"
            variant="text"
            color="error"
            density="compact"
            class="text-caption font-weight-bold ml-2"
            @click="clearFilters"
          >
            LIMPIAR
          </VBtn>
        </div>
      </div>

      <!-- Filtros Expandibles -->
      <VExpandTransition>
        <div v-show="isFiltersExpanded">
          <VDivider class="my-4 border-dashed" />
          <VRow>
            <VCol cols="12" sm="6">
              <div class="text-caption font-weight-bold mb-2 text-uppercase text-disabled">
                Fecha Inicio
              </div>
              <VTextField
                v-model="filters.date_start"
                type="date"
                variant="outlined"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar-event"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <div class="text-caption font-weight-bold mb-2 text-uppercase text-disabled">
                Fecha Fin
              </div>
              <VTextField
                v-model="filters.date_end"
                type="date"
                variant="outlined"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar-plus"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.overlap-overflow {
  overflow: visible !important;
}

.border-dashed {
  border-style: dashed !important;
  opacity: 0.15;
}

:deep(.v-field__outline) {
  --v-field-border-opacity: 0.12;
}

:deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1;
}
</style>
