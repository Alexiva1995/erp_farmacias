<script setup>
import { computed } from 'vue';

const props = defineProps({
  loading: { type: Boolean, default: false },
  startDate: { type: String, required: true },
  endDate: { type: String, required: true },
});

const emit = defineEmits(['update:startDate', 'update:endDate', 'fetch', 'reset']);

const localStartDate = computed({
  get: () => props.startDate,
  set: (val) => emit('update:startDate', val),
});

const localEndDate = computed({
  get: () => props.endDate,
  set: (val) => emit('update:endDate', val),
});
</script>

<template>
  <VCard class="mb-6 rounded-lg" variant="outlined">
    <VCardText class="pa-4">
      <VRow align="center" dense>
        <VCol cols="12" sm="5" md="4" lg="3">
          <VTextField
            v-model="localStartDate"
            type="date"
            label="Fecha Inicio"
            :disabled="loading"
            density="comfortable"
            variant="outlined"
            hide-details="auto"
            prepend-inner-icon="tabler-calendar"
          />
        </VCol>

        <VCol cols="12" sm="5" md="4" lg="3">
          <VTextField
            v-model="localEndDate"
            type="date"
            label="Fecha Fin"
            :disabled="loading"
            density="comfortable"
            variant="outlined"
            hide-details="auto"
            prepend-inner-icon="tabler-calendar"
          />
        </VCol>

        <VSpacer class="d-none d-md-block" />

        <VCol cols="12" sm="2" md="auto" class="d-flex align-center justify-end gap-2 mt-2 mt-sm-0">
          <VBtn
            color="primary"
            variant="flat"
            prepend-icon="tabler-refresh"
            :loading="loading"
            :disabled="loading"
            @click="emit('fetch')"
          >
            Actualizar
          </VBtn>

          <VBtn
            color="secondary"
            variant="tonal"
            icon="tabler-eraser"
            :disabled="loading"
            @click="emit('reset')"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Restablecer Periodo</VTooltip>
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
