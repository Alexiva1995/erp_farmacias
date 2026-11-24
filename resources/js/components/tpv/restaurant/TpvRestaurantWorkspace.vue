<script setup>
import { ref } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import RestaurantMenuGrid from './RestaurantMenuGrid.vue'
import RestaurantTicketPanel from './RestaurantTicketPanel.vue'
import RestaurantFloorQuickDialog from './RestaurantFloorQuickDialog.vue'

const props = defineProps({
  dishes: { type: Array, default: () => [] },
  dishesLoading: { type: Boolean, default: false },
  dishCategories: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
  productsLoading: { type: Boolean, default: false },
  isLoadingInitialOrder: { type: Boolean, default: false },
  hasOpenOrder: { type: Boolean, default: false },
  openOrderData: { type: Object, default: null },
  orderItems: { type: Array, default: () => [] },
  selectedClient: { type: Object, default: null },
  selectedDisplayCurrency: { type: String, default: 'USD' },
  exchangeRates: { type: Object, default: () => ({}) },
  totalProductsAmount: { type: Number, default: 0 },
  totalIvaAmount: { type: Number, default: 0 },
  totalOrderAmount: { type: Number, default: 0 },
  isFlashSubmitting: { type: Boolean, default: false },
})

const emit = defineEmits([
  'add-dish',
  'add-product',
  'search-products',
  'currency-changed',
  'update-quantity',
  'remove-item',
  'edit-dish-modifiers',
  'open-buys-modal',
  'flash-checkout',
  'reserver-order',
  'cancelar-order',
  'open-pedidos-modal',
  'start-quick-order',
  'edit-client',
  'refresh-order',
])

const showFloorModal = ref(false)
const kdsLoading = ref(false)
const preBillLoading = ref(false)
const splitLoading = ref(false)

const handleSendToKds = async () => {
  if (!props.openOrderData?.id) return
  kdsLoading.value = true
  try {
    await axios.post(`/kds/orders/${props.openOrderData.id}/send`)
    await emit('refresh-order')
    toast.success('Comanda enviada a cocina (KDS).')
  } catch (error) {
    toast.error(error.response?.data?.message || 'No se pudo enviar a cocina.')
  } finally {
    kdsLoading.value = false
  }
}

const handleGeneratePreBill = async () => {
  if (!props.openOrderData?.id) return
  preBillLoading.value = true
  try {
    await axios.post(`/orders/${props.openOrderData.id}/pre-bill`, { service_percentage: 0 })
    await emit('refresh-order')
    toast.success('Pre-cuenta generada exitosamente.')
  } catch (error) {
    toast.error(error.response?.data?.message || 'No se pudo generar la pre-cuenta.')
  } finally {
    preBillLoading.value = false
  }
}

const handleSplitCheck = async (parts) => {
  if (!props.openOrderData?.id) return
  splitLoading.value = true
  try {
    const { data } = await axios.post(`/orders/${props.openOrderData.id}/split`, {
      type: 'equal',
      parts,
    })
    await emit('refresh-order')
    toast.success(data.message || 'Cuenta dividida exitosamente.')
  } catch (error) {
    toast.error(error.response?.data?.message || 'No se pudo dividir la cuenta.')
  } finally {
    splitLoading.value = false
  }
}
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12" md="7" lg="8">
        <RestaurantMenuGrid
          :dishes="dishes"
          :dishes-loading="dishesLoading"
          :dish-categories="dishCategories"
          :products="products"
          :products-loading="productsLoading"
          :selected-display-currency="selectedDisplayCurrency"
          :exchange-rates="exchangeRates"
          @add-dish="(payload) => emit('add-dish', payload)"
          @add-product="(payload) => emit('add-product', payload)"
          @search-products="(q) => emit('search-products', q)"
        />
      </VCol>

      <VCol cols="12" md="5" lg="4">
        <RestaurantTicketPanel
          :is-loading-initial-order="isLoadingInitialOrder"
          :has-open-order="hasOpenOrder"
          :open-order-data="openOrderData"
          :order-items="orderItems"
          :selected-client="selectedClient"
          :selected-display-currency="selectedDisplayCurrency"
          :total-products-amount="totalProductsAmount"
          :total-iva-amount="totalIvaAmount"
          :total-order-amount="totalOrderAmount"
          :kds-loading="kdsLoading"
          :pre-bill-loading="preBillLoading"
          :split-loading="splitLoading"
          :is-flash-submitting="isFlashSubmitting"
          @currency-changed="(c) => emit('currency-changed', c)"
          @update-quantity="(p) => emit('update-quantity', p)"
          @remove-item="(item) => emit('remove-item', item)"
          @edit-dish-modifiers="(item) => emit('edit-dish-modifiers', item)"
          @open-buys-modal="emit('open-buys-modal')"
          @flash-checkout="(payload) => emit('flash-checkout', payload)"
          @reserver-order="emit('reserver-order')"
          @cancelar-order="emit('cancelar-order')"
          @open-pedidos-modal="emit('open-pedidos-modal')"
          @open-floor-modal="showFloorModal = true"
          @start-quick-order="emit('start-quick-order')"
          @edit-client="emit('edit-client')"
          @send-to-kds="handleSendToKds"
          @generate-pre-bill="handleGeneratePreBill"
          @split-check="handleSplitCheck"
        />
      </VCol>
    </VRow>

    <RestaurantFloorQuickDialog
      v-model="showFloorModal"
      @order-selected="emit('refresh-order')"
    />
  </div>
</template>
