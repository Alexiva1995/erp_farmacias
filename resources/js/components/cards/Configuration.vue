<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";

const form = ref({
  fiscal_mode: 'demo',
  special_taxpayer_status: 'desactivada',
  all_foreign_sales_spe: false,
  blind_cash_closure: false,
  tpv_mode: 'complete',
})

const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)

const configFiscal = [
  { 
    label: "Modo Demo (Pruebas)", 
    value: "demo", 
    icon: "tabler-device-computer-camera",
    color: "warning",
    description: "Operaciones de simulación sin emisión de documentos fiscales reales."
  },
  { 
    label: "Modo Activo (Producción)", 
    value: "activa", 
    icon: "tabler-broadcast",
    color: "success",
    description: "Emisión de comprobantes y facturas con valor fiscal vinculados a impresora."
  },
]

const specialTaxpayerOptions = [
  { label: "Desactivado", value: "desactivada", color: "secondary" },
  { label: "Activo (Contribuyente Especial)", value: "activa", color: "primary" },
]

const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  try {
    const response = await axios.get('/general-settings', {
      params: {
        only: 'fiscal_mode,special_taxpayer_status,all_foreign_sales_spe,blind_cash_closure,tpv_mode'
      }
    })
    const settings = response.data.data
    form.value = {
      fiscal_mode: settings.fiscal_mode ?? 'demo',
      special_taxpayer_status: settings.special_taxpayer_status ?? 'desactivada',
      all_foreign_sales_spe: !!settings.all_foreign_sales_spe,
      blind_cash_closure: !!settings.blind_cash_closure,
      tpv_mode: settings.tpv_mode ?? 'complete',
    }
  } catch (error) {
    console.error("Error cargando configuración:", error)
    hasError.value = true
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
      fiscal_mode: form.value.fiscal_mode,
      special_taxpayer_status: form.value.special_taxpayer_status,
      all_foreign_sales_spe: form.value.all_foreign_sales_spe,
      blind_cash_closure: form.value.blind_cash_closure,
      tpv_mode: form.value.tpv_mode
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
  <VCard class="mb-6 position-relative rounded-lg shadow-sm">
    <!-- Indicador de guardado en segundo plano -->
    <VLinearProgress
      v-if="isSaving"
      indeterminate
      color="primary"
      height="4"
      class="position-absolute top-0 left-0 right-0 z-index-2"
    />

    <!-- Header Principal -->
    <VCardText class="pb-4 pt-6">
      <div class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div>
          <h4 class="text-h5 font-weight-bold mb-1 d-flex align-center gap-2">
            <VIcon icon="tabler-settings" color="primary" size="28" />
            Configuración General y Parámetros del Sistema
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Ajustes operativos de facturación, impuestos y seguridad de caja. Los cambios se guardan automáticamente.
          </p>
        </div>

        <VChip
          v-if="!isLoading && !hasError"
          :color="form.fiscal_mode === 'activa' ? 'success' : 'warning'"
          variant="tonal"
          size="small"
          class="font-weight-medium"
        >
          <VIcon
            start
            size="16"
            :icon="form.fiscal_mode === 'activa' ? 'tabler-circle-check' : 'tabler-alert-triangle'"
          />
          {{ form.fiscal_mode === 'activa' ? 'Entorno de Producción' : 'Entorno de Pruebas (Demo)' }}
        </VChip>
      </div>
    </VCardText>

    <VDivider />

    <!-- Estado de Carga / Skeleton -->
    <VCardText v-if="isLoading" class="py-6">
      <VRow>
        <VCol cols="12" md="6" v-for="i in 4" :key="i">
          <VSkeletonLoader type="article, actions" class="border rounded" />
        </VCol>
      </VRow>
    </VCardText>

    <!-- Estado de Error -->
    <VCardText v-else-if="hasError" class="py-12 text-center">
      <VIcon icon="tabler-alert-circle" color="error" size="48" class="mb-3" />
      <h5 class="text-h6 text-error mb-1">No se pudo cargar la configuración</h5>
      <p class="text-body-2 text-medium-emphasis mb-4">Ocurrió un error al conectar con el servidor.</p>
      <VBtn color="primary" variant="outlined" prepend-icon="tabler-reload" @click="fetchSettings">
        Reintentar Petición
      </VBtn>
    </VCardText>

    <!-- Formulario Principal -->
    <VCardText v-else class="py-6">
      <VRow>
        <!-- Configuración Fiscal -->
        <VCol cols="12" md="6">
          <VCard variant="outlined" class="h-100 pa-4 border-dashed">
            <div class="d-flex align-start gap-3 mb-3">
              <VAvatar color="primary" variant="tonal" rounded>
                <VIcon icon="tabler-receipt-tax" size="24" />
              </VAvatar>
              <div>
                <VCardTitle class="text-subtitle-1 font-weight-bold px-0 py-0">Configuración Fiscal</VCardTitle>
                <p class="text-caption text-medium-emphasis mb-0">
                  Modo de procesamiento para la impresora y facturación.
                </p>
              </div>
            </div>

            <VRadioGroup
              v-model="form.fiscal_mode"
              :disabled="isSaving"
              class="mt-2"
              @update:model-value="updateSettings"
            >
              <VRadio
                v-for="item in configFiscal"
                :key="item.value"
                :value="item.value"
                color="primary"
                class="mb-2"
              >
                <template #label>
                  <div>
                    <span class="font-weight-medium text-body-2">{{ item.label }}</span>
                    <div class="text-caption text-medium-emphasis">{{ item.description }}</div>
                  </div>
                </template>
              </VRadio>
            </VRadioGroup>
          </VCard>
        </VCol>

        <!-- Sujeto Pasivo Especial (S.P.E.) -->
        <VCol cols="12" md="6">
          <VCard variant="outlined" class="h-100 pa-4 border-dashed">
            <div class="d-flex align-start gap-3 mb-3">
              <VAvatar color="secondary" variant="tonal" rounded>
                <VIcon icon="tabler-user-shield" size="24" />
              </VAvatar>
              <div>
                <VCardTitle class="text-subtitle-1 font-weight-bold px-0 py-0">Sujeto Pasivo Especial (S.P.E.)</VCardTitle>
                <p class="text-caption text-medium-emphasis mb-0">
                  Retenciones aplicables a contribuyentes especiales SENIAT.
                </p>
              </div>
            </div>

            <VRadioGroup
              v-model="form.special_taxpayer_status"
              :disabled="isSaving"
              class="mt-2"
              @update:model-value="updateSettings"
            >
              <VRadio
                v-for="item in specialTaxpayerOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
                color="primary"
                class="mb-2"
              />
            </VRadioGroup>
          </VCard>
        </VCol>

        <!-- Recargo SPE Global -->
        <VCol cols="12" md="6">
          <VCard variant="outlined" class="h-100 pa-4 border-dashed">
            <div class="d-flex align-start gap-3 mb-2">
              <VAvatar color="info" variant="tonal" rounded>
                <VIcon icon="tabler-currency-dollar" size="24" />
              </VAvatar>
              <div>
                <VCardTitle class="text-subtitle-1 font-weight-bold px-0 py-0">Recargo SPE Global</VCardTitle>
                <p class="text-caption text-medium-emphasis mb-0">
                  Aplica recargos a transacciones procesadas en divisas.
                </p>
              </div>
            </div>

            <VSwitch
              v-model="form.all_foreign_sales_spe"
              label="Aplicar recargo SPE a TODAS las ventas en divisas (USD/COP)"
              class="mt-3"
              color="primary"
              hide-details
              :disabled="isSaving"
              @update:model-value="updateSettings"
            />
          </VCard>
        </VCol>

        <!-- Modalidad de Cierre de Caja -->
        <VCol cols="12" md="6">
          <VCard variant="outlined" class="h-100 pa-4 border-dashed">
            <div class="d-flex align-start gap-3 mb-2">
              <VAvatar color="warning" variant="tonal" rounded>
                <VIcon icon="tabler-lock" size="24" />
              </VAvatar>
              <div>
                <VCardTitle class="text-subtitle-1 font-weight-bold px-0 py-0">Modalidad de Cierre de Caja</VCardTitle>
                <p class="text-caption text-medium-emphasis mb-0">
                  Control interno de saldos teóricos en el cierre diario.
                </p>
              </div>
            </div>

            <VSwitch
              v-model="form.blind_cash_closure"
              label="Habilitar Cierre de Caja Ciego (Ocultar montos teóricos a cajeros)"
              class="mt-3"
              color="primary"
              hide-details
              :disabled="isSaving"
              @update:model-value="updateSettings"
            />
          </VCard>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
