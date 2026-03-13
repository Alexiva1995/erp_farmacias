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
  { label: "60 Días", range: 60 },
  { label: "90 Días", range: 90 },
  { label: "120 Días", range: 120 },
  { label: "150 Días", range: 150 },
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
</script><template>
  <VCard class="mb-6 overflow-visible" variant="flat" border>
    <VCardText class="pa-3">
      <!-- Fila Principal: Búsqueda y Filtros Rápidos -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="4" lg="4">
          <AppTextField
            v-model="searchQueryModel"
            placeholder="Buscar por Producto, Lote..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
          />
        </VCol>

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

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isExpanded ? 'primary' : 'secondary'"
            size="38"
            @click="isExpanded = !isExpanded"
          >
            <VIcon :icon="isExpanded ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2" />

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

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isExpanded">
          <VDivider class="my-3 border-opacity-10" />
          
          <VRow dense>
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
          </VRow>
        </div>
      </VExpandTransition>

      <!-- Barra de Acciones de Selección -->
      <VExpandTransition>
        <div v-if="hasSelectedLots">
          <VDivider class="my-3 border-opacity-10" />
          <div class="d-flex align-center justify-space-between bg-error-lighten-5 pa-2 rounded">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-alert-circle" color="error" size="20" />
              <span class="text-caption font-weight-black text-error">
                {{ props.selectedLots.length }} SELECCIONADOS
              </span>
            </div>

            <VBtn
              color="error"
              variant="flat"
              size="small"
              prepend-icon="tabler-calendar-off"
              @click="emit('expire-selected')"
            >
              MARCAR CADUCADOS
            </VBtn>
          </div>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.bg-error-lighten-5 {
  background-color: rgba(var(--v-theme-error), 0.08) !important;
}

.gap-2 { gap: 8px !important; }
.gap-4 { gap: 16px !important; }
</style>
