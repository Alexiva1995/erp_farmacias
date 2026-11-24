<script setup>
import { computed, ref } from 'vue'
import { formatCurrency } from '@/utils/currencyFormatter'
import { useBrandingStore } from '@/stores/useBrandingStore'

const brandingStore = useBrandingStore()
const enableFlashCheckout = computed(() => Boolean(brandingStore.settings?.enable_flash_checkout))

const props = defineProps({
  isLoadingInitialOrder: { type: Boolean, default: false },
  hasOpenOrder: { type: Boolean, default: false },
  openOrderData: { type: Object, default: null },
  orderItems: { type: Array, default: () => [] },
  selectedClient: { type: Object, default: null },
  selectedDisplayCurrency: { type: String, default: 'USD' },
  totalProductsAmount: { type: Number, default: 0 },
  totalIvaAmount: { type: Number, default: 0 },
  totalOrderAmount: { type: Number, default: 0 },
  kdsLoading: { type: Boolean, default: false },
  preBillLoading: { type: Boolean, default: false },
  splitLoading: { type: Boolean, default: false },
  isFlashSubmitting: { type: Boolean, default: false },
})

const emit = defineEmits([
  'currency-changed',
  'update-quantity',
  'remove-item',
  'edit-dish-modifiers',
  'open-buys-modal',
  'flash-checkout',
  'reserver-order',
  'cancelar-order',
  'open-pedidos-modal',
  'open-floor-modal',
  'start-quick-order',
  'edit-client',
  'send-to-kds',
  'generate-pre-bill',
  'split-check',
])

const showSplitDialog = ref(false)
const splitParts = ref(2)

const availableCurrencies = ['USD', 'BS', 'COP']

const getItemUnitDisplayPrice = (item) => {
  const curr = String(props.selectedDisplayCurrency || 'USD').toUpperCase()
  if (curr === 'BS') return Number(item.price_bs ?? item.base_price_bs ?? 0)
  if (curr === 'COP') return Number(item.price_cop ?? item.base_price_cop ?? 0)
  return Number(item.price ?? item.base_price ?? 0)
}

const getItemLineTotal = (item) => {
  return getItemUnitDisplayPrice(item) * (Number(item.selectedQuantity) || 0)
}

const getModifierChipColor = (mod) => {
  if (mod.modifier_type === 'remove') return 'error'
  if (mod.modifier_type === 'add') return 'success'
  return 'primary'
}

const getModifierLabel = (mod) => {
  if (mod.modifier_type === 'remove') return `Sin ${mod.name}`
  if (mod.modifier_type === 'add') return `+ ${mod.name}`
  return mod.name
}

const getKitchenStatusColor = (status) => {
  if (status === 'ready') return 'success'
  if (status === 'in_progress') return 'info'
  if (status === 'queued') return 'warning'
  return 'secondary'
}

const getKitchenStatusLabel = (status) => {
  if (status === 'ready') return 'Listo'
  if (status === 'in_progress') return 'Preparando'
  if (status === 'queued') return 'En Cocina'
  if (status === 'served') return 'Servido'
  return status
}

const hasItems = computed(() => (props.orderItems || []).length > 0)

const handleConfirmSplit = () => {
  emit('split-check', Math.max(2, Number(splitParts.value) || 2))
  showSplitDialog.value = false
}
</script>

<template>
  <VCard variant="outlined" class="rounded-lg h-100 d-flex flex-column">
    <!-- Cabecera del Ticket -->
    <VCardText class="pa-3 pb-2">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-receipt" color="primary" />
          <span class="text-subtitle-1 font-weight-black">
            {{ hasOpenOrder && openOrderData ? `Comanda #${openOrderData.id}` : 'Nueva Comanda' }}
          </span>
          <VChip
            v-if="openOrderData?.dining_table?.name || openOrderData?.dining_table_id"
            size="x-small"
            color="primary"
            variant="tonal"
          >
            {{ openOrderData?.dining_table?.name || `Mesa #${openOrderData.dining_table_id}` }}
          </VChip>
        </div>

        <VMenu location="bottom end">
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              size="small"
              color="primary"
              variant="flat"
              append-icon="tabler-chevron-down"
            >
              {{ selectedDisplayCurrency }}
            </VBtn>
          </template>
          <VList density="compact">
            <VListItem
              v-for="curr in availableCurrencies"
              :key="curr"
              :active="curr === selectedDisplayCurrency"
              @click="emit('currency-changed', curr)"
            >
              <VListItemTitle class="font-weight-bold">{{ curr }}</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>
      </div>

      <!-- Accesos rápidos de Salón, Pedidos y Cliente -->
      <div class="d-flex align-center gap-2 flex-wrap mb-2">
        <VBtn
          size="small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-armchair"
          @click="emit('open-floor-modal')"
        >
          Mesas
        </VBtn>
        <VBtn
          size="small"
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-list-details"
          @click="emit('open-pedidos-modal')"
        >
          Pedidos
        </VBtn>
        <VBtn
          v-if="!hasOpenOrder"
          size="small"
          variant="flat"
          color="success"
          prepend-icon="tabler-plus"
          @click="emit('start-quick-order')"
        >
          Abrir Comanda
        </VBtn>
      </div>

      <div
        v-if="hasOpenOrder && selectedClient"
        class="d-flex align-center justify-space-between pa-2 rounded border"
      >
        <div class="d-flex align-center gap-2 text-truncate">
          <VIcon icon="tabler-user" size="16" color="primary" />
          <span class="text-caption font-weight-bold text-truncate">
            {{ selectedClient.name }} {{ selectedClient.last_name || '' }}
          </span>
          <span class="text-caption text-disabled">
            ({{ selectedClient.identification }})
          </span>
        </div>
        <VBtn
          icon="tabler-pencil"
          size="x-small"
          variant="text"
          color="primary"
          title="Cambiar o editar cliente"
          @click="emit('edit-client')"
        />
      </div>
    </VCardText>

    <VDivider />

    <!-- Cuerpo: Ítems de la Comanda -->
    <VCardText class="pa-3 flex-grow-1" style="max-height: 44vh; overflow-y: auto;">
      <div v-if="isLoadingInitialOrder" class="py-8 d-flex justify-center">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <div
        v-else-if="!hasItems"
        class="py-8 d-flex flex-column align-center justify-center text-center"
      >
        <VAvatar size="48" color="primary" variant="tonal" class="mb-2">
          <VIcon icon="tabler-tools-kitchen-2" size="24" />
        </VAvatar>
        <div class="text-subtitle-2 font-weight-bold mb-1">Comanda vacía</div>
        <p class="text-caption text-medium-emphasis mb-0">
          Toca un plato del menú para agregarlo y elegir sus sabores o extras.
        </p>
      </div>

      <div v-else class="d-flex flex-column gap-2">
        <VCard
          v-for="(item, idx) in orderItems"
          :key="item.order_detail_id || `${item.dish_id || item.product_id}-${idx}`"
          variant="outlined"
          class="pa-2 rounded-lg"
        >
          <div class="d-flex align-start justify-space-between gap-2">
            <div class="flex-grow-1 overflow-hidden">
              <div class="d-flex align-center gap-1 flex-wrap">
                <span class="text-body-2 font-weight-black text-high-emphasis">
                  {{ item.title }}
                </span>
                <VChip
                  v-if="item.is_dish && item.kitchen_status"
                  size="x-small"
                  :color="getKitchenStatusColor(item.kitchen_status)"
                  variant="tonal"
                >
                  {{ getKitchenStatusLabel(item.kitchen_status) }}
                </VChip>
              </div>

              <!-- Modificadores / Sabores seleccionados -->
              <div
                v-if="item.is_dish && Array.isArray(item.modifiers) && item.modifiers.length"
                class="d-flex align-center flex-wrap gap-1 mt-1"
              >
                <VChip
                  v-for="(mod, mIdx) in item.modifiers"
                  :key="mod.id || mod.modifier_option_id || mIdx"
                  size="x-small"
                  :color="getModifierChipColor(mod)"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ getModifierLabel(mod) }}
                </VChip>
              </div>

              <!-- Nota de cocina -->
              <div
                v-if="item.notes"
                class="text-caption text-success font-weight-medium mt-1 d-flex align-center gap-1"
              >
                <VIcon icon="tabler-message" size="13" />
                <span>{{ item.notes }}</span>
              </div>
            </div>

            <div class="text-right">
              <div class="text-body-2 font-weight-black text-primary">
                {{ formatCurrency(getItemLineTotal(item), selectedDisplayCurrency) }}
              </div>
              <div class="text-caption text-disabled">
                c/u {{ formatCurrency(getItemUnitDisplayPrice(item), selectedDisplayCurrency) }}
              </div>
            </div>
          </div>

          <div class="d-flex align-center justify-space-between mt-2 pt-1 border-t">
            <div class="d-flex align-center gap-1">
              <VBtn
                icon="tabler-minus"
                size="x-small"
                variant="tonal"
                :disabled="item.selectedQuantity <= 1"
                @click="emit('update-quantity', { productId: item.product_id, quantity: item.selectedQuantity - 1, orderDetailId: item.order_detail_id })"
              />
              <span class="text-body-2 font-weight-bold px-2">{{ item.selectedQuantity }}</span>
              <VBtn
                icon="tabler-plus"
                size="x-small"
                variant="tonal"
                :disabled="!item.is_dish && item.selectedQuantity >= item.availableQuantity"
                @click="emit('update-quantity', { productId: item.product_id, quantity: item.selectedQuantity + 1, orderDetailId: item.order_detail_id })"
              />
            </div>

            <div class="d-flex align-center gap-1">
              <VBtn
                v-if="item.is_dish"
                size="x-small"
                variant="text"
                color="primary"
                prepend-icon="tabler-adjustments"
                @click="emit('edit-dish-modifiers', item)"
              >
                Sabores/Nota
              </VBtn>
              <VBtn
                icon="tabler-trash"
                size="x-small"
                variant="text"
                color="error"
                title="Eliminar ítem"
                @click="emit('remove-item', item)"
              />
            </div>
          </div>
        </VCard>
      </div>
    </VCardText>

    <VDivider />

    <!-- Pie: Totales y Acciones -->
    <VCardText class="pa-3">
      <div class="d-flex justify-space-between text-caption mb-1">
        <span class="text-medium-emphasis font-weight-bold">SUBTOTAL</span>
        <span class="font-weight-bold">{{ formatCurrency(totalProductsAmount, selectedDisplayCurrency) }}</span>
      </div>
      <div v-if="totalIvaAmount > 0" class="d-flex justify-space-between text-caption mb-1">
        <span class="text-medium-emphasis font-weight-bold">IVA</span>
        <span class="font-weight-bold">{{ formatCurrency(totalIvaAmount, selectedDisplayCurrency) }}</span>
      </div>
      <div class="d-flex justify-space-between align-center mb-3">
        <span class="text-subtitle-2 font-weight-black">TOTAL A COBRAR</span>
        <span class="text-h6 font-weight-black text-primary">
          {{ formatCurrency(totalOrderAmount, selectedDisplayCurrency) }}
        </span>
      </div>

      <!-- Acciones de Salón / Cocina -->
      <div v-if="hasOpenOrder && hasItems" class="d-flex gap-2 flex-wrap mb-2">
        <VBtn
          size="small"
          variant="tonal"
          color="warning"
          prepend-icon="tabler-chef-hat"
          :loading="kdsLoading"
          class="flex-grow-1"
          @click="emit('send-to-kds')"
        >
          Enviar Cocina
        </VBtn>
        <VBtn
          size="small"
          variant="tonal"
          color="info"
          prepend-icon="tabler-file-invoice"
          :loading="preBillLoading"
          class="flex-grow-1"
          @click="emit('generate-pre-bill')"
        >
          Pre-cuenta
        </VBtn>
        <VBtn
          size="small"
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-arrows-split"
          :loading="splitLoading"
          class="flex-grow-1"
          @click="showSplitDialog = true"
        >
          Dividir
        </VBtn>
      </div>

      <!-- Acciones Principales -->
      <div class="d-flex gap-2">
        <VBtn
          v-if="hasOpenOrder"
          variant="outlined"
          color="error"
          icon="tabler-x"
          title="Cancelar Orden"
          @click="emit('cancelar-order')"
        />
        <VBtn
          variant="outlined"
          color="primary"
          prepend-icon="tabler-device-floppy"
          :disabled="!hasOpenOrder || !hasItems"
          class="flex-grow-1"
          @click="emit('reserver-order')"
        >
          Guardar
        </VBtn>
        <VBtn
          variant="flat"
          color="success"
          prepend-icon="tabler-cash"
          :disabled="!hasOpenOrder || !hasItems || isFlashSubmitting"
          class="flex-grow-1 font-weight-bold"
          @click="emit('open-buys-modal')"
        >
          Cobrar
        </VBtn>
        <VTooltip v-if="enableFlashCheckout" text="Cobro Rápido Flash (Efectivo COP)" location="top">
          <template #activator="{ props: tooltipProps }">
            <VBtn
              v-bind="tooltipProps"
              variant="flat"
              color="warning"
              :loading="isFlashSubmitting"
              :disabled="!hasOpenOrder || !hasItems || isFlashSubmitting"
              class="font-weight-black px-3"
              @click="emit('flash-checkout', { method: 'cash', currency: 'COP' })"
            >
              <VIcon icon="tabler-bolt" size="18" class="me-1" />
              <span>COP</span>
            </VBtn>
          </template>
        </VTooltip>
      </div>
    </VCardText>

    <!-- Diálogo rápido para Dividir Cuenta -->
    <VDialog v-model="showSplitDialog" max-width="380">
      <VCard class="rounded-lg">
        <VCardTitle class="pa-4 text-subtitle-1 font-weight-bold">
          Dividir Cuenta en Partes Iguales
        </VCardTitle>
        <VCardText class="pa-4 pt-0">
          <VTextField
            v-model.number="splitParts"
            type="number"
            min="2"
            max="12"
            label="Número de partes"
            variant="outlined"
            density="comfortable"
          />
        </VCardText>
        <VCardActions class="pa-4 pt-0 justify-end gap-2">
          <VBtn variant="outlined" color="secondary" @click="showSplitDialog = false">
            Cancelar
          </VBtn>
          <VBtn color="primary" variant="flat" @click="handleConfirmSplit">
            Dividir
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>
