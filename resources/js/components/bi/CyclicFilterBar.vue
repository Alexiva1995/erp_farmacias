<script setup>
import { defineProps, defineEmits } from 'vue'

const props = defineProps({
  filters: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['apply', 'clear'])
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardText class="pa-4">
      <VRow align="center" dense>
        <!-- Fecha Inicio -->
        <VCol cols="12" sm="5" md="3">
          <AppDateTimePicker
            v-model="filters.startDate"
            label="Fecha Inicio"
            placeholder="Seleccionar fecha"
            density="comfortable"
            variant="outlined"
            hide-details="auto"
            :disabled="loading"
            prepend-inner-icon="tabler-calendar"
          />
        </VCol>

        <!-- Fecha Fin -->
        <VCol cols="12" sm="5" md="3">
          <AppDateTimePicker
            v-model="filters.endDate"
            label="Fecha Fin"
            placeholder="Seleccionar fecha"
            density="comfortable"
            variant="outlined"
            hide-details="auto"
            :disabled="loading"
            prepend-inner-icon="tabler-calendar-check"
          />
        </VCol>

        <VSpacer />

        <!-- Acciones de Filtrado -->
        <VCol cols="12" sm="2" md="auto" class="d-flex align-center gap-2 justify-end">
          <VBtn
            color="primary"
            variant="flat"
            :loading="loading"
            :disabled="loading"
            prepend-icon="tabler-filter"
            @click="emit('apply')"
          >
            Filtrar
          </VBtn>

          <VBtn
            color="secondary"
            variant="outlined"
            :disabled="loading"
            icon="tabler-eraser"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
