<script setup>
// Filtros para ISLR con selector de CE y exportación
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  selectedYear: { type: Number, required: true },
  availableYears: { type: Array, default: () => [] },
  isCeEnabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:selectedYear",
  "update:isCeEnabled",
  "refresh",
  "clear",
  "adjust-ut",
  "export",
]);

const yearModel = computed({
  get: () => props.selectedYear,
  set: (val) => emit("update:selectedYear", val),
});

const ceModel = computed({
  get: () => props.isCeEnabled,
  set: (val) => emit("update:isCeEnabled", val),
});
</script>

<template>
  <AppFilterBase
    :show-search="false"
    :show-advanced="false"
    class="py-1"
    @clear="emit('clear')"
  >
    <!-- Selector de Año y Switch de Contribuyente Especial -->
    <template #search>
      <div class="d-flex align-center gap-3 w-100 flex-wrap">
        <VSelect
          v-model="yearModel"
          :items="props.availableYears"
          placeholder="Año Fiscal"
          variant="outlined"
          density="compact"
          hide-details
          color="primary"
          style="max-width: 170px;"
        >
          <template #prepend-inner>
            <VIcon icon="tabler-calendar" size="18" color="disabled" class="me-2" />
          </template>
        </VSelect>

        <!-- Switch Condición CE -->
        <div class="d-flex align-center bg-light rounded px-3 py-1 border">
          <VSwitch
            v-model="ceModel"
            color="warning"
            density="compact"
            hide-details
            class="me-2"
          />
          <div class="d-flex flex-column">
            <span class="text-caption font-weight-black leading-none">
              Contribuyente Especial
            </span>
            <span class="text-super-xs text-medium-emphasis">
              {{ ceModel ? 'Anticipos 0.75% activos' : 'Régimen Ordinario' }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <template #actions-extra>
      <!-- Exportar Reporte -->
      <VBtn
        icon
        color="success"
        variant="tonal"
        size="38"
        class="rounded-circle shadow-sm me-1"
        @click="emit('export')"
      >
        <VIcon icon="tabler-file-spreadsheet" size="20" />
        <VTooltip activator="parent" location="top">Exportar Consolidado (CSV/Excel)</VTooltip>
      </VBtn>

      <!-- Ajuste UT -->
      <VBtn
        icon
        color="info"
        variant="tonal"
        size="38"
        class="rounded-circle shadow-sm me-1"
        @click="emit('adjust-ut')"
      >
        <VIcon icon="tabler-adjustments-alt" size="20" />
        <VTooltip activator="parent" location="top">Ajustar Unidad Tributaria</VTooltip>
      </VBtn>

      <!-- Actualizar -->
      <VBtn
        icon
        color="primary"
        variant="flat"
        size="38"
        class="rounded-circle shadow-sm me-1"
        :loading="props.loading"
        @click="emit('refresh')"
      >
        <VIcon icon="tabler-refresh" size="20" />
        <VTooltip activator="parent" location="top">Actualizar Datos</VTooltip>
      </VBtn>
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
