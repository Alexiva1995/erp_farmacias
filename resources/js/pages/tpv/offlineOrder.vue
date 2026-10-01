<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/plugins/axios'

const router = useRouter()
const emit = defineEmits(['synced'])

// ─── Estado de la Aplicación y Tasas ─────────────────────────────────────────
const cachedRates = ref({
  USD: 1,
  BS: 45.50,
  COP: 4100,
})

const searchQuery = ref('')
const cart = ref([])
const isProcessing = ref(false)
const syncPendingCount = ref(0)
const localProducts = ref([])
const dbInstance = ref(null)

// ─── Estado de Conexión y Sincronización ─────────────────────────────────────
const isOnline = ref(navigator.onLine)
const isSyncing = ref(false)
const syncProgress = ref({ current: 0, total: 0 })

// ─── Eventos de Conexión ─────────────────────────────────────────────────────
const handleOnline = () => { isOnline.value = true }
const handleOffline = () => { isOnline.value = false }

// ─── Configuración de IndexedDB ──────────────────────────────────────────────
const DB_NAME = 'ErpFarmaciasOfflineDB'
const DB_VERSION = 1
const STORE_PRODUCTS = 'products'
const STORE_ORDERS = 'offline_orders'

const initIndexedDB = () => {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION)

    request.onerror = (event) => {
      console.error('IndexedDB Error:', event.target.error)
      reject(event.target.error)
    }

    request.onupgradeneeded = (event) => {
      const db = event.target.result
      if (!db.objectStoreNames.contains(STORE_PRODUCTS)) {
        db.createObjectStore(STORE_PRODUCTS, { keyPath: 'id' })
      }
      if (!db.objectStoreNames.contains(STORE_ORDERS)) {
        db.createObjectStore(STORE_ORDERS, { keyPath: 'uuid' })
      }
    }

    request.onsuccess = (event) => {
      dbInstance.value = event.target.result
      resolve(dbInstance.value)
    }
  })
}

// ─── Carga del Catálogo Local ────────────────────────────────────────────────
const loadLocalCatalog = () => {
  if (!dbInstance.value) return

  const transaction = dbInstance.value.transaction([STORE_PRODUCTS], 'readonly')
  const store = transaction.objectStore(STORE_PRODUCTS)
  const request = store.getAll()

  request.onsuccess = () => {
    if (request.result.length === 0) {
      const mockCatalog = [
        { 
          id: 1, barcode: '12345', name: 'Paracetamol 500mg', 
          base_price_usd: 2.50, stock: 100,
          has_individual_offer: false, offer_price_usd: null, offer_expires_at: null,
        },
        { 
          id: 2, barcode: '54321', name: 'Losartán 50mg (OFERTA)', 
          base_price_usd: 6.00, stock: 50,
          has_individual_offer: true, offer_price_usd: 4.50, offer_expires_at: '2026-12-31T23:59:59',
        },
        { 
          id: 3, barcode: '11111', name: 'Vitamina C 1g', 
          base_price_usd: 5.00, stock: 20,
          has_individual_offer: true, offer_price_usd: 2.00, offer_expires_at: '2020-01-01T00:00:00',
        },
      ]
      const writeTx = dbInstance.value.transaction([STORE_PRODUCTS], 'readwrite')
      const writeStore = writeTx.objectStore(STORE_PRODUCTS)
      mockCatalog.forEach(product => writeStore.put(product))
      localProducts.value = mockCatalog
    } else {
      localProducts.value = request.result
    }
  }
}

// ─── Lógica de Precios y Ofertas ─────────────────────────────────────────────
const getActivePrice = (item) => {
  if (item.has_individual_offer && item.offer_expires_at) {
    const isNotExpired = new Date() < new Date(item.offer_expires_at)
    if (isNotExpired) {
      return item.offer_price_usd
    }
  }
  return item.base_price_usd
}

// ─── Lógica del Carrito ──────────────────────────────────────────────────────
const searchProduct = () => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return

  const foundProduct = localProducts.value.find(p => 
    p.barcode === query || p.name.toLowerCase().includes(query)
  )

  if (foundProduct) {
    addToCart(foundProduct)
    searchQuery.value = ''
  } else {
    alert('Producto no encontrado en caché local.')
  }
}

const addToCart = (product) => {
  const existingItem = cart.value.find(item => item.id === product.id)
  if (existingItem) {
    if (existingItem.quantity < product.stock) {
      existingItem.quantity++
    }
  } else {
    cart.value.push({ ...product, quantity: 1 })
  }
}

const removeFromCart = (index) => {
  cart.value.splice(index, 1)
}

// ─── Cálculos Multimoneda ────────────────────────────────────────────────────
const cartTotalUSD = computed(() => {
  return cart.value.reduce((total, item) => total + (getActivePrice(item) * item.quantity), 0)
})

const totals = computed(() => {
  return {
    USD: cartTotalUSD.value.toFixed(2),
    BS: (cartTotalUSD.value * cachedRates.value.BS).toFixed(2),
    COP: (cartTotalUSD.value * cachedRates.value.COP).toFixed(0),
  }
})

// ─── Operaciones de Venta Offline ────────────────────────────────────────────
const generateUUID = () => {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0
    const v = c === 'x' ? r : (r & 0x3 | 0x8)
    return v.toString(16)
  })
}

const processOfflineOrder = () => {
  if (cart.value.length === 0) return
  isProcessing.value = true

  setTimeout(() => {
    const newOfflineOrder = {
      uuid: generateUUID(),
      timestamp: new Date().toISOString(),
      items: JSON.parse(JSON.stringify(cart.value)),
      total_usd: cartTotalUSD.value,
      rates_used: { ...cachedRates.value },
      status: 'pending_sync',
    }

    const transaction = dbInstance.value.transaction([STORE_ORDERS], 'readwrite')
    const store = transaction.objectStore(STORE_ORDERS)
    
    store.put(newOfflineOrder)

    transaction.oncomplete = () => {
      cart.value = []
      isProcessing.value = false
      updatePendingSyncCount()
    }
    transaction.onerror = (error) => {
      console.error('Error al guardar la orden:', error)
      isProcessing.value = false
    }
  }, 500)
}

const updatePendingSyncCount = () => {
  if (!dbInstance.value) return
  const transaction = dbInstance.value.transaction([STORE_ORDERS], 'readonly')
  const store = transaction.objectStore(STORE_ORDERS)
  const request = store.count()
  request.onsuccess = () => { syncPendingCount.value = request.result }
}

// ─── Motor de Sincronización y Retorno (Outbox Pattern) ──────────────────────
const syncAndReturn = async () => {
  if (isSyncing.value) return
  isSyncing.value = true

  const transaction = dbInstance.value.transaction([STORE_ORDERS], 'readonly')
  const store = transaction.objectStore(STORE_ORDERS)
  const request = store.getAll()

  request.onsuccess = async () => {
    const orders = request.result
    syncProgress.value.total = orders.length
    syncProgress.value.current = 0

    if (orders.length === 0) {
      router.push('/tpv/orderUser').catch(() => {
        window.location.href = '/tpv/orderUser'
      })
      return
    }

    for (const order of orders) {
      try {
        syncProgress.value.current++
        await axios.post('/tpv/orders/sync-contingency', order)

        const deleteTx = dbInstance.value.transaction([STORE_ORDERS], 'readwrite')
        deleteTx.objectStore('offline_orders').delete(order.uuid)
      } catch (error) {
        console.error(`Fallo al sincronizar la orden contingente ${order.uuid}:`, error)
      }
    }

    isSyncing.value = false
    updatePendingSyncCount()
    
    if (syncPendingCount.value === 0) {
      emit('synced')
      router.push('/tpv/orderUser').catch(() => {
        window.location.href = '/tpv/orderUser'
      })
    } else {
      alert('Se presentaron errores sincronizando algunas órdenes. Estas se mantendrán en contingencia para revisión.')
    }
  }
}

// ─── Ciclo de Vida ───────────────────────────────────────────────────────────
onMounted(async () => {
  window.addEventListener('online', handleOnline)
  window.addEventListener('offline', handleOffline)

  try {
    await initIndexedDB()
    loadLocalCatalog()
    updatePendingSyncCount()
  } catch (error) {
    console.error('Error inicializando TPV Offline', error)
  }
})

onUnmounted(() => {
  window.removeEventListener('online', handleOnline)
  window.removeEventListener('offline', handleOffline)
})
</script>

<template>
  <VContainer fluid>
    <!-- Alerta dinámica cuando vuelve el Internet -->
    <VSlideYTransition>
      <VAlert
        v-if="isOnline && syncPendingCount > 0"
        type="success"
        variant="elevated"
        class="mb-6"
        elevation="3"
      >
        <div class="d-flex align-center justify-space-between w-100 flex-wrap gap-2">
          <div>
            <span class="text-h6 font-weight-bold d-block mb-1">¡Conexión Restablecida!</span>
            <span class="text-body-2">
              Tienes {{ syncPendingCount }} venta(s) de contingencia listas para subir a la base de datos principal.
            </span>
          </div>
          
          <VBtn
            color="white"
            variant="flat"
            size="large"
            class="text-success font-weight-bold"
            :loading="isSyncing"
            prepend-icon="tabler-cloud-upload"
            @click="syncAndReturn"
          >
            <template v-if="isSyncing">
              Sincronizando {{ syncProgress.current }} de {{ syncProgress.total }}...
            </template>
            <template v-else>
              Sincronizar y Volver al TPV
            </template>
          </VBtn>
        </div>
      </VAlert>
    </VSlideYTransition>

    <!-- Alerta visual de estado offline -->
    <VAlert
      v-if="!isOnline"
      type="warning"
      variant="tonal"
      class="mb-6"
      border="start"
      icon="tabler-wifi-off"
    >
      <div class="text-h6">PUNTO DE VENTA OFFLINE (MODO CONTINGENCIA)</div>
      <div class="text-body-2">
        Las ventas se guardan localmente en el navegador. Al reconectar, aparecerá la opción para sincronizar al servidor central.
      </div>
    </VAlert>

    <VRow>
      <VCol cols="12" md="8">
        <VCard class="mb-4">
          <VCardText class="pa-4">
            <VTextField
              v-model="searchQuery"
              placeholder="Escanear código de barras o escribir nombre del producto..."
              prepend-inner-icon="tabler-barcode"
              @keyup.enter="searchProduct"
              hide-details
              autofocus
              :disabled="isSyncing"
            />
          </VCardText>
        </VCard>

        <VCard>
          <VTable>
            <thead>
              <tr>
                <th class="text-left">Código</th>
                <th class="text-left">Descripción</th>
                <th class="text-right">Precio USD</th>
                <th class="text-center">Cant.</th>
                <th class="text-right">Subtotal USD</th>
                <th class="text-center">Acción</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="cart.length === 0">
                <td colspan="6" class="text-center py-8 text-disabled">
                  <VIcon icon="tabler-shopping-cart-x" size="48" class="mb-2 opacity-50" />
                  <p class="text-body-1 mb-0">No hay productos en la orden de contingencia</p>
                </td>
              </tr>
              <tr v-for="(item, index) in cart" :key="item.id">
                <td><span class="text-caption font-weight-medium">{{ item.barcode }}</span></td>
                <td>
                  <span class="font-weight-medium">{{ item.name }}</span>
                  <VChip 
                    v-if="item.has_individual_offer && new Date() < new Date(item.offer_expires_at)"
                    color="success" 
                    size="x-small" 
                    class="ms-2 font-weight-bold"
                  >
                    OFERTA
                  </VChip>
                </td>
                <td class="text-right">
                  <div v-if="item.has_individual_offer && new Date() < new Date(item.offer_expires_at)">
                    <span class="text-decoration-line-through text-disabled text-caption me-1">${{ item.base_price_usd.toFixed(2) }}</span>
                    <span class="text-success font-weight-bold">${{ getActivePrice(item).toFixed(2) }}</span>
                  </div>
                  <div v-else class="font-weight-medium">
                    ${{ getActivePrice(item).toFixed(2) }}
                  </div>
                </td>
                <td class="text-center">
                  <div class="d-inline-flex align-center">
                    <VBtn icon="tabler-minus" size="x-small" variant="tonal" @click="item.quantity > 1 ? item.quantity-- : removeFromCart(index)" :disabled="isSyncing" />
                    <span class="mx-2 font-weight-bold">{{ item.quantity }}</span>
                    <VBtn icon="tabler-plus" size="x-small" variant="tonal" :disabled="item.quantity >= item.stock || isSyncing" @click="item.quantity++" />
                  </div>
                </td>
                <td class="text-right font-weight-bold">
                  ${{ (getActivePrice(item) * item.quantity).toFixed(2) }}
                </td>
                <td class="text-center">
                  <VBtn icon="tabler-trash" color="error" size="small" variant="text" @click="removeFromCart(index)" :disabled="isSyncing" />
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>

      <!-- Resumen Multimoneda -->
      <VCol cols="12" md="4">
        <VCard class="h-100 d-flex flex-column">
          <VCardItem class="bg-primary text-white py-3">
            <VCardTitle class="text-white text-h6 font-weight-bold">Totalizador Multimoneda</VCardTitle>
          </VCardItem>
          
          <VCardText class="flex-grow-1 pt-6">
            <div class="d-flex justify-space-between align-center mb-4">
              <span class="text-h6 text-medium-emphasis">TOTAL USD:</span>
              <span class="text-h4 font-weight-black text-primary">${{ totals.USD }}</span>
            </div>
            <VDivider class="mb-4" />
            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-body-1 text-medium-emphasis">TOTAL BS:</span>
              <span class="text-h5 font-weight-bold">Bs {{ totals.BS }}</span>
            </div>
            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-body-1 text-medium-emphasis">TOTAL COP:</span>
              <span class="text-h5 font-weight-bold">$ {{ totals.COP }}</span>
            </div>
            
            <div class="mt-4 pa-3 rounded bg-var-theme-background text-caption text-medium-emphasis">
              Tasas congeladas: 1 USD = {{ cachedRates.BS }} BS | {{ cachedRates.COP }} COP
            </div>

            <VSheet color="warning" variant="tonal" class="pa-3 mt-6 rounded d-flex align-center" v-if="syncPendingCount > 0 && !isOnline">
              <VIcon icon="tabler-cloud-upload" size="24" class="me-2 text-warning" />
              <span class="text-caption font-weight-medium">
                {{ syncPendingCount }} orden(es) pendiente(s) por sincronizar.
              </span>
            </VSheet>
          </VCardText>

          <VCardActions class="pa-4">
            <VBtn 
              color="success" 
              variant="flat" 
              block 
              size="x-large" 
              class="font-weight-bold"
              prepend-icon="tabler-device-floppy"
              :disabled="cart.length === 0 || isProcessing || isSyncing" 
              :loading="isProcessing" 
              @click="processOfflineOrder"
            >
              Registrar Venta Offline
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
