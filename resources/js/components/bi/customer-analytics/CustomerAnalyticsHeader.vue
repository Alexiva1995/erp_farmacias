<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
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
});

const emit = defineEmits(['update:startDate', 'update:endDate', 'refresh']);
</script>

<template>
  <VCard class="mb-6 rounded-lg border-sm elevation-1">
    <VCardText class="pa-4">
      <VRow align="center" justify="space-between">
        <VCol cols="12" md="6" class="d-flex align-center">
          <VAvatar color="primary" variant="tonal" size="44" rounded="lg" class="me-3">
            <VIcon icon="tabler-users-group" size="26" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-bold mb-0">Análisis de Cartera de Clientes</h2>
            <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">
              Inteligencia de Clientes y Recurrencia
            </span>
          </div>
        </VCol>

        <VCol cols="12" md="6">
          <div class="d-flex align-center justify-md-end gap-3 flex-wrap">
            <div style="max-width: 160px;" class="flex-grow-1 flex-md-grow-0">
              <VTextField
                :model-value="startDate"
                type="date"
                label="Fecha Desde"
                density="compact"
                hide-details
                variant="outlined"
                :disabled="loading"
                @update:model-value="emit('update:startDate', $event)"
              />
            </div>
            <span class="text-medium-emphasis font-weight-medium">a</span>
            <div style="max-width: 160px;" class="flex-grow-1 flex-md-grow-0">
              <VTextField
                :model-value="endDate"
                type="date"
                label="Fecha Hasta"
                density="compact"
                hide-details
                variant="outlined"
                :disabled="loading"
                @update:model-value="emit('update:endDate', $event)"
              />
            </div>

            <VBtn
              icon
              variant="flat"
              color="primary"
              size="40"
              :loading="loading"
              :disabled="loading"
              @click="emit('refresh')"
            >
              <VIcon icon="tabler-refresh" size="20" />
            </VBtn>
          </div>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
