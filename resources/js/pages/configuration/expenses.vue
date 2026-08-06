<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert"
import { useBrandingStore } from "@/stores/useBrandingStore"
import ExpenseSettingCard from '@/Components/configuration/ExpenseSettingCard.vue'

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
  expenseModeSimple.value ? 'Modo Simple' : 'Modo Real (Desglosado)'
)

const expenseModeColor = computed(() => 
  expenseModeSimple.value ? 'warning' : 'info'
)

const autoApproveLabel = computed(() => 
  expenseAutoApprove.value ? 'Auto-aprobar' : 'Flujo con revisión'
)

const autoApproveColor = computed(() => 
  expenseAutoApprove.value ? 'success' : 'secondary'
)

// Carga optimizada solicitando únicamente las propiedades necesarias
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''

  try {
    const response = await axios.get('/general-settings', {
      params: { only: 'expense_mode,expense_auto_approve' }
    })
    const settings = response.data.data
    if (settings) {
      expenseModeSimple.value = settings.expense_mode === 'simple'
      expenseAutoApprove.value = !!settings.expense_auto_approve
    }
  } catch (error) {
    console.error("Error cargando configuración de Gastos:", error)
    hasError.value = true
    errorMessage.value = "No se pudo cargar la configuración de Gastos. Verifique su conexión e intente de nuevo."
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

// Guarda la actualización en el servidor con rollback optimista
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
      expense_auto_approve: expenseAutoApprove.value
    })
    
    await brandingStore.fetchSettings()
    toast.success("Configuración de gastos actualizada exitosamente")
  } catch (error) {
    // Rollback en caso de error
    expenseModeSimple.value = previousMode
    expenseAutoApprove.value = previousAuto
    console.error("Error al guardar configuración de gastos:", error)
    toast.error("Error al actualizar la configuración")
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
    <!-- Tarjeta Principal de Configuración de Gastos -->
    <VCard class="mb-6 rounded-lg border shadow-sm position-relative overflow-hidden">
      <!-- Indicador de procesamiento -->
      <VProgressLinear
        v-if="isSaving"
        indeterminate
        color="primary"
        height="3"
        class="position-absolute top-0 left-0 right-0"
      />

      <VCardItem class="py-5">
        <!-- Encabezado Principal -->
        <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
          <VIcon icon="tabler-trending-down" color="primary" size="28" />
          Configuración de Gastos
        </VCardTitle>
        <p class="text-caption text-medium-emphasis mb-6">
          Personaliza el flujo de registro y el estado inicial de aprobación para el módulo de control de gastos de la farmacia.
        </p>

        <VDivider class="mb-6" />

        <!-- Banner de Error con Reintento -->
        <VAlert
          v-if="hasError"
          type="error"
          variant="tonal"
          class="mb-6 rounded-lg"
          closable
        >
          <template #title>
            Error de Carga
          </template>
          {{ errorMessage }}
          <template #append>
            <VBtn
              color="error"
              variant="text"
              size="small"
              @click="fetchSettings"
            >
              Reintentar
            </VBtn>
          </template>
        </VAlert>

        <!-- Skeletons durante Carga Inicial -->
        <VRow v-if="isLoading">
          <VCol cols="12" md="6">
            <VSkeletonLoader type="article, actions" class="rounded-lg border" height="150" />
          </VCol>
          <VCol cols="12" md="6">
            <VSkeletonLoader type="article, actions" class="rounded-lg border" height="150" />
          </VCol>
        </VRow>

        <!-- Opciones de Configuración con Componente Modular -->
        <VRow v-else-if="!hasError">
          <!-- Modalidad de Gasto -->
          <VCol cols="12" md="6">
            <ExpenseSettingCard
              title="Modalidad de Gasto"
              description="Elige 'Modo Simple' para registrar el total directamente como exento, o 'Modo Real' con desglose de base imponible e IVA (16%)."
              icon="tabler-receipt-tax"
              :model-value="expenseModeSimple"
              :badge-text="expenseModeLabel"
              :badge-color="expenseModeColor"
              label="Habilitar Modo Simple"
              :is-saving="isSaving"
              @update:model-value="(val) => updateExpenseSetting('mode', val)"
            />
          </VCol>

          <!-- Aprobación Directa -->
          <VCol cols="12" md="6">
            <ExpenseSettingCard
              title="Aprobación Directa de Gastos"
              description="Define si los gastos registrados se marcan automáticamente como 'Aprobado' (omitiendo el flujo de revisión) o inician en estado 'Pendiente'."
              icon="tabler-checkup-list"
              :model-value="expenseAutoApprove"
              :badge-text="autoApproveLabel"
              :badge-color="autoApproveColor"
              label="Auto-aprobar Gastos"
              :is-saving="isSaving"
              @update:model-value="(val) => updateExpenseSetting('autoApprove', val)"
            />
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>
