import { defineStore } from 'pinia'
import { ref, reactive, computed } from 'vue'
import axios from '@/plugins/axios'

const defaultFilters = () => ({
  search: '',
  laboratory_id: null,
  supplier_id: null,
})

const defaultData = () => ({
  groups: [],
  summary: {
    total_laboratories: 0,
    total_products: 0,
    total_lots: 0,
    total_units: 0,
    total_amount: 0,
  },
  metadata: {},
})

export const useSupplierReturnsStore = defineStore('SupplierReturnsStore', () => {
  // ── Estado del reporte ───────────────────────────────────────────────────────
  const loading         = ref(false)
  const catalogsLoading = ref(false)
  const error           = ref(null)
  const data            = reactive(defaultData())
  const filters         = reactive(defaultFilters())

  // ── Catálogos para filtros (gestionados en el store, no en la página) ────────
  const laboratories = ref([])
  const suppliers    = ref([])

  // ── Derived state ────────────────────────────────────────────────────────────
  const hasGroups = computed(() => data.groups.length > 0)
  const hasActiveFilters = computed(() =>
    !!(filters.laboratory_id || filters.supplier_id || filters.search)
  )

  // ── Acciones ─────────────────────────────────────────────────────────────────

  /**
   * Carga catálogos de laboratorios y proveedores en paralelo.
   * Solo selecciona los campos id y name para minimizar el payload.
   */
  async function fetchCatalogs() {
    catalogsLoading.value = true
    try {
      const [labRes, supRes] = await Promise.all([
        axios.get('/laboratories').catch(() => ({ data: [] })),
        axios.get('/suppliers').catch(() => ({ data: [] })),
      ])
      laboratories.value = normalizeList(labRes.data)
      suppliers.value    = normalizeList(supRes.data)
    } catch (err) {
      console.error('[SupplierReturnsStore] Error cargando catálogos:', err)
    } finally {
      catalogsLoading.value = false
    }
  }

  /** Carga el reporte de devoluciones con los filtros activos. */
  async function fetchReport() {
    loading.value = true
    error.value   = null

    try {
      const response = await axios.get('/bi/supplier-returns', { params: filters })
      // El Resource de Laravel envuelve la data dentro de { data: { ... } }
      const payload = response.data?.data ?? response.data
      Object.assign(data, defaultData(), payload)
    } catch (err) {
      // Distinguir error de validación (422) de error interno (500+)
      if (err?.response?.status === 422) {
        error.value = 'Filtro inválido: ' + (err.response.data?.message ?? 'verifica los parámetros.')
      } else if (err?.response?.status >= 500) {
        error.value = 'Error interno del servidor. Por favor, intenta de nuevo.'
      } else {
        error.value = err?.response?.data?.message ?? 'Error al cargar el reporte.'
      }
      console.error('[SupplierReturnsStore] Error:', err)
    } finally {
      loading.value = false
    }
  }

  /** Reinicia filtros y vuelve a cargar el reporte. */
  function resetFilters() {
    Object.assign(filters, defaultFilters())
    fetchReport()
  }

  // ── Helpers ──────────────────────────────────────────────────────────────────

  /** Normaliza respuestas paginadas y no paginadas de los catálogos. */
  function normalizeList(responseData) {
    if (Array.isArray(responseData)) return responseData
    if (Array.isArray(responseData?.data)) return responseData.data
    return []
  }

  return {
    // Estado
    loading,
    catalogsLoading,
    error,
    data,
    filters,
    laboratories,
    suppliers,
    // Computed
    hasGroups,
    hasActiveFilters,
    // Acciones
    fetchCatalogs,
    fetchReport,
    resetFilters,
  }
})
