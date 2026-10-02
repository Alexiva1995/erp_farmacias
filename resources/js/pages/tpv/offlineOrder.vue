<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/plugins/axios'
import { formatCurrency } from '@/utils/currencyFormatter'
import { roundUpToNearestHundred } from '@/utils/roundUpToNearesHundred.js'
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

// Cantidades de entrada para añadir (Map productId -> quantity)
const inputQuantities = ref(new Map())

// Paginación y búsqueda del catálogo local
const catalogPage = ref(1)
const catalogItemsPerPage = ref(10)
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
        // Inicializar mapa de cantidades
        request.result.forEach(p => {
          if (!inputQuantities.value.has(p.id)) {
            inputQuantities.value.set(p.id, 1)
          }
        })
      } else if (navigator.onLine) {
        syncCatalogInBackground().then(() => loadLocalCatalog())
      }
    }
  } catch (e) {
    console.error('Error cargando catálogo local:', e)
  }
}

// ─── Lógica de Precios y Ofertas ─────────────────────────────────────────────
const calculatePriceWithDiscount = (basePrice, product = null) => {
  const price = parseFloat(basePrice) || 0
  const prodPct = parseFloat(product?.discount_percentage || 0)
  return prodPct > 0 ? price * (1 - prodPct / 100) : price
}

const getActivePrice = (item) => {
  if (item.has_individual_offer && item.offer_expires_at) {
    const isNotExpired = new Date() < new Date(item.offer_expires_at)
    if (isNotExpired && item.offer_price_usd) {
      return Number(item.offer_price_usd)
    }
  }
  return calculatePriceWithDiscount(item.base_price_usd, item)
}

const getPriceClass = (item) => {
  const prodPct = parseFloat(item.discount_percentage || 0)
  return prodPct > 0 ? 'text-success font-weight-black' : 'font-weight-bold text-high-emphasis'
}

// ─── Filtrado del Catálogo Completo (Tipo TPV) ──────────────────────────────
const filteredCatalog = computed(() => {
  const query = catalogSearchFilter.value.trim().toLowerCase()
  if (!query) return localProducts.value

  return localProducts.value.filter(p => 
    (p.barcode && p.barcode.toLowerCase().includes(query)) || 
    (p.name && p.name.toLowerCase().includes(query)) ||
    (p.active_ingredient && p.active_ingredient.toLowerCase().includes(query)) ||
    (p.laboratory_name && p.laboratory_name.toLowerCase().includes(query)) ||
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

  let found = localProducts.value.find(p => p.barcode === query || String(p.id) === query)

  if (!found) {
    found = localProducts.value.find(p => p.name.toLowerCase().includes(query))
  }

  if (found) {
    addToCart(found, 1)
    searchQuery.value = ''
  } else {
    notFoundMessage.value = `Código o producto "${searchQuery.value}" no encontrado en la caché (${localProducts.value.length} productos cargados).`
    showNotFoundSnackbar.value = true
  }
}

// ─── Lógica del Carrito ──────────────────────────────────────────────────────
const addToCart = (product, qtyToAdd = 1) => {
  const qty = parseInt(qtyToAdd) || 1
  if (qty <= 0) return

  const existingItem = cart.value.find(item => item.id === product.id)
  if (existingItem) {
    existingItem.quantity += qty
  } else {
    cart.value.push({ 
      ...product, 
      quantity: qty 
    })
  }
}

const handleAddProductWithQuantity = (product) => {
  const qty = inputQuantities.value.get(product.id) || 1
  addToCart(product, qty)
  inputQuantities.value.set(product.id, 1)
}

const handleQuantityInput = (productId, val) => {
  let cleanVal = parseInt(val)
  if (isNaN(cleanVal) || cleanVal < 1) cleanVal = 1
  inputQuantities.value.set(productId, cleanVal)
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
        <div class="d-flex align-center flex-wrap gap-2">
          <VIcon
            :icon="isOnline ? 'tabler-wifi' : 'tabler-wifi-off'"
            :color="isOnline ? 'success' : 'warning'"
            class="me-1"
          />
          <span class="text-body-2 font-weight-medium">
            Estado: <strong :class="isOnline ? 'text-success' : 'text-warning'">{{ isOnline ? 'Conectado (Online)' : 'Modo Contingencia (Offline)' }}</strong>
          </span>
          <VDivider vertical class="mx-2" />
          <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
            <VIcon icon="tabler-database" start size="14" />
            {{ localProducts.length }} productos precargados
          </VChip>
          <span v-if="lastGlobalCatalogSync" class="text-caption text-disabled">
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
      <!-- Panel Izquierdo: Catálogo y Escáner con Diseño Idéntico al TPV Normal -->
      <VCol cols="12" md="8">
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
              <div v-if="activeTab === 'catalog'" style="min-width: 280px;">
                <VTextField
                  v-model="catalogSearchFilter"
                  placeholder="Buscar por producto, cód. barra, activo..."
                  prepend-inner-icon="tabler-search"
                  density="compact"
                  hide-details
                  clearable
                />
              </div>
            </div>
          </VCardItem>

          <VCardText class="pa-2">
            <!-- Vista 1: Catálogo Estilo TPV Normal con ID, Stock, Laboratorio y Principio Activo -->
            <div v-if="activeTab === 'catalog'">
              <VTable density="compact" class="rounded border text-no-wrap">
                <thead>
                  <tr>
                    <th class="text-left" style="width: 70px;">ID</th>
                    <th class="text-center" style="width: 70px;">STOCK</th>
                    <th class="text-left">PRODUCTO</th>
                    <th class="text-right" style="width: 100px;">USD</th>
                    <th class="text-right" style="width: 110px;">BS</th>
                    <th class="text-right" style="width: 110px;">COP</th>
                    <th class="text-center" style="width: 140px;">AÑADIR</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="paginatedCatalog.length === 0">
                    <td colspan="7" class="text-center py-8 text-disabled">
                      <VIcon icon="tabler-search-off" size="36" class="mb-1 opacity-50" />
                      <p class="text-body-2 mb-0">No se encontraron productos coincidentes en la memoria local</p>
                    </td>
                  </tr>
                  <tr v-for="product in paginatedCatalog" :key="product.id">
                    <!-- ID con Badge Estilo TPV -->
                    <td>
                      <span class="product-id-badge text-caption font-weight-bold">
                        {{ product.id }}
                      </span>
                    </td>

                    <!-- Stock con Chip -->
                    <td class="text-center">
                      <VChip
                        :color="product.stock > 0 ? 'success' : 'error'"
                        size="small"
                        variant="flat"
                        class="font-weight-black px-2"
                      >
                        {{ Math.floor(product.stock) }}
                      </VChip>
                    </td>

                    <!-- Producto con Nombre, Badges, Principio Activo y Laboratorio -->
                    <td>
                      <div class="d-flex flex-column py-2">
                        <div class="d-flex align-center flex-wrap gap-1">
                          <span 
                            class="text-subtitle-2 font-weight-black text-high-emphasis leading-tight text-uppercase"
                            :class="{ 'text-primary': product.psychotropic == 1 }"
                          >
                            {{ product.name }}
                          </span>

                          <!-- Badge IVA -->
                          <VChip v-if="product.iva == 1" size="x-small" color="secondary" variant="tonal" class="font-weight-bold px-1 text-caption">G</VChip>
                          
                          <!-- Badge Origen Colombiano -->
                          <VChip v-if="product.is_colombian_origin == 1" size="x-small" color="info" variant="tonal" class="font-weight-bold">COL</VChip>
                          
                          <!-- Badge Descuento -->
                          <VChip
                            v-if="product.discount_percentage > 0"
                            color="success"
                            size="x-small"
                            variant="tonal"
                            class="font-weight-bold"
                          >
                            -{{ product.discount_percentage }}%
                          </VChip>
                        </div>

                        <!-- Sublínea: Principio Activo | Laboratorio | Ubicación -->
                        <div class="text-super-xs mt-1 d-flex align-center flex-wrap">
                          <span class="text-disabled font-weight-medium text-uppercase">
                            {{ product.active_ingredient || '—' }}
                          </span>
                          <span class="text-disabled mx-1">|</span>
                          <span class="font-weight-black text-uppercase text-primary">
                            {{ product.laboratory_name || 'GENÉRICO' }}
                          </span>
                          <template v-if="product.location">
                            <span class="text-disabled mx-1">|</span>
                            <span class="text-success font-weight-medium text-uppercase">
                              📍 {{ product.location }}
                            </span>
                          </template>
                        </div>
                      </div>
                    </td>

                    <!-- Precio USD -->
                    <td class="text-right">
                      <div class="d-flex flex-column align-end">
                        <del v-if="product.discount_percentage > 0" class="text-caption text-disabled text-decoration-line-through">
                          {{ formatCurrency(product.base_price_usd) }}
                        </del>
                        <span :class="getPriceClass(product)" class="text-body-2">
                          {{ formatCurrency(getActivePrice(product)) }}
                        </span>
                      </div>
                    </td>

                    <!-- Precio BS -->
                    <td class="text-right">
                      <div class="d-flex flex-column align-end">
                        <del v-if="product.discount_percentage > 0" class="text-caption text-disabled text-decoration-line-through">
                          {{ formatCurrency(product.price_bs, 'BS') }}
                        </del>
                        <span :class="getPriceClass(product)" class="text-body-2">
                          {{ formatCurrency(getActivePrice(product) * cachedRates.BS, 'BS') }}
                        </span>
                      </div>
                    </td>

                    <!-- Precio COP -->
                    <td class="text-right">
                      <div class="d-flex flex-column align-end">
                        <del v-if="product.discount_percentage > 0" class="text-caption text-disabled text-decoration-line-through">
                          {{ formatCurrency(roundUpToNearestHundred(product.price_cop), 'COP') }}
                        </del>
                        <span :class="getPriceClass(product)" class="text-primary font-weight-black text-body-2">
                          {{ formatCurrency(roundUpToNearestHundred(getActivePrice(product) * cachedRates.COP), 'COP') }}
                        </span>
                      </div>
                    </td>

                    <!-- Input Cantidad + Botón Añadir Estilo TPV -->
                    <td class="text-center">
                      <div class="d-flex align-center justify-center gap-1">
                        <VTextField
                          :model-value="inputQuantities.get(product.id) || 1"
                          @update:model-value="(val) => handleQuantityInput(product.id, val)"
                          type="number"
                          density="compact"
                          variant="outlined"
                          hide-details
                          style="max-width: 60px;"
                          class="font-weight-black text-center"
                          :disabled="product.stock === 0"
                        />
                        <VBtn
                          color="primary"
                          size="small"
                          height="36"
                          class="rounded font-weight-black px-2"
                          :disabled="product.stock === 0"
                          @click="handleAddProductWithQuantity(product)"
                        >
                          <VIcon icon="tabler-plus" size="18" />
                        </VBtn>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </VTable>

              <!-- Paginador del Catálogo -->
              <div class="d-flex justify-space-between align-center mt-3 px-2 flex-wrap">
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
            <div v-else class="pa-4">
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
      <VCol cols="12" md="4">
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
                  <span class="text-caption text-disabled font-monospace">{{ item.barcode || `#${item.id}` }}</span>
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
.gap-1 {
  gap: 4px;
}
.gap-2 {
  gap: 8px;
}
.text-super-xs {
  font-size: 0.72rem;
  line-height: 1rem;
}
.product-id-badge {
  background-color: #28c76f;
  color: #fff;
  padding: 3px 7px;
  border-radius: 6px;
  display: inline-block;
}
</style>
