<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'
import ExpenseSettingCard from '@/components/configuration/ExpenseSettingCard.vue'
import ExpenseCategoryTable from '@/components/configuration/ExpenseCategoryTable.vue'

const brandingStore = useBrandingStore()

// Estados reactivos de la configuración
const expenseModeSimple = ref(false)
const expenseAutoApprove = ref(false)

// Estados de control de UI
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')

// Propiedades computadas para etiquetas e información de estado
const expenseModeLabel = computed(() =>
  expenseModeSimple.value ? 'Modo Simple (Exento)' : 'Modo Real (Base + IVA 16%)'
)

const expenseModeColor = computed(() =>
  expenseModeSimple.value ? 'warning' : 'info'
)

const autoApproveLabel = computed(() =>
  expenseAutoApprove.value ? 'Aprobación Automática' : 'Flujo con Auditoría'
)

const autoApproveColor = computed(() =>
  expenseAutoApprove.value ? 'success' : 'secondary'
)

// Carga inicial de configuraciones del servidor
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''

  try {
    const response = await axios.get('/general-settings', {
      params: { only: 'expense_mode,expense_auto_approve' },
    })

    const settings = response.data?.data
    if (settings) {
      expenseModeSimple.value = settings.expense_mode === 'simple'
      expenseAutoApprove.value = Boolean(settings.expense_auto_approve)
    }
  } catch (error) {
    console.error('Error cargando configuración de Gastos:', error)
    hasError.value = true
    errorMessage.value = 'No se pudo cargar la configuración de Gastos. Verifique su conexión e intente nuevamente.'
    toast.error('Error al sincronizar la configuración')
  } finally {
    isLoading.value = false
  }
}

// Persistencia en el servidor con reversión optimista
const updateExpenseSetting = async (key, val) => {
  if (isSaving.value) return

  const previousMode = expenseModeSimple.value
  const previousAuto = expenseAutoApprove.value

  if (key === 'mode') {
    expenseModeSimple.value = val
  } else if (key === 'autoApprove') {
    expenseAutoApprove.value = val
  }

  isSaving.value = true

  try {
    await axios.post('/general-settings', {
      expense_mode: expenseModeSimple.value ? 'simple' : 'real',
      expense_auto_approve: expenseAutoApprove.value,
    })

    await brandingStore.fetchSettings()
    toast.success('Configuración actualizada exitosamente')
  } catch (error) {
    // Reversión del estado en caso de fallo
    expenseModeSimple.value = previousMode
    expenseAutoApprove.value = previousAuto
    console.error('Error al guardar configuración de gastos:', error)
    toast.error('Error al persistir la configuración en el servidor')
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div>
    <!-- Tarjeta Principal de Configuración Global -->
    <VCard class="mb-6 rounded-lg border shadow-sm position-relative overflow-hidden">
      <!-- Indicador lineal de guardado en segundo plano -->
      <VProgressLinear
        v-if="isSaving"
        indeterminate
        color="primary"
        height="3"
        class="position-absolute top-0 left-0 right-0"
      />

      <VCardItem class="py-5">
        <!-- Encabezado del Módulo -->
        <VCardTitle class="text-h5 font-weight-bold text-uppercase d-flex align-center gap-2 mb-1">
          <VIcon icon="tabler-adjustments-alt" color="primary" size="28" />
          Configuración de Gastos y Egresos
        </VCardTitle>
        <p class="text-body-2 text-medium-emphasis mb-6">
          Define el tratamiento fiscal y el flujo de aprobación operativa para el registro de egresos en la farmacia.
        </p>

        <VDivider class="mb-6" />

        <!-- Alerta de Error con opción de Reintento -->
        <VAlert
          v-if="hasError"
          type="error"
          variant="tonal"
          class="mb-6 rounded-lg"
          closable
        >
          <template #title>
            Fallo en la comunicación con el servidor
          </template>
          {{ errorMessage }}
          <template #append>
            <VBtn
              color="error"
              variant="outlined"
              size="small"
              @click="fetchSettings"
            >
              Reintentar
            </VBtn>
          </template>
        </VAlert>

        <!-- Skeletons durante Carga -->
        <VRow v-if="isLoading">
          <VCol cols="12" md="6">
            <VSkeletonLoader type="article, actions" class="rounded-lg border" height="160" />
          </VCol>
          <VCol cols="12" md="6">
            <VSkeletonLoader type="article, actions" class="rounded-lg border" height="160" />
          </VCol>
        </VRow>

        <!-- Opciones de Configuración -->
        <VRow v-else-if="!hasError">
          <!-- Modalidad Fiscal de Gasto -->
          <VCol cols="12" md="6">
            <ExpenseSettingCard
              title="Modalidad de Desglose Fiscal"
              description="Elige 'Modo Simple' para asentar el importe íntegro exento de impuestos, o 'Modo Real' para calcular base imponible e IVA (16%)."
              icon="tabler-receipt-tax"
              :model-value="expenseModeSimple"
              :badge-text="expenseModeLabel"
              :badge-color="expenseModeColor"
              label="Habilitar Modo Simple"
              :is-saving="isSaving"
              @update:model-value="(val) => updateExpenseSetting('mode', val)"
            />
          </VCol>

          <!-- Flujo de Aprobación Directa -->
          <VCol cols="12" md="6">
            <ExpenseSettingCard
              title="Aprobación Inmediata de Egresos"
              description="Determina si los gastos registrados se asientan automáticamente como 'Aprobados' o si requieren pasar por la cola de revisión contable."
              icon="tabler-shield-check"
              :model-value="expenseAutoApprove"
              :badge-text="autoApproveLabel"
              :badge-color="autoApproveColor"
              label="Aprobar Automáticamente"
              :is-saving="isSaving"
              @update:model-value="(val) => updateExpenseSetting('autoApprove', val)"
            />
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>

    <!-- Catálogo Maestro de Categorías -->
    <ExpenseCategoryTable />
  </div>
</template>

