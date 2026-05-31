import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'

export const usePosStore = defineStore('pos', () => {
  // --- ESTADOS REACTIVOS (State) ---
  const activeOrder = ref(null)
  const cartItems = ref([])
  const clients = ref([])
  const activeClient = ref(null)
  const isProcessing = ref(false)
  const availableProducts = ref([])
  const currency = ref('USD') // USD, BS, COP

  // --- VALORES DERIVADOS (Getters / Computed) ---
  const cartSubtotal = computed(() => {
    return cartItems.value.reduce((total, item) => total + (item.price * item.quantity), 0)
  })

  const cartTotalUSD = computed(() => {
    return cartItems.value.reduce((total, item) => total + ((item.unit_price_usd || item.price) * item.quantity), 0)
  })

  const totalItemsCount = computed(() => {
    return cartItems.value.reduce((total, item) => total + item.quantity, 0)
  })

  // --- ACCIONES (Actions / Methods) ---
  
  // Buscar clientes por cédula/identificación
  async function searchClient(identification) {
    try {
      const response = await axios.get(`/tpv/order/client/${identification}`)
      // ApiResponse retorna { success: true, data: { found: true, client: {...} } }
      const responseData = response.data?.data || response.data
      if (responseData && responseData.found && responseData.client) {
        activeClient.value = responseData.client
        return responseData.client
      }
      return null
    } catch (error) {
      console.error('Error al consultar cliente:', error)
      throw error
    }
  }

  // Cargar productos en el catálogo del TPV
  async function fetchProducts() {
    try {
      const response = await axios.get('/productsAll')
      const responseData = response.data?.data || response.data
      availableProducts.value = responseData
    } catch (error) {
      console.error('Error al cargar productos:', error)
    }
  }

  // Inicializar o recuperar orden pendiente del vendedor
  async function loadPendingOrder() {
    try {
      const response = await axios.get('/tpv/order/seller/my-open-order')
      const responseData = response.data?.data || response.data
      if (responseData?.order) {
        activeOrder.value = responseData.order
        cartItems.value = responseData.order.details || []
        activeClient.value = responseData.order.client
        currency.value = responseData.order.currency || 'USD'
      } else {
        activeOrder.value = null
        cartItems.value = []
        activeClient.value = null
      }
    } catch (error) {
      console.error('Error al recuperar orden pendiente:', error)
    }
  }

  // Agregar producto al carro con llamadas HTTP al backend
  async function addProductToOrder(productId, quantity = 1, priceUnit, priceUsdUnit, packId = null) {
    if (!activeOrder.value) {
      console.error('Debe inicializar una orden primero')
      return
    }

    isProcessing.value = true
    try {
      const response = await axios.post(`/tpv/orders/${activeOrder.value.id}/items`, {
        product_id: productId,
        quantity: quantity,
        price_at_product: priceUnit,
        price_usd_unit: priceUsdUnit,
        currency_at_order: currency.value || activeOrder.value.currency || 'USD',
        pack_id: packId
      })

      // Actualizar carro localmente
      await loadPendingOrder()
      return response.data
    } catch (error) {
      console.error('Error al agregar ítem a la orden:', error)
      throw error
    } finally {
      isProcessing.value = false
    }
  }

  // Completar cobro final de la orden en TPV
  async function completeSale(paymentDetails) {
    if (!activeOrder.value) return

    isProcessing.value = true
    try {
      const response = await axios.post(`/tpv/orders/${activeOrder.value.id}/complete`, paymentDetails)
      
      // Limpieza del estado del TPV tras completar cobro
      activeOrder.value = null
      cartItems.value = []
      activeClient.value = null
      
      return response.data
    } catch (error) {
      console.error('Error al procesar el cobro de la venta:', error)
      throw error
    } finally {
      isProcessing.value = false
    }
  }

  return {
    activeOrder,
    cartItems,
    clients,
    activeClient,
    isProcessing,
    availableProducts,
    currency,
    cartSubtotal,
    cartTotalUSD,
    totalItemsCount,
    searchClient,
    fetchProducts,
    loadPendingOrder,
    addProductToOrder,
    completeSale
  }
})
