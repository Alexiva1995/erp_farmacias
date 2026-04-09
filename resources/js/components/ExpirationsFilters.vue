<script setup>
// Filtros para fechas de vencimiento de inventario con acciones por lotes
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed, ref } from "vue";

const props = defineProps({
  searchQuery:        { type: String, required: true },
  selectedLaboratory: { type: [Number, String, null], required: true },
  startDate:          { type: [String, null], required: true },
  endDate:            { type: [String, null], required: true },
  laboratories:       { type: Array, default: () => [] },
  loading:            { type: Boolean, default: false },
  selectedLots:       { type: Array, default: () => [] },
  isStrictSearch:     { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:startDate",
  "update:endDate",
  "update:isStrictSearch",
  "clear",
  "expire-selected",
  "export",
]);

const searchQueryModel = computed({
  get: () => props.searchQuery,
  set: (value) => emit("update:searchQuery", value),
});

const laboratoryModel = computed({
  get: () => props.selectedLaboratory,
  set: (value) => emit("update:selectedLaboratory", value),
});

const startDateModel = computed({
  get: () => props.startDate,
  set: (value) => emit("update:startDate", value),
});

const endDateModel = computed({
  get: () => props.endDate,
  set: (value) => emit("update:endDate", value),
});

const isStrictSearchModel = computed({
  get: () => props.isStrictSearch,
  set: (value) => emit("update:isStrictSearch", value),
});

const hasSelectedLots = computed(() => props.selectedLots.length > 0);

const hasAdvancedFilters = computed(() =>
  !!(props.selectedLaboratory || props.startDate || props.endDate)
);

// Filtros rápidos
const quickFilters = [
  { label: "Vencidos", range: -1  },
  { label: "Este mes", range: 0   },
  { label: "60 Días",  range: 60  },
  { label: "90 Días",  range: 90  },
  { label: "120 Días", range: 120 },
  { label: "150 Días", range: 150 },
];

const isFilterActive = (filter) => {
  const today = new Date().toISOString().split("T")[0];
  const targetDate = new Date();

  if (filter.range === -1) {
    targetDate.setDate(targetDate.getDate() - 1);
    const yesterdayStr = targetDate.toISOString().split("T")[0];
    return props.startDate === null && props.endDate === yesterdayStr;
  }

  if (filter.range === 0) {
    targetDate.setMonth(targetDate.getMonth() + 1);
    targetDate.setDate(0); // Último día del mes
  } else {
    targetDate.setDate(targetDate.getDate() + filter.range);
  }
  const targetStr = targetDate.toISOString().split("T")[0];

  return props.startDate === today && props.endDate === targetStr;
};

const applyQuickFilter = (filter) => {
  const today = new Date().toISOString().split("T")[0];
  const targetDate = new Date();

  if (filter.range === -1) {
    targetDate.setDate(targetDate.getDate() - 1);
    const yesterdayStr = targetDate.toISOString().split("T")[0];
    emit("update:startDate", null);
    emit("update:endDate", yesterdayStr);
    return;
  }

  if (filter.range === 0) {
    targetDate.setMonth(targetDate.getMonth() + 1);
    targetDate.setDate(0);
  } else {
    targetDate.setDate(targetDate.getDate() + filter.range);
  }

  const targetStr = targetDate.toISOString().split("T")[0];
  emit("update:startDate", today);
  emit("update:endDate", targetStr);
};
</script>

<template>
  <AppFilterBase
    :search="searchQueryModel"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar por Producto, Lote..."
    class="py-1"
    @update:search="searchQueryModel = $event"
    @clear="emit('clear')"
    @export="emit('export', $event)"
  >
    <template #search-extra>
      <!-- Filtros Rápidos (Chips) -->
      <VCol cols="auto" class="d-none d-lg-flex align-center gap-1">
        <VChip
          v-for="filter in quickFilters"
          :key="filter.label"
          :color="isFilterActive(filter) ? 'primary' : 'secondary'"
          :variant="isFilterActive(filter) ? 'flat' : 'tonal'"
          size="small"
          class="cursor-pointer font-weight-bold"
          @click="applyQuickFilter(filter)"
        >
          {{ filter.label }}
        </VChip>
      </VCol>

      <!-- Búsqueda Estricta -->
      <VCol cols="auto" class="d-none d-sm-flex">
        <VCheckbox
          v-model="isStrictSearchModel"
          label="Estricta"
          color="primary"
          density="compact"
          hide-details
        />
      </VCol>
    </template>

    <template #advanced-filters>
      <!-- Laboratorio -->
      <VCol cols="12" sm="6" md="4">
        <VAutocomplete
          v-model="laboratoryModel"
          :items="props.laboratories"
          :loading="props.loading"
          placeholder="Laboratorio"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-flask"
        />
      </VCol>

      <!-- Vence Desde -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          v-model="startDateModel"
          placeholder="Vence Desde"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
        />
      </VCol>

      <!-- Vence Hasta -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          v-model="endDateModel"
          placeholder="Vence Hasta"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
        />
      </VCol>
    </template>

    <template #actions-extra>
      <!-- Botón Compacto de Acción por Lotes (Badge + Icono) -->
      <VFadeTransition>
        <div v-if="hasSelectedLots" class="d-flex align-center">
          <VBadge
            color="error"
            :content="props.selectedLots.length"
            offset-x="3"
            offset-y="-3"
            class="ms-2"
          >
            <VBtn
              icon
              color="error"
              variant="flat"
              size="38"
              class="rounded-circle shadow-sm"
              @click="emit('expire-selected')"
            >
              <VIcon icon="tabler-calendar-off" />
              <VTooltip activator="parent" location="top">
                Marcar {{ props.selectedLots.length }} items como caducados
              </VTooltip>
            </VBtn>
          </VBadge>
        </div>
      </VFadeTransition>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.bg-error-lighten-5 {
  background-color: rgba(var(--v-theme-error), 0.08) !important;
}
.cursor-pointer {
  cursor: pointer;
}
</style>
