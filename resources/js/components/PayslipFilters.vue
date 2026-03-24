<script setup>
// Filtros de Nómina (Payroll)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed, ref } from "vue";

const props = defineProps({
  startDate: { type: [String, null], default: null },
  endDate:   { type: [String, null], default: null },
  loading:   { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:startDate",
  "update:endDate",
  "clear",
  "generated",
  "download-bulk",
  "refresh",
]);

const isGenerating = ref(false);

const hasAdvancedFilters = computed(() => !!(props.startDate || props.endDate));

const handleManualPayment = async () => {
  isGenerating.value = true;
  try {
    emit("generated");
  } finally {
    isGenerating.value = false;
  }
};
</script>

<template>
  <AppFilterBase
    :search="''"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Filtros del período..."
    @clear="emit('clear')"
  >
    <!-- Sobre-escribimos el search para poner las fechas principales si no hay búsqueda por texto -->
    <template #search>
      <div class="d-flex align-center gap-2 flex-grow-1 min-width-0 w-100">
        <!-- Fecha Inicial -->
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Fecha Inicial"
          clearable
          density="compact"
          hide-details
          class="flex-grow-1"
          style="min-width: 130px;"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar"
          @update:model-value="emit('update:startDate', $event)"
        />

        <span class="text-disabled d-none d-sm-inline">—</span>

        <!-- Fecha Final -->
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Fecha Final"
          clearable
          density="compact"
          hide-details
          class="flex-grow-1"
          style="min-width: 130px;"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="emit('update:endDate', $event)"
        />
      </div>
    </template>

    <template #actions-extra>
      <!-- Actualizar -->
      <VBtn
        icon
        variant="tonal"
        color="secondary"
        size="38"
        class="ml-1 d-none d-sm-flex"
        :loading="props.loading"
        @click="emit('refresh')"
      >
        <VIcon icon="tabler-refresh" size="20" />
        <VTooltip activator="parent" location="top">Actualizar Datos</VTooltip>
      </VBtn>

      <VBtn
        color="success"
        variant="tonal"
        size="38"
        icon
        class="ml-1"
        @click="emit('download-bulk')"
      >
        <VIcon icon="tabler-download" size="20" />
        <VTooltip activator="parent" location="top">Descargar Todo 2025</VTooltip>
      </VBtn>

      <VBtn
        color="primary"
        variant="tonal"
        size="38"
        icon
        class="ml-1"
        :loading="isGenerating"
        :disabled="isGenerating"
        @click="handleManualPayment"
      >
        <VIcon icon="tabler-player-play" size="20" />
        <VTooltip activator="parent" location="top">Generar Nómina Manual</VTooltip>
      </VBtn>
    </template>
  </AppFilterBase>
</template>
