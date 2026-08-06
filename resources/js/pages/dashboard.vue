<template>
  <div>
    <div v-if="!brandingStore.isLoaded" class="d-flex align-center justify-center py-12">
      <VProgressCircular indeterminate color="primary" />
    </div>
    <div v-else>
      <!-- Título Semántico para SEO y accesibilidad -->
      <h1 class="d-none">Panel de Control - ERP Farmacias</h1>

      <!-- Fila 1: Felicitaciones y Estadísticas Mensuales -->
      <DashboardLeaderStats
        :leader="leader"
        :stats="stats"
        :loading="loadingStats"
      />

      <!-- Fila 2: Ventas, Gastos, Clientes Nuevos y Reporte de Ingresos -->
      <VRow class="mb-6 match-height">
        <VCol cols="12" md="4">
          <VRow class="match-height">
            <VCol cols="6" class="pb-4">
              <EcommerceTotalProfitLineCharts />
            </VCol>
            <VCol cols="6" class="pb-4">
              <EcommerceExpensesRadialBarCharts />
            </VCol>
            <VCol cols="12">
              <EcommerceGeneratedLeads />
            </VCol>
          </VRow>
        </VCol>

        <VCol cols="12" md="8">
          <EcommerceRevenueReport class="h-100" />
        </VCol>
      </VRow>

      <!-- Fila 3: Cierres, Productos Populares y Transacciones -->
      <DashboardRecentSummaryCards
        :recent-closures="recentClosures"
        :popular-products="popularProducts"
        :transactions="transactions"
        :loading="loadingSummary"
      />

      <!-- Sección de Analytics -->
      <Suspense>
        <template #default>
          <div class="mb-6">
            <VRow class="match-height">
              <VCol cols="12" md="4">
                <VRow>
                  <VCol cols="12">
                    <AnalyticsAverageDailySales />
                  </VCol>
                  <VCol cols="12">
                    <AnalyticsSalesOverview />
                  </VCol>
                </VRow>
              </VCol>
              <VCol cols="12" md="8">
                <AnalyticsEarningReportsWeeklyOverview />
              </VCol>
            </VRow>

            <VRow class="mt-6 match-height">
              <VCol cols="12" md="6">
                <AnalyticsSupportTracker />
              </VCol>
              <VCol cols="12" md="3">
                <AnalyticsSalesByCountries />
              </VCol>
              <VCol cols="12" md="3">
                <AnalyticsMonthlyCampaignState />
              </VCol>
            </VRow>
          </div>
        </template>
        <template #fallback>
          <div class="d-flex justify-center pa-10">
            <VProgressCircular indeterminate color="primary" />
          </div>
        </template>
      </Suspense>

      <!-- Sección de CRM -->
      <Suspense>
        <template #default>
          <VRow class="match-height mb-6">
            <VCol cols="12" md="4" sm="6" lg="2">
              <CrmOrderBarChart :data="weeklyOrders" />
            </VCol>
            <VCol cols="12" md="4" sm="6" lg="2">
              <CrmSalesAreaCharts :data="weeklySales" />
            </VCol>
            <VCol v-for="demo in simpleStatisticsDemoCards" :key="demo.title" cols="12" sm="6" md="4" lg="2">
              <VCard>
                <VCardText>
                  <VAvatar :color="demo.color" variant="tonal" rounded size="44">
                    <VIcon :icon="demo.icon" size="28" />
                  </VAvatar>
                  <h5 class="text-h5 mt-3">{{ demo.title }}</h5>
                  <p class="my-1 text-caption text-medium-emphasis">{{ demo.subTitle }}</p>
                  <p class="mb-3 text-high-emphasis font-weight-bold">{{ demo.stat }}</p>
                  <VChip :color="demo.color" label size="small">{{ demo.change }}</VChip>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="8" lg="4">
              <CrmRevenueGrowth :data="weeklyProfit" />
            </VCol>
            <VCol cols="12" md="8">
              <CrmEarningReportsYearlyOverview />
            </VCol>
            <VCol cols="12" md="4">
              <CrmAnalyticsSales />
            </VCol>
          </VRow>
        </template>
        <template #fallback>
          <div class="d-flex justify-center pa-10">
            <VProgressCircular indeterminate color="primary" />
          </div>
        </template>
      </Suspense>

      <!-- Sección de Facturas Cargadas -->
      <VRow class="mb-6">
        <VCol cols="12">
          <DashboardLoadedInvoices
            :invoices="loadedInvoices"
            :loading="loadingInvoices"
            :total-invoices="totalLoadedInvoices"
            :items-per-page="invoicesItemsPerPage"
            :page="invoicesPage"
            :is-admin="authStore.isAdmin"
            @refresh="fetchLoadedInvoices"
            @update:options="(opt) => {
              invoicesPage = opt.page;
              invoicesItemsPerPage = opt.itemsPerPage;
              fetchLoadedInvoices();
            }"
          />
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from "vue"
import { useRouter } from "vue-router"
import axios from "@/plugins/axios"
import { useAuthStore } from "@/stores/auth"
import { useBrandingStore } from "@/stores/useBrandingStore"

import DashboardLeaderStats from "@/components/dashboard/DashboardLeaderStats.vue"
import DashboardRecentSummaryCards from "@/components/dashboard/DashboardRecentSummaryCards.vue"
import DashboardLoadedInvoices from "@/components/dashboard/DashboardLoadedInvoices.vue"

import EcommerceRevenueReport from "@/components/EcommerceRevenueReport.vue"
import EcommerceTotalProfitLineCharts from "@/components/EcommerceTotalProfitLineCharts.vue"
import EcommerceExpensesRadialBarCharts from "@/components/EcommerceExpensesRadialBarCharts.vue"
import EcommerceGeneratedLeads from "@/components/EcommerceGeneratedLeads.vue"

import AnalyticsAverageDailySales from '@/views/dashboards/analytics/AnalyticsAverageDailySales.vue'
import AnalyticsEarningReportsWeeklyOverview from '@/views/dashboards/analytics/AnalyticsEarningReportsWeeklyOverview.vue'
import AnalyticsMonthlyCampaignState from '@/views/dashboards/analytics/AnalyticsMonthlyCampaignState.vue'
import AnalyticsSalesByCountries from '@/views/dashboards/analytics/AnalyticsSalesByCountries.vue'
import AnalyticsSalesOverview from '@/views/dashboards/analytics/AnalyticsSalesOverview.vue'
import AnalyticsSupportTracker from '@/views/dashboards/analytics/AnalyticsSupportTracker.vue'

import CrmAnalyticsSales from '@/views/dashboards/crm/CrmAnalyticsSales.vue'
import CrmEarningReportsYearlyOverview from '@/views/dashboards/crm/CrmEarningReportsYearlyOverview.vue'
import CrmOrderBarChart from '@/views/dashboards/crm/CrmOrderBarChart.vue'
import CrmRevenueGrowth from '@/views/dashboards/crm/CrmRevenueGrowth.vue'
import CrmSalesAreaCharts from '@/views/dashboards/crm/CrmSalesAreaCharts.vue'

const authStore = useAuthStore()
const router = useRouter()
const brandingStore = useBrandingStore()

// Facturas
const loadedInvoices = ref([])
const totalLoadedInvoices = ref(0)
const loadingInvoices = ref(false)
const invoicesPage = ref(1)
const invoicesItemsPerPage = ref(5)

const fetchLoadedInvoices = async () => {
  loadingInvoices.value = true
  try {
    const response = await axios.get("/invoices", {
      params: {
        page: invoicesPage.value,
        itemsPerPage: invoicesItemsPerPage.value,
        status: "loaded",
      }
    })
    loadedInvoices.value = response.data.data
    totalLoadedInvoices.value = response.data.total
  } catch (error) {
    console.error("Error al obtener facturas cargadas:", error)
  } finally {
    loadingInvoices.value = false
  }
}

// Stats & Leader
const leader = ref(null)
const stats = ref({
  sales: '$0.00',
  clients: '0',
  products: '0',
  revenue: '$0.00',
})
const loadingStats = ref(true)

const fetchStats = async () => {
  loadingStats.value = true
  try {
    const startOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]
    const endOfMonth = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0]

    const cashResponse = await axios.get("/finances/cash-closure/monthlyCash")
    if (cashResponse.data?.data?.length > 0) {
      const currentMonthData = cashResponse.data.data[0]
      stats.value.sales = `${currentMonthData.total_usd_equivalent} USD`
    }

    const profitResponse = await axios.get("/dashboard/profit", {
      params: { start_date: startOfMonth, end_date: endOfMonth }
    })
    if (profitResponse.data?.profit !== undefined) {
      stats.value.revenue = formatCurrencyUSD(profitResponse.data.profit)
    }

    const clientsResponse = await axios.post("/crm/clients/count", {
      fechaDesde_filtro: startOfMonth,
      fechaHasta_filtro: endOfMonth,
    })
    if (clientsResponse.data?.status && clientsResponse.data?.data) {
      stats.value.clients = clientsResponse.data.data.count.toString()
    }

    const unitsResponse = await axios.get("/dashboard/units-sold", {
      params: { start_date: startOfMonth, end_date: endOfMonth }
    })
    if (unitsResponse.data?.units !== undefined) {
      stats.value.products = unitsResponse.data.units.toString()
    }
  } catch (error) {
    console.error("Error fetching stats:", error)
  } finally {
    loadingStats.value = false
  }
}

const fetchLeader = async () => {
  try {
    const response = await axios.get("/rrhh/employee-performance", {
      params: {
        month: new Date().getMonth() + 1,
        year: new Date().getFullYear(),
      },
    })
    if (response.data?.status && response.data.data?.length > 0) {
      leader.value = response.data.data.reduce((prev, curr) => 
        Number(curr.sales || 0) > Number(prev.sales || 0) ? curr : prev
      )
    }
  } catch (error) {
    console.error("Error fetching leader:", error)
  }
}

// Cierres, Populares y Transacciones
const recentClosures = ref([])
const popularProducts = ref([])
const transactions = ref([])
const loadingSummary = ref(true)

const fetchSummaryCardsData = async () => {
  loadingSummary.value = true
  try {
    await Promise.all([
      axios.get("/finances/cash-closure/dailyCash", { params: { itemsPerPage: 8, page: 1, sortBy: 'id', orderBy: 'desc' } })
        .then(res => { if (res.data?.data) recentClosures.value = res.data.data }),
      axios.get("/dashboard/popular-products", { params: { limit: 8 } })
        .then(res => { if (res.data?.data) popularProducts.value = res.data.data }),
      axios.get("/finances/transactions/income-summary")
        .then(res => {
          if (res.data?.data) {
            const typeConfig = {
              CASH: { icon: 'tabler-wallet', color: 'success' },
              CARD: { icon: 'tabler-credit-card', color: 'primary' },
              TRANSFER: { icon: 'tabler-building-bank', color: 'info' },
              MOBILE: { icon: 'tabler-device-mobile', color: 'warning' },
              BINANCE: { icon: 'tabler-currency-bitcoin', color: 'warning' },
              PAYPAL: { icon: 'tabler-brand-paypal', color: 'info' },
              CREDIT: { icon: 'tabler-clock', color: 'error' },
            }
            transactions.value = res.data.data.map(tx => ({
              title: tx.method,
              subtitle: `Ingresos en ${tx.currency} (USD)`,
              amount: tx.amount,
              icon: (typeConfig[tx.type] || { icon: 'tabler-currency-dollar' }).icon,
              color: (typeConfig[tx.type] || { color: 'secondary' }).color,
            }))
          }
        })
    ])
  } catch (error) {
    console.error("Error al cargar tarjetas de resumen:", error)
  } finally {
    loadingSummary.value = false
  }
}

// CRM Data & Analytics
const simpleStatisticsDemoCards = ref([
  { icon: 'tabler-credit-card', color: 'success', title: 'Ganancia Total', subTitle: 'Última Semana', stat: '$0', change: '0%' },
  { icon: 'tabler-currency-dollar', color: 'success', title: 'Venta Total', subTitle: 'Última Semana', stat: '$0', change: '0%' },
])
const weeklyOrders = ref({ value: 0, change: 0 })
const weeklySales = ref({ value: 0, change: 0 })
const weeklyProfit = ref({ value: 0, change: 0 })

const fetchAnalyticsData = async () => {
  try {
    const response = await axios.get('/dashboard/analytics-data')
    const data = response.data
    if (data.weekly_metrics) {
      simpleStatisticsDemoCards.value[0].stat = formatCurrencyUSD(data.weekly_metrics.profit.value)
      simpleStatisticsDemoCards.value[0].change = `${data.weekly_metrics.profit.change >= 0 ? '+' : ''}${data.weekly_metrics.profit.change}%`
      simpleStatisticsDemoCards.value[0].color = data.weekly_metrics.profit.change >= 0 ? 'success' : 'error'

      simpleStatisticsDemoCards.value[1].stat = formatCurrencyUSD(data.weekly_metrics.sales.value)
      simpleStatisticsDemoCards.value[1].change = `${data.weekly_metrics.sales.change >= 0 ? '+' : ''}${data.weekly_metrics.sales.change}%`
      simpleStatisticsDemoCards.value[1].color = data.weekly_metrics.sales.change >= 0 ? 'success' : 'error'

      weeklyOrders.value = data.weekly_metrics.orders
      weeklySales.value = data.weekly_metrics.sales
      weeklyProfit.value = data.weekly_metrics.profit
    }
  } catch (error) {
    console.error('Error al cargar datos de analíticas:', error)
  }
}

const formatCurrencyUSD = (amount) =>
  new Intl.NumberFormat("es-US", { style: "currency", currency: "USD" }).format(amount)

watch(() => authStore.user, (newUser) => {
  if (newUser) {
    fetchLeader()
    fetchStats()
    fetchSummaryCardsData()
    fetchAnalyticsData()
    fetchLoadedInvoices()
  }
}, { immediate: true })

watch(() => authStore.isLoaded, (isLoaded) => {
  if (isLoaded && !authStore.isAdmin) {
    router.push('/tpv/orderUser')
  }
})
</script>

<style lang="scss">
@use "@core-scss/template/libs/apex-chart.scss";

.match-height .v-col {
  display: flex;
}
.match-height .v-card {
  width: 100%;
}
</style>
