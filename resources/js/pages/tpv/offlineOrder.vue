<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/plugins/axios'
import { 
  getOfflineDB, 
  syncCatalogInBackground, 
  isSyncingGlobalCatalog, 
  lastGlobalCatalogSync 
} from '@/composables/useOfflineCatalogSync'

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
const isProcessing = ref(false)
const syncPendingCount = ref(0)
const notFoundMessage = ref('')
const showNotFoundSnackbar = ref(false)
const activeTab = ref('catalog') // 'catalog' | 'scanner'

// Paginación del catálogo local
const catalogPage = ref(1)
const catalogItemsPerPage = ref(8)
const catalogSearchFilter = ref('')

// ─── Estado de Conexión y Sincronización de Órdenes ───────────────────────────
const isOnline = ref(navigator.onLine)
const isSyncingOrders = ref(false)
const syncProgress = ref({ current: 0, total: 0 })

// ─── Eventos de Conexión ─────────────────────────────────────────────────────
const handleOnline = () => {
  isOnline.value = true
  syncCatalogInBackground().then(() => loadLocalCatalog())
}

const handleOffline = () => {
  isOnline.value = false
}

// ─── Carga del Catálogo Local desde IndexedDB ────────────────────────────────
const loadLocalCatalog = async () => {
  try {
    const db = await getOfflineDB()
    const tx = db.transaction(['products'], 'readonly')
    const store = tx.objectStore('products')
    const request = store.getAll()

    request.onsuccess = () => {
      if (request.result && request.result.length > 0) {
        localProducts.value = request.result
      } else if (navigator.onLine) {
        syncCatalogInBackground().then(() => loadLocalCatalog())
      }
    }
  } catch (e) {
    console.error('Error cargando catálogo local:', e)
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

// ─── Filtrado del Catálogo Completo (Tipo TPV) ──────────────────────────────
const filteredCatalog = computed(() => {
  const query = catalogSearchFilter.value.trim().toLowerCase()
  if (!query) return localProducts.value

  return localProducts.value.filter(p => 
    p.barcode.toLowerCase().includes(query) || 
    p.name.toLowerCase().includes(query) ||
    String(p.id) === query
  )
})

const paginatedCatalog = computed(() => {
  const start = (catalogPage.value - 1) * catalogItemsPerPage.value
  return filteredCatalog.value.slice(start, start + catalogItemsPerPage.value)
})

const totalCatalogPages = computed(() => {
  return Math.ceil(filteredCatalog.value.length / catalogItemsPerPage.value) || 1
})

// ─── Búsqueda Rápida / Escáner de Código de Barras ──────────────────────────
const handleBarcodeScan = () => {
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
    notFoundMessage.value = `Código o producto "${searchQuery.value}" no encontrado en la caché (${localProducts.value.length} disponibles).`
    showNotFoundSnackbar.value = true
  }
}

// ─── Lógica del Carrito ──────────────────────────────────────────────────────
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

const processOfflineOrder = async () => {
  if (cart.value.length === 0) return
  isProcessing.value = true

  try {
    const db = await getOfflineDB()
    const newOfflineOrder = {
      uuid: generateUUID(),
      timestamp: new Date().toISOString(),
      items: JSON.parse(JSON.stringify(cart.value)),
      total_usd: cartTotalUSD.value,
      rates_used: { ...cachedRates.value },
      status: 'pending_sync',
    }

    const tx = db.transaction(['offline_orders'], 'readwrite')
    const store = tx.objectStore('offline_orders')
    store.put(newOfflineOrder)

    tx.oncomplete = () => {
      cart.value = []
      isProcessing.value = false
      updatePendingSyncCount()
    }
    tx.onerror = () => {
      isProcessing.value = false
    }
  } catch (e) {
    isProcessing.value = false
  }
}

const updatePendingSyncCount = async () => {
  try {
    const db = await getOfflineDB()
    const tx = db.transaction(['offline_orders'], 'readonly')
    const store = tx.objectStore('offline_orders')
    const request = store.count()
    request.onsuccess = () => { syncPendingCount.value = request.result }
  } catch (e) {}
}

// ─── Sincronización de Órdenes Pendientes a Laravel ─────────────────────────
const syncAndReturn = async () => {
  if (isSyncingOrders.value) return
  isSyncingOrders.value = true

  try {
    const db = await getOfflineDB()
    const tx = db.transaction(['offline_orders'], 'readonly')
    const store = tx.objectStore('offline_orders')
    const request = store.getAll()

    request.onsuccess = async () => {
      const orders = request.result
      syncProgress.value.total = orders.length
      syncProgress.value.current = 0

      if (orders.length === 0) {
        emit('synced')
        router.push('/tpv/orderUser').catch(() => { window.location.href = '/tpv/orderUser' })
        return
      }

      for (const order of orders) {
        try {
          syncProgress.value.current++
          await axios.post('/tpv/orders/sync-contingency', order)

          const deleteTx = db.transaction(['offline_orders'], 'readwrite')
          deleteTx.objectStore('offline_orders').delete(order.uuid)
        } catch (error) {
          console.error(`Fallo sincronizando orden ${order.uuid}:`, error)
        }
      }

      isSyncingOrders.value = false
      await updatePendingSyncCount()
      
      if (syncPendingCount.value === 0) {
        emit('synced')
        router.push('/tpv/orderUser').catch(() => { window.location.href = '/tpv/orderUser' })
      }
    }
  } catch (e) {
    isSyncingOrders.value = false
  }
}

const manualSyncCatalog = async () => {
  await syncCatalogInBackground()
  await loadLocalCatalog()
}

// ─── Ciclo de Vida ───────────────────────────────────────────────────────────
onMounted(async () => {
  window.addEventListener('online', handleOnline)
  window.addEventListener('offline', handleOffline)

  await loadLocalCatalog()
  await updatePendingSyncCount()
})

onUnmounted(() => {
  window.removeEventListener('online', handleOnline)
  window.removeEventListener('offline', handleOffline)
})
</script>

<template>
  <VContainer fluid class="pa-0">
    <!-- Alerta dinámica cuando vuelve el Internet -->
    <VSlideYTransition>
      <VAlert
        v-if="isOnline && syncPendingCount > 0"
        type="success"
        variant="elevated"
        class="mb-4"
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

    <!-- Barra de Estado del Catálogo Offline -->
    <VCard variant="outlined" class="mb-4 pa-3 bg-surface">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div class="d-flex align-center">
          <VIcon
            :icon="isOnline ? 'tabler-wifi' : 'tabler-wifi-off'"
            :color="isOnline ? 'success' : 'warning'"
            class="me-2"
          />
          <span class="text-body-2 font-weight-medium">
            Estado: <strong :class="isOnline ? 'text-success' : 'text-warning'">{{ isOnline ? 'Conectado (Online)' : 'Modo Contingencia (Offline)' }}</strong>
          </span>
          <VDivider vertical class="mx-3" />
          <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
            <VIcon icon="tabler-database" start size="14" />
            {{ localProducts.length }} productos precargados
          </VChip>
          <span v-if="lastGlobalCatalogSync" class="text-caption text-disabled ms-2">
            (Actualizado: {{ lastGlobalCatalogSync }})
          </span>
        </div>

        <VBtn
          v-if="isOnline"
          size="small"
          variant="tonal"
          color="primary"
          :loading="isSyncingGlobalCatalog"
          prepend-icon="tabler-refresh"
          @click="manualSyncCatalog"
        >
          Forzar Recarga de Catálogo
        </VBtn>
      </div>
    </VCard>

    <VRow>
      <!-- Panel Izquierdo: Catálogo y Escáner estilo TPV -->
      <VCol cols="12" md="7">
        <VCard class="mb-4">
          <VCardItem class="py-2 px-4 border-b">
            <div class="d-flex align-center justify-space-between flex-wrap gap-2">
              <VTabs v-model="activeTab" density="compact" color="primary">
                <VTab value="catalog">
                  <VIcon icon="tabler-layout-grid" start size="18" />
                  Catálogo de Productos
                </VTab>
                <VTab value="scanner">
                  <VIcon icon="tabler-barcode" start size="18" />
                  Lector de Código de Barras
                </VTab>
              </VTabs>

              <!-- Buscador Rápido del Catálogo -->
              <div v-if="activeTab === 'catalog'" style="min-width: 260px;">
                <VTextField
                  v-model="catalogSearchFilter"
                  placeholder="Filtrar por nombre o código..."
                  prepend-inner-icon="tabler-search"
                  density="compact"
                  hide-details
                  clearable
                />
              </div>
            </div>
          </VCardItem>

          <VCardText class="pa-4">
            <!-- Vista 1: Catálogo Estilo TPV -->
            <div v-if="activeTab === 'catalog'">
              <VTable density="compact" class="rounded border">
                <thead>
                  <tr>
                    <th class="text-left">Producto</th>
                    <th class="text-left">Código</th>
                    <th class="text-right">Precio USD</th>
                    <th class="text-right">Precio BS</th>
                    <th class="text-center">Stock</th>
                    <th class="text-center">Acción</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="paginatedCatalog.length === 0">
                    <td colspan="6" class="text-center py-6 text-disabled">
                      <VIcon icon="tabler-search-off" size="32" class="mb-1 opacity-50" />
                      <p class="text-body-2 mb-0">No se encontraron productos coincidentes en la memoria local</p>
                    </td>
                  </tr>
                  <tr v-for="product in paginatedCatalog" :key="product.id">
                    <td>
                      <span class="font-weight-medium text-body-2">{{ product.name }}</span>
                      <VChip 
                        v-if="product.has_individual_offer && new Date() < new Date(product.offer_expires_at)"
                        color="success" 
                        size="x-small" 
                        class="ms-2 font-weight-bold"
                      >
                        OFERTA
                      </VChip>
                    </td>
                    <td>
                      <span class="text-caption text-disabled font-monospace">{{ product.barcode || 'N/A' }}</span>
                    </td>
                    <td class="text-right font-weight-bold">
                      <div v-if="product.has_individual_offer && new Date() < new Date(product.offer_expires_at)">
                        <span class="text-decoration-line-through text-disabled text-caption me-1">${{ product.base_price_usd.toFixed(2) }}</span>
                        <span class="text-success">${{ getActivePrice(product).toFixed(2) }}</span>
                      </div>
                      <div v-else>
                        ${{ getActivePrice(product).toFixed(2) }}
                      </div>
                    </td>
                    <td class="text-right text-caption font-weight-medium">
                      Bs {{ (getActivePrice(product) * cachedRates.BS).toFixed(2) }}
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" :color="product.stock > 5 ? 'default' : 'error'" variant="tonal">
                        {{ product.stock }}
                      </VChip>
                    </td>
                    <td class="text-center">
                      <VBtn
                        size="x-small"
                        color="primary"
                        variant="flat"
                        prepend-icon="tabler-plus"
                        @click="addToCart(product)"
                      >
                        Agregar
                      </VBtn>
                    </td>
                  </tr>
                </tbody>
              </VTable>

              <!-- Paginador del Catálogo -->
              <div class="d-flex justify-space-between align-center mt-3 flex-wrap">
                <span class="text-caption text-disabled">
                  Mostrando {{ paginatedCatalog.length }} de {{ filteredCatalog.length }} productos
                </span>
                <VPagination
                  v-model="catalogPage"
                  :length="totalCatalogPages"
                  density="compact"
                  total-visible="5"
                />
              </div>
            </div>

            <!-- Vista 2: Modo Escáner de Código de Barras -->
            <div v-else>
              <VTextField
                v-model="searchQuery"
                label="Escanear con lector o escribir código y presionar Enter"
                placeholder="Ejemplo: 810028133655"
                prepend-inner-icon="tabler-barcode"
                @keyup.enter="handleBarcodeScan"
                autofocus
                clearable
                hide-details
                class="mb-4"
              />
              <p class="text-caption text-disabled mb-0">
                <VIcon icon="tabler-info-circle" size="14" class="me-1" />
                Al escanear un código de barras físico, se agregará inmediatamente al carrito de la derecha.
              </p>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Panel Derecho: Carrito y Totalizador Multimoneda -->
      <VCol cols="12" md="5">
        <VCard class="mb-4">
          <VCardItem class="bg-primary text-white py-2 px-4">
            <div class="d-flex justify-space-between align-center">
              <span class="font-weight-bold text-body-1 text-white">Orden Actual ({{ cart.reduce((s, i) => s + i.quantity, 0) }} ítems)</span>
              <VBtn v-if="cart.length > 0" size="x-small" variant="text" color="white" @click="cart = []">
                Vaciar
              </VBtn>
            </div>
          </VCardItem>

          <VTable density="compact" class="border-b">
            <thead>
              <tr>
                <th class="text-left">Producto</th>
                <th class="text-right">Precio</th>
                <th class="text-center">Cant.</th>
                <th class="text-right">Subtotal</th>
                <th class="text-center"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="cart.length === 0">
                <td colspan="5" class="text-center py-8 text-disabled">
                  <VIcon icon="tabler-shopping-cart-x" size="40" class="mb-2 opacity-50" />
                  <p class="text-body-2 mb-0">No hay productos en la orden</p>
                </td>
              </tr>
              <tr v-for="(item, index) in cart" :key="item.id">
                <td>
                  <span class="font-weight-medium text-body-2 d-block">{{ item.name }}</span>
                  <span class="text-caption text-disabled font-monospace">{{ item.barcode }}</span>
                </td>
                <td class="text-right font-weight-medium text-caption">
                  ${{ getActivePrice(item).toFixed(2) }}
                </td>
                <td class="text-center">
                  <div class="d-inline-flex align-center">
                    <VBtn icon="tabler-minus" size="x-small" variant="tonal" @click="item.quantity > 1 ? item.quantity-- : removeFromCart(index)" />
                    <span class="mx-2 font-weight-bold text-body-2">{{ item.quantity }}</span>
                    <VBtn icon="tabler-plus" size="x-small" variant="tonal" :disabled="item.quantity >= item.stock" @click="item.quantity++" />
                  </div>
                </td>
                <td class="text-right font-weight-bold text-body-2">
                  ${{ (getActivePrice(item) * item.quantity).toFixed(2) }}
                </td>
                <td class="text-center">
                  <VBtn icon="tabler-trash" color="error" size="x-small" variant="text" @click="removeFromCart(index)" />
                </td>
              </tr>
            </tbody>
          </VTable>

          <!-- Resumen de Pagos Multimoneda -->
          <VCardText class="pa-4 bg-var-theme-background">
            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-h6 text-medium-emphasis">TOTAL USD:</span>
              <span class="text-h4 font-weight-black text-primary">${{ totals.USD }}</span>
            </div>
            <VDivider class="mb-3" />
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-body-2 text-medium-emphasis">TOTAL BS:</span>
              <span class="text-h6 font-weight-bold">Bs {{ totals.BS }}</span>
            </div>
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-body-2 text-medium-emphasis">TOTAL COP:</span>
              <span class="text-h6 font-weight-bold">$ {{ totals.COP }}</span>
            </div>
            <div class="text-caption text-disabled mt-2">
              Tasas: 1 USD = {{ cachedRates.BS }} BS | {{ cachedRates.COP }} COP
            </div>

            <VSheet color="warning" variant="tonal" class="pa-3 mt-4 rounded d-flex align-center" v-if="syncPendingCount > 0 && !isOnline">
              <VIcon icon="tabler-cloud-upload" size="20" class="me-2 text-warning" />
              <span class="text-caption font-weight-medium">
                {{ syncPendingCount }} orden(es) en cola esperando sincronización.
              </span>
            </VSheet>

            <VBtn 
              color="success" 
              variant="flat" 
              block 
              size="large" 
              class="font-weight-bold mt-4"
              prepend-icon="tabler-device-floppy"
              :disabled="cart.length === 0 || isProcessing || isSyncingOrders" 
              :loading="isProcessing" 
              @click="processOfflineOrder"
            >
              Registrar Venta Offline
            </VBtn>
          </VCardText>
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
</style>
