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
 * Sincroniza silenciosamente el catálogo de productos y tasas de cambio en IndexedDB
 * sin interrumpir la navegación ni la experiencia del usuario.
 */
export async function syncCatalogInBackground() {
  if (!navigator.onLine || isSyncingGlobalCatalog.value) return false
  isSyncingGlobalCatalog.value = true

  try {
    const db = await getOfflineDB()

    // 1. Sincronizar tasas de cambio
    try {
      const ratesRes = await axios.get('/public/exchange-rates')
      const ratesData = ratesRes.data?.data || ratesRes.data || []
      const rateBsObj = ratesData.find(r => r.currency_to === 'BS' || r.code === 'BS' || r.currency === 'BS')
      const rateCopObj = ratesData.find(r => r.currency_to === 'COP' || r.code === 'COP' || r.currency === 'COP')

      if (rateBsObj?.rate || rateBsObj?.effective_rate) {
        localStorage.setItem('tpv_offline_rate_bs', String(rateBsObj.effective_rate || rateBsObj.rate))
      }
      if (rateCopObj?.rate || rateCopObj?.effective_rate) {
        localStorage.setItem('tpv_offline_rate_cop', String(rateCopObj.effective_rate || rateCopObj.rate))
      }
    } catch (e) {
      // Silenciar error secundario de tasas
    }

    // 2. Descargar catálogo completo de productos
    const response = await axios.get('/tpv/order', { params: { itemsPerPage: -1 } })
    const rawProducts = response.data?.data || []

    if (Array.isArray(rawProducts) && rawProducts.length > 0) {
      const tx = db.transaction([STORE_PRODUCTS], 'readwrite')
      const store = tx.objectStore(STORE_PRODUCTS)
      
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
