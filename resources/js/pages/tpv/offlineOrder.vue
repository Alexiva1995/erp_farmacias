<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/plugins/axios'
import { roundUpToNearestHundred } from '@/utils/roundUpToNearesHundred.js'
import { 
  getOfflineDB, 
  syncCatalogInBackground, 
  isSyncingGlobalCatalog, 
  lastGlobalCatalogSync 
} from '@/composables/useOfflineCatalogSync'
import BuysModal from '@/components/dialogs/BuysModal.vue'

const router = useRouter()
const emit = defineEmits(['synced'])

// ─── Estado de Tasas y Moneda Activa ──────────────────────────────────────────
const selectedDisplayCurrency = ref('COP') // 'COP' | 'USD' | 'BS'
const availableCurrencies = ['COP', 'USD', 'BS']

const cachedRates = ref({
  USD: 1,
  BS: Number(localStorage.getItem('tpv_offline_rate_bs')) || 45.50,
  COP: Number(localStorage.getItem('tpv_offline_rate_cop')) || 4100,
})

// ─── Estado de Productos y Carrito ───────────────────────────────────────────
const barcodeSearchQuery = ref('')
const cart = ref([])
const localProducts = ref([])
const isProcessing = ref(false)
const syncPendingCount = ref(0)
const notFoundMessage = ref('')
const showNotFoundSnackbar = ref(false)
const showCheckoutModal = ref(false)
const selectedPaymentMethod = ref('cash_cop')
const isStrictSearch = ref(false)

// Cantidades de entrada para añadir (Map productId -> quantity)
const inputQuantities = ref(new Map())

// Paginación y búsqueda del catálogo local
const catalogPage = ref(1)
const catalogItemsPerPage = ref(15)
const catalogSearchFilter = ref('')

// ─── Estado de Conexión y Sincronización de Órdenes ───────────────────────────
const isOnline = ref(navigator.onLine)
const isSyncingOrders = ref(false)
const syncProgress = ref({ current: 0, total: 0 })

// ─── Formateadores de Moneda ────────────────────────────────────────────────
const formatCurrency = (val, currency = selectedDisplayCurrency.value) => {
  const num = Number(val) || 0
  if (currency === 'COP') {
    return `${num.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} COP`
  }
  if (currency === 'BS') {
    return `${num.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Bs`
  }
  return num.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ─── Eventos de Conexión ─────────────────────────────────────────────────────
const fetchRatesFromApi = async () => {
  if (!navigator.onLine) return
  try {
    const ratesRes = await axios.get('/public/exchange-rates')
    const apiRates = Array.isArray(ratesRes.data) ? ratesRes.data : (ratesRes.data?.data || [])
    
    let rateBs = null
    let rateCop = null
    let rateEur = null
    let rateBcv = null
    let rateBinance = null

    apiRates.forEach(r => {
      const code = String(r.currency_code || r.code || r.currency || '').toUpperCase()
      const val = parseFloat(r.rate)
      if (code === 'BS') rateBs = val
      if (code === 'COP') rateCop = val
      if (code === 'EUR') rateEur = val
      if (code === 'BCV') rateBcv = val
      if (code === 'BINANCE') rateBinance = val
    })

    const activeBsRate = rateEur || rateBs || rateBcv || rateBinance
    const activeCopRate = rateCop

    if (activeBsRate && !isNaN(activeBsRate) && activeBsRate > 0) {
      cachedRates.value.BS = activeBsRate
      localStorage.setItem('tpv_offline_rate_bs', String(activeBsRate))
    }
    if (activeCopRate && !isNaN(activeCopRate) && activeCopRate > 0) {
      cachedRates.value.COP = activeCopRate
      localStorage.setItem('tpv_offline_rate_cop', String(activeCopRate))
    }
  } catch (e) {
    console.warn('[Offline TPV] Error obteniendo tasas en tiempo real:', e)
  }
}

const handleOnline = () => {
  isOnline.value = true
  fetchRatesFromApi()
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
        request.result.forEach(p => {
          if (!inputQuantities.value.has(p.id)) {
            inputQuantities.value.set(p.id, 1)
          }
        })
      } else if (navigator.onLine) {
        fetchRatesFromApi()
        syncCatalogInBackground().then(() => loadLocalCatalog())
      }
    }
  } catch (e) {
    console.error('Error cargando catálogo local:', e)
  }
}

// ─── Lógica de Precios y Monedas ─────────────────────────────────────────────
const getProductPriceWithoutTax = (product, currency = selectedDisplayCurrency.value) => {
  const baseUsd = parseFloat(product.base_price_usd || product.sale_price || 0)
  const prodPct = parseFloat(product.discount_percentage || 0)

  if (currency === 'USD') {
    return prodPct > 0 ? baseUsd * (1 - prodPct / 100) : baseUsd
  }

  if (currency === 'BS') {
    const baseBs = (product.price_bs && Number(product.price_bs) > 0)
      ? Number(product.price_bs)
      : (baseUsd * cachedRates.value.BS)
    return prodPct > 0 ? baseBs * (1 - prodPct / 100) : baseBs
  }

  if (currency === 'COP') {
    const baseCop = (product.price_cop && Number(product.price_cop) > 0)
      ? Number(product.price_cop)
      : (baseUsd * cachedRates.value.COP)
    return prodPct > 0 ? baseCop * (1 - prodPct / 100) : baseCop
  }

  return baseUsd
}

const getProductPriceWithTax = (product, currency = selectedDisplayCurrency.value) => {
  const priceSinIva = getProductPriceWithoutTax(product, currency)
  const taxRate = product.iva == 1 ? 0.16 : 0
  const finalPrice = taxRate > 0 ? priceSinIva * (1 + taxRate) : priceSinIva

  if (currency === 'COP') return roundUpToNearestHundred(finalPrice)
  return finalPrice
}

const getProductIvaAmount = (product, currency = selectedDisplayCurrency.value) => {
  if (product.iva != 1) return 0
  const priceSinIva = getProductPriceWithoutTax(product, currency)
  return priceSinIva * 0.16
}

// ─── Opciones de Ordenamiento ────────────────────────────────────────────────
const currentSortOption = ref(null)

const sortOptions = [
  { title: "Precio Mayor a Menor", icon: "tabler-arrow-up", key: "sale_price", order: "desc" },
  { title: "Precio Menor a Mayor", icon: "tabler-arrow-down", key: "sale_price", order: "asc" },
  { title: "Más Unidades (Stock)", icon: "tabler-plus", key: "stock", order: "desc" },
  { title: "Menos Unidades (Stock)", icon: "tabler-minus", key: "stock", order: "asc" },
  { title: "Nombre A-Z", icon: "tabler-sort-ascending-letters", key: "name", order: "asc" },
  { title: "Nombre Z-A", icon: "tabler-sort-descending-letters", key: "name", order: "desc" },
]

const setSortOption = (opt) => {
  currentSortOption.value = opt
  catalogPage.value = 1
}

const clearSort = () => {
  currentSortOption.value = null
  catalogPage.value = 1
}

// ─── Filtrado y Ordenado del Catálogo Completo (Misma lógica de Laravel OrderQueryService) ───
const filteredCatalog = computed(() => {
  const query = catalogSearchFilter.value.trim().toLowerCase()
  let list = localProducts.value

  if (query) {
    // 1. Detectar búsquedas especiales por origen colombiano o IVA
    const isColombianSearch = ['col', '(col)', 'colombiano', 'colombianos'].includes(query)
    const isIvaSearch = ['g', '(g)', 'iva', 'gravado'].includes(query)

    if (isColombianSearch) {
      list = list.filter(p => Number(p.is_colombian_origin) === 1)
    } else if (isIvaSearch) {
      list = list.filter(p => Number(p.iva) === 1)
    } else if (isStrictSearch.value) {
      // Búsqueda estricta por límite de palabras (idéntica al REGEXP de MySQL)
      const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
      const regex = new RegExp(`(^|[^a-zA-Z0-9])${escaped}([^a-zA-Z0-9]|$)`, 'i')
      list = list.filter(p => {
        return (
          String(p.id) === query ||
          (p.barcode && p.barcode.toLowerCase() === query) ||
          regex.test(p.name || '') ||
          regex.test(p.active_ingredient || '')
        )
      })
    } else {
      // Búsqueda multi-palabra (AND): todas las palabras deben coincidir en algún campo
      const words = query.split(/\s+/).filter(Boolean)
      list = list.filter(p => {
        const idStr = String(p.id)
        const barcodeStr = (p.barcode || '').toLowerCase()
        const nameStr = (p.name || '').toLowerCase()
        const activeStr = (p.active_ingredient || '').toLowerCase()
        const labStr = (p.laboratory_name || '').toLowerCase()

        // Coincidencia exacta de ID o código de barras
        if (idStr === query || barcodeStr === query) return true

        // Todas las palabras ingresadas deben estar presentes en el producto
        return words.every(word => {
          return (
            nameStr.includes(word) ||
            activeStr.includes(word) ||
            labStr.includes(word) ||
            barcodeStr.includes(word) ||
            idStr.includes(word)
          )
        })
      })
    }
  }

  if (currentSortOption.value) {
    const { key, order } = currentSortOption.value
    list = [...list].sort((a, b) => {
      if (key === 'sale_price') {
        const pA = getProductPriceWithTax(a, selectedDisplayCurrency.value)
        const pB = getProductPriceWithTax(b, selectedDisplayCurrency.value)
        return order === 'desc' ? pB - pA : pA - pB
      }
      if (key === 'stock') {
        const sA = Number(a.stock || 0)
        const sB = Number(b.stock || 0)
        return order === 'desc' ? sB - sA : sA - sB
      }
      if (key === 'name') {
        const nA = (a.name || '').toLowerCase()
        const nB = (b.name || '').toLowerCase()
        return order === 'desc' ? nB.localeCompare(nA) : nA.localeCompare(nB)
      }
      return 0
    })
  }

  return list
})

const paginatedCatalog = computed(() => {
  const start = (catalogPage.value - 1) * catalogItemsPerPage.value
  return filteredCatalog.value.slice(start, start + catalogItemsPerPage.value)
})

const totalCatalogPages = computed(() => {
  return Math.ceil(filteredCatalog.value.length / catalogItemsPerPage.value) || 1
})

// ─── Escáner de Código de Barras / Búsqueda Rápida de Orden ─────────────────
const handleBarcodeScan = () => {
  const query = barcodeSearchQuery.value.trim().toLowerCase()
  if (!query) return

  let found = localProducts.value.find(p => p.barcode === query || String(p.id) === query)

  if (!found) {
    found = localProducts.value.find(p => p.name.toLowerCase().includes(query))
  }

  if (found) {
    addToCart(found, 1)
    barcodeSearchQuery.value = ''
  } else {
    notFoundMessage.value = `Código o producto "${barcodeSearchQuery.value}" no encontrado en la memoria local (${localProducts.value.length} productos).`
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

const incrementCartItem = (product) => {
  product.quantity += 1
}

const decrementCartItem = (product) => {
  if (product.quantity > 1) {
    product.quantity -= 1
  }
}

const removeFromCart = (index) => {
  cart.value.splice(index, 1)
}

const clearCart = () => {
  cart.value = []
}

// ─── Cálculos Totales del Carrito ────────────────────────────────────────────
const cartSubtotal = computed(() => {
  return cart.value.reduce((sum, item) => {
    return sum + (getProductPriceWithoutTax(item, selectedDisplayCurrency.value) * item.quantity)
  }, 0)
})

const cartIva = computed(() => {
  return cart.value.reduce((sum, item) => {
    return sum + (getProductIvaAmount(item, selectedDisplayCurrency.value) * item.quantity)
  }, 0)
})

const cartTotal = computed(() => {
  const total = cartSubtotal.value + cartIva.value
  if (selectedDisplayCurrency.value === 'COP') {
    return roundUpToNearestHundred(total)
  }
  return total
})

const cartTotalUsd = computed(() => {
  return cart.value.reduce((sum, item) => {
    return sum + (getProductPriceWithTax(item, 'USD') * item.quantity)
  }, 0)
})

// ─── Operaciones de Venta Offline ────────────────────────────────────────────
const generateUUID = () => {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0
    const v = c === 'x' ? r : (r & 0x3 | 0x8)
    return v.toString(16)
  })
}

const offlineOrderUUID = ref(generateUUID())

const offlineOrderData = computed(() => {
  return {
    id: offlineOrderUUID.value.substring(0, 8).toUpperCase(),
    uuid: offlineOrderUUID.value,
    created_at: new Date().toISOString(),
    seller: {
      username: 'Cajero Local',
    },
    client: {
      name: 'Cliente',
      last_name: 'General',
      phone: 'N/A',
      is_spe: false,
    },
    total: cartTotal.value,
    total_amount: cartTotal.value,
    currency: selectedDisplayCurrency.value,
  }
})

const offlineOrderProducts = computed(() => {
  return cart.value.map(item => ({
    id: item.id,
    title: item.name || item.title,
    name: item.name || item.title,
    selectedQuantity: item.quantity,
    quantity: item.quantity,
    price: getProductPriceWithoutTax(item, selectedDisplayCurrency.value),
    pivot_price_cop: getProductPriceWithoutTax(item, 'COP'),
    pivot_price_usd: getProductPriceWithoutTax(item, 'USD'),
    pivot_price_bs: getProductPriceWithoutTax(item, 'BS'),
    laboratory: item.laboratory_name || item.laboratory || 'N/A',
    iva_percentage: item.iva_percentage || 0,
    discount_percentage: item.discount_percentage || 0,
    notes: item.notes || null,
  }))
})

const handleOpenCheckoutModal = () => {
  if (cart.value.length === 0) return
  offlineOrderUUID.value = generateUUID()
  showCheckoutModal.value = true
}

const handlePurchaseCompleted = async (payload) => {
  try {
    const db = await getOfflineDB()
    const newOfflineOrder = {
      uuid: offlineOrderUUID.value,
      timestamp: new Date().toISOString(),
      items: JSON.parse(JSON.stringify(cart.value)),
      payments: payload?.payments || [],
      total_usd: cartTotalUsd.value,
      total_amount: payload?.total_amount || cartTotal.value,
      change_amount: payload?.changeAmount || 0,
      change_amount_cop: payload?.changeAmountInCop || 0,
      change_amount_usd: payload?.changeAmountUSD || 0,
      currency: selectedDisplayCurrency.value,
      rates_used: { ...cachedRates.value },
      status: 'pending_sync',
    }

    const tx = db.transaction(['offline_orders'], 'readwrite')
    const store = tx.objectStore('offline_orders')
    store.put(newOfflineOrder)

    tx.oncomplete = () => {
      updatePendingSyncCount()
    }
  } catch (e) {
    console.error('Error guardando orden offline en IndexedDB:', e)
  }
}

const handleModalClosed = () => {
  showCheckoutModal.value = false
}

const handleFinishAndReload = () => {
  cart.value = []
  showCheckoutModal.value = false
  offlineOrderUUID.value = generateUUID()
}

const handlePrintTicket = () => {
  window.print()
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

// ─── Sincronización de Órdenes a Laravel ────────────────────────────────────
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
  await fetchRatesFromApi()
  await syncCatalogInBackground()
  await loadLocalCatalog()
}

// ─── Helpers de Ubicación y Estilos ──────────────────────────────────────────
const getProductLocations = (product) => {
  if (product.lot_locations && Array.isArray(product.lot_locations) && product.lot_locations.length > 0) {
    return product.lot_locations.filter(Boolean)
  }
  return product.location ? [product.location] : []
}

// ─── Ciclo de Vida ───────────────────────────────────────────────────────────
onMounted(async () => {
  window.addEventListener('online', handleOnline)
  window.addEventListener('offline', handleOffline)

  await fetchRatesFromApi()
  await loadLocalCatalog()
  await updatePendingSyncCount()
})

onUnmounted(() => {
  window.removeEventListener('online', handleOnline)
  window.removeEventListener('offline', handleOffline)
})
</script>

<template>
  <div class="w-100">
    <!-- Alerta dinámica cuando vuelve el Internet -->
    <VSlideYTransition>
      <VAlert
        v-if="isOnline && syncPendingCount > 0"
        type="success"
        variant="elevated"
        class="mb-3"
        elevation="3"
      >
        <div class="d-flex align-center justify-space-between w-100 flex-wrap gap-2">
          <div>
            <span class="text-subtitle-1 font-weight-bold d-block mb-1">¡Conexión Restablecida!</span>
            <span class="text-body-2">
              Tienes {{ syncPendingCount }} venta(s) de contingencia guardadas localmente listas para sincronizar con el servidor.
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

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- CARD SUPERIOR: ORDEN (Réplica Exacta de OpenOrderCard)                 -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <VCard class="open-order-card shadow-sm border rounded-xl overflow-hidden mb-4 w-100">
      <!-- Encabezado de la Orden -->
      <VCardItem class="pa-3 pb-2 bg-surface border-b">
        <div class="d-flex align-center justify-space-between flex-nowrap">
          <div class="d-flex align-center gap-2 overflow-hidden">
            <VIcon icon="tabler-file-description" color="primary" size="24" class="opacity-80 flex-shrink-0" />
            <div class="d-flex flex-column overflow-hidden">
              <h2 class="text-subtitle-1 font-weight-950 text-high-emphasis uppercase letter-spacing-1 leading-none mb-1">
                ORDEN
              </h2>
              <div class="d-flex align-center gap-2">
                <span class="text-caption font-weight-bold text-primary truncate">Alexis Jose Valera Valbuena</span>
                <VIcon size="14" color="primary" class="opacity-70">tabler-edit</VIcon>
                <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">V- 24150980</span>
              </div>
            </div>
          </div>

          <div class="d-flex align-center gap-1 gap-sm-2 flex-shrink-0 ms-auto">
            <VChip
              size="x-small"
              :color="isOnline ? 'success' : 'warning'"
              variant="tonal"
              class="font-weight-black text-uppercase me-2"
            >
              {{ isOnline ? 'Online' : 'Contingencia Offline' }}
            </VChip>
            <VBtn
              icon="tabler-x"
              variant="text"
              color="secondary"
              size="small"
              density="comfortable"
              class="rounded-circle"
              @click="clearCart"
              title="Cancelar / Vaciar orden"
            />
          </div>
        </div>
      </VCardItem>

      <!-- Barra de Acciones de la Orden: Items, Input Barcode, Ofertas, Moneda -->
      <VCardText class="pa-3">
        <div class="d-flex align-center justify-space-between mb-3 flex-wrap gap-2 px-1">
          <!-- Contador de Ítems -->
          <div class="d-flex align-center gap-1.5 shrink-0 py-1">
            <VIcon icon="tabler-list-details" color="primary" size="18" class="opacity-80" />
            <span class="text-caption font-weight-bold text-primary uppercase letter-spacing-1">Ítems</span>
            <VChip size="x-small" variant="tonal" color="primary" class="font-weight-black px-2">{{ cart.length }}</VChip>
          </div>

          <!-- Buscador Refinado con Mejor Espaciado -->
          <div class="d-flex align-center flex-grow-1 mx-sm-1" style="min-inline-size: 220px;">
            <VTextField
              v-model="barcodeSearchQuery"
              placeholder="Escanear código o ingresar cotización..."
              density="compact"
              variant="flat"
              bg-color="grey-lighten-4"
              hide-details
              prepend-inner-icon="tabler-scan"
              class="rounded-lg custom-search-slim shadow-sm border flex-grow-1"
              @keydown.enter="handleBarcodeScan"
            >
              <template #append-inner>
                <VBtn
                  icon="tabler-arrow-right"
                  variant="text"
                  color="primary"
                  size="x-small"
                  class="me-n1"
                  :disabled="!barcodeSearchQuery"
                  @click="handleBarcodeScan"
                />
              </template>
            </VTextField>
          </div>

          <!-- Selectores a la Derecha: Ofertas / Descuentos + Moneda -->
          <div class="d-flex align-center gap-2 flex-wrap ms-auto">
            <!-- Botón Ofertas -->
            <VBtn
              variant="outlined"
              color="primary"
              size="small"
              class="rounded-lg font-weight-bold text-uppercase"
              height="38"
            >
              <span>OFERTAS</span>
              <VIcon end icon="tabler-chevron-down" size="14" />
            </VBtn>

            <!-- Selector de Moneda -->
            <VMenu location="bottom end">
              <template #activator="{ props: menuProps }">
                <VBtn
                  v-bind="menuProps"
                  variant="flat"
                  color="primary"
                  size="small"
                  class="rounded-lg font-weight-bold px-3"
                  height="38"
                >
                  <VIcon start icon="tabler-currency-dollar" size="16" />
                  <span>{{ selectedDisplayCurrency }}</span>
                  <VIcon end icon="tabler-chevron-down" size="14" />
                </VBtn>
              </template>
              <VList density="compact" class="rounded-lg shadow-lg">
                <VListItem
                  v-for="currencyOption in availableCurrencies"
                  :key="currencyOption"
                  :value="currencyOption"
                  :active="selectedDisplayCurrency === currencyOption"
                  color="primary"
                  @click="selectedDisplayCurrency = currencyOption"
                >
                  <VListItemTitle class="font-weight-bold text-caption">{{ currencyOption }}</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </div>
        </div>

        <!-- Lista de Productos en la Orden -->
        <div v-if="cart.length === 0" class="text-center py-8 text-disabled bg-grey-lighten-5 rounded-xl border border-dashed mx-3">
          <VIcon icon="tabler-shopping-cart-off" size="48" class="mb-3 opacity-20" />
          <p class="text-subtitle-2 font-weight-950 uppercase opacity-60">La orden está vacía</p>
          <p class="text-super-xs">Use el buscador de productos o el escáner para comenzar su venta</p>
        </div>

        <div v-else class="mt-2">
          <div class="d-flex flex-column gap-2 overflow-y-auto" style="max-block-size: 380px; padding-inline-end: 4px;">
            <div 
              v-for="(product, index) in cart" 
              :key="product.id" 
              class="product-row pa-2 rounded-lg border bg-surface d-flex align-center gap-3"
            >
              <!-- Cantidad Selector Estilo Premium -->
              <div class="d-flex align-center gap-1 bg-grey-lighten-4 rounded-lg px-1 border" style="block-size: 36px;">
                <VBtn 
                  icon="tabler-minus" 
                  size="24" 
                  variant="text" 
                  color="primary" 
                  @click="decrementCartItem(product)" 
                  :disabled="product.quantity <= 1" 
                />
                
                <div class="px-2 font-weight-950 text-primary text-body-2 min-width-24 text-center">
                  {{ product.quantity }}
                </div>

                <VBtn 
                  icon="tabler-plus" 
                  size="24" 
                  variant="text" 
                  color="primary" 
                  @click="incrementCartItem(product)" 
                />
              </div>

              <!-- Información del Producto y Desglose Inline -->
              <div class="flex-grow-1 overflow-hidden">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <h3 class="text-caption font-weight-950 text-high-emphasis text-uppercase leading-tight mb-0">
                    {{ (product.name || '').toUpperCase() }}
                  </h3>
                  <div 
                    class="d-flex align-center gap-1 text-super-xs flex-wrap"
                    style="white-space: pre-wrap;"
                  >
                    <span class="text-disabled">{{ product.active_ingredient || '—' }}</span>
                    <span class="text-disabled">|</span>
                    <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px; color: #e91e63 !important;">
                      {{ product.laboratory_name || 'GENÉRICO' }}
                    </span>
                  </div>

                  <!-- Desglose de Precios Inline (Inmediatamente después del título) -->
                  <div class="d-none d-sm-flex align-center gap-1 flex-wrap w-100">
                    <div class="d-flex align-center gap-1 bg-grey-lighten-4 px-1 rounded border">
                       <span class="text-super-xs text-disabled font-weight-black uppercase">U:</span>
                       <span class="text-super-xs font-weight-black text-secondary">
                         {{ formatCurrency(getProductPriceWithTax(product, selectedDisplayCurrency), selectedDisplayCurrency) }}
                       </span>
                    </div>
                    
                    <div class="d-flex align-center gap-1 bg-grey-lighten-4 px-1 rounded border">
                       <span class="text-super-xs text-disabled font-weight-black uppercase">S:</span>
                       <span class="text-super-xs font-weight-black text-high-emphasis">
                         {{ formatCurrency(getProductPriceWithoutTax(product, selectedDisplayCurrency) * product.quantity, selectedDisplayCurrency) }}
                       </span>
                    </div>

                    <div class="d-flex align-center gap-1 bg-grey-lighten-4 px-1 rounded border">
                       <span class="text-super-xs text-disabled font-weight-black uppercase">I:</span>
                       <span class="text-super-xs font-weight-black text-success">
                         {{ formatCurrency(getProductIvaAmount(product, selectedDisplayCurrency) * product.quantity, selectedDisplayCurrency) }}
                       </span>
                    </div>

                    <VChip v-if="product.discount_percentage > 0" color="success" size="x-small" variant="flat" class="text-super-xs px-1">
                      -{{ product.discount_percentage }}%
                    </VChip>
                  </div>
                </div>
              </div>

              <!-- Precio Total Ítem -->
              <div class="text-right d-flex flex-column align-end" style="min-inline-size: 100px;">
                <span class="text-subtitle-2 font-weight-950 text-primary leading-tight">
                  {{ formatCurrency(getProductPriceWithTax(product, selectedDisplayCurrency) * product.quantity, selectedDisplayCurrency) }}
                </span>
              </div>

              <!-- Acción Eliminar -->
              <VBtn 
                icon="tabler-x" 
                variant="text" 
                color="error" 
                size="x-small" 
                class="opacity-60"
                @click="removeFromCart(index)"
              />
            </div>
          </div>
        </div>
      </VCardText>

      <!-- Footer Unificado: Totales y Acciones -->
      <VCardText class="pa-4 bg-grey-lighten-5 border-t mt-3">
        <div class="d-flex flex-column gap-3">
          <!-- Fila de Totales: Subtotal/IVA y Total Final ultra destacado con espaciado amplio -->
          <div class="d-flex align-center justify-space-between flex-wrap gap-3 px-1 pt-1">
            <!-- Subtotal e IVA agrupados con alto contraste -->
            <div class="d-flex align-center gap-4 flex-wrap">
              <!-- Subtotal -->
              <div class="d-flex flex-column">
                <span class="total-label mb-1">Subtotal</span>
                <span class="total-value">
                  {{ formatCurrency(cartSubtotal, selectedDisplayCurrency) }}
                </span>
              </div>

              <!-- IVA -->
              <div class="d-flex flex-column">
                <span class="total-label mb-1">IVA (16%)</span>
                <span class="total-value text-success font-weight-bold">
                  + {{ formatCurrency(cartIva, selectedDisplayCurrency) }}
                </span>
              </div>
            </div>

            <!-- Monto Total Grande y Visible -->
            <div class="d-flex flex-column align-end">
              <span class="total-label mb-1">Total a Cobrar</span>
              <div class="text-h5 font-weight-950 text-primary leading-none d-flex align-center gap-1" style="font-size: 1.25rem !important;">
                {{ formatCurrency(cartTotal, selectedDisplayCurrency) }}
              </div>
            </div>
          </div>

          <!-- Botones de Acción Inferiores: Cancelar | Cobrar -->
          <div>
            <VRow dense class="align-center">
              <VCol cols="12" sm="4">
                <VBtn
                  color="secondary"
                  variant="outlined"
                  height="44"
                  block
                  class="rounded-lg font-weight-bold text-none btn-neutral-cancel"
                  :disabled="cart.length === 0"
                  @click="clearCart"
                >
                  <VIcon icon="tabler-trash" size="18" class="me-1" />
                  <span>Cancelar</span>
                </VBtn>
              </VCol>

              <VCol cols="12" sm="8">
                <VBtn
                  color="primary"
                  variant="flat"
                  height="44"
                  block
                  class="rounded-lg font-weight-bold text-none elevation-2 text-subtitle-2"
                  :disabled="cart.length === 0 || isProcessing"
                  @click="handleOpenCheckoutModal"
                >
                  <VIcon icon="tabler-circle-check" size="20" class="me-1" />
                  <span>COBRAR AHORA</span>
                </VBtn>
              </VCol>
            </VRow>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- BARRA DE BÚSQUEDA Y FILTROS DEL CATÁLOGO (Estilo AppFilterBase)       -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <VCard class="mb-3 rounded-xl border bg-surface pa-3 elevation-1 w-100">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div class="d-flex align-center flex-grow-1 gap-3" style="max-width: 600px;">
          <VTextField
            v-model="catalogSearchFilter"
            placeholder="Buscar por Producto, Cód. Barra, C. Activo..."
            prepend-inner-icon="tabler-search"
            density="compact"
            variant="outlined"
            hide-details
            clearable
            class="rounded-lg flex-grow-1"
          />

          <VCheckbox
            v-model="isStrictSearch"
            label="Estricta"
            density="compact"
            hide-details
            class="font-weight-medium text-caption"
          />
        </div>

        <div class="d-flex align-center gap-2 ms-auto">
          <!-- Selector de Ordenamiento por Mayor / Menor -->
          <VMenu location="bottom end">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                size="small"
                variant="tonal"
                :color="currentSortOption ? 'primary' : 'cyan'"
                class="rounded-lg"
                title="Ordenar por mayor/menor precio o stock"
              >
                <VIcon icon="tabler-arrows-sort" size="18" />
              </VBtn>
            </template>
            <VList density="compact" class="rounded-lg shadow-lg">
              <VListItem
                v-for="opt in sortOptions"
                :key="opt.title"
                :active="currentSortOption?.key === opt.key && currentSortOption?.order === opt.order"
                color="primary"
                @click="setSortOption(opt)"
              >
                <template #prepend>
                  <VIcon :icon="opt.icon" size="18" class="me-2" />
                </template>
                <VListItemTitle class="font-weight-medium text-caption">{{ opt.title }}</VListItemTitle>
              </VListItem>
              <VDivider v-if="currentSortOption" />
              <VListItem v-if="currentSortOption" color="error" @click="clearSort">
                <template #prepend>
                  <VIcon icon="tabler-x" size="18" class="me-2 text-error" />
                </template>
                <VListItemTitle class="font-weight-medium text-caption text-error">Quitar orden</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <VBtn icon size="small" variant="tonal" color="primary" class="rounded-lg" title="Cambiar moneda" @click="selectedDisplayCurrency = (selectedDisplayCurrency === 'COP' ? 'USD' : (selectedDisplayCurrency === 'USD' ? 'BS' : 'COP'))">
            <VIcon icon="tabler-arrows-left-right" size="18" />
          </VBtn>
          <VBtn icon size="small" variant="tonal" color="purple" class="rounded-lg" title="Limpiar búsqueda" @click="catalogSearchFilter = ''">
            <VIcon icon="tabler-eraser" size="18" />
          </VBtn>
          <VBtn
            v-if="isOnline"
            size="small"
            variant="tonal"
            color="primary"
            class="rounded-lg ms-2"
            :loading="isSyncingGlobalCatalog"
            prepend-icon="tabler-refresh"
            @click="manualSyncCatalog"
          >
            Recargar
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- TABLA DEL CATÁLOGO DE PRODUCTOS (Estilo OrderProductsTable)           -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <VCard class="rounded-xl border bg-surface elevation-1 overflow-hidden w-100 mb-6">
      <VTable density="compact" class="text-no-wrap tpv-custom-table">
        <thead>
          <tr>
            <th class="text-left" style="width: 80px;">ID</th>
            <th class="text-center" style="width: 70px;">STOCK</th>
            <th class="text-left">PRODUCTO</th>
            <th class="text-right" style="width: 100px;">USD</th>
            <th class="text-right" style="width: 120px;">BS</th>
            <th class="text-right" style="width: 125px;">COP</th>
            <th class="text-center" style="width: 110px;">AÑADIR</th>
            <th class="text-center" style="width: 90px;">ACCIÓN</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="paginatedCatalog.length === 0">
            <td colspan="8" class="text-center py-8 text-disabled">
              <VIcon icon="tabler-search-off" size="36" class="mb-1 opacity-50" />
              <p class="text-body-2 mb-0">No se encontraron productos coincidentes en la memoria local</p>
            </td>
          </tr>
          <tr v-for="product in paginatedCatalog" :key="product.id">
            <!-- ID Badge Verde -->
            <td>
              <span class="custom-id-badge">
                {{ product.id }}
              </span>
            </td>

            <!-- Stock Badge Verde -->
            <td class="text-center">
              <span :class="product.stock > 0 ? 'custom-stock-badge' : 'custom-stock-badge-empty'">
                {{ Math.floor(product.stock) }}
              </span>
            </td>

            <!-- Columna Producto: Nombre + G chip + Principio Activo | Lab Rosa | 📍 Ubicación Verde -->
            <td>
              <div class="d-flex flex-column py-1.5">
                <div class="d-flex align-center flex-wrap gap-1">
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis text-uppercase">
                    {{ product.name }}
                  </span>

                  <!-- Chip Gravado (G) -->
                  <span v-if="product.iva == 1" class="custom-badge-iva">G</span>
                  
                  <!-- Chip Colombiano (COL) -->
                  <span v-if="product.is_colombian_origin == 1" class="custom-badge-col">COL</span>

                  <!-- Chip Descuento -->
                  <span v-if="product.discount_percentage > 0" class="custom-badge-discount">
                    -{{ product.discount_percentage }}%
                  </span>
                </div>

                <!-- Subtítulo: Principio activo | Lab (Rosa) | Ubicación (Verde) -->
                <div class="d-flex align-center flex-wrap gap-1 text-super-xs mt-0.5">
                  <span class="text-disabled text-uppercase">{{ product.active_ingredient || '—' }}</span>
                  <span class="text-disabled">|</span>
                  <span class="custom-lab-text text-uppercase truncate" style="max-inline-size: 140px;">
                    {{ product.laboratory_name || 'GENÉRICO' }}
                  </span>
                  <template v-if="getProductLocations(product).length > 0">
                    <span class="text-disabled">|</span>
                    <span class="custom-location-text text-uppercase">
                      📍 {{ getProductLocations(product).join(', ') }}
                    </span>
                  </template>
                </div>
              </div>
            </td>

            <!-- Precio USD -->
            <td class="text-right font-weight-bold text-body-2">
              {{ formatCurrency(getProductPriceWithTax(product, 'USD'), 'USD') }}
            </td>

            <!-- Precio BS -->
            <td class="text-right font-weight-bold text-body-2">
              {{ formatCurrency(getProductPriceWithTax(product, 'BS'), 'BS') }}
            </td>

            <!-- Precio COP Destacado en Magenta -->
            <td class="text-right font-weight-black text-body-2 text-primary">
              {{ formatCurrency(getProductPriceWithTax(product, 'COP'), 'COP') }}
            </td>

            <!-- Input Cantidad + Botón Añadir -->
            <td class="text-center">
              <div class="d-flex align-center justify-center gap-1">
                <input
                  type="number"
                  min="1"
                  :value="inputQuantities.get(product.id) || 1"
                  class="custom-quantity-input"
                  @input="(e) => handleQuantityInput(product.id, e.target.value)"
                  @keydown.enter="handleAddProductWithQuantity(product)"
                />
                <VBtn
                  color="primary"
                  size="small"
                  variant="flat"
                  class="rounded-lg px-2 custom-add-btn"
                  @click="handleAddProductWithQuantity(product)"
                  title="Añadir a la orden"
                >
                  <VIcon icon="tabler-plus" size="18" />
                </VBtn>
              </div>
            </td>

            <!-- Acciones: Alternativas y Falla -->
            <td class="text-center">
              <div class="d-flex align-center justify-center gap-1">
                <VBtn
                  icon="tabler-eye"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  class="rounded-lg"
                  title="Alternativas"
                />
                <VBtn
                  icon="tabler-alert-triangle"
                  size="x-small"
                  variant="tonal"
                  color="error"
                  class="rounded-lg"
                  title="Reportar Falla"
                />
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>

      <!-- Paginación Centrada al Pie -->
      <div class="d-flex justify-center py-3 border-t">
        <VPagination
          v-model="catalogPage"
          :length="totalCatalogPages"
          :total-visible="5"
          density="compact"
          size="small"
          active-color="primary"
        />
      </div>
    </VCard>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL DE PAGO EXACTO DEL TPV (COMPLETO / CONTINGENCIA)                -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <BuysModal
      v-model:isDialogVisible="showCheckoutModal"
      :orderData="offlineOrderData"
      :totalAmount="cartTotal"
      :selectedCurrency="selectedDisplayCurrency"
      :orderProducts="offlineOrderProducts"
      :selectedDisplayCurrency="selectedDisplayCurrency"
      :isOffline="true"
      @purchase-completed="handlePurchaseCompleted"
      @modal-closed="handleModalClosed"
      @finish-and-reload="handleFinishAndReload"
      @printTicke-completed="handlePrintTicket"
    />

    <!-- Snackbar de Producto No Encontrado -->
    <VSnackbar v-model="showNotFoundSnackbar" color="warning" timeout="3500" location="top center">
      {{ notFoundMessage }}
    </VSnackbar>
  </div>
</template>

<style scoped>
/* ─── Estilos Generales y Badges ─────────────────────────────────────────────── */
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.leading-none {
  line-height: 1 !important;
}

.font-weight-950 {
  font-weight: 950 !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ─── Badges de ID y Stock (Verde Esmeralda idéntico al TPV Normal) ──────────── */
.custom-id-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 3px 8px;
  border-radius: 6px;
  background-color: #10b981 !important;
  color: #ffffff !important;
  font-size: 0.8125rem;
  font-weight: 900;
  letter-spacing: 0.5px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

.custom-stock-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 24px;
  padding: 2px 6px;
  border-radius: 6px;
  background-color: #10b981 !important;
  color: #ffffff !important;
  font-size: 0.8125rem;
  font-weight: 900;
}

.custom-stock-badge-empty {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 24px;
  padding: 2px 6px;
  border-radius: 6px;
  background-color: #ef4444 !important;
  color: #ffffff !important;
  font-size: 0.8125rem;
  font-weight: 900;
}

/* ─── Chips de Producto: G (IVA) y COL (Origen) ─────────────────────────────── */
.custom-badge-iva {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.65rem;
  font-weight: 900;
  padding: 1px 5px;
  border-radius: 4px;
  background-color: #cbd5e1;
  color: #334155;
}

.custom-badge-col {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.65rem;
  font-weight: 900;
  padding: 1px 5px;
  border-radius: 4px;
  background-color: #dbeafe;
  color: #1d4ed8;
}

.custom-badge-discount {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.65rem;
  font-weight: 900;
  padding: 1px 5px;
  border-radius: 4px;
  background-color: #dcfce7;
  color: #15803d;
}

/* ─── Laboratorio (Rosa/Magenta) y Ubicación (Verde) ────────────────────────── */
.custom-lab-text {
  color: #9c27b0 !important;
  font-weight: 900 !important;
}

.custom-location-text {
  color: #10b981 !important;
  font-weight: 600 !important;
}

/* ─── Input Cantidad y Botón Añadir en la Tabla ──────────────────────────────── */
.custom-quantity-input {
  width: 52px;
  height: 32px;
  padding: 2px 4px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.22);
  border-radius: 6px;
  text-align: center;
  font-size: 0.875rem;
  font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
  background: white;
  outline: none;
}

.custom-quantity-input:focus {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.15);
}

.custom-add-btn {
  min-width: 32px !important;
  height: 32px !important;
}

/* ─── Tabla Customizada del TPV ─────────────────────────────────────────────── */
.tpv-custom-table :deep(thead th) {
  font-size: 0.75rem !important;
  font-weight: 800 !important;
  color: rgba(var(--v-theme-on-surface), 0.6) !important;
  text-transform: uppercase;
  background-color: #f8fafc !important;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08) !important;
}

.tpv-custom-table :deep(tbody tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

/* ─── Totales y Botón Cancelar ──────────────────────────────────────────────── */
.total-label {
  color: #4b5563 !important;
  font-size: 0.8125rem !important;
  font-weight: 700 !important;
  line-height: 1 !important;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.total-value {
  color: #111827 !important;
  font-size: 0.9375rem !important;
  font-weight: 700 !important;
  line-height: 1 !important;
}

.btn-neutral-cancel {
  border-color: rgba(var(--v-theme-on-surface), 0.22) !important;
  color: rgba(var(--v-theme-on-surface), 0.7) !important;
}

.btn-neutral-cancel:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.04) !important;
  border-color: rgba(var(--v-theme-on-surface), 0.38) !important;
  color: rgba(var(--v-theme-on-surface), 0.9) !important;
}
</style>
