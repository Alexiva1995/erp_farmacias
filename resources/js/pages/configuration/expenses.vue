<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()
const expenseModeSimple = ref(false)
const expenseAutoApprove = ref(false)

const fetchSettings = async () => {
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    expenseModeSimple.value = settings.expense_mode === 'simple'
    expenseAutoApprove.value = !!settings.expense_auto_approve
  } catch (error) {
    console.error("Error cargando configuración de Gastos:", error)
    toast.error("Error al cargar la configuración")
  }
}

const updateSettings = async () => {
  try {
    await axios.post('/general-settings', {
      expense_mode: expenseModeSimple.value ? 'simple' : 'real',
      expense_auto_approve: expenseAutoApprove.value
    })
    await brandingStore.fetchSettings()

    toast.success("Configuración de Gastos actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar:", error)
    toast.error("Error al actualizar la configuración")
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div>
    <!-- Configuración de Gastos -->
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
          <VIcon icon="tabler-trending-down" class="text-primary" size="26" />
          Configuración de Gastos
        </VCardTitle>
        <p class="text-caption text-medium-emphasis mb-6">
          Personaliza el flujo de registro y el estado inicial de aprobación para el módulo de control de gastos de la farmacia.
        </p>

        <VRow>
          <!-- Modalidad de Gasto -->
          <VCol cols="12" md="6" class="d-flex flex-column justify-space-between mb-4">
            <div>
              <div class="font-weight-bold text-subtitle-2 mb-1">Modalidad de Gasto</div>
              <div class="text-caption text-medium-emphasis mb-3 leading-tight">
                Elige "Modo Simple" para registrar el total directamente como exento, o deshabilítalo para el "Modo Real" con desglose de base imponible e IVA (16%).
              </div>
            </div>
            <div>
              <VSwitch
                v-model="expenseModeSimple"
                label="Habilitar Modo Simple"
                color="primary"
                hide-details
                @update:model-value="updateSettings"
              />
            </div>
          </VCol>

          <!-- Aprobación de Gastos -->
          <VCol cols="12" md="6" class="d-flex flex-column justify-space-between mb-4">
            <div>
              <div class="font-weight-bold text-subtitle-2 mb-1">Aprobación Directa</div>
              <div class="text-caption text-medium-emphasis mb-3 leading-tight">
                Define si los gastos registrados se marcan automáticamente como "Aprobado" (omitiendo el flujo de revisión) o inician como "Pendiente".
              </div>
            </div>
            <div>
              <VSwitch
                v-model="expenseAutoApprove"
                label="Auto-aprobar Gastos"
                color="primary"
                hide-details
                @update:model-value="updateSettings"
              />
            </div>
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>
