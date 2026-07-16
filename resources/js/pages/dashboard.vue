<template>
  <div>
    <div v-if="!brandingStore.isLoaded" class="d-flex align-center justify-center py-12">
      <VProgressCircular indeterminate color="primary" />
    </div>
    <div v-else-if="brandingStore.settings.business_type === 'minimarket'">
      <MinimarketDashboard />
    </div>
    <div v-else>
      <!-- Fila 1: Felicitaciones y Estadísticas -->
      <VRow class="mb-6 match-height">
      <!-- Tarjeta de Felicitaciones -->
      <VCol cols="12" md="4">
        <VCard class="h-100 bg-light-primary">
          <VCardText class="d-flex flex-column justify-space-between h-100">
            <div class="d-flex align-center gap-3 mb-2">
              <VAvatar size="50" class="leader-avatar border-2 border-white shadow-lg">
                <VImg :src="leader?.photo || '/images/avatars/seller-avatar.png'" />
              </VAvatar>
              <div>
                <h6 class="text-h6 text-primary font-weight-semibold mb-0">
                  ¡Felicitaciones {{ leader?.name || 'Admin' }}! 🎉
                </h6>
                <div class="text-caption text-medium-emphasis">
                  Líder de Ventas
                </div>
              </div>
            </div>
            <div>
              <div class="text-h5 text-primary font-weight-bold">
                {{ formatCurrencyUSD(leader?.sales || 0) }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Tarjeta de Estadísticas -->
      <VCol cols="12" md="8">
        <VCard class="h-100">
          <VCardTitle class="pt-4 px-4 d-flex justify-space-between align-center">
            <span>Estadísticas</span>
            <span class="text-caption text-medium-emphasis">Actualizado hace 1 mes</span>
          </VCardTitle>
          <VCardText class="pa-4 d-flex align-center justify-space-around flex-wrap">
            <!-- Ventas -->
            <div class="d-flex align-center mb-4 mr-4">
              <VAvatar color="primary-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-chart-bar" color="primary" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.sales }}</div>
                <div class="text-caption text-medium-emphasis">Ventas</div>
              </div>
            </div>
            <!-- Clientes -->
            <div class="d-flex align-center mb-4 mr-4">
              <VAvatar color="info-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-users" color="info" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.clients }}</div>
                <div class="text-caption text-medium-emphasis">Clientes Nuevos</div>
              </div>
            </div>
            <!-- Productos -->
            <div class="d-flex align-center mb-4 mr-4">
              <VAvatar color="error-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-box" color="error" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.products }}</div>
                <div class="text-caption text-medium-emphasis">Productos (Unidades)</div>
              </div>
            </div>
            <!-- Ingresos -->
            <div class="d-flex align-center mb-4">
              <VAvatar color="success-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-currency-dollar" color="success" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.revenue }}</div>
                <div class="text-caption text-medium-emphasis">Ganancia</div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Fila 2: Ventas, Gastos, Clientes Nuevos y Reporte de Ingresos -->
    <VRow class="mb-6 match-height">
      <!-- Columna Lateral de Tarjetas (Ventas, Gastos, Clientes Nuevos) -->
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

      <!-- Columna del Reporte de Ingresos Mensuales -->
      <VCol cols="12" md="8">
        <EcommerceRevenueReport class="h-100" />
      </VCol>
    </VRow>

    <!-- Fila 3: Reportes Detallados -->
    <VRow class="mb-6 match-height">
      <!-- Cierres Diarios Recientes -->
      <VCol cols="12" md="4">
        <VCard class="h-100">
          <VCardTitle class="pt-4 px-4 d-flex justify-space-between align-center">
            <span>Cierres Diarios Recientes</span>
            <VIcon icon="tabler-calendar-stats" class="text-medium-emphasis" size="20" />
          </VCardTitle>
          <VCardText class="pa-0">
            <VList density="compact">
              <VListItem v-for="(closure, index) in recentClosures" :key="closure.id || index" class="px-4 py-2">
                <template #prepend>
                  <VAvatar color="success-lighten-5" size="36" class="mr-3" rounded="lg">
                    <VIcon icon="tabler-calendar-event" color="success" size="20" />
                  </VAvatar>
                </template>
                <VListItemTitle class="text-body-2 font-weight-medium">
                  {{ formatDate(closure.created_at) }}
                </VListItemTitle>
                <VListItemSubtitle class="text-xs text-medium-emphasis">
                  Cierre #{{ closure.id }} • Consolidado
                </VListItemSubtitle>
                <template #append>
                  <span class="text-body-2 font-weight-bold text-success">
                    {{ formatCurrencyUSD(closure.total_sales) }}
                  </span>
                </template>
              </VListItem>
              <VListItem v-if="recentClosures.length === 0" class="px-4 py-6 text-center text-medium-emphasis text-caption">
                Sin cierres registrados recientemente
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Popular Products -->
      <VCol cols="12" md="4">
        <VCard class="h-100">
          <VCardTitle class="pt-4 px-4">Productos Populares</VCardTitle>
          <VCardText class="pa-0">
            <VList density="compact">
              <VListItem v-for="(prod, index) in popularProducts" :key="index" class="px-4 py-2">
                <template #prepend>
                  <VAvatar color="primary-lighten-5" size="36" class="mr-3" rounded="lg">
                    <VIcon icon="tabler-package" color="primary" size="20" />
                  </VAvatar>
                </template>
                <VListItemTitle class="text-body-2 font-weight-medium">{{ prod.name }}</VListItemTitle>
                <VListItemSubtitle class="text-caption text-medium-emphasis">
                  {{ prod.laboratory }} · {{ prod.quantity }} uds
                </VListItemSubtitle>
                <template #append>
                  <span class="text-body-2 font-weight-bold">{{ formatCurrencyUSD(prod.price) }}</span>
                </template>
              </VListItem>
              <VListItem v-if="popularProducts.length === 0" class="px-4 py-6 text-center text-medium-emphasis text-caption">
                Sin productos vendidos este mes
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Transactions -->
      <VCol cols="12" md="4">
        <VCard class="h-100">
          <VCardTitle class="pt-4 px-4">Transacciones</VCardTitle>
          <VCardText class="pa-0">
            <VList density="compact">
              <VListItem v-for="(tx, index) in transactions" :key="index" class="px-4 py-2">
                <template #prepend>
                  <VAvatar :color="tx.color + '-lighten-5'" size="36" class="mr-3" rounded="lg">
                    <VIcon :icon="tx.icon" :color="tx.color" size="20" />
                  </VAvatar>
                </template>
                <VListItemTitle class="text-body-2 font-weight-medium">{{ tx.title }}</VListItemTitle>
                <VListItemSubtitle class="text-caption text-medium-emphasis">{{ tx.subtitle }}</VListItemSubtitle>
                <template #append>
                  <span :class="`text-body-2 font-weight-bold ${tx.amount > 0 ? 'text-success' : 'text-error'}`">
                    {{ tx.amount > 0 ? '+' : '' }}{{ formatCurrencyUSD(tx.amount) }}
                  </span>
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Sección de Analytics -->
    <Suspense>
      <template #default>
        <div class="mb-6">
          <VRow class="match-height">
            <!-- Columna de Ventas (Promedio y Resumen) -->
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
            <!-- Columna de Informe de Ganancias -->
            <VCol cols="12" md="8">
              <AnalyticsEarningReportsWeeklyOverview />
            </VCol>
          </VRow>

          <VRow class="mt-6 match-height">
            <!-- Tracker de Ventas (50% ancho) -->
            <VCol cols="12" md="6">
              <AnalyticsSupportTracker />
            </VCol>
            
            <!-- Ventas por Laboratorio (Monto - 25% ancho) -->
            <VCol cols="12" md="3">
              <AnalyticsSalesByCountries />
            </VCol>

            <!-- Ventas por Laboratorio (Unidades - 25% ancho) -->
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
        <VRow class="match-height">
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
                <p class="my-1">{{ demo.subTitle }}</p>
                <p class="mb-3 text-high-emphasis">{{ demo.stat }}</p>
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
          <VCol cols="12" md="4">
            <CrmSalesByCountries />
          </VCol>
          <VCol cols="12" md="4">
            <CrmProjectStatus />
          </VCol>
          <VCol cols="12" md="4">
            <CrmActiveProject />
          </VCol>
          <VCol cols="12" md="6">
            <CrmActivityTimeline />
          </VCol>
          <VCol cols="12" md="6">
            <CrmRecentTransactions />
          </VCol>
        </VRow>
      </template>
      <template #fallback>
        <div class="d-flex justify-center pa-10">
          <VProgressCircular indeterminate color="primary" />
        </div>
      </template>
    </Suspense>

    <!-- Sección de Logística -->
    <Suspense>
      <template #default>
        <div class="mb-6">
          <VRow class="match-height">
            <VCol cols="12">
              <LogisticsCardStatistics />
            </VCol>
            
            <VCol cols="12" md="6">
              <LogisticsVehicleOverview />
            </VCol>
            <VCol cols="12" md="6">
              <LogisticsShipmentStatistics />
            </VCol>

            <VCol cols="12" md="6">
              <LogisticsDeliveryPerformance />
            </VCol>
            <VCol cols="12" md="6">
              <AcademyTopicYouAreInterested />
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

    <!-- Sección de Academia -->
    <Suspense>
      <template #default>
        <div>
          <VRow class="match-height">
            <VCol cols="12" md="8">
              <AcademyCardPopularInstructors />
            </VCol>
            <VCol cols="12" md="4" sm="6">
              <AcademyCardTopCourses />
            </VCol>
            
            <!-- Tabla de Facturas Cargadas (Sustituyendo Cursos que estás tomando) -->
            <VCol cols="12">
              <VCard title="Facturas Cargadas" subtitle="Listado de facturas en estado cargado">
                <template #append>
                  <VBtn
                    icon
                    variant="text"
                    size="small"
                    @click="fetchLoadedInvoices"
                  >
                    <VIcon icon="tabler-refresh" />
                  </VBtn>
                </template>
                <VCardText>
                  <InvoiceTable
                    :invoices="loadedInvoices"
                    :loading="loadingInvoices"
                    :total-invoices="totalLoadedInvoices"
                    :items-per-page="invoicesItemsPerPage"
                    :page="invoicesPage"
                    :is-admin="authStore.isAdmin"
                    actions-mode="approval"
                    @update:options="(opt) => {
                      invoicesPage = opt.page;
                      invoicesItemsPerPage = opt.itemsPerPage;
                      fetchLoadedInvoices();
                    }"
                  />
                </VCardText>
              </VCard>
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
    </div>
  </div>
</template>

<script setup>
import EcommerceRevenueReport from "@/components/EcommerceRevenueReport.vue";
import EcommerceTotalProfitLineCharts from "@/components/EcommerceTotalProfitLineCharts.vue";
import EcommerceExpensesRadialBarCharts from "@/components/EcommerceExpensesRadialBarCharts.vue";
import EcommerceGeneratedLeads from "@/components/EcommerceGeneratedLeads.vue";
import { useAuthStore } from "@/stores/auth";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";

// Componentes de Analytics
import AnalyticsAverageDailySales from '@/views/dashboards/analytics/AnalyticsAverageDailySales.vue'
import AnalyticsEarningReportsWeeklyOverview from '@/views/dashboards/analytics/AnalyticsEarningReportsWeeklyOverview.vue'
import AnalyticsMonthlyCampaignState from '@/views/dashboards/analytics/AnalyticsMonthlyCampaignState.vue'
import AnalyticsProjectTable from '@/views/dashboards/analytics/AnalyticsProjectTable.vue'
import AnalyticsSalesByCountries from '@/views/dashboards/analytics/AnalyticsSalesByCountries.vue'
import AnalyticsSalesOverview from '@/views/dashboards/analytics/AnalyticsSalesOverview.vue'
import AnalyticsSourceVisits from '@/views/dashboards/analytics/AnalyticsSourceVisits.vue'
import AnalyticsSupportTracker from '@/views/dashboards/analytics/AnalyticsSupportTracker.vue'
import AnalyticsTotalEarning from '@/views/dashboards/analytics/AnalyticsTotalEarning.vue'
import AnalyticsWebsiteAnalytics from '@/views/dashboards/analytics/AnalyticsWebsiteAnalytics.vue'

// Componentes de CRM
import CrmActiveProject from '@/views/dashboards/crm/CrmActiveProject.vue'
import CrmActivityTimeline from '@/views/dashboards/crm/CrmActivityTimeline.vue'
import CrmAnalyticsSales from '@/views/dashboards/crm/CrmAnalyticsSales.vue'
import CrmEarningReportsYearlyOverview from '@/views/dashboards/crm/CrmEarningReportsYearlyOverview.vue'
import CrmOrderBarChart from '@/views/dashboards/crm/CrmOrderBarChart.vue'
import CrmProjectStatus from '@/views/dashboards/crm/CrmProjectStatus.vue'
import CrmRecentTransactions from '@/views/dashboards/crm/CrmRecentTransactions.vue'
import CrmRevenueGrowth from '@/views/dashboards/crm/CrmRevenueGrowth.vue'
import CrmSalesAreaCharts from '@/views/dashboards/crm/CrmSalesAreaCharts.vue'
import CrmSalesByCountries from '@/views/dashboards/crm/CrmSalesByCountries.vue'

// Componentes de Logística
import LogisticsCardStatistics from '@/views/apps/logistics/LogisticsCardStatistics.vue'
import LogisticsDeliveryExpectations from '@/views/apps/logistics/LogisticsDeliveryExpectations.vue'
import LogisticsDeliveryPerformance from '@/views/apps/logistics/LogisticsDeliveryPerformance.vue'
import LogisticsOrderByCountries from '@/views/apps/logistics/LogisticsOrderByCountries.vue'
import LogisticsOverviewTable from '@/views/apps/logistics/LogisticsOverviewTable.vue'
import LogisticsShipmentStatistics from '@/views/apps/logistics/LogisticsShipmentStatistics.vue'
import LogisticsVehicleOverview from '@/views/apps/logistics/LogisticsVehicleOverview.vue'
// Componentes de Academia
import AcademyCardPopularInstructors from '@/views/apps/academy/AcademyCardPopularInstructors.vue'
import AcademyCardTopCourses from '@/views/apps/academy/AcademyCardTopCourses.vue'
import AcademyCourseTable from '@/views/apps/academy/AcademyCourseTable.vue'
import AcademyTopicYouAreInterested from '@/views/apps/academy/AcademyTopicYouAreInterested.vue'

// Componentes de Facturas (Integración Home)
import InvoiceTable from "@/components/InvoiceTable.vue";

import customCheck from '@images/svg/Check.svg'
import customLaptop from '@images/svg/laptop.svg'
import customLightbulb from '@images/svg/lightbulb.svg'

import { useBrandingStore } from "@/stores/useBrandingStore";
import MinimarketDashboard from "@/components/MinimarketDashboard.vue";

const authStore = useAuthStore();
const router = useRouter();
const brandingStore = useBrandingStore();

// Estado para Facturas Cargadas
const loadedInvoices = ref([]);
const totalLoadedInvoices = ref(0);
const loadingInvoices = ref(false);
const invoicesPage = ref(1);
const invoicesItemsPerPage = ref(5);

const fetchLoadedInvoices = async () => {
  loadingInvoices.value = true;
  try {
    const response = await axios.get("/invoices", {
      params: {
        page: invoicesPage.value,
        itemsPerPage: invoicesItemsPerPage.value,
        status: "loaded",
      }
    });
    loadedInvoices.value = response.data.data;
    totalLoadedInvoices.value = response.data.total;
  } catch (error) {
    console.error("Error al obtener facturas cargadas:", error);
  } finally {
    loadingInvoices.value = false;
  }
};

const simpleStatisticsDemoCards = ref([
  {
    icon: 'tabler-credit-card',
    color: 'success',
    title: 'Ganancia Total',
    subTitle: 'Última Semana',
    stat: '$0',
    change: '0%',
  },
  {
    icon: 'tabler-currency-dollar',
    color: 'success',
    title: 'Venta Total',
    subTitle: 'Última Semana',
    stat: '$0',
    change: '0%',
  },
])

const weeklyOrders = ref({ value: 0, change: 0 })
const weeklySales = ref({ value: 0, change: 0 })
const weeklyProfit = ref({ value: 0, change: 0 })

// Donut Chart Colors (Academia)
const donutChartColors = {
  donut: {
    series1: '#22A95E',
    series2: '#24B364',
    series3: '#56CA00',
    series4: '#53D28C',
    series5: '#7EDDA9',
    series6: '#A9E9C5',
  },
}

// Donuts Chart Config (Academia)
const timeSpendingChartConfig = {
  chart: {
    height: 157,
    width: 130,
    parentHeightOffset: 0,
    type: 'donut',
  },
  labels: ['36h', '56h', '16h', '32h', '56h', '16h'],
  colors: [
    donutChartColors.donut.series1,
    donutChartColors.donut.series2,
    donutChartColors.donut.series3,
    donutChartColors.donut.series4,
    donutChartColors.donut.series5,
    donutChartColors.donut.series6,
  ],
  stroke: {
    width: 0,
  },
  dataLabels: {
    enabled: false,
    formatter(val) {
      return `${Number.parseInt(val)}%`
    },
  },
  legend: {
    show: false,
  },
  tooltip: {
    theme: false,
  },
  grid: {
    padding: {
      top: 0,
    },
  },
  plotOptions: {
    pie: {
      donut: {
        size: '75%',
        labels: {
          show: true,
          value: {
            fontSize: '1.125rem',
            color: 'rgba(var(--v-theme-on-background), var(--v-high-emphasis-opacity))',
            fontWeight: 500,
            offsetY: -15,
            formatter(val) {
              return `${Number.parseInt(val)}%`
            },
          },
          name: {
            offsetY: 20,
          },
          total: {
            show: true,
            fontSize: '15px',
            label: 'Total',
            color: 'rgba(var(--v-theme-on-background), var(--v-disabled-opacity))',
            formatter() {
              return '231h'
            },
          },
        },
      },
    },
  },
}

const timeSpendingChartSeries = [23, 35, 10, 20, 35, 23]

const fetchAnalyticsData = async () => {
  try {
    const response = await axios.get('/dashboard/analytics-data')
    const data = response.data

    if (data.weekly_metrics) {
      // Actualizar Ganancia Total
      simpleStatisticsDemoCards.value[0].stat = formatCurrencyUSD(data.weekly_metrics.profit.value)
      simpleStatisticsDemoCards.value[0].change = `${data.weekly_metrics.profit.change >= 0 ? '+' : ''}${data.weekly_metrics.profit.change}%`
      simpleStatisticsDemoCards.value[0].color = data.weekly_metrics.profit.change >= 0 ? 'success' : 'error'

      // Actualizar Venta Total
      simpleStatisticsDemoCards.value[1].stat = formatCurrencyUSD(data.weekly_metrics.sales.value)
      simpleStatisticsDemoCards.value[1].change = `${data.weekly_metrics.sales.change >= 0 ? '+' : ''}${data.weekly_metrics.sales.change}%`
      simpleStatisticsDemoCards.value[1].color = data.weekly_metrics.sales.change >= 0 ? 'success' : 'error'

      // Guardar para otros componentes
      weeklyOrders.value = data.weekly_metrics.orders
      weeklySales.value = data.weekly_metrics.sales
      weeklyProfit.value = data.weekly_metrics.profit
    }
  } catch (error) {
    console.error('Error al cargar datos extendidos de analíticas:', error)
  }
}

const leader = ref(null);
const stats = ref({
  sales: '$0.00',
  clients: '0',
  products: '0',
  revenue: '$0.00',
});

const fetchStats = async () => {
  try {
    const startOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    const endOfMonth = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0];

    // 1. Ventas desde Cierre de Caja (Monto unificado en USD)
    const cashResponse = await axios.get("/finances/cash-closure/monthlyCash");
    if (cashResponse.data && cashResponse.data.data && cashResponse.data.data.length > 0) {
      // Buscamos el mes actual en los datos (por nombre o tomamos el primero si no se encuentra)
      const monthsEn = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
      const monthsEs = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
      
      const currentMonthIdx = new Date().getMonth();
      const currentYear = new Date().getFullYear().toString();
      
      const currentMonthNameEn = monthsEn[currentMonthIdx];
      const currentMonthNameEs = monthsEs[currentMonthIdx];
      
      const currentMonthData = cashResponse.data.data.find(item => 
        (item.period.includes(currentMonthNameEn) || item.period.includes(currentMonthNameEs)) && 
        item.period.includes(currentYear)
      ) || cashResponse.data.data[0]; // Si no lo encuentra, usa el más reciente (primero)
      
      stats.value.sales = `${currentMonthData.total_usd_equivalent} USD`;
    }

    // 2. Ganancia Real en USD (Precio Venta - Costo) del mes actual
    const profitResponse = await axios.get("/dashboard/profit", {
      params: {
        start_date: startOfMonth,
        end_date: endOfMonth,
      }
    });
    if (profitResponse.data && profitResponse.data.profit !== undefined) {
      stats.value.revenue = formatCurrencyUSD(profitResponse.data.profit);
    }
    
    // 3. Clientes Nuevos
    const clientsResponse = await axios.post("/crm/clients/filtrar-sin-paginar", {
      fechaDesde_filtro: startOfMonth,
      fechaHasta_filtro: endOfMonth,
    });
    if (clientsResponse.data && clientsResponse.data.data) {
      stats.value.clients = clientsResponse.data.data.length.toString();
    }

    // 4. Productos (Unidades Vendidas)
    const unitsResponse = await axios.get("/dashboard/units-sold", {
      params: {
        start_date: startOfMonth,
        end_date: endOfMonth,
      }
    });
    if (unitsResponse.data && unitsResponse.data.units !== undefined) {
      stats.value.products = unitsResponse.data.units.toString();
    }
  } catch (error) {
    console.error("Error fetching stats:", error);
  }
};

const fetchLeader = async () => {
  try {
    const response = await axios.get("/rrhh/employee-performance", {
      params: {
        month: new Date().getMonth() + 1,
        year: new Date().getFullYear(),
      },
    });
    if (response.data && response.data.status) {
      const employees = response.data.data;
      if (employees.length > 0) {
        let maxSales = -1;
        let bestEmployee = null;
        employees.forEach(e => {
          const sales = Number(e.sales || 0);
          if (sales > maxSales) {
            maxSales = sales;
            bestEmployee = e;
          }
        });
        leader.value = bestEmployee;
      }
    }
  } catch (error) {
    console.error("Error fetching leader:", error);
  }
};

const recentClosures = ref([]);

// Obtener los cierres de caja diarios recientes
const fetchRecentClosures = async () => {
  try {
    const response = await axios.get("/finances/cash-closure/dailyCash", {
      params: {
        itemsPerPage: 8,
        page: 1,
        sortBy: 'id',
        orderBy: 'desc',
      }
    });
    if (response.data && response.data.data) {
      recentClosures.value = response.data.data;
    }
  } catch (error) {
    console.error("Error al obtener cierres diarios recientes:", error);
  }
};

// Formatear fecha en español
const formatDate = (dateStr) => {
  if (!dateStr) return "";
  const date = new Date(dateStr);
  const months = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
  return `${date.getDate()} de ${months[date.getMonth()]} ${date.getFullYear()}`;
};

const formatCurrencyUSD = (amount) =>
  new Intl.NumberFormat("es-US", {
    style: "currency",
    currency: "USD",
  }).format(amount);

const popularProducts = ref([]);

const fetchPopularProducts = async () => {
  try {
    const response = await axios.get("/dashboard/popular-products", {
      params: {
        limit: 8,
      }
    });
    if (response.data && response.data.data) {
      popularProducts.value = response.data.data;
    }
  } catch (error) {
    console.error("Error al obtener productos populares:", error);
  }
};

const transactions = ref([]);

const fetchTransactionsSummary = async () => {
  try {
    const response = await axios.get("/finances/transactions/income-summary");
    if (response.data && response.data.data) {
      const typeConfig = {
        CASH: { icon: 'tabler-wallet', color: 'success' },
        CARD: { icon: 'tabler-credit-card', color: 'primary' },
        TRANSFER: { icon: 'tabler-building-bank', color: 'info' },
        MOBILE: { icon: 'tabler-device-mobile', color: 'warning' },
        BINANCE: { icon: 'tabler-currency-bitcoin', color: 'warning' },
        PAYPAL: { icon: 'tabler-brand-paypal', color: 'info' },
        CREDIT: { icon: 'tabler-clock', color: 'error' },
      };

      transactions.value = response.data.data.map(tx => {
        const config = typeConfig[tx.type] || { icon: 'tabler-currency-dollar', color: 'secondary' };
        return {
          title: tx.method,
          subtitle: `Ingresos en ${tx.currency} (USD)`,
          amount: tx.amount,
          icon: config.icon,
          color: config.color,
        };
      });
    }
  } catch (error) {
    console.error("Error al obtener el resumen de transacciones:", error);
  }
};

onMounted(() => {
  if (authStore.isLoaded) {
    fetchLeader();
    fetchStats();
    fetchRecentClosures();
    fetchPopularProducts();
    fetchTransactionsSummary();
    fetchAnalyticsData();
    fetchLoadedInvoices();
  }
});

watch(() => authStore.user, (newUser) => {
  if (newUser) {
    fetchLeader();
    fetchStats();
    fetchRecentClosures();
    fetchPopularProducts();
    fetchTransactionsSummary();
    fetchAnalyticsData();
    fetchLoadedInvoices();
  }
}, { immediate: true });

watch(() => authStore.isLoaded, (isLoaded) => {
  if (isLoaded && !authStore.isAdmin) {
    router.push('/tpv/orderUser');
  }
});
</script>

<style lang="scss">
@use "@core-scss/template/libs/apex-chart.scss";

.match-height .v-col {
  display: flex;
}

.match-height .v-card {
  width: 100%;
}

.bg-light-primary {
  background-color: rgb(var(--v-theme-primary), 0.1) !important;
}
</style>
