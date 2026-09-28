<script setup>
/**
 * Reporte de Devoluciones a Proveedores.
 * Muestra lotes con vencimiento <= 90 días agrupados por laboratorio.
 * Incluye semaforización de urgencia, ordenamiento dinámico y generación de cartas en PDF.
 */
import { onMounted, onUnmounted, ref, computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useSupplierReturnsStore } from '@/stores/supplier-returns-store'
import pdfSupplierReturnsGenerator, { generateSingleLabPdfDoc } from '@/utils/pdfSupplierReturnsGenerator'
import SupplierReturnsLotTable from '@/components/bi/SupplierReturnsLotTable.vue'

// ── Store ────────────────────────────────────────────────────────────────────
const store = useSupplierReturnsStore()
const {
  loading, catalogsLoading, error,
  data, filters,
  laboratories, suppliers,
  hasGroups, hasActiveFilters,
} = storeToRefs(store)

// ── Estado local de UI ────────────────────────────────────────────────────────
const snackbar        = ref({ show: false, message: '', color: 'success' })
const buyerNameDialog = ref(false)
const pdfGenerating   = ref(false)
const buyerName       = ref(localStorage.getItem('supplier_returns_buyer_name') || 'Encargada de Compras')
const expandedGroups  = ref([])

// Filtros y ordenamiento secundarios en cliente
const selectedUrgency = ref('all') // 'all' | 'critical' | 'warning' | 'preventive'
const labSearch       = ref('')
const sortBy          = ref('amount_desc')

const sortOptions = [
  { title: 'Mayor monto ($)', value: 'amount_desc' },
  { title: 'Más unidades', value: 'units_desc' },
  { title: 'Más productos', value: 'products_desc' },
  { title: 'Nombre (A-Z)', value: 'name_asc' },
]

const showMessage = (msg, color = 'success') => {
  snackbar.value = { show: true, message: msg, color }
}

// ── Computed derivados del store ──────────────────────────────────────────────
const groups   = computed(() => data.value?.groups   ?? [])
const summary  = computed(() => data.value?.summary  ?? {})
const metadata = computed(() => data.value?.metadata ?? {})

// ── Distribución por franjas de criticidad (Semaforización) ──────────────────
const urgencyStats = computed(() => {
  let criticalUnits = 0, criticalAmount = 0, criticalLots = 0
  let warningUnits = 0, warningAmount = 0, warningLots = 0
  let preventiveUnits = 0, preventiveAmount = 0, preventiveLots = 0

  groups.value.forEach(group => {
    (group.lots || []).forEach(lot => {
      const days = Number(lot.days_to_expiry)
      const qty = Number(lot.quantity || 0)
      const amt = Number(lot.total_amount || 0)

      if (days <= 30) {
        criticalUnits += qty
        criticalAmount += amt
        criticalLots++
      } else if (days <= 60) {
        warningUnits += qty
        warningAmount += amt
        warningLots++
      } else {
        preventiveUnits += qty
        preventiveAmount += amt
        preventiveLots++
      }
    })
  })

  return {
    critical: { units: criticalUnits, amount: criticalAmount, lots: criticalLots },
    warning: { units: warningUnits, amount: warningAmount, lots: warningLots },
    preventive: { units: preventiveUnits, amount: preventiveAmount, lots: preventiveLots },
  }
})

// KPIs principales
const kpiCards = computed(() => [
  {
    title: 'Laboratorios', icon: 'tabler-building-factory', color: 'primary',
    value: summary.value.total_laboratories ?? 0, suffix: '',
    desc: 'Con lotes a 90 días',
  },
  {
    title: 'Productos en riesgo', icon: 'tabler-pill', color: 'warning',
    value: summary.value.total_products ?? 0, suffix: ' SKU',
    desc: 'Distintos productos',
  },
  {
    title: 'Unidades totales', icon: 'tabler-packages', color: 'secondary',
    value: Number(summary.value.total_units ?? 0).toLocaleString('es-VE'), suffix: ' U.',
    desc: 'Total unidades afectadas',
  },
  {
    title: 'Monto total en riesgo', icon: 'tabler-cash-off', color: 'error',
    value: `$${Number(summary.value.total_amount ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`,
    suffix: '', desc: 'Costo total de inventario',
  },
])

// ── Procesamiento, Filtrado y Ordenamiento de Grupos ──────────────────────────
const processedGroups = computed(() => {
  let list = groups.value.map(group => {
    let lots = group.lots || []
    if (selectedUrgency.value === 'critical') {
      lots = lots.filter(l => Number(l.days_to_expiry) <= 30)
    } else if (selectedUrgency.value === 'warning') {
      lots = lots.filter(l => Number(l.days_to_expiry) > 30 && Number(l.days_to_expiry) <= 60)
    } else if (selectedUrgency.value === 'preventive') {
      lots = lots.filter(l => Number(l.days_to_expiry) > 60)
    }

    const totalUnits = lots.reduce((acc, l) => acc + Number(l.quantity || 0), 0)
    const totalAmount = lots.reduce((acc, l) => acc + Number(l.total_amount || 0), 0)
    const distinctProducts = new Set(lots.map(l => l.product_name)).size
    const hasCritical = (group.lots || []).some(l => Number(l.days_to_expiry) <= 30)

    return {
      ...group,
      lots,
      filtered_units: totalUnits,
      filtered_amount: totalAmount,
      filtered_products_count: distinctProducts,
      has_critical: hasCritical,
    }
  })

  // Filtrar laboratorios sin lotes tras aplicar la urgencia
  if (selectedUrgency.value !== 'all') {
    list = list.filter(g => g.lots.length > 0)
  }

  // Filtrar por búsqueda ágil de laboratorio
  if (labSearch.value?.trim()) {
    const term = labSearch.value.trim().toLowerCase()
    list = list.filter(g => g.laboratory_name.toLowerCase().includes(term))
  }

  // Ordenar
  return list.sort((a, b) => {
    if (sortBy.value === 'amount_desc') return b.filtered_amount - a.filtered_amount
    if (sortBy.value === 'units_desc') return b.filtered_units - a.filtered_units
    if (sortBy.value === 'products_desc') return b.filtered_products_count - a.filtered_products_count
    if (sortBy.value === 'name_asc') return a.laboratory_name.localeCompare(b.laboratory_name)
    return 0
  })
})

// ── Watchers ──────────────────────────────────────────────────────────────────

// Debounce de 500ms en el campo de búsqueda de texto del backend
let searchTimer = null
watch(() => filters.value.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => store.fetchReport(), 500)
})

// Propagar errores del store al snackbar
watch(error, val => { if (val) showMessage(val, 'error') })

// ── Ciclo de vida ─────────────────────────────────────────────────────────────
onMounted(() => {
  store.fetchCatalogs()
  store.fetchReport()
})

onUnmounted(() => clearTimeout(searchTimer))

// ── Acciones ──────────────────────────────────────────────────────────────────

/** Expande todos los paneles visibles */
const expandAll = () => {
  expandedGroups.value = processedGroups.value.map((_, idx) => idx)
}

/** Colapsa todos los paneles */
const collapseAll = () => {
  expandedGroups.value = []
}

/** Abre el diálogo de confirmación del PDF si hay datos disponibles */
const downloadPdf = () => {
  if (!hasGroups.value) {
    showMessage('No hay datos disponibles para generar el PDF.', 'warning')
    return
  }
  buyerNameDialog.value = true
}

/** Genera y descarga el PDF de todos los laboratorios; bloquea el botón durante la operación */
const confirmDownloadPdf = async () => {
  buyerNameDialog.value = false
  pdfGenerating.value   = true
  
  if (buyerName.value?.trim()) {
    localStorage.setItem('supplier_returns_buyer_name', buyerName.value.trim())
  }

  try {
    await new Promise(resolve => setTimeout(resolve, 50))
    await pdfSupplierReturnsGenerator(data.value, { buyerName: buyerName.value.trim() })
    showMessage('PDF generado exitosamente.', 'success')
  } catch (err) {
    console.error('[SupplierReturns] Error generando PDF:', err)
    showMessage('Error al generar el PDF. Intenta de nuevo.', 'error')
  } finally {
    pdfGenerating.value = false
  }
}

/** Genera la carta PDF individual para un laboratorio específico */
const downloadSingleLab = (group) => {
  try {
    generateSingleLabPdfDoc(group, data.value, { buyerName: buyerName.value.trim() })
    showMessage(`Carta de canje para ${group.laboratory_name} descargada exitosamente.`, 'success')
  } catch (err) {
    console.error('[SupplierReturns] Error generando PDF individual:', err)
    showMessage('Error al generar la carta del laboratorio.', 'error')
  }
}

/** Reinicia los filtros y emite toast informativo */
const resetFilters = () => {
  selectedUrgency.value = 'all'
  labSearch.value       = ''
  sortBy.value          = 'amount_desc'
  store.resetFilters()
  showMessage('Filtros restablecidos.', 'info')
}
</script>

<template>
  <VContainer fluid class="supplier-returns pa-0">

    <!-- ─── Encabezado de página ─────────────────────────────────────────────── -->
    <div class="d-flex align-center mb-5 gap-3 flex-wrap">
      <div>
        <h1 class="text-h5 font-weight-black d-flex align-center gap-2">
          <VIcon icon="tabler-rotate-clockwise" color="warning" size="28" />
          Reporte de Devoluciones a Proveedores
        </h1>
        <p class="text-caption text-disabled mb-0">
          Lotes con vencimiento en los próximos <strong>90 días</strong>
          · Solicitud de canje preventivo
        </p>
      </div>
      <VSpacer />
      <VBtn
        color="error"
        variant="flat"
        prepend-icon="tabler-file-type-pdf"
        :disabled="loading || pdfGenerating || !hasGroups"
        :loading="pdfGenerating"
        class="rounded-lg shadow-sm"
        @click="downloadPdf"
      >
        Descargar Todo (PDF)
      </VBtn>
    </div>

    <!-- ─── Panel de Filtros Principales ────────────────────────────────────── -->
    <VCard class="mb-5 rounded-lg border shadow-sm">
      <VCardText class="pa-4">
        <VRow align="center">
          <VCol cols="12" md="4">
            <AppTextField
              v-model="filters.search"
              placeholder="Buscar producto, barcode o No. lote..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              :disabled="loading"
            />
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <AppAutocomplete
              v-model="filters.laboratory_id"
              :items="laboratories"
              :loading="catalogsLoading"
              item-title="name"
              item-value="id"
              placeholder="Laboratorio"
              clearable
              density="compact"
              hide-details
              prepend-inner-icon="tabler-flask"
              :disabled="loading"
              @update:modelValue="store.fetchReport()"
            />
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <AppAutocomplete
              v-model="filters.supplier_id"
              :items="suppliers"
              :loading="catalogsLoading"
              item-title="name"
              item-value="id"
              placeholder="Proveedor / Droguería"
              clearable
              density="compact"
              hide-details
              prepend-inner-icon="tabler-truck"
              :disabled="loading"
              @update:modelValue="store.fetchReport()"
            />
          </VCol>

          <VCol cols="auto">
            <div class="d-flex gap-2">
              <VBtn
                icon
                variant="flat"
                color="primary"
                size="38"
                class="rounded-circle"
                :loading="loading"
                :disabled="loading"
                aria-label="Actualizar"
                @click="store.fetchReport()"
              >
                <VIcon icon="tabler-refresh" size="20" />
                <VTooltip activator="parent" location="top">Actualizar</VTooltip>
              </VBtn>

              <VBtn
                icon
                variant="text"
                color="secondary"
                size="38"
                class="rounded-circle"
                :disabled="loading || (!hasActiveFilters && selectedUrgency === 'all' && !labSearch)"
                aria-label="Limpiar filtros"
                @click="resetFilters"
              >
                <VIcon icon="tabler-eraser" size="20" />
                <VTooltip activator="parent" location="top">Limpiar filtros</VTooltip>
              </VBtn>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- ─── KPI Cards Globales ────────────────────────────────────────────────── -->
    <VRow class="mb-4">
      <VCol
        v-for="(kpi, i) in kpiCards"
        :key="i"
        cols="12" sm="6" md="3"
      >
        <VCard class="rounded-lg border shadow-sm h-100 kpi-card">
          <VCardText class="pa-4 d-flex align-center">
            <template v-if="loading">
              <VSkeletonLoader type="avatar" width="48" height="48" class="me-4 rounded-lg flex-shrink-0" />
              <div class="flex-grow-1">
                <VSkeletonLoader type="text" width="60%" class="mb-1" />
                <VSkeletonLoader type="heading" width="85%" />
              </div>
            </template>
            <template v-else>
              <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="me-4 flex-shrink-0">
                <VIcon :icon="kpi.icon" size="24" />
              </VAvatar>
              <div class="overflow-hidden">
                <p class="text-caption text-disabled mb-0 font-weight-bold text-uppercase">{{ kpi.title }}</p>
                <h3 class="text-h5 font-weight-black mb-0">{{ kpi.value }}{{ kpi.suffix }}</h3>
                <p class="text-super-xs text-disabled mb-0">{{ kpi.desc }}</p>
              </div>
            </template>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- ─── Desglose por Criticidad (Semaforización & Filtro Rápido) ─────────── -->
    <VCard class="mb-5 rounded-lg border shadow-sm bg-surface">
      <VCardText class="pa-4">
        <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-3">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-traffic-lights" color="warning" size="22" />
            <span class="font-weight-bold text-body-2">Distribución de Riesgo por Urgencia</span>
          </div>
          <span class="text-caption text-disabled">
            Corte: {{ metadata.cutoff_date || 'Próximos 90 días' }}
          </span>
        </div>

        <VRow dense>
          <!-- Crítico <= 30 días -->
          <VCol cols="12" md="4">
            <VCard
              variant="tonal"
              color="error"
              class="cursor-pointer transition-fast urgency-box rounded-lg border"
              :class="{ 'border-2 border-error font-weight-bold ring-active': selectedUrgency === 'critical' }"
              @click="selectedUrgency = selectedUrgency === 'critical' ? 'all' : 'critical'"
            >
              <VCardText class="pa-3 d-flex align-center justify-space-between">
                <div>
                  <div class="d-flex align-center gap-1 mb-1">
                    <VIcon icon="tabler-alert-triangle-filled" size="16" />
                    <span class="text-caption font-weight-bold">CRÍTICO (≤ 30 DÍAS)</span>
                  </div>
                  <h4 class="text-h6 font-weight-black mb-0">
                    ${{ Number(urgencyStats.critical.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                  </h4>
                </div>
                <div class="text-end">
                  <VChip size="x-small" color="error" variant="flat" class="font-weight-bold">
                    {{ Number(urgencyStats.critical.units).toLocaleString('es-VE') }} U.
                  </VChip>
                  <p class="text-super-xs text-disabled mb-0 mt-1">{{ urgencyStats.critical.lots }} lotes</p>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Advertencia 31-60 días -->
          <VCol cols="12" md="4">
            <VCard
              variant="tonal"
              color="warning"
              class="cursor-pointer transition-fast urgency-box rounded-lg border"
              :class="{ 'border-2 border-warning font-weight-bold ring-active': selectedUrgency === 'warning' }"
              @click="selectedUrgency = selectedUrgency === 'warning' ? 'all' : 'warning'"
            >
              <VCardText class="pa-3 d-flex align-center justify-space-between">
                <div>
                  <div class="d-flex align-center gap-1 mb-1">
                    <VIcon icon="tabler-clock-exclamation" size="16" />
                    <span class="text-caption font-weight-bold">ADVERTENCIA (31-60 DÍAS)</span>
                  </div>
                  <h4 class="text-h6 font-weight-black mb-0">
                    ${{ Number(urgencyStats.warning.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                  </h4>
                </div>
                <div class="text-end">
                  <VChip size="x-small" color="warning" variant="flat" class="font-weight-bold">
                    {{ Number(urgencyStats.warning.units).toLocaleString('es-VE') }} U.
                  </VChip>
                  <p class="text-super-xs text-disabled mb-0 mt-1">{{ urgencyStats.warning.lots }} lotes</p>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Preventivo 61-90 días -->
          <VCol cols="12" md="4">
            <VCard
              variant="tonal"
              color="info"
              class="cursor-pointer transition-fast urgency-box rounded-lg border"
              :class="{ 'border-2 border-info font-weight-bold ring-active': selectedUrgency === 'preventive' }"
              @click="selectedUrgency = selectedUrgency === 'preventive' ? 'all' : 'preventive'"
            >
              <VCardText class="pa-3 d-flex align-center justify-space-between">
                <div>
                  <div class="d-flex align-center gap-1 mb-1">
                    <VIcon icon="tabler-shield-check" size="16" />
                    <span class="text-caption font-weight-bold">PREVENTIVO (61-90 DÍAS)</span>
                  </div>
                  <h4 class="text-h6 font-weight-black mb-0">
                    ${{ Number(urgencyStats.preventive.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                  </h4>
                </div>
                <div class="text-end">
                  <VChip size="x-small" color="info" variant="flat" class="font-weight-bold">
                    {{ Number(urgencyStats.preventive.units).toLocaleString('es-VE') }} U.
                  </VChip>
                  <p class="text-super-xs text-disabled mb-0 mt-1">{{ urgencyStats.preventive.lots }} lotes</p>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- ─── Skeleton durante primera carga ────────────────────────────────────── -->
    <template v-if="loading">
      <VCard v-for="n in 3" :key="`skel-${n}`" class="rounded-lg border mb-3 shadow-sm">
        <VCardText class="pa-4">
          <VSkeletonLoader type="list-item-avatar-two-line" />
        </VCardText>
      </VCard>
    </template>

    <!-- ─── Estado vacío ──────────────────────────────────────────────────────── -->
    <VCard v-else-if="!processedGroups.length" class="rounded-lg border shadow-sm">
      <VCardText class="d-flex flex-column align-center justify-center pa-12 text-center">
        <VIcon icon="tabler-check-circle" size="64" color="success" class="mb-4" />
        <p class="text-h6 font-weight-bold mb-2">Sin vencimientos en este criterio</p>
        <p class="text-body-2 text-disabled">
          No hay lotes con stock positivo que coincidan con los filtros y la franja de urgencia seleccionada.
        </p>
        <VBtn
          v-if="hasActiveFilters || selectedUrgency !== 'all' || labSearch"
          variant="tonal"
          color="secondary"
          class="mt-4"
          prepend-icon="tabler-eraser"
          @click="resetFilters"
        >
          Limpiar filtros y franjas
        </VBtn>
      </VCardText>
    </VCard>

    <!-- ─── Barra de Control y Lista de Laboratorios ───────────────────────────── -->
    <template v-else>
      <!-- Barra de herramientas de la lista -->
      <div class="d-flex align-center justify-space-between mb-3 gap-3 flex-wrap">
        <div class="d-flex align-center gap-2 flex-wrap">
          <VChip color="primary" variant="tonal" size="small" prepend-icon="tabler-building-factory">
            {{ processedGroups.length }} {{ processedGroups.length === 1 ? 'laboratorio mostrado' : 'laboratorios mostrados' }}
          </VChip>
          <VChip v-if="selectedUrgency !== 'all'" color="warning" variant="tonal" size="small" closable @click:close="selectedUrgency = 'all'">
            Franja: {{ selectedUrgency === 'critical' ? 'Crítico (≤ 30d)' : selectedUrgency === 'warning' ? 'Advertencia (31-60d)' : 'Preventivo (61-90d)' }}
          </VChip>
        </div>

        <div class="d-flex align-center gap-2 flex-wrap">
          <!-- Búsqueda rápida local -->
          <AppTextField
            v-model="labSearch"
            placeholder="Filtrar laboratorio..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            hide-details
            style="inline-size: 220px;"
          />

          <!-- Selector de ordenamiento -->
          <AppSelect
            v-model="sortBy"
            :items="sortOptions"
            density="compact"
            hide-details
            prepend-inner-icon="tabler-sort-descending"
            style="inline-size: 190px;"
          />

          <!-- Botones expandir/colapsar -->
          <VBtn variant="tonal" size="small" color="secondary" @click="expandAll">
            Expandir
          </VBtn>
          <VBtn variant="tonal" size="small" color="secondary" @click="collapseAll">
            Colapsar
          </VBtn>
        </div>
      </div>

      <!-- Acordeón de laboratorios -->
      <VExpansionPanels v-model="expandedGroups" multiple class="return-panels">
        <VExpansionPanel
          v-for="(group, gIdx) in processedGroups"
          :key="group.laboratory_id"
          :value="gIdx"
          class="rounded-lg border mb-3 shadow-sm"
          elevation="0"
        >
          <!-- Cabecera del panel -->
          <VExpansionPanelTitle class="pa-4">
            <div class="d-flex align-center justify-space-between w-100 flex-wrap gap-2 pe-3">
              <div class="d-flex align-center gap-3 flex-grow-1">
                <VAvatar
                  :color="group.has_critical ? 'error' : 'primary'"
                  variant="tonal"
                  size="36"
                  rounded="md"
                >
                  <VIcon :icon="group.has_critical ? 'tabler-alert-triangle' : 'tabler-building-factory'" size="20" />
                </VAvatar>
                <div>
                  <span class="font-weight-black text-uppercase text-body-2 d-block">
                    {{ group.laboratory_name }}
                  </span>
                  <div class="d-flex gap-2 flex-wrap mt-1">
                    <VChip size="x-small" variant="tonal" color="warning">
                      {{ group.filtered_products_count }} {{ group.filtered_products_count === 1 ? 'producto' : 'productos' }}
                    </VChip>
                    <VChip size="x-small" variant="tonal" color="secondary">
                      {{ Number(group.filtered_units).toLocaleString('es-VE') }} unidades
                    </VChip>
                    <VChip size="x-small" variant="tonal" :color="group.has_critical ? 'error' : 'primary'">
                      ${{ Number(group.filtered_amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                    </VChip>
                  </div>
                </div>
              </div>

              <!-- Botón rápido para descargar PDF individual de este laboratorio -->
              <VBtn
                size="small"
                variant="outlined"
                color="error"
                prepend-icon="tabler-file-type-pdf"
                class="rounded-lg text-caption"
                @click.stop="downloadSingleLab(group)"
              >
                Carta PDF
              </VBtn>
            </div>
          </VExpansionPanelTitle>

          <!-- Tabla de lotes -->
          <VExpansionPanelText class="pa-0">
            <VDivider />
            <SupplierReturnsLotTable :lots="group.lots" />
          </VExpansionPanelText>
        </VExpansionPanel>
      </VExpansionPanels>
    </template>

    <!-- ─── Diálogo: firma antes de generar PDF completo ─────────────────────── -->
    <VDialog v-model="buyerNameDialog" max-width="420" persistent>
      <VCard class="rounded-lg">
        <VCardTitle class="pa-4 d-flex align-center gap-2">
          <VIcon icon="tabler-file-type-pdf" color="error" />
          Generar Cartas de Devolución
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <p class="text-body-2 text-medium-emphasis mb-3">
            El PDF generará una carta formal por cada laboratorio afectado.
            Indica el nombre de quien firma la solicitud de canje:
          </p>
          <AppTextField
            v-model="buyerName"
            label="Nombre de la encargada de compras"
            prepend-inner-icon="tabler-user"
            density="compact"
            hide-details
            autofocus
          />
        </VCardText>
        <VCardActions class="pa-4 pt-0 d-flex gap-2 justify-end">
          <VBtn variant="outlined" color="secondary" @click="buyerNameDialog = false">
            Cancelar
          </VBtn>
          <VBtn
            variant="flat"
            color="error"
            prepend-icon="tabler-download"
            :disabled="pdfGenerating"
            :loading="pdfGenerating"
            @click="confirmDownloadPdf"
          >
            Generar y Descargar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── Snackbar de feedback ──────────────────────────────────────────────── -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      location="top right"
      :timeout="3500"
    >
      {{ snackbar.message }}
      <template #actions>
        <VBtn icon variant="text" @click="snackbar.show = false">
          <VIcon icon="tabler-x" />
        </VBtn>
      </template>
    </VSnackbar>

  </VContainer>
</template>

<style scoped>
.kpi-card {
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}
.kpi-card:hover {
  box-shadow: 0 4px 20px rgba(var(--v-theme-on-surface), 0.08) !important;
  transform: translateY(-2px);
}

.urgency-box {
  transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}
.urgency-box:hover {
  transform: translateY(-2px);
}

.ring-active {
  box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.35) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

.return-panels .v-expansion-panel {
  border-radius: 8px !important;
}
</style>
