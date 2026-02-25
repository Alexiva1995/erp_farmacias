<script setup>
import { computed } from "vue";

const props = defineProps({
  startDate: { type: String, default: "" },
  endDate: { type: String, default: "" },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:startDate",
  "update:endDate",
  "apply-filter",
  "clear-filter",
]);

// Computed para los v-model
const startDateModel = computed({
  get: () => props.startDate,
  set: (value) => emit("update:startDate", value),
});

const endDateModel = computed({
  get: () => props.endDate,
  set: (value) => emit("update:endDate", value),
});

// Validaciones
const isValidDateRange = computed(() => {
  if (!props.startDate || !props.endDate) return true;
  return new Date(props.startDate) <= new Date(props.endDate);
});

const hasFilters = computed(() => {
  return props.startDate || props.endDate;
});

// Métodos
const handleApplyFilter = () => {
  if (!isValidDateRange.value) {
    return;
  }
  emit("apply-filter");
};

const handleClearFilter = () => {
  emit("clear-filter");
};

// Presets de fechas comunes
const setMonthPreset = () => {
  const now = new Date();
  const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  emit("update:startDate", startOfMonth.toISOString().split("T")[0]);
  emit("update:endDate", endOfMonth.toISOString().split("T")[0]);
};

const setQuarterPreset = () => {
  const now = new Date();
  const quarter = Math.floor(now.getMonth() / 3);
  const startOfQuarter = new Date(now.getFullYear(), quarter * 3, 1);
  const endOfQuarter = new Date(now.getFullYear(), (quarter + 1) * 3, 0);

  emit("update:startDate", startOfQuarter.toISOString().split("T")[0]);
  emit("update:endDate", endOfQuarter.toISOString().split("T")[0]);
};

const setYearPreset = () => {
  const now = new Date();
  const startOfYear = new Date(now.getFullYear(), 0, 1);
  const endOfYear = new Date(now.getFullYear(), 11, 31);

  emit("update:startDate", startOfYear.toISOString().split("T")[0]);
  emit("update:endDate", endOfYear.toISOString().split("T")[0]);
};
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <!-- Campos de fecha -->
        <VCol cols="12" sm="6" md="6">
          <AppDateTimePicker
            :model-value="startDateModel"
            placeholder="Fecha Inicial"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            :error="!isValidDateRange"
            :error-messages="
              !isValidDateRange
                ? ['La fecha inicial debe ser menor a la fecha final']
                : []
            "
            @update:model-value="startDateModel = $event"
          />
        </VCol>

        <VCol cols="12" sm="6" md="6">
          <AppDateTimePicker
            :model-value="endDateModel"
            placeholder="Fecha Final"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            :error="!isValidDateRange"
            :error-messages="
              !isValidDateRange
                ? ['La fecha final debe ser mayor a la fecha inicial']
                : []
            "
            @update:model-value="endDateModel = $event"
          />
        </VCol>

        <!-- Espacio vacío para mantener alineación -->
        <VCol cols="12" sm="6" md="4">
          <!-- Columna vacía para alineación -->
        </VCol>
      </VRow>

      <!-- Información del período seleccionado -->
      <VRow v-if="startDate && endDate && isValidDateRange" class="mt-2">
        <VCol cols="12">
          <VAlert type="info" variant="tonal" density="compact" class="mb-0">
            <template #prepend>
              <VIcon icon="tabler-info-circle" />
            </template>

            <span class="text-body-2">
              <strong>Período seleccionado:</strong>
              {{
                new Date(startDate).toLocaleDateString("es-CO", {
                  year: "numeric",
                  month: "long",
                  day: "numeric",
                })
              }}
              -
              {{
                new Date(endDate).toLocaleDateString("es-CO", {
                  year: "numeric",
                  month: "long",
                  day: "numeric",
                })
              }}
            </span>
          </VAlert>
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn
        color="secondary"
        variant="outlined"
        @click="handleClearFilter"
        :disabled="!hasFilters || loading"
      >
        Limpiar Filtros
      </VBtn>

      <!-- Presets de fecha -->
      <div class="d-flex align-center gap-2 flex-wrap">
        <VBtn
          size="small"
          variant="tonal"
          color="info"
          @click="setMonthPreset"
          :disabled="loading"
        >
          <VIcon icon="tabler-calendar-month" size="16" class="me-1" />
          Mes Actual
        </VBtn>

        <VBtn
          size="small"
          variant="tonal"
          color="info"
          @click="setQuarterPreset"
          :disabled="loading"
        >
          <VIcon icon="tabler-calendar-stats" size="16" class="me-1" />
          Trimestre
        </VBtn>

        <VBtn
          size="small"
          variant="tonal"
          color="info"
          @click="setYearPreset"
          :disabled="loading"
        >
          <VIcon icon="tabler-calendar-year" size="16" class="me-1" />
          Año Actual
        </VBtn>
      </div>

      <VSpacer />

      <VBtn
        color="primary"
        prepend-icon="tabler-filter"
        @click="handleApplyFilter"
        :disabled="!isValidDateRange || loading"
        :loading="loading"
      >
        Aplicar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
