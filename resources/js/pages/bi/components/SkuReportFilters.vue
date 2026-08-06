<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  loading: Boolean,
  exporting: Boolean,
  laboratories: { type: Array, default: () => [] }
});

const emit = defineEmits(['update:filters', 'fetch', 'clear', 'export']);

const search = ref('');
const startDate = ref('2026-04-01');
const endDate = ref('');
const selectedLaboratory = ref(null);
const selectedGroup = ref(null);
const semaphoreFilter = ref(null);
const statusFilter = ref(1);

const isAdvancedFiltersVisible = ref(false);

const hasActiveAdvancedFilters = computed(() => {
  return startDate.value || endDate.value || selectedLaboratory.value || selectedGroup.value || statusFilter.value !== null;
});

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const getFilterValues = () => ({
  search: search.value,
  start_date: startDate.value,
  end_date: endDate.value,
  laboratory_id: selectedLaboratory.value,
  group_id: selectedGroup.value,
  semaphore: semaphoreFilter.value,
  is_active: statusFilter.value
});

const notifyUpdate = () => {
  emit('update:filters', getFilterValues());
};

const handleClear = () => {
  search.value = '';
  startDate.value = '';
  endDate.value = '';
  selectedLaboratory.value = null;
  selectedGroup.value = null;
  semaphoreFilter.value = null;
  statusFilter.value = null;
  isAdvancedFiltersVisible.value = false;
  notifyUpdate();
  emit('clear');
};

const handleFetch = () => {
  notifyUpdate();
  emit('fetch');
};

const handleExport = () => {
  notifyUpdate();
  emit('export');
};
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
    <VCardText class="pa-4">
      <!-- Barra de Búsqueda Principal -->
      <VRow align="center" no-gutters class="gap-2">
        <VCol cols="12" md="4" lg="4">
          <AppTextField
            v-model="search"
            placeholder="Buscar por SKU o Nombre..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            hide-details
            class="premium-input-compact"
            @update:model-value="notifyUpdate"
          />
        </VCol>

        <VCol cols="12" md="3" lg="3">
          <AppSelect
            v-model="semaphoreFilter"
            :items="[
              { title: '✅ Rentable (>25%)', value: 'verde' },
              { title: '⚠️ Medio (10-25%)', value: 'amarillo' },
              { title: '🚨 Peligro (<10%)', value: 'rojo' },
              { title: '🏴 Pérdidas (<0%)', value: 'negro' }
            ]"
            placeholder="Estado de Rentabilidad"
            density="compact"
            hide-details
            clearable
            class="premium-select-compact"
            prepend-inner-icon="tabler-traffic-lights"
            @update:model-value="notifyUpdate"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            class="rounded-circle shadow-sm"
            @click="toggleAdvancedFilters"
          >
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="2"
              offset-y="-2"
            >
              <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
            </VBadge>
            <VIcon v-else :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
          </VBtn>

          <VBtn
            icon
            variant="flat"
            color="primary"
            size="38"
            class="rounded-circle shadow-sm"
            :loading="loading"
            @click="handleFetch"
          >
            <VIcon icon="tabler-player-play" size="20" />
            <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2 border-opacity-10" />

          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            class="rounded-circle shadow-sm"
            :disabled="loading"
            @click="handleClear"
          >
            <VIcon icon="tabler-eraser" size="20" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>

          <VBtn
            icon
            variant="tonal"
            color="success"
            size="38"
            class="rounded-circle shadow-sm"
            :loading="exporting"
            :disabled="loading || exporting"
            @click="handleExport"
          >
            <VIcon icon="tabler-download" size="20" />
            <VTooltip activator="parent" location="top">Exportar (Excel/CSV)</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Panel Avanzado -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          <VRow>
            <VCol cols="12" sm="6" md="4">
              <AppDateTimePicker
                v-model="startDate"
                placeholder="Fecha Inicio"
                density="compact"
                hide-details
                class="premium-input-compact"
                prepend-inner-icon="tabler-calendar"
                @update:model-value="notifyUpdate"
              />
            </VCol>

            <VCol cols="12" sm="6" md="4">
              <AppDateTimePicker
                v-model="endDate"
                placeholder="Fecha Fin"
                density="compact"
                hide-details
                class="premium-input-compact"
                prepend-inner-icon="tabler-calendar-check"
                @update:model-value="notifyUpdate"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppAutocomplete
                v-model="selectedLaboratory"
                :items="laboratories"
                item-title="name"
                item-value="id"
                placeholder="Laboratorio / Proveedor"
                clearable
                variant="outlined"
                density="compact"
                hide-details
                class="premium-select-compact"
                prepend-inner-icon="tabler-flask"
                @update:model-value="notifyUpdate"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppSelect
                v-model="statusFilter"
                :items="[{ title: 'Activos', value: 1 }, { title: 'Inactivos', value: 0 }, { title: 'Todos', value: null }]"
                placeholder="Estado"
                density="compact"
                hide-details
                clearable
                class="premium-select-compact"
                prepend-inner-icon="tabler-power"
                @update:model-value="notifyUpdate"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>
