<script setup>
import { ref, computed, watch } from 'vue';

const getFirstDayOfCurrentMonth = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
};

const props = defineProps({
  loading: Boolean,
  exporting: Boolean,
  laboratories: { type: Array, default: () => [] },
  modelValue: {
    type: Object,
    default: () => ({
      search: "",
      start_date: "",
      end_date: "",
      laboratory_id: null,
      group_id: null,
      semaphore: null,
      is_active: 1
    })
  }
});

const emit = defineEmits(['update:modelValue', 'update:filters', 'fetch', 'clear', 'export']);

const search = ref(props.modelValue.search || '');
const startDate = ref(props.modelValue.start_date || getFirstDayOfCurrentMonth());
const endDate = ref(props.modelValue.end_date || '');
const selectedLaboratory = ref(props.modelValue.laboratory_id || null);
const selectedGroup = ref(props.modelValue.group_id || null);
const semaphoreFilter = ref(props.modelValue.semaphore || null);
const statusFilter = ref(props.modelValue.is_active !== undefined ? props.modelValue.is_active : 1);

const isAdvancedFiltersVisible = ref(false);

watch(() => props.modelValue, (newVal) => {
  if (newVal) {
    if (newVal.search !== undefined) search.value = newVal.search;
    if (newVal.start_date !== undefined) startDate.value = newVal.start_date;
    if (newVal.end_date !== undefined) endDate.value = newVal.end_date;
    if (newVal.laboratory_id !== undefined) selectedLaboratory.value = newVal.laboratory_id;
    if (newVal.group_id !== undefined) selectedGroup.value = newVal.group_id;
    if (newVal.semaphore !== undefined) semaphoreFilter.value = newVal.semaphore;
    if (newVal.is_active !== undefined) statusFilter.value = newVal.is_active;
  }
}, { deep: true });

const hasActiveAdvancedFilters = computed(() => {
  return (startDate.value && startDate.value !== getFirstDayOfCurrentMonth()) || endDate.value || selectedLaboratory.value || selectedGroup.value || statusFilter.value !== 1;
});

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const getFilterValues = () => ({
  ...props.modelValue,
  search: search.value,
  start_date: startDate.value,
  end_date: endDate.value,
  laboratory_id: selectedLaboratory.value,
  group_id: selectedGroup.value,
  semaphore: semaphoreFilter.value,
  is_active: statusFilter.value
});

const notifyUpdate = () => {
  const vals = getFilterValues();
  emit('update:modelValue', vals);
  emit('update:filters', vals);
};

const handleClear = () => {
  search.value = '';
  startDate.value = getFirstDayOfCurrentMonth();
  endDate.value = '';
  selectedLaboratory.value = null;
  selectedGroup.value = null;
  semaphoreFilter.value = null;
  statusFilter.value = 1;
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
