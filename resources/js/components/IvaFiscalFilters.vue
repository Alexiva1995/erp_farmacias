<script setup>
// Filtros para IVA Fiscal
import AppFilterBase from "@/components/AppFilterBase.vue";
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
  "refresh",
]);

// Validaciones
const isValidDateRange = computed(() => {
  if (!props.startDate || !props.endDate) return true;
  return new Date(props.startDate) <= new Date(props.endDate);
});

const hasActiveFilters = computed(() => {
  return props.startDate || props.endDate;
});

// Presets de meses específicos del año en curso
const months = [
  "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
  "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
];

const setSpecificMonth = (monthIndex) => {
  const now = new Date();
  const year = now.getFullYear();
  // El mes en Date usa base 0 (0 = Enero, 11 = Diciembre)
  const startOfMonth = new Date(year, monthIndex, 1);
  const endOfMonth = new Date(year, monthIndex + 1, 0);

  // Formato local en lugar de toISOString para evitar desfase horario
  const formatOffsetDate = (date) => {
    const d = new Date(date);
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().split("T")[0];
  };

  emit("update:startDate", formatOffsetDate(startOfMonth));
  emit("update:endDate", formatOffsetDate(endOfMonth));
  setTimeout(() => emit("apply-filter"), 100);
};
</script>

<template>
  <AppFilterBase
    :show-search="false"
    :show-advanced="false"
    class="py-1"
    @clear="emit('clear-filter')"
  >
    <!-- Filtros de fecha siempre visibles -->
    <template #search>
      <VRow dense class="w-100 flex-nowrap align-center">
        <!-- Fecha Inicial -->
        <VCol cols="6">
          <AppDateTimePicker
            :model-value="props.startDate"
            placeholder="Desde"
            variant="outlined"
            density="compact"
            hide-details
            clearable
            prepend-inner-icon="tabler-calendar"
            :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            :error="!isValidDateRange"
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>

        <!-- Fecha Final -->
        <VCol cols="6">
          <AppDateTimePicker
            :model-value="props.endDate"
            placeholder="Hasta"
            variant="outlined"
            density="compact"
            hide-details
            clearable
            prepend-inner-icon="tabler-calendar-check"
            :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            :error="!isValidDateRange"
            @update:model-value="emit('update:endDate', $event)"
          />
        </VCol>
      </VRow>
    </template>

    <template #actions-extra>
      <!-- Presets de Fecha -->
      <VMenu transition="scale-transition">
        <template #activator="{ props: menuProps }">
          <VBtn
            v-bind="menuProps"
            icon
            variant="tonal"
            color="info"
            size="38"
            class="rounded-circle shadow-sm me-1"
          >
            <VIcon icon="tabler-calendar-stats" size="20" />
            <VTooltip activator="parent" location="top">Períodos Predefinidos</VTooltip>
          </VBtn>
        </template>
        <VList class="rounded-lg shadow-lg border-0 pa-2" min-width="180" max-height="300">
          <VListItem 
            v-for="(month, index) in months" 
            :key="index"
            class="rounded-md mb-1" 
            @click="setSpecificMonth(index)"
          >
            <template #prepend>
              <VIcon icon="tabler-calendar-month" size="18" class="me-3" color="info" />
            </template>
            <VListItemTitle class="text-xs font-weight-bold">{{ month }}</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>

      <VBtn
        icon
        color="primary"
        variant="flat"
        size="38"
        class="rounded-circle shadow-sm me-1"
        :loading="props.loading"
        @click="emit('apply-filter')"
      >
        <VIcon icon="tabler-check" size="20" />
        <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
      </VBtn>

      <VBtn
        icon
        color="secondary"
        variant="tonal"
        size="38"
        class="rounded-circle shadow-sm"
        :loading="props.loading"
        @click="emit('refresh')"
      >
        <VIcon icon="tabler-refresh" size="20" />
        <VTooltip activator="parent" location="top">Sincronizar Datos</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Alerta de fecha inválida -->
      <VCol v-if="!isValidDateRange" cols="12">
        <VAlert type="error" variant="tonal" density="compact" class="rounded-lg">
          <span class="text-xs font-weight-bold">La fecha inicial debe ser menor o igual a la fecha final.</span>
        </VAlert>
      </VCol>
    </template>
  </AppFilterBase>
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

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}
</style>
