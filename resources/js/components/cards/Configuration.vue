<script setup>
import { onMounted } from 'vue'
import { useAbility } from '@casl/vue'
import { useGeneralSettings } from '@/composables/configuration/useGeneralSettings'

const ability = useAbility()

const {
  form,
  isLoading,
  isSaving,
  hasError,
  isDirty,
  fetchSettings,
  resetForm,
  saveSettings,
} = useGeneralSettings()

const fiscalModeOptions = [
  {
    label: 'Modo Demo (Pruebas)',
    value: 'demo',
    icon: 'tabler-device-desktop-analytics',
    description: 'Operaciones de simulación sin emisión de documentos fiscales reales.',
  },
  {
    label: 'Modo Activo (Producción)',
    value: 'activa',
    icon: 'tabler-receipt',
    description: 'Emisión de comprobantes y facturas con valor fiscal vinculados a impresora.',
  },
]

const specialTaxpayerOptions = [
  {
    label: 'Desactivado (No Sujeto)',
    value: 'desactivada',
    description: 'No aplica retenciones de Sujeto Pasivo Especial.',
  },
  {
    label: 'Activo (Contribuyente Especial)',
    value: 'activa',
    description: 'Aplica retenciones SENIAT correspondientes a contribuyentes especiales.',
  },
]

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div v-if="ability.can('manage', 'admin')">
    <VCard class="mb-6 rounded-lg elevation-1 position-relative">
      <!-- Indicador de guardado superior -->
      <VProgressLinear
        v-if="isSaving"
        indeterminate
        color="primary"
        height="4"
        class="position-absolute top-0 left-0 right-0 z-index-2"
      />

      <!-- Cabecera Principal -->
      <VCardItem class="pb-4 pt-6">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <VCardTitle class="text-h5 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-settings" color="primary" size="28" />
              Configuración General y Parámetros del Sistema
            </VCardTitle>
            <VCardSubtitle class="text-body-2 text-medium-emphasis mt-1">
              Ajustes operativos de facturación, régimen tributario y seguridad de caja.
            </VCardSubtitle>
          </div>

          <div class="d-flex align-center gap-2">
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
        </div>
      </VCardItem>

      <VDivider />

      <!-- Estado de Carga / Skeleton -->
      <VCardText v-if="isLoading" class="py-8">
        <VRow>
          <VCol v-for="n in 4" :key="n" cols="12" md="6">
            <VSkeletonLoader type="article, actions" class="border rounded" />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Estado de Error de Conexión -->
      <VCardText v-else-if="hasError" class="py-12 text-center">
        <VIcon icon="tabler-alert-circle" color="error" size="56" class="mb-3" />
        <h3 class="text-h6 font-weight-bold text-error mb-1">
          No se pudo sincronizar la configuración
        </h3>
        <p class="text-body-2 text-medium-emphasis mb-6">
          Ocurrió un error al consultar los parámetros en el servidor.
        </p>
        <VBtn
          color="primary"
          variant="outlined"
          prepend-icon="tabler-reload"
          density="comfortable"
          @click="fetchSettings"
        >
          Reintentar Petición
        </VBtn>
      </VCardText>

      <!-- Formulario Principal -->
      <VCardText v-else class="py-6">
        <VRow>
          <!-- Configuración Fiscal -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="h-100 pa-4 rounded-lg">
              <div class="d-flex align-start gap-3 mb-4">
                <VAvatar color="primary" variant="tonal" rounded size="40">
                  <VIcon icon="tabler-receipt-tax" size="24" />
                </VAvatar>
                <div>
                  <div class="text-subtitle-1 font-weight-bold text-high-emphasis">
                    Configuración Fiscal
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Modo de procesamiento para la impresora y facturación legal.
                  </div>
                </div>
              </div>

              <VRadioGroup
                v-model="form.fiscal_mode"
                :disabled="isSaving"
                density="comfortable"
                hide-details="auto"
              >
                <VRadio
                  v-for="item in fiscalModeOptions"
                  :key="item.value"
                  :value="item.value"
                  color="primary"
                  class="mb-3"
                >
                  <template #label>
                    <div>
                      <div class="font-weight-medium text-body-2">{{ item.label }}</div>
                      <div class="text-caption text-medium-emphasis">{{ item.description }}</div>
                    </div>
                  </template>
                </VRadio>
              </VRadioGroup>
            </VCard>
          </VCol>

          <!-- Sujeto Pasivo Especial (S.P.E.) -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="h-100 pa-4 rounded-lg">
              <div class="d-flex align-start gap-3 mb-4">
                <VAvatar color="secondary" variant="tonal" rounded size="40">
                  <VIcon icon="tabler-building-bank" size="24" />
                </VAvatar>
                <div>
                  <div class="text-subtitle-1 font-weight-bold text-high-emphasis">
                    Sujeto Pasivo Especial (S.P.E.)
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Retenciones aplicables a contribuyentes especiales SENIAT.
                  </div>
                </div>
              </div>

              <VRadioGroup
                v-model="form.special_taxpayer_status"
                :disabled="isSaving"
                density="comfortable"
                hide-details="auto"
              >
                <VRadio
                  v-for="item in specialTaxpayerOptions"
                  :key="item.value"
                  :value="item.value"
                  color="primary"
                  class="mb-3"
                >
                  <template #label>
                    <div>
                      <div class="font-weight-medium text-body-2">{{ item.label }}</div>
                      <div class="text-caption text-medium-emphasis">{{ item.description }}</div>
                    </div>
                  </template>
                </VRadio>
              </VRadioGroup>
            </VCard>
          </VCol>

          <!-- Recargo SPE Global -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="h-100 pa-4 rounded-lg">
              <div class="d-flex align-start gap-3 mb-3">
                <VAvatar color="info" variant="tonal" rounded size="40">
                  <VIcon icon="tabler-currency-dollar" size="24" />
                </VAvatar>
                <div>
                  <div class="text-subtitle-1 font-weight-bold text-high-emphasis">
                    Recargo SPE Global
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Aplica recargos tributarios a transacciones en moneda extranjera.
                  </div>
                </div>
              </div>

              <VSwitch
                v-model="form.all_foreign_sales_spe"
                label="Aplicar recargo SPE a TODAS las ventas en divisas (USD/COP)"
                color="primary"
                density="comfortable"
                hide-details="auto"
                :disabled="isSaving"
                class="mt-2"
              />
            </VCard>
          </VCol>

          <!-- Modalidad de Cierre de Caja -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="h-100 pa-4 rounded-lg">
              <div class="d-flex align-start gap-3 mb-3">
                <VAvatar color="warning" variant="tonal" rounded size="40">
                  <VIcon icon="tabler-lock" size="24" />
                </VAvatar>
                <div>
                  <div class="text-subtitle-1 font-weight-bold text-high-emphasis">
                    Modalidad de Cierre de Caja
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Control interno de saldos teóricos en el cierre diario.
                  </div>
                </div>
              </div>

              <VSwitch
                v-model="form.blind_cash_closure"
                label="Habilitar Cierre de Caja Ciego (Ocultar montos teóricos a cajeros)"
                color="warning"
                density="comfortable"
                hide-details="auto"
                :disabled="isSaving"
                class="mt-2"
              />
            </VCard>
          </VCol>

          <!-- Cuentas y Crédito Especial (CE) -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="h-100 pa-4 rounded-lg">
              <div class="d-flex align-start gap-3 mb-3">
                <VAvatar color="success" variant="tonal" rounded size="40">
                  <VIcon icon="tabler-shield-check" size="24" />
                </VAvatar>
                <div class="flex-grow-1">
                  <div class="d-flex align-center gap-1">
                    <span class="text-subtitle-1 font-weight-bold text-high-emphasis">
                      Cuentas y Crédito Especial (CE)
                    </span>
                    <VTooltip text="Habilita líneas de crédito y consumos especiales en caja para clientes autorizados." location="top">
                      <template #activator="{ props: tooltipProps }">
                        <VIcon
                          v-bind="tooltipProps"
                          icon="tabler-help-circle"
                          size="18"
                          class="text-medium-emphasis cursor-pointer"
                        />
                      </template>
                    </VTooltip>
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Módulo de gestión de cuentas corrientes y créditos en punto de venta.
                  </div>
                </div>
              </div>

              <VSwitch
                v-model="form.enable_ce"
                label="Habilitar gestión de Crédito Especial (CE)"
                color="primary"
                density="comfortable"
                hide-details="auto"
                :disabled="isSaving"
                class="mt-2"
              />
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider v-if="!isLoading && !hasError" />

      <!-- Barra de Acciones y Persistencia -->
      <VCardActions v-if="!isLoading && !hasError" class="pa-4 bg-surface">
        <div class="d-flex align-center gap-2">
          <VIcon
            :icon="isDirty ? 'tabler-alert-circle' : 'tabler-check'"
            :color="isDirty ? 'warning' : 'success'"
            size="20"
          />
          <span class="text-caption text-medium-emphasis">
            {{ isDirty ? 'Hay cambios sin guardar' : 'Configuración sincronizada con el servidor' }}
          </span>
        </div>

        <VSpacer />

        <VBtn
          variant="outlined"
          color="secondary"
          density="comfortable"
          :disabled="!isDirty || isSaving"
          @click="resetForm"
        >
          Descartar
        </VBtn>

        <VBtn
          color="primary"
          variant="flat"
          density="comfortable"
          prepend-icon="tabler-device-floppy"
          :loading="isSaving"
          :disabled="!isDirty || isSaving"
          @click="saveSettings"
        >
          Guardar Cambios
        </VBtn>
      </VCardActions>
    </VCard>
  </div>

  <!-- Vista sin Permisos -->
  <VCard v-else class="text-center pa-12">
    <VIcon icon="tabler-lock" color="error" size="64" class="mb-4" />
    <h2 class="text-h5 font-weight-bold mb-2">Acceso Denegado</h2>
    <p class="text-body-2 text-medium-emphasis">
      No posee los privilegios requeridos para administrar la configuración general del sistema.
    </p>
  </VCard>
</template>
