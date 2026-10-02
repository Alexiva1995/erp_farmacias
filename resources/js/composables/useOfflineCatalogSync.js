import axios from '@/plugins/axios'
import { ref } from 'vue'

const DB_NAME = 'ErpFarmaciasOfflineDB'
const DB_VERSION = 1
const STORE_PRODUCTS = 'products'
const STORE_ORDERS = 'offline_orders'

let dbPromise = null

export const getOfflineDB = () => {
  if (dbPromise) return dbPromise

  dbPromise = new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION)

    request.onerror = (event) => {
      console.error('[Offline DB] Error abriendo IndexedDB:', event.target.error)
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
      resolve(event.target.result)
    }
  })

  return dbPromise
}

export const isSyncingGlobalCatalog = ref(false)
export const lastGlobalCatalogSync = ref(localStorage.getItem('tpv_offline_catalog_sync') || '')

/**
 * Sincroniza silenciosamente el catálogo completo de productos con ID, laboratorio,
 * principio activo, ubicación, impuestos y tasas de cambio en IndexedDB.
 */
export async function syncCatalogInBackground() {
  if (!navigator.onLine || isSyncingGlobalCatalog.value) return false
  isSyncingGlobalCatalog.value = true

  try {
    const db = await getOfflineDB()

    // 1. Sincronizar tasas de cambio
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

      // Para farmacia la tasa de Bs estándar es EUR o BS o BCV
      const activeBsRate = rateEur || rateBs || rateBcv || rateBinance
      const activeCopRate = rateCop

      if (activeBsRate && !isNaN(activeBsRate) && activeBsRate > 0) {
        localStorage.setItem('tpv_offline_rate_bs', String(activeBsRate))
      }
      if (activeCopRate && !isNaN(activeCopRate) && activeCopRate > 0) {
        localStorage.setItem('tpv_offline_rate_cop', String(activeCopRate))
      }
    } catch (e) {
      console.warn('[Offline Sync] Error sincronizando tasas:', e)
    }

    // 2. Descargar catálogo completo de productos del TPV
    const response = await axios.get('/tpv/order', { params: { itemsPerPage: -1 } })
    const rawProducts = response.data?.data || []

    if (Array.isArray(rawProducts) && rawProducts.length > 0) {
      const tx = db.transaction([STORE_PRODUCTS], 'readwrite')
      const store = tx.objectStore(STORE_PRODUCTS)
      
      store.clear()

      const normalizedList = rawProducts.map(p => {
        const rawPrice = Number(p.sale_price ?? p.price ?? p.base_price ?? p.unit_price_usd ?? 0)
        const rawOfferPrice = p.offer_price ?? p.offer_price_usd ?? p.individual_offer_price
        const rawStock = Number(p.valid_stock_sum ?? p.stock ?? p.total_stock ?? 0)

        return {
          id: p.id,
          barcode: String(p.barcode || p.code || '').trim(),
          name: p.name || p.title || 'Producto sin nombre',
          active_ingredient: p.active_ingredient || '',
          laboratory_name: p.laboratory_name || (p.laboratory ? p.laboratory.name : 'Genérico'),
          laboratory_id: p.laboratory_id || null,
          location: p.location || '',
          iva: Number(p.iva || 0),
          is_colombian_origin: Number(p.is_colombian_origin || 0),
          psychotropic: Number(p.psychotropic || 0),
          base_price_usd: rawPrice,
          price_bs: Number(p.price_bs || 0),
          price_cop: Number(p.price_cop || 0),
          stock: rawStock,
          valid_stock_sum: rawStock,
          has_individual_offer: Boolean(p.has_individual_offer || p.is_offer_individual || p.discount_percentage > 0),
          offer_price_usd: rawOfferPrice ? Number(rawOfferPrice) : null,
          offer_expires_at: p.offer_expires_at || p.individual_offer_expires_at || null,
          discount_percentage: Number(p.discount_percentage || 0),
          discount_type: p.discount_type || null,
        }
      })

      normalizedList.forEach(item => store.put(item))

      await new Promise((res) => {
        tx.oncomplete = () => {
          const nowStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
          lastGlobalCatalogSync.value = nowStr
          localStorage.setItem('tpv_offline_catalog_sync', nowStr)
          localStorage.setItem('tpv_offline_catalog_count', String(normalizedList.length))
          res(true)
        }
      })
      return true
    }
  } catch (error) {
    console.warn('[Offline Sync] Fallo en sincronización silenciosa del catálogo:', error)
  } finally {
    isSyncingGlobalCatalog.value = false
  }
  return false
}
