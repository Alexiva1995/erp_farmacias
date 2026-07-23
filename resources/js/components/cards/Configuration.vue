<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";

const fiscalMode = ref('')
const specialStatus = ref('')
const allForeignSalesSpe = ref(false)
const blindCashClosure = ref(false)
const tpvMode = ref('complete')

const isLoading = ref(true)
const isSaving = ref(false)

const configFiscal = [
  { label: "Modo Demo (Pruebas)", value: "demo", icon: "tabler-device-computer-camera" },
  { label: "Modo Activo (Producción)", value: "activa", icon: "tabler-broadcast" },
]

const specialTaxpayerOptions = [
  { label: "Desactivado", value: "desactivada" },
  { label: "Activo (Contribuyente Especial)", value: "activa" },
]

const fetchSettings = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    fiscalMode.value = settings.fiscal_mode
    specialStatus.value = settings.special_taxpayer_status
    allForeignSalesSpe.value = !!settings.all_foreign_sales_spe
    blindCashClosure.value = !!settings.blind_cash_closure
    tpvMode.value = settings.tpv_mode || 'complete'
  } catch (error) {
    console.error("Error cargando configuración:", error)
    toast.error("Error al cargar la configuración de la aplicación")
  } finally {
    isLoading.value = false
  }
}

const updateSettings = async () => {
  if (isSaving.value) return
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      fiscal_mode: fiscalMode.value,
      special_taxpayer_status: specialStatus.value,
      all_foreign_sales_spe: allForeignSalesSpe.value,
      blind_cash_closure: blindCashClosure.value,
      tpv_mode: tpvMode.value
    })
    toast.success("Configuración actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar:", error)
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
  <VCard class="mb-6 position-relative">
    <!-- Indicador de carga inicial -->
    <div 
      v-if="isLoading" 
      class="d-flex flex-column align-center justify-center py-12"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
        class="mb-4"
      />
      <span class="text-muted text-body-1">Cargando parámetros del sistema...</span>
    </div>

    <template v-else>
      <!-- Indicador de guardado en segundo plano -->
      <VLinearProgress
        v-if="isSaving"
        indeterminate
        color="success"
        height="4"
        class="position-absolute top-0 left-0 right-0 z-index-2"
      />

      <VCardText class="pb-2 pt-6">
        <h4 class="text-h5 mb-1 d-flex align-center gap-2">
          <VIcon icon="tabler-settings" class="text-primary" />
          Configuración General y Parámetros del Sistema
        </h4>
        <p class="text-body-2 text-muted">
          Ajustes operativos de facturación, impuestos y seguridad de caja. Los cambios se guardan de forma automática.
        </p>
      </VCardText>

      <VDivider />

      <VCardItem class="py-5">
        <div class="d-flex align-start gap-3 mb-2">
          <VIcon icon="tabler-receipt-tax" class="text-secondary mt-1" />
          <div>
            <VCardTitle class="text-h6 pb-1">Configuración Fiscal</VCardTitle>
            <p class="text-caption text-muted mb-3">
              Define el modo en que la impresora fiscal procesará las operaciones comerciales.
            </p>
          </div>
        </div>
        <VRadioGroup
          v-model="fiscalMode"
          inline
          :disabled="isSaving"
          @update:model-value="updateSettings"
        >
          <VRadio   
            v-for="item in configFiscal"
            :key="item.value"
            :label="item.label"
            :value="item.value"
            color="primary"
          />
        </VRadioGroup>
      </VCardItem>

      <VDivider />

      <VCardItem class="py-5">
        <div class="d-flex align-start gap-3 mb-2">
          <VIcon icon="tabler-user-shield" class="text-secondary mt-1" />
          <div>
            <VCardTitle class="text-h6 pb-1">Sujeto Pasivo Especial (S.P.E.)</VCardTitle>
            <p class="text-caption text-muted mb-3">
              Activa o desactiva las retenciones aplicables a los contribuyentes especiales del SENIAT.
            </p>
          </div>
        </div>
        <VRadioGroup
          v-model="specialStatus"
          inline
          :disabled="isSaving"
          @update:model-value="updateSettings"
        >
          <VRadio   
            v-for="item in specialTaxpayerOptions"
            :key="item.value"
            :label="item.label"
            :value="item.value"
            color="primary"
          />
        </VRadioGroup>
      </VCardItem>

      <VDivider />

      <VCardItem class="py-5">
        <div class="d-flex align-start gap-3 mb-2">
          <VIcon icon="tabler-currency-dollar" class="text-secondary mt-1" />
          <div>
            <VCardTitle class="text-h6 pb-1">Recargo SPE Global</VCardTitle>
            <p class="text-caption text-muted mb-0">
              Aplica de manera global los recargos correspondientes a todas las transacciones procesadas en divisas.
            </p>
          </div>
        </div>
        <VSwitch
          v-model="allForeignSalesSpe"
          label="Aplicar recargo SPE a TODAS las ventas extranjeras (USD/COP)"
          class="mt-3"
          color="primary"
          :disabled="isSaving"
          @update:model-value="updateSettings"
        />
      </VCardItem>

      <VDivider />

      <VCardItem class="py-5">
        <div class="d-flex align-start gap-3 mb-2">
          <VIcon icon="tabler-lock" class="text-secondary mt-1" />
          <div>
            <VCardTitle class="text-h6 pb-1">Modalidad de Cierre de Caja</VCardTitle>
            <p class="text-caption text-muted mb-0">
              Medida de control interno que evita que los usuarios vean los saldos teóricos esperados al cerrar caja.
            </p>
          </div>
        </div>
        <VSwitch
          v-model="blindCashClosure"
          label="Habilitar Cierre de Caja Ciego (Ocultar tablas de reportes a los usuarios)"
          class="mt-3"
          color="primary"
          :disabled="isSaving"
          @update:model-value="updateSettings"
        />
      </VCardItem>
    </template>
  </VCard>
</template>

