import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useExpiryStore } from '../expiry-store'
import axios from '@/plugins/axios'

vi.mock('@/plugins/axios', () => ({
  default: {
    get: vi.fn(),
  },
}))

describe('Expiry Pinia Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('debe establecer valores iniciales correctos', () => {
    const store = useExpiryStore()
    expect(store.loading).toBe(true)
    expect(store.dashboardData.kpis.total_cost_merma_month).toBe(0)
    expect(store.filters.search).toBe('')
  })

  it('debe obtener y guardar los datos de BI dashboardData al ejecutar fetchDashboardData', async () => {
    const store = useExpiryStore()
    const mockData = {
      horizon: ['1m', '3m'],
      kpis: { total_units_expired_month: 120, total_cost_merma_month: 2500.5 },
    }

    axios.get.mockResolvedValueOnce({ data: mockData })

    await store.fetchDashboardData()

    expect(axios.get).toHaveBeenCalledWith('/bi/expiry', { params: store.filters })
    expect(store.dashboardData).toEqual(mockData)
    expect(store.loading).toBe(false)
  })

  it('debe poder actualizar un filtro individual y recargar datos automáticamente', async () => {
    const store = useExpiryStore()
    axios.get.mockResolvedValueOnce({ data: {} })

    await store.setFilter('search', 'Ibuprofeno')

    expect(store.filters.search).toBe('Ibuprofeno')
    expect(axios.get).toHaveBeenCalled()
  })

  it('debe poder limpiar (reset) todos los filtros a sus estados por defecto', async () => {
    const store = useExpiryStore()
    store.filters.search = 'Paracetamol'
    store.filters.semaphore = 'rojo'

    axios.get.mockResolvedValueOnce({ data: {} })

    await store.resetFilters()

    expect(store.filters.search).toBe('')
    expect(store.filters.semaphore).toBeNull()
    expect(axios.get).toHaveBeenCalled()
  })
})
