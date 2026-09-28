<script setup>
import { defineProps, defineEmits } from 'vue'

const props = defineProps({
  filters: {
    type: Object,
    required: true,
  },
  categories: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['apply', 'clear', 'export-pdf', 'export-excel'])
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardText class="pa-4">
      <VRow align="center" dense>
        <!-- Fecha Inicio -->
        <VCol cols="12" sm="6" md="3">
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
        <VCol cols="12" sm="6" md="3">
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

        <!-- Filtro por Categoría -->
        <VCol cols="12" sm="6" md="3">
          <VAutocomplete
            v-model="filters.categoryId"
            :items="categories"
            item-title="name"
            item-value="id"
            label="Categoría"
            placeholder="Todas las categorías"
            density="comfortable"
            variant="outlined"
            hide-details="auto"
            clearable
            :disabled="loading"
            prepend-inner-icon="tabler-category"
          />
        </VCol>

        <VSpacer />

        <!-- Acciones de Filtrado y Exportación -->
        <VCol cols="12" sm="6" md="auto" class="d-flex align-center gap-2 justify-end flex-wrap">
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

          <VBtn
            color="error"
            variant="tonal"
            :disabled="loading"
            prepend-icon="tabler-file-type-pdf"
            @click="emit('export-pdf')"
          >
            PDF
          </VBtn>

          <VBtn
            color="success"
            variant="tonal"
            :disabled="loading"
            icon="tabler-file-spreadsheet"
            @click="emit('export-excel')"
          >
            <VIcon icon="tabler-file-spreadsheet" />
            <VTooltip activator="parent" location="top">Exportar Excel (CSV)</VTooltip>
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
