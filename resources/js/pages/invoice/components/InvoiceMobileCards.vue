<script setup>
// Componente de lista de tarjetas de productos para vista responsive móvil
const props = defineProps({
  processedInvoiceDetails: {
    type: Array,
    default: () => [],
  },
  loadingDetails: {
    type: Boolean,
    default: false,
  },
  isEditableMode: {
    type: Boolean,
    default: false,
  },
  isEditMode: {
    type: Boolean,
    default: false,
  },
  editingDetailId: {
    type: [Number, String, null],
    default: null,
  },
  editedDetailData: {
    type: Object,
    default: () => ({}),
  },
  invoice: {
    type: Object,
    required: true,
  },
  locations: {
    type: Array,
    default: () => [],
  },
  isLocationMode: {
    type: Boolean,
    default: false,
  },
  invoiceHasIva: {
    type: Boolean,
    default: false,
  },
  isNearExpiration: {
    type: Function,
    required: true,
  },
  isItemReturned: {
    type: Function,
    required: true,
  },
  formatCurrency: {
    type: Function,
    required: true,
  },
  getPriceVsAutoOrderIndicator: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits([
  'recalculateTotalFromUnit',
  'recalculateUnitFromTotal',
  'updateLocation',
  'saveEditingDetail',
  'cancelEditingDetail',
  'moveItemUp',
  'moveItemDown',
  'toggleReturnItem',
  'toggleTax',
  'startEditingDetail',
  'removeProductFromInvoice',
])
</script>

<template>
  <div class="mobile-products-list pa-2 pa-sm-4 bg-light-surface rounded-lg">
    <div v-if="loadingDetails" class="d-flex justify-center py-8">
      <VProgressCircular indeterminate color="primary" />
    </div>
    <div v-else-if="processedInvoiceDetails.length === 0" class="text-center py-8 text-disabled text-sm">
      No hay productos en esta factura.
    </div>
    <div v-else class="d-flex flex-column ga-3">
      <VCard
        v-for="(item, index) in processedInvoiceDetails"
        :key="item.id"
        class="mobile-detail-card border shadow-none rounded-lg overflow-hidden"
        :class="{
          'near-expiration-border': isNearExpiration(item),
          'returned-border': isItemReturned(item)
        }"
      >
        <VCardText class="pa-3">
          <!-- Encabezado Tarjeta -->
          <div class="d-flex justify-space-between align-start mb-2">
            <div class="d-flex flex-column ga-1" style="max-inline-size: 75%">
              <div class="d-flex align-center ga-1">
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase line-clamp-2" :class="{ 'returned-item': isItemReturned(item) }">
                  {{ item.product?.name || 'N/A' }}
                </span>
                <VChip v-if="item.tax_enabled" size="x-small" color="success" variant="flat" class="font-weight-black">IVA</VChip>
              </div>
              <span class="text-caption font-weight-bold text-disabled text-uppercase">{{ item.product?.laboratory?.name || 'Sin Laboratorio' }}</span>
            </div>

            <div class="d-flex flex-column align-end">
              <VTooltip v-if="isNearExpiration(item)" location="left">
                <template #activator="{ props: tipProps }">
                  <VIcon v-bind="tipProps" icon="tabler-alert-triangle-filled" color="warning" size="18" />
                </template>
                <span>Próximo a vencer</span>
              </VTooltip>
              <VIcon v-if="isItemReturned(item)" icon="tabler-arrow-back-up" color="warning" size="18" />
            </div>
          </div>

          <VDivider class="my-2 border-dashed" />

          <!-- Grid de Información Financiera -->
          <div class="grid-financial-info mb-4">
            <div class="detail-item">
              <span class="label">Cantidad</span>
              <VTextField
                v-if="isEditableMode && item.id === editingDetailId"
                v-model.number="editedDetailData.quantity"
                type="number"
                density="compact"
                hide-details
                variant="outlined"
                class="mt-1"
              />
              <span v-else class="value">{{ item.quantity }}</span>
            </div>
            <div class="detail-item">
              <span class="label">Costo (USD)</span>
              <div v-if="isEditableMode && item.id === editingDetailId" class="d-flex flex-column gap-1 mt-1">
                <VTextField
                  v-model.number="editedDetailData.unit_cost"
                  @input="emit('recalculateTotalFromUnit')"
                  type="number"
                  step="0.01"
                  density="compact"
                  hide-details
                  variant="outlined"
                  placeholder="Costo Unitario"
                  label="Unitario"
                />
                <VTextField
                  v-model.number="editedDetailData.total_cost_input"
                  @input="emit('recalculateUnitFromTotal')"
                  type="number"
                  step="0.01"
                  density="compact"
                  hide-details
                  variant="outlined"
                  placeholder="Costo Total"
                  label="Total"
                />
              </div>
              <div v-else class="d-flex flex-column align-start">
                <div class="d-flex align-center gap-1">
                  <span class="value font-weight-bold">{{ formatCurrency(item.unit_cost_usd, 'USD') }}</span>
                  <!-- Indicador precio vs autoorden activa -->
                  <VTooltip
                    v-if="getPriceVsAutoOrderIndicator(item)"
                    :text="getPriceVsAutoOrderIndicator(item).tooltip"
                    location="top"
                  >
                    <template #activator="{ props: tipProps }">
                      <VChip
                        v-bind="tipProps"
                        size="x-small"
                        :color="getPriceVsAutoOrderIndicator(item).color"
                        variant="tonal"
                        class="px-1 font-weight-bold"
                      >
                        <VIcon :icon="getPriceVsAutoOrderIndicator(item).icon" size="13" class="me-0.5" />
                        {{ getPriceVsAutoOrderIndicator(item).badgeText }}
                      </VChip>
                    </template>
                  </VTooltip>
                </div>
              </div>
            </div>
            <div class="detail-item">
              <span class="label">IVA</span>
              <span class="value">{{ formatCurrency(item.tax_amount, invoice.currency) }}</span>
            </div>
            <div class="detail-item">
              <span class="label">Total Item</span>
              <div class="d-flex flex-column align-start">
                <span class="value font-weight-black text-primary">{{ formatCurrency(item.total_cost_usd, 'USD') }}</span>
              </div>
            </div>
          </div>

          <!-- Información Logística -->
          <div class="bg-light pa-2 rounded-lg border-dashed mb-2">
            <div class="grid-logistics-info">
              <div class="detail-item">
                <span class="label">Lote</span>
                <VTextField
                  v-if="isEditableMode && item.id === editingDetailId"
                  v-model="editedDetailData.lot_number"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="mt-1"
                />
                <span v-else class="value text-xs">{{ item.lot_number || 'S/L' }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Vencimiento</span>
                <VTextField
                  v-if="isEditableMode && item.id === editingDetailId"
                  v-model="editedDetailData.expiration_date"
                  type="date"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="mt-1"
                />
                <span v-else class="value text-xs" :class="{'text-warning font-weight-bold': isNearExpiration(item)}">
                  {{ item.expiration_date || 'S/V' }}
                </span>
              </div>
              <div class="detail-item w-100" v-if="isLocationMode && !isItemReturned(item)">
                <span class="label">Ubicación</span>
                <VAutocomplete
                  :model-value="item.location"
                  :items="locations"
                  item-title="name"
                  item-value="name"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="mt-1"
                  placeholder="Buscar..."
                  @update:model-value="emit('updateLocation', item.id, $event)"
                />
              </div>
              <div class="detail-item" v-else>
                <span class="label">Ubicación</span>
                <span class="value text-xs">{{ item.location || '-' }}</span>
              </div>
            </div>
          </div>

          <!-- Botones de Acción Móvil -->
          <div v-if="isEditableMode && isEditMode" class="mt-3 pt-2 border-t d-flex align-center justify-space-between ga-2">
            <template v-if="item.id === editingDetailId">
              <VBtn color="success" size="small" variant="flat" block class="rounded-lg" @click="emit('saveEditingDetail')">
                <VIcon icon="tabler-check" />
              </VBtn>
              <VBtn color="error" size="small" variant="tonal" class="rounded-lg" @click="emit('cancelEditingDetail')">
                <VIcon icon="tabler-x" />
              </VBtn>
            </template>
            <template v-else>
              <div class="d-flex ga-1">
                <VBtn color="secondary" variant="tonal" size="x-small" icon="tabler-arrow-up" :disabled="index === 0" @click="emit('moveItemUp', item)" />
                <VBtn color="secondary" variant="tonal" size="x-small" icon="tabler-arrow-down" :disabled="index === processedInvoiceDetails.length - 1" @click="emit('moveItemDown', item)" />
              </div>

              <div class="d-flex ga-2">
                <VBtn
                  :color="isItemReturned(item) ? 'warning' : 'secondary'"
                  variant="tonal"
                  size="small"
                  class="rounded-lg"
                  @click="emit('toggleReturnItem', item)"
                >
                  <VIcon icon="tabler-arrow-back-up" />
                </VBtn>

                <VBtn
                  v-if="invoiceHasIva"
                  :color="item.tax_enabled ? 'success' : 'secondary'"
                  variant="tonal"
                  size="small"
                  class="rounded-lg"
                  @click="emit('toggleTax', item)"
                >
                  <VIcon icon="tabler-receipt-tax" />
                </VBtn>

                <VBtn color="warning" variant="tonal" size="small" class="rounded-lg" @click="emit('startEditingDetail', item)">
                  <VIcon icon="tabler-edit" />
                </VBtn>

                <VBtn color="error" variant="tonal" size="small" class="rounded-lg" @click="emit('removeProductFromInvoice', item.id)">
                  <VIcon icon="tabler-trash" />
                </VBtn>
              </div>
            </template>
          </div>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>
