<script setup>
// Componente maestro de filtros — Estándar de Oro basado en ProductFilters.vue
import { computed, ref } from "vue";

const props = defineProps({
  // Buscador principal
  search:            { type: String,  default: "" },
  searchPlaceholder: { type: String,  default: "Buscar..." },
  searchIcon:        { type: String,  default: "tabler-search" },

  // Visibilidad de botones de acción
  showAdd:    { type: Boolean, default: false },
  showExport: { type: Boolean, default: false },
  showSort:   { type: Boolean, default: false },

  // Botón añadir
  addButtonText: { type: String, default: "Añadir" },

  // Ordenamiento
  sortOptions: { type: Array, default: () => [] },

  // Estado
  hasAdvancedFilters: { type: Boolean, default: false },
  loading:            { type: Boolean, default: false },

  // Card flat (sin elevación)
  flat: { type: Boolean, default: false },

  // Controlar visibilidad de filtros avanzados
  showAdvanced: { type: Boolean, default: true },

  // Control de grid (Ancho de columna de búsqueda)
  searchCols:   { type: [Number, String], default: 12 },
  searchMdCols: { type: [Number, String], default: 4 },
  searchLgCols: { type: [Number, String], default: 4 },
});

const emit = defineEmits([
  "update:search",
  "clear",
  "export",
  "add",
  "sort",
]);

// ── Panel avanzado colapsable ──────────────────────────────────────────────
const isPanelVisible = ref(false);

const togglePanel = () => {
  isPanelVisible.value = !isPanelVisible.value;
};

// ── Ordenamiento ──────────────────────────────────────────────────────────
const selectedSort = ref(null);

const handleSortClick = (option) => {
  selectedSort.value = { key: option.key, order: option.order };
  emit("sort", selectedSort.value);
};

const clearSort = () => {
  selectedSort.value = null;
  emit("sort", { key: undefined, order: undefined });
};

const selectedSortIcon = computed(() => {
  if (!selectedSort.value) return "tabler-sort-ascending";
  const opt = props.sortOptions.find(
    (o) => o.key === selectedSort.value.key && o.order === selectedSort.value.order
  );
  return opt?.icon ?? "tabler-sort-ascending";
});

const isOptionActive = (option) =>
  selectedSort.value?.key === option.key &&
  selectedSort.value?.order === option.order;

// ── Indicador de filtros avanzados activos ─────────────────────────────────
const hasBadge = computed(
  () => (props.hasAdvancedFilters || !!selectedSort.value) && !isPanelVisible.value
);

// ── Limpiar todo ───────────────────────────────────────────────────────────
const handleClear = () => {
  clearSort();
  emit("clear");
};
</script>

<template>
  <VCard :class="{ 'rounded-lg border shadow-sm overflow-hidden mb-4': !flat, 'elevation-0 border-0': flat }">
    <VCardText class="pa-3">

      <!-- ── Fila Principal: Buscador + Acciones ──────────────────────── -->
      <VRow align="center" no-gutters class="gap-2">

        <!-- Slot de búsqueda (Configurable vía props) — por defecto AppTextField estándar -->
        <VCol :cols="searchCols" :md="searchMdCols" :lg="searchLgCols">
          <slot name="search">
            <AppTextField
              :model-value="search"
              :placeholder="searchPlaceholder"
              :prepend-inner-icon="searchIcon"
              clearable
              density="compact"
              persistent-placeholder
              hide-details
              @update:model-value="emit('update:search', $event)"
            />
          </slot>
        </VCol>

        <!-- Slot extra izquierda (ej: checkbox "Búsqueda Estricta") -->
        <VCol v-if="$slots['search-extra']" cols="auto" class="d-none d-sm-flex">
          <slot name="search-extra" />
        </VCol>

        <VSpacer />

        <!-- ── Grupo de botones de acción ─────────────────────────────── -->
        <div class="d-flex align-center gap-1">

          <!-- Toggle panel avanzado -->
          <VBtn
            v-if="showAdvanced"
            icon
            variant="tonal"
            :color="isPanelVisible ? 'primary' : 'secondary'"
            size="38"
            class="rounded-pill"
            @click="togglePanel"
          >
            <VIcon :icon="isPanelVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            <VBadge v-if="hasBadge" color="error" dot offset-x="3" offset-y="-3" />
          </VBtn>

          <!-- Ordenar Por -->
          <VMenu v-if="showSort && sortOptions.length > 0">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                variant="tonal"
                color="info"
                size="38"
                class="rounded-pill"
              >
                <VIcon :icon="selectedSortIcon" />
                <VTooltip activator="parent" location="top">Ordenar Por</VTooltip>
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem
                v-for="(opt, i) in sortOptions"
                :key="i"
                :active="isOptionActive(opt)"
                color="primary"
                @click="handleSortClick(opt)"
              >
                <template #prepend>
                  <VIcon :icon="opt.icon" size="20" />
                </template>
                <VListItemTitle>{{ opt.title }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <!-- Exportar -->
          <VMenu v-if="showExport">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                color="success"
                variant="tonal"
                size="38"
                class="rounded-pill"
              >
                <VIcon icon="tabler-file-export" />
                <VTooltip activator="parent" location="top">Exportar Reporte</VTooltip>
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem @click="emit('export', 'xlsx')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-csv" size="18" color="success" />
                </template>
                <VListItemTitle>Excel</VListItemTitle>
              </VListItem>
              <VListItem @click="emit('export', 'pdf')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" size="18" color="error" />
                </template>
                <VListItemTitle>PDF</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <!-- Slot de acciones adicionales (ej: botón específico del módulo) -->
          <slot name="actions-extra" />

          <!-- Añadir -->
          <VBtn
            v-if="showAdd"
            color="primary"
            variant="flat"
            size="38"
            class="rounded-pill"
            @click="emit('add')"
          >
            <VIcon icon="tabler-plus" />
            <VTooltip activator="parent" location="top">{{ addButtonText }}</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar todo -->
          <VBtn
            variant="text"
            color="secondary"
            size="38"
            class="rounded-pill"
            @click="handleClear"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- ── Panel de Filtros Avanzados Colapsable ──────────────────────── -->
      <VExpandTransition>
        <div v-show="isPanelVisible">
          <VDivider class="my-3 border-opacity-10" />
          <!-- Slot principal: cada módulo coloca sus VCols con sus inputs -->
          <VRow dense>
            <slot name="advanced-filters" />
          </VRow>
        </div>
      </VExpandTransition>

    </VCardText>
  </VCard>
</template>

<style scoped>
/* Tamaño de fuente estándar igual a ProductFilters.vue */
:deep(.v-field__input) {
  font-size: 0.9rem;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

@media (max-width: 600px) {
  :deep(.v-field__input) {
    font-size: 0.8rem;
  }
}
</style>
