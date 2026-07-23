<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()
const expenseModeSimple = ref(false)
const expenseAutoApprove = ref(false)

// Estados de control para UX
const isLoading = ref(true)
const isSaving = ref(false)

const fetchSettings = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    expenseModeSimple.value = settings.expense_mode === 'simple'
    expenseAutoApprove.value = !!settings.expense_auto_approve
  } catch (error) {
    console.error("Error cargando configuración de Gastos:", error)
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

const toggleExpenseMode = async (newValue) => {
  if (isSaving.value) return
  isSaving.value = true
  
  try {
    await axios.post('/general-settings', {
      expense_mode: newValue ? 'simple' : 'real',
      expense_auto_approve: expenseAutoApprove.value
    })
    await brandingStore.fetchSettings()
    toast.success("Modalidad de gasto actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar modalidad:", error)
    toast.error("Error al actualizar la modalidad de gasto")
    // Reversión del estado local en caso de error
    expenseModeSimple.value = !newValue
  } finally {
    isSaving.value = false
  }
}

const toggleAutoApprove = async (newValue) => {
  if (isSaving.value) return
  isSaving.value = true

  try {
    await axios.post('/general-settings', {
      expense_mode: expenseModeSimple.value ? 'simple' : 'real',
      expense_auto_approve: newValue
    })
    await brandingStore.fetchSettings()
    toast.success("Pre-aprobación de gastos actualizada")
  } catch (error) {
    console.error("Error al guardar auto-aprobación:", error)
    toast.error("Error al actualizar la configuración de aprobación")
    // Reversión del estado local en caso de error
    expenseAutoApprove.value = !newValue
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
    <!-- Configuración de Gastos -->
    <VCard class="mb-6 rounded-lg border shadow-sm position-relative overflow-hidden">
      <!-- Indicador de guardado/procesamiento en segundo plano -->
      <VProgressLinear
        v-if="isSaving"
        indeterminate
        color="primary"
        height="3"
        class="position-absolute top-0 left-0 right-0"
      />

      <VCardItem class="py-5">
        <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
          <VIcon icon="tabler-trending-down" class="text-primary" size="26" />
          Configuración de Gastos
        </VCardTitle>
        <p class="text-caption text-medium-emphasis mb-6">
          Personaliza el flujo de registro y el estado inicial de aprobación para el módulo de control de gastos de la farmacia.
        </p>

        <!-- Skeleton loader mientras se consultan los parámetros iniciales -->
        <div v-if="isLoading" class="d-flex flex-column gap-6 py-4">
          <VSkeletonLoader type="paragraph" class="w-100" />
          <VSkeletonLoader type="paragraph" class="w-100" />
        </div>

        <VRow v-else>
          <!-- Modalidad de Gasto -->
          <VCol cols="12" md="6" class="d-flex flex-column justify-space-between mb-4">
            <div class="mb-3">
              <div class="font-weight-bold text-subtitle-2 mb-1 d-flex align-center gap-1">
                <span>Modalidad de Gasto</span>
                <VChip size="x-small" :color="expenseModeSimple ? 'warning' : 'info'" variant="tonal" class="font-weight-bold">
                  {{ expenseModeSimple ? 'Simple' : 'Real (Desglosado)' }}
                </VChip>
              </div>
              <div class="text-caption text-medium-emphasis leading-tight">
                Elige "Modo Simple" para registrar el total directamente como exento, o deshabilítalo para el "Modo Real" con desglose de base imponible e IVA (16%).
              </div>
            </div>
            <div>
              <VSwitch
                v-model="expenseModeSimple"
                label="Habilitar Modo Simple"
                color="primary"
                hide-details
                :disabled="isSaving"
                @update:model-value="toggleExpenseMode"
              />
            </div>
          </VCol>

          <!-- Aprobación de Gastos -->
          <VCol cols="12" md="6" class="d-flex flex-column justify-space-between mb-4">
            <div class="mb-3">
              <div class="font-weight-bold text-subtitle-2 mb-1 d-flex align-center gap-1">
                <span>Aprobación Directa</span>
                <VChip size="x-small" :color="expenseAutoApprove ? 'success' : 'secondary'" variant="tonal" class="font-weight-bold">
                  {{ expenseAutoApprove ? 'Auto-aprobar' : 'Flujo con revisión' }}
                </VChip>
              </div>
              <div class="text-caption text-medium-emphasis leading-tight">
                Define si los gastos registrados se marcan automáticamente como "Aprobado" (omitiendo el flujo de revisión) o inician como "Pendiente".
              </div>
            </div>
            <div>
              <VSwitch
                v-model="expenseAutoApprove"
                label="Auto-aprobar Gastos"
                color="primary"
                hide-details
                :disabled="isSaving"
                @update:model-value="toggleAutoApprove"
              />
            </div>
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>

