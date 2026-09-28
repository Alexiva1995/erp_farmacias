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
  <VCard class="mb-5 rounded-lg border shadow-sm overflow-hidden bg-surface">
    <VCardText class="pa-4">
      <!-- Barra de Filtros Principal -->
      <VRow align="center" dense>
        <VCol cols="12" md="5" lg="4">
          <AppTextField
            v-model="search"
            placeholder="Buscar por SKU, Nombre o Principio Activo..."
            prepend-inner-icon="tabler-search"
            clearable
            density="comfortable"
            variant="outlined"
            hide-details="auto"
            @update:model-value="notifyUpdate"
          />
        </VCol>

        <VCol cols="12" sm="6" md="4" lg="3">
          <AppSelect
            v-model="semaphoreFilter"
            :items="[
              { title: '✅ Rentable (>25%)', value: 'verde' },
              { title: '⚠️ Medio (10-25%)', value: 'amarillo' },
              { title: '🚨 Peligro (0-10%)', value: 'rojo' },
              { title: '🏴 Pérdidas (<0%)', value: 'negro' }
            ]"
            placeholder="Estado de Rentabilidad"
            density="comfortable"
            variant="outlined"
            hide-details="auto"
            clearable
            prepend-inner-icon="tabler-traffic-lights"
            @update:model-value="notifyUpdate"
          />
        </VCol>

        <VSpacer class="d-none d-lg-block" />

        <VCol cols="12" sm="6" md="3" lg="auto" class="d-flex align-center justify-end gap-1 flex-wrap">
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            rounded="circle"
            @click="toggleAdvancedFilters"
          >
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="2"
              offset-y="-2"
            >
              <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            </VBadge>
            <VIcon v-else :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
          </VBtn>

          <VBtn
            icon
            variant="tonal"
            color="primary"
            size="38"
            rounded="circle"
            :loading="loading"
            @click="handleFetch"
          >
            <VIcon icon="tabler-refresh" />
            <VTooltip activator="parent" location="top">Consultar</VTooltip>
          </VBtn>

          <VBtn
            icon
            variant="tonal"
            color="secondary"
            size="38"
            rounded="circle"
            :disabled="loading"
            @click="handleClear"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>

          <VBtn
            icon
            variant="tonal"
            color="success"
            size="38"
            rounded="circle"
            :loading="exporting"
            :disabled="loading || exporting"
            @click="handleExport"
          >
            <VIcon icon="tabler-file-export" />
            <VTooltip activator="parent" location="top">Exportar Excel</VTooltip>
          </VBtn>
        </VCol>
      </VRow>

      <!-- Panel Avanzado -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-4 opacity-20" />
          <VRow dense>
            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                v-model="startDate"
                placeholder="Fecha Inicio"
                density="comfortable"
                variant="outlined"
                hide-details="auto"
                prepend-inner-icon="tabler-calendar"
                @update:model-value="notifyUpdate"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                v-model="endDate"
                placeholder="Fecha Fin"
                density="comfortable"
                variant="outlined"
                hide-details="auto"
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
                placeholder="Laboratorio / Fabricante"
                clearable
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-flask"
                @update:model-value="notifyUpdate"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppSelect
                v-model="statusFilter"
                :items="[{ title: 'Solo Activos', value: 1 }, { title: 'Inactivos', value: 0 }, { title: 'Todos', value: null }]"
                placeholder="Estado Producto"
                density="comfortable"
                variant="outlined"
                hide-details="auto"
                clearable
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
