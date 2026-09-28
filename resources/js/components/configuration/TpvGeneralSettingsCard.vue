<script setup>
import { computed } from 'vue'

const props = defineProps({
  tpvMode: {
    type: String,
    default: 'complete'
  },
  tpvStyle: {
    type: String,
    default: 'pharmacy'
  },
  enableFlashCheckout: {
    type: Boolean,
    default: false
  },
  tpvRateType: {
    type: String,
    default: 'bcv'
  },
  defaultCurrency: {
    type: String,
    default: 'USD'
  },
  enableQuotations: {
    type: Boolean,
    default: true
  },
  quotationStyle: {
    type: String,
    default: 'pharmacy'
  },
  roundUsdUp: {
    type: Boolean,
    default: false
  },
  canEdit: {
    type: Boolean,
    default: true
  },
  isSaving: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits([
  'update:tpvMode',
  'update:tpvStyle',
  'update:enableFlashCheckout',
  'update:tpvRateType',
  'update:defaultCurrency',
  'update:enableQuotations',
  'update:quotationStyle',
  'update:roundUsdUp'
])

const isCompleteMode = computed({
  get: () => props.tpvMode === 'complete',
  set: (val) => emit('update:tpvMode', val ? 'complete' : 'simple')
})

const currentStyle = computed({
  get: () => props.tpvStyle,
  set: (val) => emit('update:tpvStyle', val)
})

const currentRateType = computed({
  get: () => props.tpvRateType,
  set: (val) => emit('update:tpvRateType', val)
})

const currentCurrency = computed({
  get: () => props.defaultCurrency,
  set: (val) => emit('update:defaultCurrency', val)
})

const currentQuotationStyle = computed({
  get: () => props.quotationStyle,
  set: (val) => emit('update:quotationStyle', val)
})

const isFlashCheckout = computed({
  get: () => props.enableFlashCheckout,
  set: (val) => emit('update:enableFlashCheckout', val)
})

const isQuotations = computed({
  get: () => props.enableQuotations,
  set: (val) => emit('update:enableQuotations', val)
})

const isRoundUsdUp = computed({
  get: () => props.roundUsdUp,
  set: (val) => emit('update:roundUsdUp', val)
})

const styleOptions = [
  { title: 'Farmacia (Catálogo y Lotes)', value: 'pharmacy' },
  { title: 'Restaurante / Minimarket', value: 'restaurant' },
  { title: 'Alquiler Deportivo', value: 'sports_rental' }
]

const rateOptions = [
  { title: 'Tasa BCV Oficial', value: 'bcv' },
  { title: 'Tasa EUR', value: 'eur' },
  { title: 'Tasa Binance P2P', value: 'binance' }
]

const currencyOptions = [
  { title: 'Dólares (USD)', value: 'USD' },
  { title: 'Bolívares (BS)', value: 'BS' },
  { title: 'Pesos Colombianos (COP)', value: 'COP' }
]

const quotationStyleOptions = [
  { title: 'Estilo Farmacéutico', value: 'pharmacy' },
  { title: 'Estilo Restaurante / Carta', value: 'restaurant' },
  { title: 'Estilo Estético / Cosmético', value: 'cosmetic' }
]
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="px-6 py-5">
      <!-- Encabezado Principal Estandarizado -->
      <div class="d-flex align-center gap-3 mb-2">
        <VAvatar color="primary" variant="tonal" size="36" class="rounded-lg">
          <VIcon icon="tabler-cash-register" size="22" />
        </VAvatar>
        <div>
          <VCardTitle class="text-h6 font-weight-bold mb-0">
            Parámetros Operativos del TPV
          </VCardTitle>
          <VCardSubtitle class="text-body-2 text-medium-emphasis">
            Define la interfaz visual, el tipo de flujo de caja y los criterios de conversión monetaria.
          </VCardSubtitle>
        </div>
      </div>

      <VDivider class="my-4" />

      <VRow>
        <!-- Modalidad del TPV -->
        <VCol cols="12" sm="6" md="4">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between"
            :class="isCompleteMode ? 'border-primary bg-var-theme-background' : 'opacity-90'"
          >
            <div>
              <div class="d-flex align-center justify-space-between mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :color="isCompleteMode ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="36"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-arrows-right-left" size="20" />
                  </VAvatar>
                  <div>
                    <span class="text-subtitle-2 font-weight-bold d-block">Modalidad TPV</span>
                    <VChip
                      :color="isCompleteMode ? 'primary' : 'secondary'"
                      size="x-small"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ isCompleteMode ? 'Modo Completo' : 'Modo Simple' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  v-model="isCompleteMode"
                  color="primary"
                  density="comfortable"
                  hide-details="auto"
                  :disabled="!canEdit || isSaving"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                <strong>Modo completo:</strong> Exige selección de cliente y asignación de lotes.<br>
                <strong>Modo simple:</strong> Venta rápida directa en mostrador.
              </p>
            </div>
          </VCard>
        </VCol>

        <!-- Estilo Visual de TPV -->
        <VCol cols="12" sm="6" md="4">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between border-primary bg-var-theme-background"
          >
            <div>
              <div class="d-flex align-center gap-2 mb-3">
                <VAvatar color="primary" variant="tonal" size="36" class="rounded-lg">
                  <VIcon icon="tabler-layout-grid" size="20" />
                </VAvatar>
                <div>
                  <span class="text-subtitle-2 font-weight-bold d-block">Estilo de Interfaz</span>
                  <span class="text-caption text-medium-emphasis">Experiencia visual adaptada</span>
                </div>
              </div>
              <p class="text-caption text-medium-emphasis mb-3">
                Distribución de catálogo y paneles según el tipo de comercio.
              </p>
            </div>
            <VSelect
              v-model="currentStyle"
              :items="styleOptions"
              item-title="title"
              item-value="value"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              :disabled="!canEdit || isSaving"
            />
          </VCard>
        </VCol>

        <!-- Cobro Rápido (Flash Checkout) -->
        <VCol cols="12" sm="6" md="4">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between"
            :class="isFlashCheckout ? 'border-primary bg-var-theme-background' : 'opacity-90'"
          >
            <div>
              <div class="d-flex align-center justify-space-between mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :color="isFlashCheckout ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="36"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-bolt" size="20" />
                  </VAvatar>
                  <div>
                    <span class="text-subtitle-2 font-weight-bold d-block">Cobro Rápido (Flash)</span>
                    <VChip
                      :color="isFlashCheckout ? 'success' : 'secondary'"
                      size="x-small"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ isFlashCheckout ? 'Habilitado' : 'Deshabilitado' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  v-model="isFlashCheckout"
                  color="primary"
                  density="comfortable"
                  hide-details="auto"
                  :disabled="!canEdit || isSaving"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                Procesamiento instantáneo en un clic con cliente genérico para cobros en efectivo exacto.
              </p>
            </div>
          </VCard>
        </VCol>

        <!-- Tasa de Cambio TPV a Bs -->
        <VCol cols="12" sm="6" md="4">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between border-primary bg-var-theme-background"
          >
            <div>
              <div class="d-flex align-center gap-2 mb-3">
                <VAvatar color="primary" variant="tonal" size="36" class="rounded-lg">
                  <VIcon icon="tabler-currency-dollar-singapore" size="20" />
                </VAvatar>
                <div>
                  <span class="text-subtitle-2 font-weight-bold d-block">Tasa de Conversión</span>
                  <span class="text-caption text-medium-emphasis">Fuente de tasa a Bolívares</span>
                </div>
              </div>
              <p class="text-caption text-medium-emphasis mb-3">
                Referencia cambiaria para el cálculo del total en Bs al procesar la venta.
              </p>
            </div>
            <VSelect
              v-model="currentRateType"
              :items="rateOptions"
              item-title="title"
              item-value="value"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              :disabled="!canEdit || isSaving"
            />
          </VCard>
        </VCol>

        <!-- Moneda por Defecto -->
        <VCol cols="12" sm="6" md="4">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between border-primary bg-var-theme-background"
          >
            <div>
              <div class="d-flex align-center gap-2 mb-3">
                <VAvatar color="primary" variant="tonal" size="36" class="rounded-lg">
                  <VIcon icon="tabler-coins" size="20" />
                </VAvatar>
                <div>
                  <span class="text-subtitle-2 font-weight-bold d-block">Moneda Principal</span>
                  <span class="text-caption text-medium-emphasis">Moneda base en el terminal</span>
                </div>
              </div>
              <p class="text-caption text-medium-emphasis mb-3">
                Moneda inicial con la que se cargarán los montos de cobro por defecto.
              </p>
            </div>
            <VSelect
              v-model="currentCurrency"
              :items="currencyOptions"
              item-title="title"
              item-value="value"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              :disabled="!canEdit || isSaving"
            />
          </VCard>
        </VCol>

        <!-- Redondeo USD -->
        <VCol cols="12" sm="6" md="4">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between"
            :class="isRoundUsdUp ? 'border-primary bg-var-theme-background' : 'opacity-90'"
          >
            <div>
              <div class="d-flex align-center justify-space-between mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :color="isRoundUsdUp ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="36"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-math-symbols" size="20" />
                  </VAvatar>
                  <div>
                    <span class="text-subtitle-2 font-weight-bold d-block">Redondeo en USD</span>
                    <VChip
                      :color="isRoundUsdUp ? 'success' : 'secondary'"
                      size="x-small"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ isRoundUsdUp ? 'Entero Superior (Ceil)' : 'Valor Exacto' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  v-model="isRoundUsdUp"
                  color="primary"
                  density="comfortable"
                  hide-details="auto"
                  :disabled="!canEdit || isSaving"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                Ajusta el precio de los artículos en USD hacia el número entero superior para facilitar vuelto.
              </p>
            </div>
          </VCard>
        </VCol>

        <!-- Módulo de Cotizaciones -->
        <VCol cols="12">
          <VCard
            variant="outlined"
            class="rounded-lg pa-4"
            :class="isQuotations ? 'border-primary bg-var-theme-background' : 'opacity-90'"
          >
            <VRow align="center">
              <VCol cols="12" md="6">
                <div class="d-flex align-center justify-space-between">
                  <div class="d-flex align-center gap-2">
                    <VAvatar
                      :color="isQuotations ? 'primary' : 'secondary'"
                      variant="tonal"
                      size="36"
                      class="rounded-lg"
                    >
                      <VIcon icon="tabler-receipt" size="20" />
                    </VAvatar>
                    <div>
                      <span class="text-subtitle-2 font-weight-bold d-block">Cotizaciones y Presupuestos</span>
                      <VChip
                        :color="isQuotations ? 'success' : 'secondary'"
                        size="x-small"
                        variant="tonal"
                        class="font-weight-bold"
                      >
                        {{ isQuotations ? 'Habilitado' : 'Deshabilitado' }}
                      </VChip>
                    </div>
                  </div>
                  <VSwitch
                    v-model="isQuotations"
                    color="primary"
                    density="comfortable"
                    hide-details="auto"
                    :disabled="!canEdit || isSaving"
                  />
                </div>
                <p class="text-caption text-medium-emphasis mt-2 mb-0">
                  Permite a los cajeros generar e imprimir presupuestos con vencimiento sin descontar stock.
                </p>
              </VCol>

              <VCol v-if="isQuotations" cols="12" md="6">
                <label class="text-caption font-weight-bold text-medium-emphasis d-block mb-1">
                  Plantilla de Impresión para Cotizaciones
                </label>
                <VSelect
                  v-model="currentQuotationStyle"
                  :items="quotationStyleOptions"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  :disabled="!canEdit || isSaving"
                />
              </VCol>
            </VRow>
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>

