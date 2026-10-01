<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/plugins/axios'

const router = useRouter()

// ─── Estado de la Aplicación y Tasas ─────────────────────────────────────────
// En producción, estos valores se actualizan en caché mientras hay conexión
const cachedRates = ref({
  USD: 1,
  BS: 45.50,
  COP: 4100
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
          has_individual_offer: false, offer_price_usd: null, offer_expires_at: null 
        },
        { 
          id: 2, barcode: '54321', name: 'Losartán 50mg (OFERTA)', 
          base_price_usd: 6.00, stock: 50,
          has_individual_offer: true, offer_price_usd: 4.50, offer_expires_at: '2026-12-31T23:59:59' 
        },
        { 
          id: 3, barcode: '11111', name: 'Vitamina C', 
          base_price_usd: 5.00, stock: 20,
          has_individual_offer: true, offer_price_usd: 2.00, offer_expires_at: '2020-01-01T00:00:00' 
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
    COP: (cartTotalUSD.value * cachedRates.value.COP).toFixed(0)
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
      status: 'pending_sync'
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

    // Si no hay ventas por subir, volvemos directamente al TPV principal
    if (orders.length === 0) {
      router.push('/tpv/order-user')
      return
    }

    // Proceso secuencial para poder mostrar progreso y auditar errores
    for (const order of orders) {
      try {
        syncProgress.value.current++
        
        // Petición hacia el controlador de Laravel
        await axios.post('/tpv/orders/sync-contingency', order)

        // Si Laravel retorna 200 OK, eliminamos la orden de la base local
        const deleteTx = dbInstance.value.transaction([STORE_ORDERS], 'readwrite')
        deleteTx.objectStore(STORE_ORDERS).delete(order.uuid)
      } catch (error) {
        console.error(`Fallo al sincronizar la orden contingente ${order.uuid}:`, error)
        // La orden no se borra, se queda para reintento futuro
      }
    }

    isSyncing.value = false
    updatePendingSyncCount()
    
    // Si la cola llegó a cero, redirigimos al TPV Online
    if (syncPendingCount.value === 0) {
      router.push('/tpv/order-user')
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
  <v-container fluid>
    <!-- Alerta dinámica cuando vuelve el Internet -->
    <v-slide-y-transition>
      <v-alert
        v-if="isOnline && syncPendingCount > 0"
        type="success"
        variant="elevated"
        class="mb-6"
        elevation="3"
      >
        <div class="d-flex align-center justify-space-between w-100">
          <div>
            <span class="text-h6 font-weight-bold d-block mb-1">¡Conexión Restablecida!</span>
            <span class="text-body-2">
              Tienes {{ syncPendingCount }} venta(s) de contingencia listas para subir a la base de datos principal.
            </span>
          </div>
          
          <v-btn
            color="white"
            variant="outlined"
            size="large"
            :loading="isSyncing"
            @click="syncAndReturn"
            class="ml-4"
          >
            <template v-if="isSyncing">
              Sincronizando {{ syncProgress.current }} de {{ syncProgress.total }}...
            </template>
            <template v-else>
              <v-icon icon="mdi-cloud-upload" start />
              Sincronizar y Volver al TPV
            </v-btn>
          </v-btn>
        </div>
      </v-alert>
    </v-slide-y-transition>

    <!-- Alerta visual de estado offline -->
    <v-alert
      v-if="!isOnline"
      type="warning"
      variant="tonal"
      class="mb-6"
      border="start"
      icon="mdi-wifi-off"
    >
      <div class="text-h6">PUNTO DE VENTA OFFLINE (MODO CONTINGENCIA)</div>
      <div class="text-body-2">
        Las ventas se guardan en el navegador con las tasas de cambio congeladas. 
        Al regresar la conexión, aparecerá la opción para sincronizar al servidor central.
      </div>
    </v-alert>

    <v-row>
      <v-col cols="12" md="8">
        <v-card class="mb-4">
          <v-card-text>
            <v-text-field
              v-model="searchQuery"
              label="Escanear Código de Barras o Buscar Producto"
              variant="outlined"
              append-inner-icon="mdi-barcode-scan"
              @keyup.enter="searchProduct"
              hide-details
              autofocus
              :disabled="isSyncing"
            />
          </v-card-text>
        </v-card>

        <v-card>
          <v-table>
            <thead>
              <tr>
                <th class="text-left">Código</th>
                <th class="text-left">Descripción</th>
                <th class="text-right">Precio USD</th>
                <th class="text-center">Cant.</th>
                <th class="text-right">Subtotal USD</th>
                <th class="text-center">Borrar</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="cart.length === 0">
                <td colspan="6" class="text-center py-8 text-grey">
                  <v-icon icon="mdi-cart-off" size="large" class="mb-2" />
                  <br> Carrito vacío
                </td>
              </tr>
              <tr v-for="(item, index) in cart" :key="item.id">
                <td>{{ item.barcode }}</td>
                <td>
                  {{ item.name }}
                  <v-chip 
                    v-if="item.has_individual_offer && new Date() < new Date(item.offer_expires_at)"
                    color="success" 
                    size="x-small" 
                    class="ml-2"
                  >
                    OFERTA ACTIVA
                  </v-chip>
                </td>
                <td class="text-right">
                  <div v-if="item.has_individual_offer && new Date() < new Date(item.offer_expires_at)">
                    <span class="text-decoration-line-through text-grey text-caption mr-1">${{ item.base_price_usd.toFixed(2) }}</span>
                    <span class="text-success font-weight-bold">${{ getActivePrice(item).toFixed(2) }}</span>
                  </div>
                  <div v-else>
                    ${{ getActivePrice(item).toFixed(2) }}
                  </div>
                </td>
                <td class="text-center">
                  <v-btn icon="mdi-minus" size="x-small" variant="text" @click="item.quantity > 1 ? item.quantity-- : removeFromCart(index)" :disabled="isSyncing" />
                  <span class="mx-2">{{ item.quantity }}</span>
                  <v-btn icon="mdi-plus" size="x-small" variant="text" :disabled="item.quantity >= item.stock || isSyncing" @click="item.quantity++" />
                </td>
                <td class="text-right font-weight-bold">
                  ${{ (getActivePrice(item) * item.quantity).toFixed(2) }}
                </td>
                <td class="text-center">
                  <v-btn icon="mdi-delete" color="error" size="small" variant="text" @click="removeFromCart(index)" :disabled="isSyncing" />
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-col>

      <!-- Resumen Multimoneda -->
      <v-col cols="12" md="4">
        <v-card color="grey-lighten-4" class="h-100 d-flex flex-column">
          <v-card-title class="bg-primary text-white">Totalizador Multimoneda</v-card-title>
          
          <v-card-text class="flex-grow-1 pt-4">
            <div class="d-flex justify-space-between align-center mb-4">
              <span class="text-h6 text-grey-darken-1">TOTAL USD:</span>
              <span class="text-h4 font-weight-black text-primary">${{ totals.USD }}</span>
            </div>
            <v-divider class="mb-4" />
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-body-1 text-grey-darken-1">TOTAL BS:</span>
              <span class="text-h5 font-weight-bold">Bs {{ totals.BS }}</span>
            </div>
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-body-1 text-grey-darken-1">TOTAL COP:</span>
              <span class="text-h5 font-weight-bold">$ {{ totals.COP }}</span>
            </div>
            
            <div class="mt-4 text-caption text-grey">
              Tasas aplicadas: 1 USD = {{ cachedRates.BS }} BS | {{ cachedRates.COP }} COP
            </div>

            <v-sheet color="warning-lighten-4" class="pa-3 mt-6 rounded d-flex align-center" v-if="syncPendingCount > 0 && !isOnline">
              <v-icon icon="mdi-cloud-sync" color="warning-darken-2" class="mr-2" />
              <span class="text-caption text-warning-darken-2 font-weight-medium">
                {{ syncPendingCount }} orden(es) pendiente(s) por sincronizar.
              </span>
            </v-sheet>
          </v-card-text>

          <v-card-actions class="pa-4">
            <v-btn 
              color="success" 
              variant="elevated" 
              block 
              size="x-large" 
              :disabled="cart.length === 0 || isProcessing || isSyncing" 
              :loading="isProcessing" 
              @click="processOfflineOrder"
            >
              <v-icon icon="mdi-cash-register" start />
              Registrar Venta Offline
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>
