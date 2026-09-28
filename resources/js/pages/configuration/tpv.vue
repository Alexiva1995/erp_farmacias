<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useAbility } from '@casl/vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'
import TpvGeneralSettingsCard from '@/components/configuration/TpvGeneralSettingsCard.vue'
import TpvPaymentMethodsCard from '@/components/configuration/TpvPaymentMethodsCard.vue'
import TpvPromotionsCard from '@/components/configuration/TpvPromotionsCard.vue'

const ability = useAbility()
const brandingStore = useBrandingStore()

// Control de estados de carga y error
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')

// Estado por defecto del formulario de TPV
const defaultSettings = {
  tpv_mode: 'complete',
  tpv_style: 'pharmacy',
  enable_flash_checkout: false,
  enable_quotations: true,
  quotation_style: 'pharmacy',
  tpv_rate_type: 'bcv',
  default_currency: 'USD',
  round_usd_up: false,
  tpv_payment_methods: {
    COP: { enabled: true, methods: [] },
    USD: { enabled: true, methods: [] },
    BS: { enabled: true, methods: [] }
  },
  enabled_offer_types: ['general', 'individual', 'category', 'pack', 'company', 'doctor', 'prescription', 'expiration']
}

// Estados reactivos
const originalSettings = ref(JSON.parse(JSON.stringify(defaultSettings)))
const formState = reactive(JSON.parse(JSON.stringify(defaultSettings)))

// Verificación de cambios sin guardar (Dirty State)
const isDirty = computed(() => {
  return JSON.stringify(formState) !== JSON.stringify(originalSettings.value)
})

// Permiso de edición
const canEdit = computed(() => {
  return ability.can('edit', 'Configuration') || ability.can('manage', 'all')
})

// Carga optimizada de la configuración
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''

  const fields = [
    'default_currency',
    'tpv_mode',
    'tpv_style',
    'enable_flash_checkout',
    'enable_quotations',
    'quotation_style',
    'tpv_rate_type',
    'round_usd_up',
    'tpv_payment_methods',
    'enabled_offer_types'
  ].join(',')

  try {
    const response = await axios.get('/general-settings', {
      params: { only: fields }
    })
    const settings = response.data.data || {}

    const loadedData = {
      tpv_mode: settings.tpv_mode || 'complete',
      tpv_style: settings.tpv_style || 'pharmacy',
      enable_flash_checkout: !!settings.enable_flash_checkout,
      enable_quotations: settings.enable_quotations !== undefined ? !!settings.enable_quotations : true,
      quotation_style: settings.quotation_style || 'pharmacy',
      tpv_rate_type: settings.tpv_rate_type || 'bcv',
      default_currency: settings.default_currency || 'USD',
      round_usd_up: !!settings.round_usd_up,
      tpv_payment_methods: settings.tpv_payment_methods || defaultSettings.tpv_payment_methods,
      enabled_offer_types: Array.isArray(settings.enabled_offer_types)
        ? settings.enabled_offer_types
        : defaultSettings.enabled_offer_types
    }

    Object.assign(formState, JSON.parse(JSON.stringify(loadedData)))
    originalSettings.value = JSON.parse(JSON.stringify(loadedData))
  } catch (error) {
    console.error('Error cargando configuración del TPV:', error)
    hasError.value = true
    errorMessage.value = 'No se pudo obtener la configuración del TPV. Por favor, reintente.'
    toast.error('Error al cargar la configuración')
  } finally {
    isLoading.value = false
  }
}

// Restaurar cambios
const resetSettings = async () => {
  if (isDirty.value) {
    const isConfirmed = await confirmDialog({
      title: '¿Descartar cambios?',
      text: 'Se revertirán todos los ajustes modificados a su estado original guardado.',
      icon: 'question',
      confirmButtonText: 'Sí, descartar',
      cancelButtonText: 'Cancelar'
    })

    if (!isConfirmed) return
  }

  Object.assign(formState, JSON.parse(JSON.stringify(originalSettings.value)))
  toast.info('Cambios restablecidos')
}

// Alternar tipos de ofertas
const toggleOfferType = (key) => {
  if (!canEdit.value || isSaving.value || isLoading.value) return
  const index = formState.enabled_offer_types.indexOf(key)
  if (index > -1) {
    formState.enabled_offer_types.splice(index, 1)
  } else {
    formState.enabled_offer_types.push(key)
  }
}

// Persistir la configuración en el servidor
const saveSettings = async () => {
  if (!canEdit.value) {
    toast.error('No tienes permisos para modificar la configuración')
    return
  }

  if (isSaving.value || isLoading.value) return
  isSaving.value = true

  try {
    const payload = {
      default_currency: formState.default_currency,
      tpv_mode: formState.tpv_mode,
      tpv_style: formState.tpv_style,
      enable_flash_checkout: formState.enable_flash_checkout,
      enable_quotations: formState.enable_quotations,
      quotation_style: formState.quotation_style,
      tpv_rate_type: formState.tpv_rate_type,
      round_usd_up: formState.round_usd_up,
      tpv_payment_methods: formState.tpv_payment_methods,
      enabled_offer_types: formState.enabled_offer_types
    }

    await axios.post('/general-settings', payload)

    originalSettings.value = JSON.parse(JSON.stringify(formState))

    // Sincronizar Pinia store
    brandingStore.settings = {
      ...brandingStore.settings,
      ...payload
    }
    if (formState.tpv_payment_methods) {
      brandingStore.updatePaymentMethods(formState.tpv_payment_methods)
    }

    toast.success('Configuración del TPV actualizada exitosamente')
  } catch (error) {
    console.error('Error al guardar la configuración del TPV:', error)
    toast.error('Error al actualizar la configuración')
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div class="position-relative">
    <!-- Barra de procesamiento superior -->
    <VProgressLinear
      v-if="isSaving"
      indeterminate
      color="primary"
      class="position-absolute top-0 left-0 right-0"
      style="z-index: 99;"
      height="4"
    />

    <!-- Banner de Error con Reintento -->
    <VAlert
      v-if="hasError"
      type="error"
      variant="tonal"
      class="mb-6 rounded-lg"
      closable
    >
      <template #title> Error de Carga </template>
      {{ errorMessage }}
      <template #append>
        <VBtn
          color="error"
          variant="text"
          size="small"
          prepend-icon="tabler-reload"
          @click="fetchSettings"
        >
          Reintentar
        </VBtn>
      </template>
    </VAlert>

    <!-- Skeletons durante Carga Inicial -->
    <div v-if="isLoading" class="d-flex flex-column gap-6">
      <VCard class="mb-6 rounded-lg border shadow-sm">
        <VCardText class="py-10">
          <VSkeletonLoader type="article, grid" />
        </VCardText>
      </VCard>
      <VCard class="mb-6 rounded-lg border shadow-sm">
        <VCardText class="py-10">
          <VSkeletonLoader type="article, grid" />
        </VCardText>
      </VCard>
    </div>

    <!-- Contenido Principal -->
    <div v-else-if="!hasError">
      <!-- Encabezado Enterprise con Acciones Principales -->
      <VCard variant="flat" class="mb-6 rounded-lg border shadow-sm">
        <VCardItem class="px-6 py-4">
          <div class="d-flex align-center justify-space-between flex-wrap gap-4">
            <div class="d-flex align-center gap-3">
              <VAvatar color="primary" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-device-desktop-analytics" size="28" />
              </VAvatar>
              <div>
                <div class="d-flex align-center gap-2">
                  <h1 class="text-h5 font-weight-bold mb-0">Configuración de Punto de Venta (TPV)</h1>
                  <VChip
                    v-if="isDirty"
                    color="warning"
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-alert-circle"
                  >
                    Cambios sin guardar
                  </VChip>
                </div>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  Control global de modos de venta, conversión de divisas, métodos de pago e incentivos promocionales.
                </p>
              </div>
            </div>

            <!-- Acciones de Cabecera -->
            <div class="d-flex align-center gap-2">
              <VBtn
                variant="outlined"
                color="secondary"
                prepend-icon="tabler-rotate-clockwise"
                :disabled="!isDirty || isSaving || !canEdit"
                @click="resetSettings"
              >
                Restablecer
              </VBtn>

              <VBtn
                color="primary"
                prepend-icon="tabler-device-floppy"
                :loading="isSaving"
                :disabled="!isDirty || !canEdit"
                @click="saveSettings"
              >
                Guardar Cambios
              </VBtn>
            </div>
          </div>
        </VCardItem>
      </VCard>

      <!-- Configuración General del TPV -->
      <TpvGeneralSettingsCard
        v-model:tpv-mode="formState.tpv_mode"
        v-model:tpv-style="formState.tpv_style"
        v-model:enable-flash-checkout="formState.enable_flash_checkout"
        v-model:tpv-rate-type="formState.tpv_rate_type"
        v-model:default-currency="formState.default_currency"
        v-model:enable-quotations="formState.enable_quotations"
        v-model:quotation-style="formState.quotation_style"
        v-model:round-usd-up="formState.round_usd_up"
        :can-edit="canEdit"
        :is-saving="isSaving"
      />

      <!-- Métodos de Pago y Monedas -->
      <TpvPaymentMethodsCard
        v-model:payment-methods="formState.tpv_payment_methods"
        :can-edit="canEdit"
        :is-saving="isSaving"
      />

      <!-- Tipos de Ofertas y Promociones -->
      <TpvPromotionsCard
        :enabled-offer-types="formState.enabled_offer_types"
        :can-edit="canEdit"
        :is-saving="isSaving"
        @toggle="toggleOfferType"
      />
    </div>
  </div>
</template>

