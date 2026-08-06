<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert"
import { useBrandingStore } from "@/stores/useBrandingStore"
import TpvGeneralSettingsCard from '@/Components/configuration/TpvGeneralSettingsCard.vue'
import TpvPaymentMethodsCard from '@/Components/configuration/TpvPaymentMethodsCard.vue'
import TpvPromotionsCard from '@/Components/configuration/TpvPromotionsCard.vue'

const brandingStore = useBrandingStore()

// Estados reactivos principales
const tpvModeComplete = ref(true)
const tpvStyle = ref('pharmacy')
const enableFlashCheckout = ref(false)
const enableQuotations = ref(true)
const enableReservations = ref(true)
const quotationStyle = ref('pharmacy')
const tpvRateType = ref('bcv')
const defaultCurrency = ref('USD')
const roundUsdUp = ref(false)

// Métodos de pago por moneda
const tpvPaymentMethods = ref({
  COP: [],
  USD: [],
  BS: []
})

// Tipos de ofertas habilitadas
const enabledOfferTypes = ref([])

// Estados de control UI/UX y rendimiento
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')
const rawSettings = ref({})

// Carga optimizada mediante el filtro 'only' en la API de configuración general
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
    'enable_reservations',
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
    const settings = response.data.data
    rawSettings.value = settings

    tpvModeComplete.value = settings.tpv_mode !== 'simple'
    tpvStyle.value = settings.tpv_style || 'pharmacy'
    enableFlashCheckout.value = !!settings.enable_flash_checkout
    enableQuotations.value = settings.enable_quotations !== undefined ? settings.enable_quotations : true
    enableReservations.value = settings.enable_reservations !== undefined ? settings.enable_reservations : true
    quotationStyle.value = settings.quotation_style || 'pharmacy'
    tpvRateType.value = settings.tpv_rate_type || 'bcv'
    defaultCurrency.value = settings.default_currency || 'USD'
    roundUsdUp.value = !!settings.round_usd_up

    if (settings.tpv_payment_methods) {
      tpvPaymentMethods.value = settings.tpv_payment_methods
    }

    if (settings.enabled_offer_types) {
      enabledOfferTypes.value = settings.enabled_offer_types
    }
  } catch (error) {
    console.error("Error cargando configuración del TPV:", error)
    hasError.value = true
    errorMessage.value = "No se pudo obtener la configuración del TPV. Por favor, reintente."
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

// Alternar tipos de ofertas
const toggleOfferType = (key) => {
  if (isSaving.value || isLoading.value) return
  const index = enabledOfferTypes.value.indexOf(key)
  if (index > -1) {
    enabledOfferTypes.value.splice(index, 1)
  } else {
    enabledOfferTypes.value.push(key)
  }
  updateSettings()
}

// Persistir la configuración en el servidor
const updateSettings = async () => {
  if (isSaving.value || isLoading.value) return
  isSaving.value = true

  try {
    const updatedData = {
      ...rawSettings.value,
      default_currency: defaultCurrency.value,
      tpv_mode: tpvModeComplete.value ? 'complete' : 'simple',
      tpv_style: tpvStyle.value,
      enable_flash_checkout: enableFlashCheckout.value,
      enable_quotations: enableQuotations.value,
      enable_reservations: enableReservations.value,
      quotation_style: quotationStyle.value,
      tpv_rate_type: tpvRateType.value,
      round_usd_up: roundUsdUp.value,
      tpv_payment_methods: tpvPaymentMethods.value,
      enabled_offer_types: enabledOfferTypes.value
    }

    const response = await axios.post('/general-settings', updatedData)
    if (response.data && response.data.data) {
      rawSettings.value = response.data.data
    }

    brandingStore.settings = {
      ...brandingStore.settings,
      default_currency: defaultCurrency.value,
      tpv_mode: tpvModeComplete.value ? 'complete' : 'simple',
      tpv_style: tpvStyle.value,
      enable_flash_checkout: enableFlashCheckout.value,
      enable_quotations: enableQuotations.value,
      enable_reservations: enableReservations.value,
      quotation_style: quotationStyle.value,
      tpv_rate_type: tpvRateType.value,
      round_usd_up: roundUsdUp.value,
      enabled_offer_types: enabledOfferTypes.value
    }
    brandingStore.updatePaymentMethods(tpvPaymentMethods.value)

    toast.success("Configuración del TPV actualizada exitosamente")
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
        <VBtn color="error" variant="text" size="small" @click="fetchSettings">
          Reintentar
        </VBtn>
      </template>
    </VAlert>

    <!-- Skeletons durante Carga Inicial -->
    <div v-if="isLoading" class="d-flex flex-column gap-6">
      <VCard class="mb-6">
        <VCardText class="py-10">
          <VSkeletonLoader type="article, grid" />
        </VCardText>
      </VCard>
      <VCard class="mb-6">
        <VCardText class="py-10">
          <VSkeletonLoader type="article, grid" />
        </VCardText>
      </VCard>
    </div>

    <!-- Contenido Principal Desacoplado -->
    <div v-else-if="!hasError">
      <!-- Configuración General del TPV -->
      <TpvGeneralSettingsCard
        v-model:tpv-mode-complete="tpvModeComplete"
        v-model:tpv-style="tpvStyle"
        v-model:enable-flash-checkout="enableFlashCheckout"
        v-model:tpv-rate-type="tpvRateType"
        v-model:default-currency="defaultCurrency"
        v-model:enable-quotations="enableQuotations"
        v-model:enable-reservations="enableReservations"
        v-model:quotation-style="quotationStyle"
        v-model:round-usd-up="roundUsdUp"
        :is-saving="isSaving"
        @change="updateSettings"
      />

      <!-- Métodos de Pago y Monedas -->
      <TpvPaymentMethodsCard
        :payment-methods="tpvPaymentMethods"
        :is-saving="isSaving"
        @change="updateSettings"
      />

      <!-- Tipos de Ofertas y Promociones -->
      <TpvPromotionsCard
        :enabled-offer-types="enabledOfferTypes"
        :is-saving="isSaving"
        @toggle="toggleOfferType"
      />
    </div>
  </div>
</template>
