<script setup>
const props = defineProps({
  compareMode: { type: Boolean, required: true },
  startDate: { type: String, required: true },
  endDate: { type: String, required: true },
  loading: { type: Boolean, default: false }
});

const emit = defineEmits([
  'update:compareMode',
  'update:startDate',
  'update:endDate',
  'refresh'
]);
</script>

<template>
  <VCard class="mb-6 rounded-lg border">
    <VCardText class="pa-4">
      <VRow align="center" no-gutters class="ga-4">
        <div class="d-flex align-center">
          <VAvatar color="primary" variant="tonal" size="44" rounded="lg" class="me-3">
            <VIcon icon="tabler-chart-bar-popular" size="26" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-bold mb-0">Cuadro de Mando Integral RRHH</h2>
            <p class="text-caption text-medium-emphasis mb-0 text-uppercase font-weight-bold letter-spacing-1">Análisis de Personal y Gamificación</p>
          </div>
        </div>

        <VSpacer />

        <div class="d-flex align-center ga-2 flex-wrap">
          <VBtnToggle
            :model-value="compareMode"
            @update:model-value="val => emit('update:compareMode', val)"
            mandatory
            density="comfortable"
            color="primary"
            variant="tonal"
            class="me-2 rounded-lg border"
          >
            <VBtn :value="false" class="px-3">
              <VIcon icon="tabler-trophy" size="20" class="me-1" />
              <span class="text-caption d-none d-sm-inline">Ranking</span>
              <VTooltip activator="parent" location="bottom">Ranking de Empleados</VTooltip>
            </VBtn>
            <VBtn :value="true" class="px-3">
              <VIcon icon="tabler-arrows-cross" size="20" class="me-1" />
              <span class="text-caption d-none d-sm-inline">Cara a Cara</span>
              <VTooltip activator="parent" location="bottom">Comparativa Cara a Cara</VTooltip>
            </VBtn>
          </VBtnToggle>

          <VTextField
            :model-value="startDate"
            @update:model-value="val => emit('update:startDate', val)"
            type="date"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            class="date-input"
            label="Desde"
          />
          <VTextField
            :model-value="endDate"
            @update:model-value="val => emit('update:endDate', val)"
            type="date"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            class="date-input"
            label="Hasta"
          />
          
          <VBtn
            icon
            variant="tonal"
            color="primary"
            size="38"
            rounded="circle"
            :loading="loading"
            @click="emit('refresh')"
          >
            <VIcon icon="tabler-refresh" size="20" />
            <VTooltip activator="parent" location="bottom">Actualizar datos</VTooltip>
          </VBtn>
        </div>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.date-input {
  max-width: 160px;
}
.letter-spacing-1 {
  letter-spacing: 0.5px;
}
</style>
