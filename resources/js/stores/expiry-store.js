import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import axios from '@/plugins/axios'

// Estado inicial reutilizable para poder hacer reset limpio
const defaultFilters = () => ({
  search: '',
  semaphore: null,
  laboratory_id: null,
  category_id: null,
  group_id: null,
  location_id: null,
})

const defaultDashboard = () => ({
  horizon: [],
  loss_analysis: [],
  overstock: [],
  kpis: {
    total_units_expired_month: 0,
    total_cost_merma_month: 0,
    hist_units: 0,
    hist_cost: 0,
    current_inv_expired_units: 0,
    current_inv_expired_value: 0,
  },
})

export const useExpiryStore = defineStore('ExpiryStore', () => {
  // Estado
  const loading = ref(false)
  const error = ref(null)
  const dashboardData = reactive(defaultDashboard())
  const filters = reactive(defaultFilters())

  // Acciones
  async function fetchDashboardData() {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get('/bi/expiry', { params: filters })

      // Asignar propiedades directamente para mantener reactividad del objeto reactive
      Object.assign(dashboardData, defaultDashboard(), response.data)
    } catch (err) {
      console.error('Error fetching expiry dashboard data:', err)
      // Exponer el error para que la vista pueda mostrarlo al usuario
      error.value = err?.response?.data?.message ?? 'Error al cargar los datos del reporte.'
    } finally {
      loading.value = false
    }
  }

  function setFilter(key, value) {
    filters[key] = value
    fetchDashboardData()
  }

  function resetFilters() {
    Object.assign(filters, defaultFilters())
    fetchDashboardData()
  }

  return {
    // Estado expuesto
    loading,
    error,
    dashboardData,
    filters,
    // Acciones
    fetchDashboardData,
    setFilter,
    resetFilters,
  }
})
