<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/plugins/axios'

const router = useRouter()
const emit = defineEmits(['synced'])

// ─── Estado de Tasas ─────────────────────────────────────────────────────────
const cachedRates = ref({
  USD: 1,
  BS: Number(localStorage.getItem('tpv_offline_rate_bs')) || 45.50,
  COP: Number(localStorage.getItem('tpv_offline_rate_cop')) || 4100,
})

// ─── Estado de Productos y Carrito ───────────────────────────────────────────
const searchQuery = ref('')
const cart = ref([])
const localProducts = ref([])
const dbInstance = ref(null)
const isProcessing = ref(false)
const syncPendingCount = ref(0)
const isSyncingCatalog = ref(false)
const lastCatalogSync = ref(localStorage.getItem('tpv_offline_catalog_sync') || '')
const notFoundMessage = ref('')
const showNotFoundSnackbar = ref(false)

// ─── Estado de Conexión y Sincronización de Órdenes ───────────────────────────
const isOnline = ref(navigator.onLine)
const isSyncingOrders = ref(false)
const syncProgress = ref({ current: 0, total: 0 })

// ─── Eventos de Conexión ─────────────────────────────────────────────────────
const handleOnline = () => {
  isOnline.value = true
  syncCatalogFromBackend()
}
const handleOffline = () => {
  isOnline.value = false
}

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

// ─── Sincronización del Catálogo Real desde el Backend ───────────────────────
const syncCatalogFromBackend = async () => {
  if (!navigator.onLine || isSyncingCatalog.value) return
  isSyncingCatalog.value = true

  try {
    // 1. Obtener tasas de cambio reales
    try {
      const ratesRes = await axios.get('/public/exchange-rates')
      const ratesData = ratesRes.data?.data || ratesRes.data || []
      const rateBsObj = ratesData.find(r => r.currency_to === 'BS' || r.code === 'BS' || r.currency === 'BS')
      const rateCopObj = ratesData.find(r => r.currency_to === 'COP' || r.code === 'COP' || r.currency === 'COP')

      if (rateBsObj?.rate || rateBsObj?.effective_rate) {
        cachedRates.value.BS = Number(rateBsObj.effective_rate || rateBsObj.rate)
        localStorage.setItem('tpv_offline_rate_bs', cachedRates.value.BS)
      }
      if (rateCopObj?.rate || rateCopObj?.effective_rate) {
        cachedRates.value.COP = Number(rateCopObj.effective_rate || rateCopObj.rate)
        localStorage.setItem('tpv_offline_rate_cop', cachedRates.value.COP)
      }
    } catch (e) {
      console.warn('No se pudieron actualizar tasas en segundo plano:', e)
    }

    // 2. Obtener catálogo completo de productos del TPV
    const response = await axios.get('/tpv/order', { params: { itemsPerPage: -1 } })
    const rawProducts = response.data?.data || []

    if (Array.isArray(rawProducts) && rawProducts.length > 0 && dbInstance.value) {
      const tx = dbInstance.value.transaction([STORE_PRODUCTS], 'readwrite')
      const store = tx.objectStore(STORE_PRODUCTS)
      
      // Limpiar catálogo previo para tener datos frescos
      store.clear()

      const normalizedList = rawProducts.map(p => {
        const rawPrice = Number(p.sale_price ?? p.price ?? p.base_price ?? p.unit_price_usd ?? 0)
        const rawOfferPrice = p.offer_price ?? p.offer_price_usd ?? p.individual_offer_price
        return {
          id: p.id,
          barcode: String(p.barcode || p.code || '').trim(),
          name: p.name || p.title || 'Producto sin nombre',
          base_price_usd: rawPrice,
          stock: Number(p.stock ?? p.total_stock ?? 999),
          has_individual_offer: Boolean(p.has_individual_offer || p.is_offer_individual),
          offer_price_usd: rawOfferPrice ? Number(rawOfferPrice) : null,
          offer_expires_at: p.offer_expires_at || p.individual_offer_expires_at || null,
        }
      })

      normalizedList.forEach(item => store.put(item))

      tx.oncomplete = () => {
        localProducts.value = normalizedList
        const nowStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        lastCatalogSync.value = nowStr
        localStorage.setItem('tpv_offline_catalog_sync', nowStr)
      }
    }
  } catch (error) {
    console.error('Error al sincronizar catálogo desde backend:', error)
  } finally {
    isSyncingCatalog.value = false
  }
}

// ─── Carga del Catálogo Local desde IndexedDB ────────────────────────────────
const loadLocalCatalog = () => {
  if (!dbInstance.value) return

  const transaction = dbInstance.value.transaction([STORE_PRODUCTS], 'readonly')
  const store = transaction.objectStore(STORE_PRODUCTS)
  const request = store.getAll()

  request.onsuccess = () => {
    if (request.result && request.result.length > 0) {
      localProducts.value = request.result
    } else {
      // Si la DB está vacía y hay red, sincronizar de inmediato
      if (navigator.onLine) {
        syncCatalogFromBackend()
      }
    }
  }
}

// ─── Lógica de Precios y Ofertas ─────────────────────────────────────────────
const getActivePrice = (item) => {
  if (item.has_individual_offer && item.offer_expires_at) {
    const isNotExpired = new Date() < new Date(item.offer_expires_at)
    if (isNotExpired && item.offer_price_usd) {
      return Number(item.offer_price_usd)
    }
  }
  return Number(item.base_price_usd || 0)
}

// ─── Búsqueda Predictiva y Filtrado en Vivo ──────────────────────────────────
const searchResults = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query || query.length < 2) return []

  return localProducts.value
    .filter(p => p.barcode === query || p.name.toLowerCase().includes(query))
    .slice(0, 8)
})

const handleSearchEnter = () => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return

  // 1. Coincidencia exacta por código de barras o ID
  let found = localProducts.value.find(p => p.barcode === query || String(p.id) === query)

  // 2. Si no es exacto, buscar primera coincidencia por nombre
  if (!found) {
    found = localProducts.value.find(p => p.name.toLowerCase().includes(query))
  }

  if (found) {
    addToCart(found)
    searchQuery.value = ''
  } else {
    notFoundMessage.value = `Producto o código "${searchQuery.value}" no encontrado en la caché local (${localProducts.value.length} productos cargados).`
    showNotFoundSnackbar.value = true
  }
}

const addProductFromSearch = (product) => {
  addToCart(product)
  searchQuery.value = ''
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
  }, 400)
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
  if (isSyncingOrders.value) return
  isSyncingOrders.value = true

  const transaction = dbInstance.value.transaction([STORE_ORDERS], 'readonly')
  const store = transaction.objectStore(STORE_ORDERS)
  const request = store.getAll()

  request.onsuccess = async () => {
    const orders = request.result
    syncProgress.value.total = orders.length
    syncProgress.value.current = 0

    if (orders.length === 0) {
      emit('synced')
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

    isSyncingOrders.value = false
    updatePendingSyncCount()
    
    if (syncPendingCount.value === 0) {
      emit('synced')
      router.push('/tpv/orderUser').catch(() => {
        window.location.href = '/tpv/orderUser'
      })
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
    
    // Si hay conexión, sincronizar catálogo real automáticamente
    if (navigator.onLine) {
      syncCatalogFromBackend()
    }
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
            :loading="isSyncingOrders"
            prepend-icon="tabler-cloud-upload"
            @click="syncAndReturn"
          >
            <template v-if="isSyncingOrders">
              Sincronizando {{ syncProgress.current }} de {{ syncProgress.total }}...
            </template>
            <template v-else>
              Sincronizar y Volver al TPV
            </template>
          </VBtn>
        </div>
      </VAlert>
    </VSlideYTransition>

    <!-- Barra de Estado y Sincronización del Catálogo Local -->
    <VCard variant="outlined" class="mb-4 pa-3 bg-surface">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div class="d-flex align-center">
          <VIcon
            :icon="isOnline ? 'tabler-wifi' : 'tabler-wifi-off'"
            :color="isOnline ? 'success' : 'warning'"
            class="me-2"
          />
          <span class="text-body-2 font-weight-medium">
            Estado: <strong :class="isOnline ? 'text-success' : 'text-warning'">{{ isOnline ? 'Online (Conectado)' : 'Offline (Contingencia)' }}</strong>
          </span>
          <VDivider vertical class="mx-3" />
          <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
            <VIcon icon="tabler-database" start size="14" />
            {{ localProducts.length }} productos en caché local
          </VChip>
          <span v-if="lastCatalogSync" class="text-caption text-disabled ms-2">
            (Última sinc: {{ lastCatalogSync }})
          </span>
        </div>

        <VBtn
          v-if="isOnline"
          size="small"
          variant="tonal"
          color="primary"
          :loading="isSyncingCatalog"
          prepend-icon="tabler-refresh"
          @click="syncCatalogFromBackend"
        >
          Actualizar Catálogo Local
        </VBtn>
      </div>
    </VCard>

    <VRow>
      <VCol cols="12" md="8">
        <!-- Buscador con Búsqueda Predictiva -->
        <VCard class="mb-4">
          <VCardText class="pa-4">
            <VTextField
              v-model="searchQuery"
              placeholder="Escanear código de barras o escribir nombre del producto..."
              prepend-inner-icon="tabler-barcode"
              @keyup.enter="handleSearchEnter"
              clearable
              hide-details
              autofocus
              :disabled="isSyncingOrders"
            />

            <!-- Resultados en Vivo (Autocomplete Predictivo) -->
            <VList
              v-if="searchResults.length > 0"
              density="compact"
              class="mt-2 rounded border"
            >
              <VListItem
                v-for="product in searchResults"
                :key="product.id"
                class="cursor-pointer py-2"
                @click="addProductFromSearch(product)"
              >
                <template #prepend>
                  <VIcon icon="tabler-pill" class="me-2 text-primary" />
                </template>

                <VListItemTitle class="font-weight-medium">
                  {{ product.name }}
                  <span class="text-caption text-disabled ms-2">[{{ product.barcode }}]</span>
                </VListItemTitle>

                <template #append>
                  <span class="font-weight-bold text-success me-3">
                    ${{ getActivePrice(product).toFixed(2) }}
                  </span>
                  <VBtn
                    size="x-small"
                    color="primary"
                    variant="flat"
                    prepend-icon="tabler-plus"
                  >
                    Agregar
                  </VBtn>
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>

        <!-- Tabla del Carrito -->
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
                    <VBtn icon="tabler-minus" size="x-small" variant="tonal" @click="item.quantity > 1 ? item.quantity-- : removeFromCart(index)" :disabled="isSyncingOrders" />
                    <span class="mx-2 font-weight-bold">{{ item.quantity }}</span>
                    <VBtn icon="tabler-plus" size="x-small" variant="tonal" :disabled="item.quantity >= item.stock || isSyncingOrders" @click="item.quantity++" />
                  </div>
                </td>
                <td class="text-right font-weight-bold">
                  ${{ (getActivePrice(item) * item.quantity).toFixed(2) }}
                </td>
                <td class="text-center">
                  <VBtn icon="tabler-trash" color="error" size="small" variant="text" @click="removeFromCart(index)" :disabled="isSyncingOrders" />
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
              Tasas aplicadas: 1 USD = {{ cachedRates.BS }} BS | {{ cachedRates.COP }} COP
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
              :disabled="cart.length === 0 || isProcessing || isSyncingOrders" 
              :loading="isProcessing" 
              @click="processOfflineOrder"
            >
              Registrar Venta Offline
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <!-- Notificación Snackbar para productos no encontrados -->
    <VSnackbar
      v-model="showNotFoundSnackbar"
      color="error"
      location="top"
      :timeout="3500"
    >
      <div class="d-flex align-center">
        <VIcon icon="tabler-alert-circle" class="me-2" />
        {{ notFoundMessage }}
      </div>
    </VSnackbar>
  </VContainer>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
.cursor-pointer {
  cursor: pointer;
}
</style>
