<script setup>
import { computed } from 'vue'

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

// Helper para obtener fecha local en formato YYYY-MM-DD
const formatDate = date => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const applyPreset = type => {
  const now = new Date()
  if (type === 'today') {
    props.filters.startDate = formatDate(now)
    props.filters.endDate = formatDate(now)
  } else if (type === 'week') {
    const start = new Date()
    start.setDate(now.getDate() - 6)
    props.filters.startDate = formatDate(start)
    props.filters.endDate = formatDate(now)
  } else if (type === 'month') {
    props.filters.startDate = formatDate(new Date(now.getFullYear(), now.getMonth(), 1))
    props.filters.endDate = formatDate(now)
  } else if (type === 'last_month') {
    const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    const lastDay = new Date(now.getFullYear(), now.getMonth(), 0)
    props.filters.startDate = formatDate(firstDay)
    props.filters.endDate = formatDate(lastDay)
  }
  emit('apply')
}

const selectedCategoryName = computed(() => {
  if (!props.filters.categoryId) return null
  const found = props.categories.find(c => c.id === props.filters.categoryId)
  return found?.name || null
})
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardText class="pa-4">
      <!-- Fila 1: Presets rápidos y Estado -->
      <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-3 pb-2 border-b">
        <div class="d-flex align-center gap-1 flex-wrap">
          <span class="text-caption font-weight-bold text-medium-emphasis me-1">RANGOS RÁPIDOS:</span>
          <VBtn
            size="x-small"
            variant="tonal"
            color="primary"
            :disabled="loading"
            @click="applyPreset('today')"
          >
            Hoy
          </VBtn>
          <VBtn
            size="x-small"
            variant="tonal"
            color="primary"
            :disabled="loading"
            @click="applyPreset('week')"
          >
            Últimos 7 Días
          </VBtn>
          <VBtn
            size="x-small"
            variant="tonal"
            color="primary"
            :disabled="loading"
            @click="applyPreset('month')"
          >
            Mes Actual
          </VBtn>
          <VBtn
            size="x-small"
            variant="tonal"
            color="primary"
            :disabled="loading"
            @click="applyPreset('last_month')"
          >
            Mes Anterior
          </VBtn>
        </div>

        <div v-if="selectedCategoryName" class="d-flex align-center gap-1">
          <VChip
            size="small"
            color="primary"
            closable
            variant="tonal"
            @click:close="filters.categoryId = null; emit('apply')"
          >
            <VIcon icon="tabler-category" start size="14" />
            {{ selectedCategoryName }}
          </VChip>
        </div>
      </div>

      <!-- Fila 2: Inputs de Fecha, Categoría y Botones -->
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
        <VCol cols="12" sm="6" md="auto" class="d-flex align-center gap-1 justify-end flex-wrap">
          <VBtn
            icon
            variant="tonal"
            color="primary"
            size="38"
            rounded="circle"
            :loading="loading"
            :disabled="loading"
            @click="emit('apply')"
          >
            <VIcon icon="tabler-player-play" size="20" />
            <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
          </VBtn>

          <VBtn
            icon
            variant="tonal"
            color="secondary"
            size="38"
            rounded="circle"
            :disabled="loading"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-eraser" size="20" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2 border-opacity-10" />

          <VBtn
            icon
            variant="tonal"
            color="error"
            size="38"
            rounded="circle"
            :disabled="loading"
            @click="emit('export-pdf')"
          >
            <VIcon icon="tabler-file-type-pdf" size="20" />
            <VTooltip activator="parent" location="top">Exportar Reporte PDF</VTooltip>
          </VBtn>

          <VBtn
            icon
            variant="tonal"
            color="success"
            size="38"
            rounded="circle"
            :disabled="loading"
            @click="emit('export-excel')"
          >
            <VIcon icon="tabler-file-spreadsheet" size="20" />
            <VTooltip activator="parent" location="top">Exportar Cruces (CSV)</VTooltip>
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
