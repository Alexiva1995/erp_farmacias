<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  startDate: { type: [String, null], default: null },
  endDate: { type: [String, null], default: null },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:startDate",
  "update:endDate",
  "clear",
  "generated",
  "download-bulk",
  "refresh",
]);

const isFilterVisible = ref(false);
const isGenerating = ref(false);

const hasFiltersCount = computed(() => {
  let count = 0;
  if (props.startDate) count++;
  if (props.endDate) count++;
  return count;
});

const handleManualPayment = async () => {
  isGenerating.value = true;
  try {
    // La lógica de la petición axios se maneja en el componente padre 
    // para mantener PayslipFilters como un componente de UI puro
    emit("generated");
  } finally {
    isGenerating.value = false;
  }
};
</script>

<template>
  <VCard class="mb-6 rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
    <!-- Barra de Acciones Principal -->
    <VCardActions class="pa-4 px-6 d-flex align-center bg-surface">
      <div class="d-flex align-center gap-2">
        <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
          <VIcon icon="tabler-file-spreadsheet" size="20" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-sm font-weight-black uppercase leading-none mb-1">Registro de Nóminas</span>
          <span class="text-super-xs text-disabled font-weight-medium">Historial y generación de pagos</span>
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

        <VDivider vertical class="mx-1 my-2" />

        <!-- Actualizar -->
        <VBtn
          icon
          color="primary"
          variant="flat"
          size="38"
          class="rounded-lg shadow-sm"
          :loading="props.loading"
          @click="emit('refresh')"
        >
          <VIcon icon="tabler-refresh" size="20" />
          <VTooltip activator="parent" location="top">Actualizar Datos</VTooltip>
        </VBtn>

        <!-- Acciones Rápidas -->
        <VBtn
          color="success"
          variant="tonal"
          size="38"
          icon
          class="rounded-lg"
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
          class="rounded-lg"
          :loading="isGenerating"
          :disabled="isGenerating"
          @click="emit('generated')"
        >
          <VIcon icon="tabler-player-play" size="20" />
          <VTooltip activator="parent" location="top">Generar Nómina Manual</VTooltip>
        </VBtn>
      </div>
    </VCardActions>

    <!-- Panel de Filtros Colapsable -->
    <VExpandTransition>
      <div v-show="isFilterVisible">
        <VDivider class="opacity-10" />
        <VCardText class="pa-6 pt-4 bg-surface-variant-opacity-2">
          <VRow>
            <!-- Fecha Desde -->
            <VCol cols="12" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Fecha Inicial</span>
              <AppDateTimePicker
                :model-value="props.startDate"
                placeholder="Desde..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:startDate', $event)"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-calendar" size="18" color="disabled" class="me-2" />
                </template>
              </AppDateTimePicker>
            </VCol>

            <!-- Fecha Hasta -->
            <VCol cols="12" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Fecha Final</span>
              <AppDateTimePicker
                :model-value="props.endDate"
                placeholder="Hasta..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:endDate', $event)"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-calendar-check" size="18" color="disabled" class="me-2" />
                </template>
              </AppDateTimePicker>
            </VCol>

            <!-- Limpiar -->
            <VCol cols="12" md="4" class="d-flex align-end">
              <VBtn
                variant="tonal"
                color="error"
                block
                class="rounded-lg font-weight-black text-xs h-10"
                prepend-icon="tabler-filter-x"
                @click="emit('clear')"
              >
                LIMPIAR FILTROS
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
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

.h-10 {
  height: 40px !important;
}
</style>
