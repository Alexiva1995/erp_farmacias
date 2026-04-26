import { defineStore } from 'pinia'
import axios from '@/plugins/axios'

export const useExpiryStore = defineStore('ExpiryStore', {
  state: () => ({
    loading: true,
    dashboardData: {
      horizon: [],
      annual_trend: [],
      loss_analysis: [],
      risk_inventory: [],
      overstock: [],
      kpis: {
        total_units_expired_month: 0,
        total_cost_merma_month: 0
      }
    },
    filters: {
      search: '',
      semaphore: null,
      laboratory_id: null,
      category_id: null,
      group_id: null,
      location_id: null
    }
  }),

  actions: {
    async fetchDashboardData() {
      this.loading = true
      try {
        const response = await axios.get('/bi/expiry', { params: this.filters })
        this.dashboardData = response.data
      } catch (error) {
        console.error('Error fetching expiry dashboard data:', error)
      } finally {
        this.loading = false
      }
    },

    setFilter(key, value) {
      this.filters[key] = value
      this.fetchDashboardData()
    },

    resetFilters() {
      this.filters = {
        search: '',
        semaphore: null,
        laboratory_id: null,
        category_id: null,
        group_id: null,
        location_id: null
      }
      this.fetchDashboardData()
    }
  }
})
