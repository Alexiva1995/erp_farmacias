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
  <VCard class="mb-6 rounded-lg border shadow-sm bg-surface">
    <VCardText class="pa-4">
      <VRow align="center" no-gutters class="gap-4">
        <div class="d-flex align-center">
          <VAvatar color="primary" variant="tonal" size="44" rounded="lg" class="me-3">
            <VIcon icon="tabler-chart-bar-popular" size="26" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black mb-0">Cuadro de Mando Integral RRHH</h2>
            <p class="text-[11px] text-disabled mb-0 uppercase font-weight-bold">Análisis de Personal y Gamificación</p>
          </div>
        </div>

        <VSpacer />

        <div class="d-flex align-center gap-2">
          <VBtnToggle
            :model-value="compareMode"
            @update:model-value="val => emit('update:compareMode', val)"
            mandatory
            density="compact"
            color="primary"
            variant="tonal"
            class="me-4 rounded-lg overflow-hidden border"
          >
            <VBtn :value="false" class="px-3">
              <VIcon icon="tabler-trophy" size="22" />
              <VTooltip activator="parent">Ranking de Empleados</VTooltip>
            </VBtn>
            <VBtn :value="true" class="px-3">
              <VIcon icon="tabler-arrows-cross" size="22" />
              <VTooltip activator="parent">Cara a Cara (Comparativa)</VTooltip>
            </VBtn>
          </VBtnToggle>

          <AppTextField
            :model-value="startDate"
            @update:model-value="val => emit('update:startDate', val)"
            type="date"
            density="compact"
            hide-details
            class="premium-input"
          />
          <AppTextField
            :model-value="endDate"
            @update:model-value="val => emit('update:endDate', val)"
            type="date"
            density="compact"
            hide-details
            class="premium-input"
          />
          
          <VBtn
            icon
            variant="tonal"
            color="primary"
            size="38"
            :loading="loading"
            @click="emit('refresh')"
          >
            <VIcon icon="tabler-refresh" size="20" />
          </VBtn>
        </div>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.bg-surface { background-color: #fff !important; }
.font-weight-black { font-weight: 900 !important; }
.uppercase { text-transform: uppercase; letter-spacing: 0.5px; }
.premium-input { max-width: 150px; }
.gap-2 { gap: 8px; }
.gap-4 { gap: 16px; }
</style>
