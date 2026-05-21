<script setup>
import axios from '@/plugins/axios'
import { hexToRgb } from '@layouts/utils'
import { computed, onMounted, ref, watch } from 'vue'
import { useTheme } from 'vuetify'

const vuetifyTheme = useTheme()

// ── Estado ────────────────────────────────────────────────────────────────────
const currentTab   = ref(0)
const selectedYear = ref(new Date().getFullYear())
const loading      = ref(false)
const monthlyData  = ref([])
const summary      = ref({
  total_orders:   0,
  total_sales:    0,
  total_profit:   0,
  total_expenses: 0,
  total_income:   0,
  net_revenue:    0,
})

const availableYears = computed(() => {
  const y = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => y - i)
})

// ── Nombres de meses ──────────────────────────────────────────────────────────
const monthLabels = computed(() =>
  monthlyData.value.map(d => {
    const names = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']
    return names[(d.month ?? 1) - 1] ?? ''
  })
)

// ── Fetch ──────────────────────────────────────────────────────────────────────
const fetchData = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/dashboard/revenue-report', {
      params: { year: selectedYear.value },
    })
    const raw = data?.data ?? {}
    monthlyData.value = raw.monthly_data ?? []
    summary.value     = raw.summary      ?? summary.value
  } catch (e) {
    console.error('Error al cargar informes de ganancias:', e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
watch(selectedYear, fetchData)

// ── Formatters ────────────────────────────────────────────────────────────────
const fmtUsd = (v) =>
  new Intl.NumberFormat('es-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(v ?? 0)

const fmtNum = (v) =>
  new Intl.NumberFormat('es-VE').format(v ?? 0)

// ── Configuración de tabs ──────────────────────────────────────────────────────
const tabs = [
  {
    key:       'orders',
    title:     'Pedidos',
    icon:      'tabler-shopping-cart',
    color:     'primary',
    dataKey:   'orders',       // campo en monthly_data
    summaryKey:'total_orders',
    isMoney:   false,
  },
  {
    key:       'sales',
    title:     'Ventas',
    icon:      'tabler-chart-bar',
    color:     'info',
    dataKey:   'sales',
    summaryKey:'total_sales',
    isMoney:   true,
  },
  {
    key:       'profit',
    title:     'Ganancia',
    icon:      'tabler-currency-dollar',
    color:     'success',
    dataKey:   'profit',
    summaryKey:'total_profit',
    isMoney:   true,
  },
  {
    key:       'expenses',
    title:     'Gastos',
    icon:      'tabler-receipt',
    color:     'error',
    dataKey:   'expenses',
    summaryKey:'total_expenses',
    isMoney:   true,
  },
]

const activeTab = computed(() => tabs[currentTab.value])

// ── Datos de la serie activa ───────────────────────────────────────────────────
const activeSeries = computed(() =>
  monthlyData.value.map(d => +(d[activeTab.value.dataKey] ?? 0))
)

const maxVal = computed(() => Math.max(...activeSeries.value, 1))

// ── Opciones ApexCharts ────────────────────────────────────────────────────────
const chartOptions = computed(() => {
  const ct   = vuetifyTheme.current.value.colors
  const vt   = vuetifyTheme.current.value.variables

  const labelColor  = `rgba(${hexToRgb(ct['on-surface'])},${vt['disabled-opacity']})`
  const legendColor = `rgba(${hexToRgb(ct['on-background'])},${vt['high-emphasis-opacity']})`
  const borderColor = `rgba(${hexToRgb(String(vt['border-color']))},${vt['border-opacity']})`

  const isMoney   = activeTab.value.isMoney
  const themeColor = `rgba(var(--v-theme-${activeTab.value.color}), 1)`
  const dimColor   = `rgba(var(--v-theme-${activeTab.value.color}), 0.18)`

  // Color del mes destacado (el más alto)
  const maxIndex = activeSeries.value.indexOf(maxVal.value)
  const colors   = activeSeries.value.map((_, i) =>
    i === maxIndex ? themeColor : dimColor
  )

  return {
    chart: {
      type:              'bar',
      parentHeightOffset: 0,
      toolbar:           { show: false },
      animations:        { enabled: true, speed: 400 },
    },
    plotOptions: {
      bar: {
        columnWidth:              '38%',
        borderRadius:             5,
        borderRadiusApplication:  'end',
        distributed:              true,
        dataLabels:               { position: 'top' },
      },
    },
    colors,
    dataLabels: {
      enabled:   true,
      offsetY:   -22,
      formatter: (val) => {
        if (!isMoney) return fmtNum(val)
        // Monto real en USD
        return fmtUsd(val)
      },
      style: {
        fontSize:   '13px',
        fontWeight: '600',
        fontFamily: 'Public Sans',
        colors:     [legendColor],
      },
    },
    legend:  { show: false },
    tooltip: {
      enabled: true,
      theme:   'light',
      y: {
        formatter: (val) => isMoney ? fmtUsd(val) : fmtNum(val),
      },
    },
    grid: {
      show:    false,
      padding: { top: 0, bottom: 0, left: -10, right: -10 },
    },
    xaxis: {
      categories: monthLabels.value,
      axisBorder: { show: true, color: borderColor },
      axisTicks:  { show: false },
      labels: {
        style: { colors: labelColor, fontSize: '13px', fontFamily: 'Public Sans' },
      },
    },
    yaxis: {
      labels: {
        offsetX: -14,
        style:   { colors: labelColor, fontSize: '12px', fontFamily: 'Public Sans' },
        formatter: (val) => {
          if (!isMoney) return fmtNum(val)
          // Monto real en USD
          return fmtUsd(val)
        },
      },
      tickAmount: 5,
      min: 0,
    },
    states: {
      hover:  { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    responsive: [
      {
        breakpoint: 1441,
        options: { plotOptions: { bar: { columnWidth: '45%' } } },
      },
      {
        breakpoint: 600,
        options: {
          plotOptions: { bar: { columnWidth: '60%' } },
          yaxis: { labels: { show: false } },
          grid:  { padding: { left: -20, right: 0 } },
          dataLabels: { style: { fontSize: '11px', fontWeight: '400' } },
        },
      },
    ],
  }
})

const series = computed(() => [{ data: activeSeries.value }])

// ── Total del tab activo ───────────────────────────────────────────────────────
const activeTotal = computed(() => summary.value[activeTab.value.summaryKey] ?? 0)
</script>

<template>
  <VCard>
    <!-- Header -->
    <VCardTitle class="d-flex align-center justify-space-between pt-4 px-5">
      <div>
        <div class="text-h6 font-weight-semibold">Informes de Ganancias</div>
        <div class="text-caption text-medium-emphasis">Resumen Anual · {{ selectedYear }}</div>
      </div>

      <!-- Selector de año -->
      <VBtn variant="tonal" size="small" :loading="loading">
        <span>{{ selectedYear }}</span>
        <template #append>
          <VIcon size="16" icon="tabler-chevron-down" />
        </template>
        <VMenu activator="parent">
          <VList>
            <VListItem
              v-for="year in availableYears"
              :key="year"
              :value="year"
              @click="selectedYear = year"
            >
              <VListItemTitle>{{ year }}</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>
      </VBtn>
    </VCardTitle>

    <VCardText>
      <!-- Tabs -->
      <VSlideGroup
        v-model="currentTab"
        show-arrows
        mandatory
        class="mb-6"
      >
        <VSlideGroupItem
          v-for="(tab, index) in tabs"
          :key="tab.key"
          v-slot="{ isSelected, toggle }"
          :value="index"
        >
          <div
            style="block-size: 100px; inline-size: 110px;"
            :style="isSelected ? `border-color:rgb(var(--v-theme-${tab.color})) !important` : ''"
            :class="isSelected ? 'border' : 'border border-dashed'"
            class="d-flex flex-column justify-center align-center cursor-pointer rounded py-4 px-3 me-4 transition-all"
            @click="toggle"
          >
            <VAvatar
              rounded
              size="38"
              :color="isSelected ? tab.color : ''"
              variant="tonal"
              class="mb-2"
            >
              <VIcon size="20" :icon="tab.icon" />
            </VAvatar>
            <span class="text-body-2 font-weight-medium text-center" style="line-height:1.2">
              {{ tab.title }}
            </span>
          </div>
        </VSlideGroupItem>
      </VSlideGroup>

      <!-- Resumen del tab activo -->
      <div class="d-flex align-center justify-space-between mb-4 px-1">
        <div>
          <div class="text-caption text-medium-emphasis mb-1">
            Total {{ activeTab.title }} · {{ selectedYear }}
          </div>
          <div class="text-h5 font-weight-bold">
            <template v-if="activeTab.isMoney">{{ fmtUsd(activeTotal) }}</template>
            <template v-else>{{ fmtNum(activeTotal) }}</template>
          </div>
        </div>
        <VChip
          v-if="loading"
          size="small"
          color="primary"
          variant="tonal"
        >
          Cargando…
        </VChip>
      </div>

      <!-- Gráfico -->
      <VueApexCharts
        v-if="!loading && series[0].data.length > 0"
        :key="`${currentTab}-${selectedYear}`"
        :options="chartOptions"
        :series="series"
        height="230"
      />

      <!-- Skeleton si no hay data aún -->
      <div
        v-else-if="loading"
        class="d-flex align-center justify-center"
        style="height: 230px"
      >
        <VProgressCircular indeterminate color="primary" size="40" />
      </div>

      <div
        v-else
        class="d-flex align-center justify-center text-medium-emphasis text-caption"
        style="height: 230px"
      >
        Sin datos para {{ selectedYear }}
      </div>
    </VCardText>
  </VCard>
</template>
