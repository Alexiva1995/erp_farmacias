<script setup>
import axios from '@/plugins/axios'
import { hexToRgb } from '@layouts/utils'
import { computed, onMounted, ref } from 'vue'
import { useTheme } from 'vuetify'

const vuetifyTheme = useTheme()

// ── Estado ────────────────────────────────────────────────────────────────────
const loading     = ref(false)
const salesData   = ref([])   // monto en USD por mes
const ordersData  = ref([])   // cantidad de pedidos por mes
const categories  = ref([])   // etiquetas de meses

// ── Fetch: últimos 6 meses del año actual ────────────────────────────────────
const fetchData = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/dashboard/revenue-report', {
      params: { year: new Date().getFullYear() },
    })
    const monthly = data?.data?.monthly_data ?? []

    // Tomar solo los últimos 6 meses con datos (o los 6 primeros disponibles)
    const monthNames = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']
    const currentMonth = new Date().getMonth() + 1           // 1-12
    const last6 = monthly.filter(d => d.month <= currentMonth).slice(-6)

    categories.value = last6.map(d => monthNames[(d.month ?? 1) - 1])
    salesData.value  = last6.map(d => +(d.sales   ?? 0))
    ordersData.value = last6.map(d => +(d.orders  ?? 0))
  } catch (e) {
    console.error('Error al cargar ventas radar:', e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)

// ── Series ────────────────────────────────────────────────────────────────────
const series = computed(() => [
  { name: 'Ventas',  data: salesData.value  },
  { name: 'Órdenes', data: ordersData.value },
])

// ── Formatters ────────────────────────────────────────────────────────────────
const fmtUsd = (v) =>
  new Intl.NumberFormat('es-US', {
    style: 'currency', currency: 'USD',
    minimumFractionDigits: 0, maximumFractionDigits: 0,
  }).format(v ?? 0)

// ── Opciones del radar ────────────────────────────────────────────────────────
const chartOptions = computed(() => {
  const ct = vuetifyTheme.current.value.colors
  const vt = vuetifyTheme.current.value.variables

  const borderColor  = `rgba(${hexToRgb(String(vt['border-color']))},${vt['border-opacity']})`
  const labelColor   = `rgba(${hexToRgb(ct['on-surface'])},${vt['disabled-opacity']})`
  const legendColor  = `rgba(${hexToRgb(ct['on-background'])},${vt['medium-emphasis-opacity']})`

  // Escala del eje Y adaptada al max de ventas
  const maxSale    = Math.max(...salesData.value, 1)
  const maxOrders  = Math.max(...ordersData.value, 1)
  // Para que ambas series coexistan en el radar, normalizamos a %
  // (no se puede tener 2 escalas en radar, así que usamos los valores originales
  //  y dejamos que ApexCharts autoescale)

  return {
    chart: {
      type:    'radar',
      toolbar: { show: false },
      animations: { enabled: true, speed: 500 },
    },
    plotOptions: {
      radar: {
        polygons: {
          strokeColors:    borderColor,
          connectorColors: borderColor,
        },
      },
    },
    stroke: { show: false, width: 0 },
    legend: {
      show:     true,
      fontSize: '13px',
      position: 'bottom',
      labels:   { colors: legendColor, useSeriesColors: false },
      markers:  { height: 12, width: 12, offsetX: -8 },
      itemMargin: { horizontal: 10 },
      onItemHover: { highlightDataSeries: false },
    },
    colors: [ct.primary, ct.info],
    fill:    { opacity: [1, 0.85] },
    markers: { size: 0 },
    grid: {
      show:    false,
      padding: { top: 0, bottom: -5 },
    },
    xaxis: {
      categories: categories.value,
      labels: {
        show:  true,
        style: {
          colors:     Array(6).fill(labelColor),
          fontSize:   '13px',
          fontFamily: 'Public Sans',
        },
      },
    },
    yaxis: {
      show:       false,
      min:        0,
      tickAmount: 4,
    },
    tooltip: {
      enabled: true,
      theme:   'light',
      y: {
        formatter: (val, { seriesIndex }) =>
          seriesIndex === 0
            ? fmtUsd(val)                                      // Ventas → USD
            : new Intl.NumberFormat('es-VE').format(val) + ' órdenes',
      },
    },
    responsive: [
      {
        breakpoint: 769,
        options:    { chart: { height: 372 } },
      },
    ],
  }
})

const moreList = [
  { title: 'Ver más',   value: 'View More' },
  { title: 'Eliminar',  value: 'Delete'    },
]
</script>

<template>
  <VCard>
    <VCardItem class="pb-4">
      <VCardTitle>Ventas</VCardTitle>
      <VCardSubtitle>Últimos 6 Meses</VCardSubtitle>

      <template #append>
        <div class="mt-n4 me-n2">
          <MoreBtn size="small" :menu-list="moreList" />
        </div>
      </template>
    </VCardItem>

    <VCardText>
      <!-- Gráfico radar con datos reales -->
      <VueApexCharts
        v-if="!loading && series[0].data.length > 0"
        :key="categories.join()"
        :options="chartOptions"
        :series="series"
        height="290"
      />

      <!-- Loading -->
      <div
        v-else
        class="d-flex align-center justify-center"
        style="height: 290px"
      >
        <VProgressCircular
          v-if="loading"
          indeterminate
          color="primary"
          size="36"
        />
        <span v-else class="text-caption text-medium-emphasis">
          Sin datos disponibles
        </span>
      </div>
    </VCardText>
  </VCard>
</template>
