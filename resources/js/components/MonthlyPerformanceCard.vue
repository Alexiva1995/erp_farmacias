<script setup>
import { computed, ref } from 'vue'

// Props
const props = defineProps({
  loading: {
    type: Boolean,
    default: false
  },
  performanceData: {
    type: Object,
    required: true,
    default: () => ({
      salesMetrics: {
        currentMonth: {
          totalAmount: 0,
          totalUnits: 0,
          ticketAverage: 0,
          unitsAverage: 0,
          totalOrders: 0
        }
      }
    })
  }
})

// Emits
const emit = defineEmits(['filter-change'])

// Reactive data
const selectedMonth = ref({
  value: new Date().getMonth() + 1,
  year: new Date().getFullYear(),
  title: ''
})

// Computed properties
const availableMonths = computed(() => {
  const currentYear = new Date().getFullYear()
  const months = [
    { value: 1, title: 'Enero' },
    { value: 2, title: 'Febrero' },
    { value: 3, title: 'Marzo' },
    { value: 4, title: 'Abril' },
    { value: 5, title: 'Mayo' },
    { value: 6, title: 'Junio' },
    { value: 7, title: 'Julio' },
    { value: 8, title: 'Agosto' },
    { value: 9, title: 'Septiembre' },
    { value: 10, title: 'Octubre' },
    { value: 11, title: 'Noviembre' },
    { value: 12, title: 'Diciembre' }
  ]

  const options = []
  // Generar opciones para 2026 y años anteriores
  for (let year = currentYear; year >= 2026; year--) {
    months.forEach(month => {
      options.push({
        value: month.value,
        year: year,
        title: `${month.title} ${year}`
      })
    })
  }

  return options
})

// Methods
const handleMonthChange = (value) => {
  const selected = availableMonths.value.find(month => month.title === value)
  if (selected) {
    selectedMonth.value = selected
    emit('filter-change', {
      month: selected.value,
      year: selected.year
    })
  }
}

// Initialize current month
const initializeCurrentMonth = () => {
  const current = new Date()
  const currentMonthValue = current.getMonth() + 1
  const currentYear = current.getFullYear()
  
  const currentOption = availableMonths.value.find(
    month => month.value === currentMonthValue && month.year === currentYear
  )
  
  if (currentOption) {
    selectedMonth.value = currentOption
  }
}

// Initialize on mount
initializeCurrentMonth()
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center justify-space-between">
      <div class="d-flex align-center">
        <VIcon icon="tabler-calendar" class="me-2" />
        <span class="text-h6">Datos Mensuales Acumulados</span>
      </div>
      
      <VSelect
        v-model="selectedMonth.title"
        :items="availableMonths"
        item-title="title"
        item-value="title"
        size="small"
        density="compact"
        variant="outlined"
        prepend-inner-icon="tabler-calendar-event"
        class="month-selector"
        style="max-inline-size: 200px;"
        @update:model-value="handleMonthChange"
      />
    </VCardTitle>

    <VDivider />

    <VCardText>
      <VRow>
        <!-- Ventas del Mes -->
        <VCol cols="12" sm="6" md="3">
          <VCard color="primary" theme="dark" height="120" class="elevation-2">
            <VCardText class="d-flex align-center justify-center text-center pa-4">
              <div>
                <div class="bg-light-primary rounded-circle d-inline-flex align-center justify-center mb-3" style=" block-size: 48px;inline-size: 48px;">
                  <VIcon icon="tabler-cash" color="primary" size="24" />
                </div>
                <div class="text-h5 font-weight-bold text-white">
                  {{ performanceData.salesMetrics.currentMonth.totalAmount.toLocaleString('en-US', { style: 'currency', currency: 'USD' }) }}
                </div>
                <div class="text-caption text-white opacity-90">Ventas Mes</div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Unidades -->
        <VCol cols="12" sm="6" md="3">
          <VCard variant="tonal" color="success" height="120" class="elevation-2">
            <VCardText class="d-flex align-center justify-center text-center pa-4">
              <div>
                <div class="bg-light-success rounded-circle d-inline-flex align-center justify-center mb-3" style=" block-size: 48px;inline-size: 48px;">
                  <VIcon icon="tabler-package" color="success" size="24" />
                </div>
                <div class="text-h5 font-weight-bold text-success">
                  {{ performanceData.salesMetrics.currentMonth.totalUnits.toLocaleString() }}
                </div>
                <div class="text-caption text-medium-emphasis">Unidades</div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Ticket Promedio -->
        <VCol cols="12" sm="6" md="3">
          <VCard variant="tonal" color="warning" height="120" class="elevation-2">
            <VCardText class="d-flex align-center justify-center text-center pa-4">
              <div>
                <div class="bg-light-warning rounded-circle d-inline-flex align-center justify-center mb-3" style=" block-size: 48px;inline-size: 48px;">
                  <VIcon icon="tabler-receipt" color="warning" size="24" />
                </div>
                <div class="text-h5 font-weight-bold text-warning">
                  {{ performanceData.salesMetrics.currentMonth.ticketAverage.toLocaleString('en-US', { style: 'currency', currency: 'USD' }) }}
                </div>
                <div class="text-caption text-medium-emphasis">Ticket Prom.</div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Unidades Promedio -->
        <VCol cols="12" sm="6" md="3">
          <VCard variant="tonal" color="info" height="120" class="elevation-2">
            <VCardText class="d-flex align-center justify-center text-center pa-4">
              <div>
                <div class="bg-light-info rounded-circle d-inline-flex align-center justify-center mb-3" style=" block-size: 48px;inline-size: 48px;">
                  <VIcon icon="tabler-box-multiple" color="info" size="24" />
                </div>
                <div class="text-h5 font-weight-bold text-info">
                  {{ performanceData.salesMetrics.currentMonth.unitsAverage.toFixed(1) }}
                </div>
                <div class="text-caption text-medium-emphasis">Unds. Prom.</div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>

    <!-- Loading overlay -->
    <VOverlay v-model="loading" class="align-center justify-center" contained persistent>
      <VProgressCircular indeterminate color="primary" size="64">
        <VIcon icon="tabler-chart-bar" />
      </VProgressCircular>
    </VOverlay>
  </VCard>
</template>

<style scoped>
.month-selector :deep(.v-field__input) {
  font-size: 0.875rem;
}

.month-selector :deep(.v-select__selection-text) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Vuexy elevation styles */
.elevation-2 {
  box-shadow: 0 3px 6px rgba(0, 0, 0, 10%), 0 3px 6px rgba(0, 0, 0, 5%) !important;
}

/* Vuexy background utility classes */
.bg-light-primary {
  background-color: rgba(var(--v-theme-primary), 0.12) !important;
}

.bg-light-success {
  background-color: rgba(var(--v-theme-success), 0.12) !important;
}

.bg-light-warning {
  background-color: rgba(var(--v-theme-warning), 0.12) !important;
}

.bg-light-info {
  background-color: rgba(var(--v-theme-info), 0.12) !important;
}

/* Responsive adjustments */
@media (max-width: 960px) {
  .month-selector {
    max-inline-size: 150px !important;
  }
}

@media (max-width: 600px) {
  .v-card-title {
    flex-direction: column;
    align-items: flex-start !important;
    gap: 1rem;
  }

  .month-selector {
    max-inline-size: 100% !important;
  }
}
</style>
