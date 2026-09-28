<script setup>
defineProps({
  startDate: {
    type: String,
    required: true,
  },
  endDate: {
    type: String,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  exportingPdf: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:startDate', 'update:endDate', 'refresh', 'exportPdf']);
</script>

<template>
  <VCard class="mb-6 rounded-lg elevation-1" variant="outlined">
    <VCardText class="pa-4">
      <VRow align="center" justify="space-between">
        <VCol cols="12" md="5" class="d-flex align-center">
          <VAvatar color="primary" variant="tonal" size="48" rounded="lg" class="me-3">
            <VIcon icon="tabler-users-group" size="26" />
          </VAvatar>
          <div>
            <h1 class="text-h6 font-weight-bold mb-0">Análisis de Cartera de Clientes</h1>
            <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">
              Inteligencia de Clientes y Recurrencia
            </span>
          </div>
        </VCol>

        <VCol cols="12" md="7">
          <div class="d-flex align-center justify-md-end gap-3 flex-wrap">
            <div style="min-width: 155px;" class="flex-grow-1 flex-md-grow-0">
              <VTextField
                :model-value="startDate"
                type="date"
                label="Fecha Desde"
                density="comfortable"
                hide-details="auto"
                variant="outlined"
                :disabled="loading"
                @update:model-value="emit('update:startDate', $event)"
              />
            </div>

            <div style="min-width: 155px;" class="flex-grow-1 flex-md-grow-0">
              <VTextField
                :model-value="endDate"
                type="date"
                label="Fecha Hasta"
                density="comfortable"
                hide-details="auto"
                variant="outlined"
                :disabled="loading"
                @update:model-value="emit('update:endDate', $event)"
              />
            </div>

            <!-- Botón Exportar PDF -->
            <VBtn
              variant="outlined"
              color="secondary"
              size="40"
              icon
              :loading="exportingPdf"
              :disabled="loading || exportingPdf"
              @click="emit('exportPdf')"
            >
              <VIcon icon="tabler-file-type-pdf" size="20" />
              <VTooltip activator="parent" location="top">Exportar Reporte Ejecutivo a PDF</VTooltip>
            </VBtn>

            <!-- Botón Recargar -->
            <VBtn
              variant="flat"
              color="primary"
              size="40"
              icon
              :loading="loading"
              :disabled="loading"
              @click="emit('refresh')"
            >
              <VIcon icon="tabler-refresh" size="20" />
              <VTooltip activator="parent" location="top">Actualizar Métricas</VTooltip>
            </VBtn>
          </div>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
