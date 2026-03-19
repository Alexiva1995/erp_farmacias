<script setup>
import { computed, ref } from "vue";

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

const isFilterVisible = ref(false);

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

const hasFiltersCount = computed(() => {
  let count = 0;
  if (props.startDate) count++;
  if (props.endDate) count++;
  return count;
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
  setTimeout(() => emit("apply-filter"), 100);
};

const setQuarterPreset = () => {
  const now = new Date();
  const quarter = Math.floor(now.getMonth() / 3);
  const startOfQuarter = new Date(now.getFullYear(), quarter * 3, 1);
  const endOfQuarter = new Date(now.getFullYear(), (quarter + 1) * 3, 0);

  emit("update:startDate", startOfQuarter.toISOString().split("T")[0]);
  emit("update:endDate", endOfQuarter.toISOString().split("T")[0]);
  setTimeout(() => emit("apply-filter"), 100);
};

const setYearPreset = () => {
  const now = new Date();
  const startOfYear = new Date(now.getFullYear(), 0, 1);
  const endOfYear = new Date(now.getFullYear(), 11, 31);

  emit("update:startDate", startOfYear.toISOString().split("T")[0]);
  emit("update:endDate", endOfYear.toISOString().split("T")[0]);
  setTimeout(() => emit("apply-filter"), 100);
};
</script>

<template>
  <VCard class="mb-6 rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
    <!-- Barra de Acciones Principal -->
    <VCardActions class="pa-4 px-6 d-flex align-center bg-surface">
      <div class="d-flex align-center gap-2">
        <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
          <VIcon icon="tabler-calendar-search" size="20" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-sm font-weight-black uppercase leading-none mb-1">Período Fiscal</span>
          <span class="text-super-xs text-disabled font-weight-medium">Filtrar por fechas</span>
        </div>
      </div>

      <VSpacer />

      <div class="d-flex align-center gap-2">
        <!-- Toggle Filtros -->
        <VBtn
          icon
          variant="tonal"
          :color="isFilterVisible ? 'primary' : 'secondary'"
          size="38"
          @click="isFilterVisible = !isFilterVisible"
          class="rounded-lg"
        >
          <VBadge
            :model-value="hasFiltersCount > 0"
            :content="hasFiltersCount"
            color="error"
            offset-x="3"
            offset-y="3"
          >
            <VIcon :icon="isFilterVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
          </VBadge>
          <VTooltip activator="parent" location="top">{{ isFilterVisible ? 'Ocultar Filtros' : 'Mostrar Filtros' }}</VTooltip>
        </VBtn>

        <!-- Presets de Fecha (Quick Actions) -->
        <VMenu transition="scale-transition">
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              icon
              variant="tonal"
              color="info"
              size="38"
              class="rounded-lg"
            >
              <VIcon icon="tabler-calendar-stats" size="20" />
              <VTooltip activator="parent" location="top">Períodos Predefinidos</VTooltip>
            </VBtn>
          </template>
          <VList class="rounded-lg shadow-lg border-0 pa-2" min-width="180">
            <VListItem class="rounded-md mb-1" @click="setMonthPreset">
              <template #prepend>
                <VIcon icon="tabler-calendar-month" size="18" class="me-3" color="info" />
              </template>
              <VListItemTitle class="text-xs font-weight-bold">Mes Actual</VListItemTitle>
            </VListItem>
            <VListItem class="rounded-md mb-1" @click="setQuarterPreset">
              <template #prepend>
                <VIcon icon="tabler-calendar-stats" size="18" class="me-3" color="info" />
              </template>
              <VListItemTitle class="text-xs font-weight-bold">Trimestre Actual</VListItemTitle>
            </VListItem>
            <VListItem class="rounded-md" @click="setYearPreset">
              <template #prepend>
                <VIcon icon="tabler-calendar-year" size="18" class="me-3" color="info" />
              </template>
              <VListItemTitle class="text-xs font-weight-bold">Año Actual</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>

        <VDivider vertical class="mx-1 my-2" />

        <!-- Aplicar (Solo si hay cambios y panel visible?) o siempre? En este caso siempre -->
        <VBtn
          icon
          color="primary"
          variant="flat"
          size="38"
          class="rounded-lg shadow-sm"
          :loading="props.loading"
          @click="handleApplyFilter"
        >
          <VIcon icon="tabler-check" size="20" />
          <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
        </VBtn>

        <!-- Limpiar -->
        <VBtn
          icon
          variant="tonal"
          color="error"
          size="38"
          class="rounded-lg"
          @click="handleClearFilter"
          :disabled="hasFiltersCount === 0 || props.loading"
        >
          <VIcon icon="tabler-filter-x" size="20" />
          <VTooltip activator="parent" location="top">Restablecer Fechas</VTooltip>
        </VBtn>
      </div>
    </VCardActions>

    <!-- Panel de Filtros Colapsable -->
    <VExpandTransition>
      <div v-show="isFilterVisible">
        <VDivider class="opacity-10" />
        <VCardText class="pa-6 pt-4 bg-surface-variant-opacity-2">
          <VRow>
            <!-- Fecha Inicial -->
            <VCol cols="12" md="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Fecha Inicial</span>
              <AppDateTimePicker
                :model-value="startDateModel"
                placeholder="Desde..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                :error="!isValidDateRange"
                @update:model-value="startDateModel = $event"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-calendar-search" size="18" color="disabled" class="me-2" />
                </template>
              </AppDateTimePicker>
            </VCol>

            <!-- Fecha Final -->
            <VCol cols="12" md="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Fecha Final</span>
              <AppDateTimePicker
                :model-value="endDateModel"
                placeholder="Hasta..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                :error="!isValidDateRange"
                @update:model-value="endDateModel = $event"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-calendar-check" size="18" color="disabled" class="me-2" />
                </template>
              </AppDateTimePicker>
            </VCol>
          </VRow>

          <!-- Alerta de fecha inválida -->
          <VExpandTransition>
            <div v-if="!isValidDateRange" class="mt-4">
              <VAlert type="error" variant="tonal" density="compact" class="rounded-lg">
                <span class="text-xs font-weight-bold">La fecha inicial debe ser menor o igual a la fecha final.</span>
              </VAlert>
            </div>
          </VExpandTransition>
        </VCardText>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

:deep(.premium-input) {
  .v-field__outline {
    --v-field-border-opacity: 0.1;
  }
}
</style>
