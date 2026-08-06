<script setup>
import { computed } from 'vue'

const props = defineProps({
  tpvModeComplete: Boolean,
  tpvStyle: String,
  enableFlashCheckout: Boolean,
  tpvRateType: String,
  defaultCurrency: String,
  enableQuotations: Boolean,
  enableReservations: Boolean,
  quotationStyle: String,
  roundUsdUp: Boolean,
  isSaving: Boolean
})

const emit = defineEmits([
  'update:tpvModeComplete',
  'update:tpvStyle',
  'update:enableFlashCheckout',
  'update:tpvRateType',
  'update:defaultCurrency',
  'update:enableQuotations',
  'update:enableReservations',
  'update:quotationStyle',
  'update:roundUsdUp',
  'change'
])

const updateField = (event, fieldName) => {
  emit(`update:${fieldName}`, event)
  emit('change')
}

const getStyleTitle = (val) => {
  if (val === 'restaurant') return 'Restaurante / Minimarket'
  if (val === 'sports_rental') return 'Alquiler Deportivo'
  return 'Farmacia'
}

const getRateTitle = (val) => {
  if (val === 'eur') return 'Tasa EUR'
  if (val === 'binance') return 'Tasa BINANCE'
  return 'Tasa BCV'
}
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="py-5">
      <!-- Encabezado Principal Estandarizado -->
      <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
        <VIcon icon="tabler-cash-register" color="primary" size="28" />
        Configuración General del TPV
      </VCardTitle>
      <p class="text-caption text-medium-emphasis mb-6">
        Personaliza la modalidad operativa, estilo de interfaz, tasas de cambio y parámetros de venta rápida.
      </p>

      <VDivider class="mb-6" />

      <VRow>
        <!-- Modalidad del TPV -->
        <VCol cols="12" md="4" sm="6">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between tpv-setting-card"
            :class="tpvModeComplete ? 'is-active border-primary' : 'border-color-light opacity-90'"
          >
            <div>
              <div class="d-flex align-center justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :color="tpvModeComplete ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="36"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-arrows-right-left" size="18" />
                  </VAvatar>
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Modalidad del TPV</h3>
                    <VChip
                      :color="tpvModeComplete ? 'primary' : 'grey-darken-1'"
                      size="x-small"
                      variant="flat"
                      class="mt-1 font-weight-bold text-white"
                    >
                      {{ tpvModeComplete ? 'Modo Completo' : 'Modo Simple' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  :model-value="tpvModeComplete"
                  color="primary"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="(val) => updateField(val, 'tpvModeComplete')"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-0 leading-tight">
                Modo completo (requiere cliente y lotes) o modo simple (cobro directo).
              </p>
            </div>
          </VCard>
        </VCol>

        <!-- Estilo de TPV -->
        <VCol cols="12" md="4" sm="6">
          <VCard variant="outlined" class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between tpv-setting-card is-active border-primary">
            <div>
              <div class="d-flex align-center justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar color="primary" variant="tonal" size="36" class="rounded-lg">
                    <VIcon icon="tabler-layout-grid" size="18" />
                  </VAvatar>
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Estilo de TPV</h3>
                    <VChip color="primary" size="x-small" variant="flat" class="mt-1 font-weight-bold text-white">
                      {{ getStyleTitle(tpvStyle) }}
                    </VChip>
                  </div>
                </div>
              </div>
              <p class="text-caption text-medium-emphasis mb-3 leading-tight">
                Elige la interfaz principal (Farmacia, Restaurante o Deportes).
              </p>
            </div>
            <VSelect
              :model-value="tpvStyle"
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
              class="custom-tpv-select"
              :disabled="isSaving"
              @update:model-value="(val) => updateField(val, 'tpvStyle')"
            />
          </VCard>
        </VCol>

        <!-- Cobro Rápido (Flash Checkout) -->
        <VCol cols="12" md="4" sm="6">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between tpv-setting-card"
            :class="enableFlashCheckout ? 'is-active border-primary' : 'border-color-light opacity-90'"
          >
            <div>
              <div class="d-flex align-center justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :color="enableFlashCheckout ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="36"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-bolt" size="18" />
                  </VAvatar>
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Cobro Rápido</h3>
                    <VChip
                      :color="enableFlashCheckout ? 'success' : 'grey-darken-1'"
                      size="x-small"
                      variant="flat"
                      class="mt-1 font-weight-bold text-white"
                    >
                      {{ enableFlashCheckout ? 'Habilitado' : 'Deshabilitado' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  :model-value="enableFlashCheckout"
                  color="primary"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="(val) => updateField(val, 'enableFlashCheckout')"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-0 leading-tight">
                Procesamiento instantáneo de órdenes en efectivo.
              </p>
            </div>
          </VCard>
        </VCol>

        <!-- Tasa de Cambio TPV a Bs -->
        <VCol cols="12" md="4" sm="6">
          <VCard variant="outlined" class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between tpv-setting-card is-active border-primary">
            <div>
              <div class="d-flex align-center justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar color="primary" variant="tonal" size="36" class="rounded-lg">
                    <VIcon icon="tabler-currency-dollar-singapore" size="18" />
                  </VAvatar>
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Tasa de Cambio</h3>
                    <VChip color="primary" size="x-small" variant="flat" class="mt-1 font-weight-bold text-white">
                      {{ getRateTitle(tpvRateType) }}
                    </VChip>
                  </div>
                </div>
              </div>
              <p class="text-caption text-medium-emphasis mb-3 leading-tight">
                Selecciona la tasa de BD para conversión a Bolívares.
              </p>
            </div>
            <VSelect
              :model-value="tpvRateType"
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
              class="custom-tpv-select"
              :disabled="isSaving"
              @update:model-value="(val) => updateField(val, 'tpvRateType')"
            />
          </VCard>
        </VCol>

        <!-- Moneda por Defecto -->
        <VCol cols="12" md="4" sm="6">
          <VCard variant="outlined" class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between tpv-setting-card is-active border-primary">
            <div>
              <div class="d-flex align-center justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar color="primary" variant="tonal" size="36" class="rounded-lg">
                    <VIcon icon="tabler-coins" size="18" />
                  </VAvatar>
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Moneda Inicial</h3>
                    <VChip color="primary" size="x-small" variant="flat" class="mt-1 font-weight-bold text-white">
                      {{ defaultCurrency }}
                    </VChip>
                  </div>
                </div>
              </div>
              <p class="text-caption text-medium-emphasis mb-3 leading-tight">
                Moneda inicial por defecto con la que iniciará la venta.
              </p>
            </div>
            <VSelect
              :model-value="defaultCurrency"
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
              class="custom-tpv-select"
              :disabled="isSaving"
              @update:model-value="(val) => updateField(val, 'defaultCurrency')"
            />
          </VCard>
        </VCol>

        <!-- Redondeo USD -->
        <VCol cols="12" md="4" sm="6">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between tpv-setting-card"
            :class="roundUsdUp ? 'is-active border-primary' : 'border-color-light opacity-90'"
          >
            <div>
              <div class="d-flex align-center justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :color="roundUsdUp ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="36"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-math-symbols" size="18" />
                  </VAvatar>
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Redondeo USD</h3>
                    <VChip
                      :color="roundUsdUp ? 'success' : 'grey-darken-1'"
                      size="x-small"
                      variant="flat"
                      class="mt-1 font-weight-bold text-white"
                    >
                      {{ roundUsdUp ? 'Entero Mayor' : 'Exacto' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  :model-value="roundUsdUp"
                  color="primary"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="(val) => updateField(val, 'roundUsdUp')"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-0 leading-tight">
                Redondea el precio final en USD al número entero mayor.
              </p>
            </div>
          </VCard>
        </VCol>

        <!-- Cotizaciones -->
        <VCol cols="12" md="6" sm="6">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between tpv-setting-card"
            :class="enableQuotations ? 'is-active border-primary' : 'border-color-light opacity-90'"
          >
            <div>
              <div class="d-flex align-center justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :color="enableQuotations ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="36"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-receipt" size="18" />
                  </VAvatar>
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Cotizaciones</h3>
                    <VChip
                      :color="enableQuotations ? 'success' : 'grey-darken-1'"
                      size="x-small"
                      variant="flat"
                      class="mt-1 font-weight-bold text-white"
                    >
                      {{ enableQuotations ? 'Habilitado' : 'Deshabilitado' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  :model-value="enableQuotations"
                  color="primary"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="(val) => updateField(val, 'enableQuotations')"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-3 leading-tight">
                Impresión y catálogo de presupuestos o cotizaciones.
              </p>
            </div>
            <VSelect
              v-if="enableQuotations"
              :model-value="quotationStyle"
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
              class="custom-tpv-select"
              :disabled="isSaving"
              @update:model-value="(val) => updateField(val, 'quotationStyle')"
            />
          </VCard>
        </VCol>

        <!-- Módulo de Reservas -->
        <VCol cols="12" md="6" sm="6">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between tpv-setting-card"
            :class="enableReservations ? 'is-active border-primary' : 'border-color-light opacity-90'"
          >
            <div>
              <div class="d-flex align-center justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :color="enableReservations ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="36"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-calendar-event" size="18" />
                  </VAvatar>
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Módulo de Reservas</h3>
                    <VChip
                      :color="enableReservations ? 'success' : 'grey-darken-1'"
                      size="x-small"
                      variant="flat"
                      class="mt-1 font-weight-bold text-white"
                    >
                      {{ enableReservations ? 'Habilitado' : 'Deshabilitado' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  :model-value="enableReservations"
                  color="primary"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="(val) => updateField(val, 'enableReservations')"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-0 leading-tight">
                Gestión de reservas para canchas, instalaciones y servicios.
              </p>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.tpv-setting-card {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  border-width: 1.5px !important;
}

.tpv-setting-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px -4px rgba(var(--v-theme-primary), 0.15) !important;
}

.tpv-setting-card.is-active {
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}

.border-color-light {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}

:deep(.custom-tpv-select.v-input) {
  margin-block-start: 4px;
}

:deep(.custom-tpv-select .v-field) {
  min-block-size: 40px !important;
  block-size: 40px !important;
  border-radius: 8px !important;
  display: flex !important;
  align-items: center !important;
}

:deep(.custom-tpv-select .v-field__field) {
  block-size: 40px !important;
  display: flex !important;
  align-items: center !important;
}

:deep(.custom-tpv-select .v-field__input) {
  min-block-size: 40px !important;
  block-size: 40px !important;
  padding-block: 0 !important;
  display: flex !important;
  align-items: center !important;
  font-size: 0.875rem !important;
}

:deep(.custom-tpv-select .v-select__selection) {
  margin-block: 0 !important;
  display: flex !important;
  align-items: center !important;
}

:deep(.custom-tpv-select .v-field__append-inner) {
  align-items: center !important;
  padding-block-start: 0 !important;
  block-size: 40px !important;
}
</style>
