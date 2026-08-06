import { computed } from 'vue'

/**
 * Composable que centraliza las configuraciones de los gráficos ApexCharts
 * del dashboard de vencimientos. Recibe los datos y el tipo de métrica de forma reactiva.
 *
 * @param {import('vue').Ref} dashboardData - Datos del dashboard (reactive desde el store)
 * @param {import('vue').Ref<'units'|'value'>} metricType - Tipo de métrica seleccionada
 */
export function useExpiryCharts(dashboardData, metricType) {
  // ─── Helpers de formato ────────────────────────────────────────────
  const fmt = val => metricType.value === 'value'
    ? `$${Number(val).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
    : Number(val).toLocaleString('en-US')

  const fmtMoney = val => `$${Number(val).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
  const fmtNum = val => Number(val).toLocaleString('en-US')

  // Opciones comunes reutilizadas por todos los gráficos
  const baseGridOptions = { borderColor: 'rgba(144, 164, 174, 0.1)' }
  const axisLabelStyle = { colors: '#a3a3a3' }
  const noDataConfig = text => ({ text, style: { color: '#a3a3a3' } })

  // ─── 1. Horizonte por Categoría (Barra Apilada) ────────────────────
  const horizonChartConfig = computed(() => {
    const months = [...new Set(dashboardData.horizon.map(i => i.month))].sort()
    const cats = [...new Set(dashboardData.horizon.map(i => i.category_name))]
    const isVal = metricType.value === 'value'

    return {
      series: cats.map(cat => ({
        name: cat,
        data: months.map(m => {
          const item = dashboardData.horizon.find(i => i.month === m && i.category_name === cat)
          return item ? parseFloat(isVal ? item.total_value : item.total_units) : 0
        }),
      })),
      options: {
        chart: { type: 'bar', stacked: true, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
        plotOptions: { bar: { borderRadius: 4 } },
        xaxis: { categories: months, labels: { style: axisLabelStyle } },
        yaxis: { labels: { formatter: v => isVal ? `$${v.toFixed(0)}` : v.toFixed(0), style: axisLabelStyle } },
        legend: { position: 'top', labels: { colors: '#a3a3a3' } },
        dataLabels: { enabled: false },
        colors: ['#7367f0', '#28c76f', '#ea5455', '#ff9f43', '#00cfe8'],
        grid: baseGridOptions,
        noData: noDataConfig('Sin datos para el período seleccionado'),
      },
    }
  })

  // ─── 2. Tendencia 6 Meses (Área) ────────────────────────────────────
  const sixMonthTrendConfig = computed(() => {
    const now = new Date()
    const isVal = metricType.value === 'value'

    const months = Array.from({ length: 6 }, (_, i) => {
      const d = new Date(now.getFullYear(), now.getMonth() + i, 1)
      return {
        label: d.toLocaleString('es-ES', { month: 'short', year: '2-digit' }).toUpperCase(),
        key: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
      }
    })

    return {
      series: [{
        name: isVal ? 'Vencimiento (USD)' : 'Vencimiento (Unidades)',
        data: months.map(m => {
          return dashboardData.horizon
            .filter(i => i.month === m.key)
            .reduce((acc, curr) => acc + parseFloat(isVal ? curr.total_value : curr.total_units), 0)
        }),
      }],
      options: {
        chart: { type: 'area', toolbar: { show: false }, zoom: { enabled: false } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1, stops: [0, 90, 100] } },
        xaxis: { categories: months.map(m => m.label), labels: { style: { ...axisLabelStyle, fontSize: '10px' } } },
        yaxis: { labels: { formatter: v => isVal ? `$${v.toLocaleString()}` : v.toLocaleString(), style: axisLabelStyle } },
        colors: ['#28c76f'],
        grid: baseGridOptions,
        tooltip: { theme: 'dark' },
        noData: noDataConfig('Sin datos proyectados'),
      },
    }
  })

  // ─── 3. Top 10 Riesgo Financiero (Barra Horizontal) ─────────────────
  const riskBarChartConfig = computed(() => {
    const risks = dashboardData.overstock.reduce((acc, curr) => {
      const key = curr.product_id
      if (!acc[key]) acc[key] = { name: curr.name, lab: curr.laboratory_name ?? 'N/A', id: curr.product_id, cost: 0 }
      acc[key].cost += parseFloat(curr.costo_excedente)
      return acc
    }, {})

    const top10 = Object.values(risks).sort((a, b) => b.cost - a.cost).slice(0, 10)

    return {
      series: [{ name: 'Costo en Riesgo', data: top10.map(i => i.cost) }],
      options: {
        chart: { type: 'bar', toolbar: { show: false }, offsetX: -10 },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, distributed: true, barHeight: '70%' } },
        colors: ['#ea5455', '#ff9f43', '#ffc107', '#28c76f', '#00cfe8', '#7367f0', '#4b4b4b', '#82868b', '#212121', '#a8aaae'],
        xaxis: { categories: top10.map(i => `#${i.id} | ${i.name} [${i.lab}]`), labels: { formatter: v => fmtMoney(v), style: axisLabelStyle } },
        yaxis: { labels: { style: { fontSize: '10px', fontWeight: 600, colors: '#a3a3a3' }, maxWidth: 350 } },
        grid: { padding: { left: 20 }, borderColor: 'rgba(144, 164, 174, 0.1)' },
        dataLabels: { enabled: true, formatter: v => fmtMoney(v), style: { fontSize: '10px', colors: ['#fff'] } },
        legend: { show: false },
        tooltip: { theme: 'dark' },
        noData: noDataConfig('Sin productos en riesgo'),
      },
    }
  })

  // ─── 4. Historial de Mermas (Barra) ──────────────────────────────────
  const lossHistoryChartConfig = computed(() => {
    const isVal = metricType.value === 'value'
    const reversed = [...dashboardData.loss_analysis].reverse()

    return {
      series: [{ name: isVal ? 'Pérdida ($)' : 'Pérdida (U)', data: reversed.map(i => parseFloat(isVal ? i.total_cost : i.total_units)) }],
      options: {
        chart: { type: 'bar', toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 4, dataLabels: { position: 'top' } } },
        dataLabels: { enabled: true, formatter: v => isVal ? fmtMoney(v) : fmtNum(v), offsetY: -20, style: { fontSize: '9px', colors: ['#a3a3a3'] } },
        colors: ['#ea5455'],
        xaxis: {
          categories: reversed.map(i => {
            const [y, m] = i.month.split('-')
            return new Date(y, m - 1, 1).toLocaleString('es-ES', { month: 'short' }).toUpperCase()
          }),
          labels: { style: { ...axisLabelStyle, fontSize: '10px' } },
        },
        yaxis: { labels: { formatter: v => isVal ? `$${v}` : v, style: axisLabelStyle } },
        grid: baseGridOptions,
        tooltip: { theme: 'dark' },
        noData: noDataConfig('Sin historial de mermas'),
      },
    }
  })

  return {
    horizonChartConfig,
    sixMonthTrendConfig,
    riskBarChartConfig,
    lossHistoryChartConfig,
    // Formatters expuestos por si la vista los necesita
    fmtMoney,
    fmtNum,
  }
}
