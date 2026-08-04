<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore();
const tpvModeComplete = ref(true)
const tpvStyle = ref('pharmacy')
const enableFlashCheckout = ref(false)
const enableQuotations = ref(true)
const enableReservations = ref(true)
const quotationStyle = ref('pharmacy')
const tpvRateType = ref('bcv')
const defaultCurrency = ref('USD')
const roundUsdUp = ref(false)

// Variables para control de estados (UI/UX y Rendimiento)
const isLoading = ref(true)
const isSaving = ref(false)
const rawSettings = ref({}) // Evita la doble petición GET al guardar

// Estructura reactiva para almacenar metodos de pago por moneda
const tpvPaymentMethods = ref({
  COP: [],
  USD: [],
  BS: []
})

const enabledOfferTypes = ref([
  'general',
  'individual',
  'category',
  'pack',
  'company',
  'doctor',
  'prescription',
  'expiration'
])

const availableOfferOptions = [
  { key: 'general', title: 'Oferta General (% Descuento Global)', icon: 'tabler-percentage' },
  { key: 'individual', title: 'Oferta Individual (por Producto)', icon: 'tabler-package' },
  { key: 'category', title: 'Oferta por Categoría', icon: 'tabler-category' },
  { key: 'pack', title: 'Oferta de Combos / Packs', icon: 'tabler-packages' },
  { key: 'company', title: 'Oferta por Convenio', icon: 'tabler-building' },
  { key: 'doctor', title: 'Oferta por Médico', icon: 'tabler-stethoscope' },
  { key: 'prescription', title: 'Oferta por Receta / Récipe', icon: 'tabler-file-text' },
  { key: 'expiration', title: 'Oferta por Caducidad', icon: 'tabler-calendar-time' },
]

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

const fetchSettings = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    rawSettings.value = settings // Guardar estado inicial para evitar peticiones GET redundantes
    
    tpvModeComplete.value = settings.tpv_mode !== 'simple'
    tpvStyle.value = settings.tpv_style || 'pharmacy'
    enableFlashCheckout.value = !!settings.enable_flash_checkout
    enableQuotations.value = settings.enable_quotations !== undefined ? settings.enable_quotations : true
    enableReservations.value = settings.enable_reservations !== undefined ? settings.enable_reservations : true
    quotationStyle.value = settings.quotation_style || 'pharmacy'
    tpvRateType.value = settings.tpv_rate_type || 'bcv'
    defaultCurrency.value = settings.default_currency || 'USD'
    roundUsdUp.value = !!settings.round_usd_up

    // Cargar metodos de pago mapeados del backend
    if (settings.tpv_payment_methods) {
      tpvPaymentMethods.value = settings.tpv_payment_methods
    }

    if (settings.enabled_offer_types) {
      enabledOfferTypes.value = settings.enabled_offer_types
    }
  } catch (error) {
    console.error("Error cargando configuración del TPV:", error)
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

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
    <!-- Barra de progreso superior durante el guardado -->
    <VProgressLinear
      v-if="isSaving"
      indeterminate
      color="primary"
      class="position-absolute"
      style="top: 0; left: 0; right: 0; z-index: 99;"
      height="4"
    />

    <!-- Skeletons durante la carga inicial -->
    <div v-if="isLoading" class="d-flex flex-column gap-6">
      <VCard class="mb-6">
        <VCardText class="py-10">
          <VSkeletonLoader type="heading, paragraph" />
        </VCardText>
      </VCard>
      <VCard class="mb-6">
        <VCardText class="py-10">
          <VSkeletonLoader type="heading, text, grid" />
        </VCardText>
      </VCard>
      <VCard class="mb-6">
        <VCardText class="py-10">
          <VSkeletonLoader type="heading, grid" />
        </VCardText>
      </VCard>
    </div>

    <!-- Contenido Principal -->
    <div v-else :class="{ 'opacity-60 pointer-events-none': isSaving }" class="transition-all duration-200">
      <VCard class="mb-6">
        <VCardItem class="py-5">
          <VCardTitle class="text-h6 mb-6"> Configuración del Punto de Venta (TPV) </VCardTitle>
          
          <VRow>
            <!-- Columna 1: Modalidad del TPV -->
            <VCol cols="12" md="3" class="d-flex flex-column justify-space-between mb-6" style="min-block-size: 150px;">
              <div>
                <div class="font-weight-bold text-subtitle-2 mb-1">Modalidad del TPV</div>
                <div class="text-caption text-medium-emphasis mb-3 leading-tight" style="min-block-size: 40px;">
                  Define si el TPV opera en modo completo (requiere cliente y lotes) o modo simple (carga rápida y directa).
                </div>
              </div>
              <div>
                <VSwitch
                  v-model="tpvModeComplete"
                  label="Habilitar Modo Completo"
                  color="primary"
                  inset
                  :disabled="isSaving"
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Columna 2: Estilo de TPV -->
            <VCol cols="12" md="3" class="d-flex flex-column justify-space-between mb-6" style="min-block-size: 150px;">
              <div>
                <div class="font-weight-bold text-subtitle-2 mb-1">Estilo de TPV</div>
                <div class="text-caption text-medium-emphasis mb-3 leading-tight" style="min-block-size: 40px;">
                  Elige el flujo e interfaz principal del TPV (Farmacia, Restaurante o Alquileres Deportivos).
                </div>
              </div>
              <div class="pt-2">
                <VSelect
                  v-model="tpvStyle"
                  :items="[
                    { title: 'Farmacia', value: 'pharmacy' },
                    { title: 'Restaurante / Minimarket', value: 'restaurant' },
                    { title: 'Alquiler Deportivo', value: 'sports_rental' }
                  ]"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Columna 3: Cobro Rápido (Flash Checkout) -->
            <VCol cols="12" md="3" class="d-flex flex-column justify-space-between mb-6" style="min-block-size: 150px;">
              <div>
                <div class="font-weight-bold text-subtitle-2 mb-1">Cobro Rápido (Efectivo)</div>
                <div class="text-caption text-medium-emphasis mb-3 leading-tight" style="min-block-size: 40px;">
                  Habilita un botón para procesar la orden inmediatamente en efectivo de la moneda seleccionada.
                </div>
              </div>
              <div>
                <VSwitch
                  v-model="enableFlashCheckout"
                  label="Habilitar Cobro Rápido"
                  color="primary"
                  inset
                  :disabled="isSaving"
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Columna 4: Tasa de Cambio TPV a Bs -->
            <VCol cols="12" md="3" class="d-flex flex-column justify-space-between mb-6" style="min-block-size: 150px;">
              <div>
                <div class="font-weight-bold text-subtitle-2 mb-1">Tasa de Cambio a Bs</div>
                <div class="text-caption text-medium-emphasis mb-3 leading-tight" style="min-block-size: 40px;">
                  Selecciona la tasa de la BD para la conversión a Bolívares en el TPV (BCV, EUR o BINANCE).
                </div>
              </div>
              <div class="pt-2">
                <VSelect
                  v-model="tpvRateType"
                  :items="[
                    { title: 'Tasa BCV', value: 'bcv' },
                    { title: 'Tasa EUR', value: 'eur' },
                    { title: 'Tasa BINANCE', value: 'binance' }
                  ]"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Columna 5: Moneda por Defecto (TPV / Cotizaciones) -->
            <VCol cols="12" md="3" class="d-flex flex-column justify-space-between mb-6" style="min-block-size: 150px;">
              <div>
                <div class="font-weight-bold text-subtitle-2 mb-1">Moneda por Defecto</div>
                <div class="text-caption text-medium-emphasis mb-3 leading-tight" style="min-block-size: 40px;">
                  Moneda inicial con la que se crearán las órdenes en TPV y Cotizaciones (BS, USD o COP).
                </div>
              </div>
              <div class="pt-2">
                <VSelect
                  v-model="defaultCurrency"
                  :items="[
                    { title: 'Bolívares (BS)', value: 'BS' },
                    { title: 'Dólares (USD)', value: 'USD' },
                    { title: 'Pesos Colombianos (COP)', value: 'COP' }
                  ]"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Columna 5: Módulo de Cotizaciones -->
            <VCol cols="12" md="3" class="d-flex flex-column justify-space-between mb-6" style="min-block-size: 150px;">
              <div>
                <div class="font-weight-bold text-subtitle-2 mb-1">Módulo de Cotizaciones</div>
                <div class="text-caption text-medium-emphasis mb-3 leading-tight" style="min-block-size: 40px;">
                  Muestra u oculta la opción "Cotización" en el menú de navegación lateral del sistema.
                </div>
              </div>
              <div>
                <VSwitch
                  v-model="enableQuotations"
                  label="Habilitar Cotizaciones"
                  color="primary"
                  inset
                  :disabled="isSaving"
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Columna 6: Módulo de Reservas (Alquileres Deportivos / Canchas) -->
            <VCol cols="12" md="3" class="d-flex flex-column justify-space-between mb-6" style="min-block-size: 150px;">
              <div>
                <div class="font-weight-bold text-subtitle-2 mb-1">Módulo de Reservas</div>
                <div class="text-caption text-medium-emphasis mb-3 leading-tight" style="min-block-size: 40px;">
                  Muestra u oculta la opción "Reservas" en el menú de navegación lateral del sistema.
                </div>
              </div>
              <div>
                <VSwitch
                  v-model="enableReservations"
                  label="Habilitar Reservas"
                  color="primary"
                  inset
                  :disabled="isSaving"
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>

            <!-- Columna 6: Estilo de Cotización -->
            <VCol cols="12" md="3" class="d-flex flex-column justify-space-between mb-6" style="min-block-size: 150px;">
              <div>
                <div class="font-weight-bold text-subtitle-2 mb-1">Estilo de Cotización</div>
                <div class="text-caption text-medium-emphasis mb-3 leading-tight" style="min-block-size: 40px;">
                  Determina el estilo visual (Farmacia o Restaurante) que usará el catálogo e impresión de cotizaciones.
                </div>
              </div>
              <div v-if="enableQuotations" class="pt-2">
                <VSelect
                  v-model="quotationStyle"
                  :items="[
                    { title: 'Estilo Farmacia', value: 'pharmacy' },
                    { title: 'Estilo Restaurante', value: 'restaurant' },
                    { title: 'Estilo Cosmético', value: 'cosmetic' }
                  ]"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="updateSettings"
                />
              </div>
              <div v-else class="text-caption text-disabled pt-4">
                Módulo desactivado
              </div>
            </VCol>

            <!-- Columna 7: Redondeo USD (Entero Mayor) -->
            <VCol cols="12" md="3" class="d-flex flex-column justify-space-between mb-6" style="min-block-size: 150px;">
              <div>
                <div class="font-weight-bold text-subtitle-2 mb-1">Redondeo en Venta USD</div>
                <div class="text-caption text-medium-emphasis mb-3 leading-tight" style="min-block-size: 40px;">
                  Al calcular y guardar la rentabilidad, redondea el precio de venta en USD al número entero mayor.
                </div>
              </div>
              <div>
                <VSwitch
                  v-model="roundUsdUp"
                  label="Habilitar Redondeo USD"
                  color="primary"
                  inset
                  :disabled="isSaving"
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>
          </VRow>
        </VCardItem>
      </VCard>

      <!-- Nueva Sección: Métodos de Pago por Moneda -->
      <VCard class="mb-6">
        <VCardItem class="py-5">
          <VCardTitle class="text-h6 mb-2"> Monedas y Métodos de Pago Habilitados </VCardTitle>
          <p class="text-caption text-medium-emphasis mb-6">
            Define qué métodos de pago estarán activos en el Punto de Venta según la moneda seleccionada de cobro.
          </p>

          <VRow>
            <VCol v-for="(currencyData, currency) in tpvPaymentMethods" :key="currency" cols="12" md="4">
              <VCard variant="outlined" class="rounded-lg transition-all duration-200" :class="{ 'border-primary border-opacity-60': currencyData.enabled }" :style="{ opacity: currencyData.enabled ? 1 : 0.6 }">
                <VCardItem class="bg-var-theme-background py-3">
                  <div class="d-flex align-center justify-space-between w-100">
                    <VCardTitle class="text-subtitle-1 font-weight-black d-flex align-center">
                      <VIcon icon="tabler-coin" class="me-2 text-primary" size="20" />
                      Cobros en {{ currency }}
                    </VCardTitle>
                    <VSwitch
                      v-model="currencyData.enabled"
                      density="compact"
                      hide-details
                      color="primary"
                      :disabled="isSaving"
                      @update:model-value="updateSettings"
                    />
                  </div>
                </VCardItem>
                <VDivider />
                <VCardText class="py-3">
                  <div v-if="!currencyData.enabled" class="text-caption text-disabled py-6 text-center">
                    Moneda Desactivada
                  </div>
                  <div v-else-if="!currencyData.methods || currencyData.methods.length === 0" class="text-caption text-disabled py-4 text-center">
                    Sin métodos de pago configurados
                  </div>
                  <div v-else>
                    <div
                      v-for="(method, index) in currencyData.methods"
                      :key="index"
                      class="py-2 border-bottom-dashed"
                    >
                      <!-- Fila: nombre + botón de editar + switch habilitado -->
                      <div class="d-flex align-center justify-space-between mb-1">
                        <div class="d-flex align-center">
                          <span class="font-weight-bold text-body-2 me-1">{{ method.label }}</span>
                          <VBtn
                            icon="tabler-pencil"
                            variant="text"
                            size="x-small"
                            color="primary"
                            title="Editar descripción e-commerce"
                            :disabled="!currencyData.enabled || !method.enabled || isSaving"
                            @click="method.showDescription = !method.showDescription"
                          />
                        </div>
                        <VSwitch
                          v-model="method.enabled"
                          density="compact"
                          hide-details
                          color="success"
                          :disabled="!currencyData.enabled || isSaving"
                          @update:model-value="updateSettings"
                        />
                      </div>
                      <!-- Descripción editable colapsable con botón de guardar manual -->
                      <div v-if="method.showDescription" class="mt-2">
                        <div class="d-flex align-center gap-2">
                          <VTextarea
                            v-model="method.description"
                            :placeholder="`Instrucciones para pago con ${method.label}...`"
                            density="compact"
                            variant="outlined"
                            rows="2"
                            auto-grow
                            hide-details
                            :disabled="!currencyData.enabled || !method.enabled || isSaving"
                            class="text-caption flex-grow-1"
                            style="font-size: 11px;"
                          />
                          <VBtn
                            icon="tabler-device-floppy"
                            color="success"
                            size="small"
                            variant="flat"
                            class="rounded-lg flex-shrink-0"
                            title="Guardar instrucciones de pago"
                            :disabled="!currencyData.enabled || !method.enabled || isSaving"
                            @click="updateSettings"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VCardItem>
      </VCard>

      <!-- Sección: Tipos de Ofertas y Promociones Habilitadas -->
      <VCard class="mb-6">
        <VCardItem class="py-5">
          <VCardTitle class="text-h6 mb-2"> Tipos de Ofertas y Promociones Habilitadas </VCardTitle>
          <p class="text-caption text-medium-emphasis mb-6">
            Selecciona las promociones que estarán activas en el sistema. Las desmarcadas no aparecerán en el menú de navegación ni en el Punto de Venta (TPV).
          </p>

          <VRow>
            <VCol v-for="offer in availableOfferOptions" :key="offer.key" cols="12" sm="6" md="3">
              <VCard
                variant="outlined"
                class="rounded-lg cursor-pointer transition-all duration-200"
                :class="enabledOfferTypes.includes(offer.key) ? 'border-primary bg-var-theme-background border-opacity-60 shadow-sm' : 'opacity-60'"
                :style="{ pointerEvents: isSaving ? 'none' : 'auto' }"
                @click="toggleOfferType(offer.key)"
              >
                <VCardItem class="py-3 px-4">
                  <div class="d-flex align-center justify-space-between w-100">
                    <div class="d-flex align-center me-2">
                      <VIcon :icon="offer.icon" class="me-2" :color="enabledOfferTypes.includes(offer.key) ? 'primary' : 'disabled'" size="20" />
                      <span class="font-weight-bold text-caption leading-tight" :class="enabledOfferTypes.includes(offer.key) ? 'text-high-emphasis' : 'text-disabled'">
                        {{ offer.title }}
                      </span>
                    </div>
                    <VSwitch
                      :model-value="enabledOfferTypes.includes(offer.key)"
                      density="compact"
                      hide-details
                      color="primary"
                      :disabled="isSaving"
                      @click.stop
                      @update:model-value="toggleOfferType(offer.key)"
                    />
                  </div>
                </VCardItem>
              </VCard>
            </VCol>
          </VRow>
        </VCardItem>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.border-bottom-dashed:not(:last-child) {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.12);
}
.pointer-events-none {
  pointer-events: none;
}
.transition-all {
  transition: all 0.2s ease-in-out;
}
</style>

