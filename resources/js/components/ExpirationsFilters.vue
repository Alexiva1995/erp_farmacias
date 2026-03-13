<script setup>
import { ref, computed } from "vue";

const props = defineProps({
  searchQuery: {
    type: String,
    required: true,
  },
  selectedLaboratory: {
    type: [Number, String, null],
    required: true,
  },
  startDate: {
    type: [String, null],
    required: true,
  },
  endDate: {
    type: [String, null],
    required: true,
  },
  laboratories: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  selectedLots: {
    type: Array,
    default: () => [],
  },
  isStrictSearch: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:startDate",
  "update:endDate",
  "update:isStrictSearch",
  "clear",
  "expire-selected",
]);

const isExpanded = ref(false);

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

const quickFilters = [
  { label: "Este mes", range: 0 },
  { label: "Próximos 90 días", range: 90 },
];

const isFilterActive = (filter) => {
  const today = new Date().toISOString().split('T')[0];
  const targetDate = new Date();
  if (filter.range === 0) {
    targetDate.setMonth(targetDate.getMonth() + 1);
    targetDate.setDate(0); // Último día del mes
  } else {
    targetDate.setDate(targetDate.getDate() + filter.range);
  }
  const targetStr = targetDate.toISOString().split('T')[0];

  return props.startDate === today && props.endDate === targetStr;
};

const applyQuickFilter = (filter) => {
  const today = new Date().toISOString().split('T')[0];
  const targetDate = new Date();
  
  if (filter.range === 0) {
    targetDate.setMonth(targetDate.getMonth() + 1);
    targetDate.setDate(0);
  } else {
    targetDate.setDate(targetDate.getDate() + filter.range);
  }

  const targetStr = targetDate.toISOString().split('T')[0];
  
  emit("update:startDate", today);
  emit("update:endDate", targetStr);
};
</script>

<template>
  <VCard class="mb-6 overflow-hidden">
    <!-- Cabecera de Filtros Rápido y Buscador -->
    <VCardText class="pa-4">
      <VRow align="center" no-gutters class="gap-4">
        <VCol cols="12" md="5" lg="6">
          <AppTextField
            v-model="searchQueryModel"
            placeholder="Buscar por Producto, Lote..."
            clearable
            prepend-inner-icon="tabler-search"
            density="compact"
          />
        </VCol>

        <VCol cols="12" md="auto" class="d-flex align-center gap-2 flex-wrap">
          <span class="text-caption text-uppercase font-weight-black text-disabled d-none d-sm-inline">Filtro Rápido:</span>
          <VChip
            v-for="filter in quickFilters"
            :key="filter.label"
            :color="isFilterActive(filter) ? 'primary' : 'default'"
            :variant="isFilterActive(filter) ? 'flat' : 'tonal'"
            size="small"
            class="cursor-pointer font-weight-bold"
            @click="applyQuickFilter(filter)"
          >
            {{ filter.label }}
          </VChip>
        </VCol>

        <VSpacer class="d-none d-md-block" />

        <VCol cols="12" md="auto" class="text-right">
          <VBtn
            :color="isExpanded ? 'primary' : 'secondary'"
            variant="tonal"
            size="small"
            :append-icon="isExpanded ? 'tabler-chevron-up' : 'tabler-chevron-down'"
            @click="isExpanded = !isExpanded"
          >
            {{ isExpanded ? 'MENOS FILTROS' : 'MÁS FILTROS' }}
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <!-- Filtros Avanzados Colapsables -->
    <VExpandTransition>
      <div v-show="isExpanded">
        <VDivider />
        <VCardText class="bg-var-theme-background">
          <VRow>
            <VCol cols="12" sm="6" md="4">
              <VAutocomplete
                v-model="laboratoryModel"
                :items="props.laboratories"
                :loading="props.loading"
                label="Laboratorio"
                placeholder="Seleccionar..."
                item-title="name"
                item-value="id"
                clearable
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6" md="4">
              <AppDateTimePicker
                v-model="startDateModel"
                placeholder="Vence Desde"
                label="Fecha Inicio"
                clearable
                density="compact"
                :config="{
                  altInput: true,
                  altFormat: 'Y-m-d',
                  dateFormat: 'Y-m-d',
                }"
              />
            </VCol>

            <VCol cols="12" sm="6" md="4">
              <AppDateTimePicker
                v-model="endDateModel"
                placeholder="Vence Hasta"
                label="Fecha Fin"
                clearable
                density="compact"
                :config="{
                  altInput: true,
                  altFormat: 'Y-m-d',
                  dateFormat: 'Y-m-d',
                }"
              />
            </VCol>
          </VRow>

          <div class="d-flex align-center gap-4 mt-4 flex-wrap">
            <VCheckbox
              v-model="isStrictSearchModel"
              color="primary"
              density="compact"
              hide-details
            >
              <template #label>
                <span class="text-sm font-weight-medium">Búsqueda Estricta</span>
              </template>
            </VCheckbox>

            <VBtn 
              color="error" 
              variant="text" 
              size="small" 
              prepend-icon="tabler-filter-off"
              @click="emit('clear')"
            >
              LIMPIAR TODO
            </VBtn>
          </div>
        </VCardText>
      </div>
    </VExpandTransition>

    <!-- Barra de Acciones de Selección (Visible solo si hay selección) -->
    <VExpandTransition>
      <div v-if="hasSelectedLots">
        <VDivider />
        <VCardActions class="pa-3 px-6 bg-error-lighten-5 d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-x-2">
            <VIcon icon="tabler-alert-circle" color="error" size="20" />
            <span class="text-body-2 font-weight-black text-error">
              {{ props.selectedLots.length }} lote(s) seleccionado(s)
            </span>
          </div>

          <VBtn
            color="error"
            variant="elevated"
            size="small"
            prepend-icon="tabler-calendar-off"
            @click="emit('expire-selected')"
          >
            MARCAR CADUCADOS
          </VBtn>
        </VCardActions>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style scoped>
.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.03);
}

.bg-error-lighten-5 {
  background-color: rgba(var(--v-theme-error), 0.08) !important;
}

.gap-2 { gap: 8px !important; }
.gap-4 { gap: 16px !important; }
</style>
