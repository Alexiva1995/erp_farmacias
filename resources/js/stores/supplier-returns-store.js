import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
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
  const loading = ref(false)
  const error   = ref(null)
  const data    = reactive(defaultData())
  const filters = reactive(defaultFilters())

  async function fetchReport() {
    loading.value = true
    error.value   = null
    try {
      const response = await axios.get('/bi/supplier-returns', { params: filters })
      Object.assign(data, defaultData(), response.data)
    } catch (err) {
      console.error('Error fetching supplier returns report:', err)
      error.value = err?.response?.data?.message ?? 'Error al cargar el reporte de devoluciones.'
    } finally {
      loading.value = false
    }
  }

  function resetFilters() {
    Object.assign(filters, defaultFilters())
    fetchReport()
  }

  return {
    loading,
    error,
    data,
    filters,
    fetchReport,
    resetFilters,
  }
})
