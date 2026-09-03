<script setup>
import { onMounted, onUnmounted, ref, computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useSupplierReturnsStore } from '@/stores/supplier-returns-store'
import axios from '@/plugins/axios'
import pdfSupplierReturnsGenerator from '@/utils/pdfSupplierReturnsGenerator'

const store   = useSupplierReturnsStore()
const { loading, error, data, filters } = storeToRefs(store)

// Catálogos para filtros
const laboratories = ref([])
const suppliers    = ref([])

// Estado UI
const snackbar = ref({ show: false, message: '', color: 'success' })
const buyerNameDialog = ref(false)
const buyerName = ref('Encargada de Compras')
const expandedGroups = ref([])

const showMessage = (msg, color = 'success') => {
  snackbar.value = { show: true, message: msg, color }
}

const hasActiveFilters = computed(() =>
  filters.value.laboratory_id || filters.value.supplier_id || filters.value.search
)

// Agrupado con computed para no recalcular en cada render
const groups = computed(() => data.value?.groups ?? [])
const summary = computed(() => data.value?.summary ?? {})
const metadata = computed(() => data.value?.metadata ?? {})

const totalAmountFormatted = computed(() =>
  `$${Number(summary.value.total_amount ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`
)

// KPI cards
const kpiCards = computed(() => [
  {
    title: 'Laboratorios',
    value: summary.value.total_laboratories ?? 0,
    suffix: '',
    icon: 'tabler-building-factory',
    color: 'primary',
    desc: 'Con lotes a 90 días',
  },
  {
    title: 'Productos en riesgo',
    value: summary.value.total_products ?? 0,
    suffix: ' SKU',
    icon: 'tabler-pill',
    color: 'warning',
    desc: 'Distintos productos',
  },
  {
    title: 'Unidades',
    value: Number(summary.value.total_units ?? 0).toLocaleString('es-VE'),
    suffix: ' U.',
    icon: 'tabler-packages',
    color: 'error',
    desc: 'Total unidades en riesgo',
  },
  {
    title: 'Monto en riesgo',
    value: totalAmountFormatted.value,
    suffix: '',
    icon: 'tabler-cash-off',
    color: 'secondary',
    desc: 'Costo de inventario afectado',
  },
])

// Columnas de la tabla de lotes dentro de cada laboratorio
const lotHeaders = [
  { title: 'PRODUCTO', key: 'product_name', align: 'start', sortable: true },
  { title: 'No. LOTE', key: 'lot_number', align: 'center', sortable: false },
  { title: 'VENCIMIENTO', key: 'expiration_date', align: 'center', sortable: true },
  { title: 'DÍAS', key: 'days_to_expiry', align: 'center', sortable: true },
  { title: 'CANT.', key: 'quantity', align: 'end', sortable: true },
  { title: 'PROVEEDOR', key: 'supplier_name', align: 'start', sortable: true },
  { title: 'F. COMPRA', key: 'purchase_date', align: 'center', sortable: false },
  { title: 'MONTO USD', key: 'total_amount', align: 'end', sortable: true },
]

const formatDate = (date) => {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('es-VE')
}

const formatMoney = (val) =>
  `$${Number(val ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`

const daysColor = (days) => {
  if (days <= 30) return 'error'
  if (days <= 60) return 'warning'
  return 'info'
}

// Carga de catálogos
const fetchCatalogs = async () => {
  try {
    const [labRes, supRes] = await Promise.all([
      axios.get('/laboratories').catch(() => ({ data: [] })),
      axios.get('/suppliers').catch(() => ({ data: [] })),
    ])
    laboratories.value = Array.isArray(labRes.data) ? labRes.data
      : (Array.isArray(labRes.data?.data) ? labRes.data.data : [])
    suppliers.value = Array.isArray(supRes.data) ? supRes.data
      : (Array.isArray(supRes.data?.data) ? supRes.data.data : [])
  } catch (err) {
    console.error('Error cargando catálogos:', err)
  }
}

// Debounce para el campo de búsqueda
let searchTimer = null
watch(() => filters.value.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => store.fetchReport(), 500)
})

watch(error, val => { if (val) showMessage(val, 'error') })

onMounted(() => {
  fetchCatalogs()
  store.fetchReport()
})

onUnmounted(() => clearTimeout(searchTimer))

// ─── Generación del PDF ──────────────────────────────────────────────────────
const downloadPdf = () => {
  if (!groups.value.length) {
    showMessage('No hay datos disponibles para generar el PDF.', 'warning')
    return
  }
  buyerNameDialog.value = true
}

const confirmDownloadPdf = () => {
  buyerNameDialog.value = false
  try {
    pdfSupplierReturnsGenerator(data.value, {
      buyerName: buyerName.value,
    })
    showMessage('PDF generado exitosamente.', 'success')
  } catch (err) {
    console.error('Error generando PDF:', err)
    showMessage('Error al generar el PDF.', 'error')
  }
}

const resetFilters = () => {
  store.resetFilters()
  showMessage('Filtros restablecidos.', 'info')
}
</script>

<template>
  <VContainer fluid class="supplier-returns pa-0">

    <!-- ─── Encabezado de página ───────────────────────────────────────── -->
    <div class="d-flex align-center mb-5 gap-3 flex-wrap">
      <div>
        <h1 class="text-h5 font-weight-black d-flex align-center gap-2">
          <VIcon icon="tabler-rotate-clockwise" color="warning" size="28" />
          Reporte de Devoluciones a Proveedores
        </h1>
        <p class="text-caption text-disabled mb-0">
          Lotes con vencimiento en los próximos <strong>90 días</strong> · Solicitud de canje preventivo
        </p>
      </div>
      <VSpacer />
      <!-- Botón principal de PDF -->
      <VBtn
        color="error"
        variant="flat"
        prepend-icon="tabler-file-type-pdf"
        :disabled="loading || !groups.length"
        class="rounded-lg shadow-sm"
        @click="downloadPdf"
      >
        Descargar PDF
      </VBtn>
    </div>

    <!-- ─── Panel de Filtros ───────────────────────────────────────────── -->
    <VCard class="mb-5 rounded-lg border shadow-sm">
      <VCardText class="pa-4">
        <VRow align="center" class="ga-3">
          <VCol cols="12" md="4">
            <AppTextField
              v-model="filters.search"
              placeholder="Buscar por producto, barcode o No. lote..."
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
                :disabled="loading || !hasActiveFilters"
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

    <!-- ─── KPI Cards ──────────────────────────────────────────────────── -->
    <VRow class="mb-5">
      <VCol
        v-for="(kpi, i) in kpiCards"
        :key="i"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard class="rounded-lg border shadow-sm h-100 kpi-card">
          <VCardText class="pa-4 d-flex align-center">
            <template v-if="loading">
              <VSkeletonLoader type="avatar" width="48" height="48" class="me-4 rounded-lg flex-shrink-0" />
              <div class="flex-grow-1">
                <VSkeletonLoader type="text" width="70%" class="mb-1" />
                <VSkeletonLoader type="heading" width="90%" />
              </div>
            </template>
            <template v-else>
              <VAvatar
                :color="kpi.color"
                variant="tonal"
                size="48"
                rounded="lg"
                class="me-4 flex-shrink-0"
              >
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

    <!-- ─── Estado vacío ───────────────────────────────────────────────── -->
    <VCard v-if="!loading && !groups.length" class="rounded-lg border shadow-sm">
      <VCardText class="d-flex flex-column align-center justify-center pa-12 text-center">
        <VIcon icon="tabler-check-circle" size="64" color="success" class="mb-4" />
        <p class="text-h6 font-weight-bold mb-2">Sin vencimientos a 90 días</p>
        <p class="text-body-2 text-disabled">
          No hay lotes con stock positivo que venzan en los próximos 90 días
          {{ hasActiveFilters ? 'con los filtros aplicados.' : '.' }}
        </p>
        <VBtn
          v-if="hasActiveFilters"
          variant="tonal"
          color="secondary"
          class="mt-4"
          prepend-icon="tabler-eraser"
          @click="resetFilters"
        >
          Limpiar filtros
        </VBtn>
      </VCardText>
    </VCard>

    <!-- ─── Grupos por Laboratorio ─────────────────────────────────────── -->
    <template v-if="groups.length">
      <div class="mb-2 d-flex align-center gap-2">
        <VChip color="warning" variant="tonal" size="small" prepend-icon="tabler-clock-exclamation">
          {{ groups.length }} {{ groups.length === 1 ? 'laboratorio afectado' : 'laboratorios afectados' }}
        </VChip>
        <VChip color="error" variant="tonal" size="small" prepend-icon="tabler-alert-triangle">
          Horizonte: 90 días — corte {{ metadata.cutoff_date }}
        </VChip>
      </div>

      <VExpansionPanels
        v-model="expandedGroups"
        multiple
        class="return-panels"
      >
        <VExpansionPanel
          v-for="(group, gIdx) in groups"
          :key="group.laboratory_id"
          :value="gIdx"
          class="rounded-lg border mb-3 shadow-sm"
          elevation="0"
        >
          <!-- Encabezado del panel -->
          <VExpansionPanelTitle class="pa-4">
            <div class="d-flex align-center gap-3 w-100 flex-wrap">
              <VAvatar color="primary" variant="tonal" size="36" rounded="md">
                <VIcon icon="tabler-building-factory" size="20" />
              </VAvatar>
              <div class="flex-grow-1">
                <span class="font-weight-black text-uppercase text-body-2">
                  {{ group.laboratory_name || 'Sin laboratorio' }}
                </span>
                <div class="d-flex gap-2 flex-wrap mt-1">
                  <VChip size="x-small" variant="tonal" color="warning">
                    {{ group.products_count }} {{ group.products_count === 1 ? 'producto' : 'productos' }}
                  </VChip>
                  <VChip size="x-small" variant="tonal" color="error">
                    {{ Number(group.total_units).toLocaleString('es-VE') }} unidades
                  </VChip>
                  <VChip size="x-small" variant="tonal" color="secondary">
                    {{ formatMoney(group.total_amount) }}
                  </VChip>
                </div>
              </div>
            </div>
          </VExpansionPanelTitle>

          <!-- Tabla de lotes -->
          <VExpansionPanelText class="pa-0">
            <VDivider />
            <VDataTable
              :headers="lotHeaders"
              :items="group.lots"
              :items-per-page="25"
              density="compact"
              class="lot-table"
              no-data-text="Sin lotes registrados"
            >
              <!-- Producto -->
              <template #item.product_name="{ item }">
                <div class="d-flex flex-column py-1">
                  <span class="text-xs font-weight-black text-uppercase">{{ item.product_name }}</span>
                  <span v-if="item.active_ingredient" class="text-super-xs text-disabled">
                    {{ item.active_ingredient }}
                  </span>
                </div>
              </template>

              <!-- Fecha vencimiento -->
              <template #item.expiration_date="{ item }">
                <span class="font-weight-bold text-error text-xs">
                  {{ formatDate(item.expiration_date) }}
                </span>
              </template>

              <!-- Días restantes -->
              <template #item.days_to_expiry="{ item }">
                <VChip
                  :color="daysColor(item.days_to_expiry)"
                  variant="tonal"
                  size="x-small"
                  class="font-weight-black"
                >
                  {{ item.days_to_expiry }}d
                </VChip>
              </template>

              <!-- Cantidad -->
              <template #item.quantity="{ item }">
                <span class="font-weight-black text-xs">
                  {{ Number(item.quantity).toLocaleString('es-VE') }}
                </span>
              </template>

              <!-- Fecha de compra -->
              <template #item.purchase_date="{ item }">
                <span class="text-xs text-medium-emphasis">{{ formatDate(item.purchase_date) }}</span>
              </template>

              <!-- Monto -->
              <template #item.total_amount="{ item }">
                <span class="font-weight-black text-error text-xs">
                  {{ formatMoney(item.total_amount) }}
                </span>
              </template>
            </VDataTable>
          </VExpansionPanelText>
        </VExpansionPanel>
      </VExpansionPanels>
    </template>

    <!-- ─── Diálogo: nombre del encargado antes de generar PDF ───────── -->
    <VDialog v-model="buyerNameDialog" max-width="420">
      <VCard class="rounded-lg">
        <VCardTitle class="pa-4 d-flex align-center gap-2">
          <VIcon icon="tabler-file-type-pdf" color="error" />
          Generar PDF
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <p class="text-body-2 text-medium-emphasis mb-3">
            El PDF generará una carta por cada laboratorio afectado.
            Indica el nombre de quien firma la solicitud.
          </p>
          <AppTextField
            v-model="buyerName"
            label="Nombre de la encargada de compras"
            prepend-inner-icon="tabler-user"
            density="compact"
            hide-details
          />
        </VCardText>
        <VCardActions class="pa-4 pt-0 d-flex gap-2 justify-end">
          <VBtn variant="text" color="secondary" @click="buyerNameDialog = false">Cancelar</VBtn>
          <VBtn variant="flat" color="error" prepend-icon="tabler-download" @click="confirmDownloadPdf">
            Generar y Descargar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ─── Snackbar ────────────────────────────────────────────────────── -->
    <VSnackbar v-model="snackbar.show" :color="snackbar.color" location="top right" :timeout="3500">
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
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}
.return-panels .v-expansion-panel {
  border-radius: 8px !important;
}
.lot-table :deep(th) {
  background-color: rgb(var(--v-theme-surface)) !important;
  font-size: 0.62rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.4px !important;
}
.lot-table :deep(td) {
  font-size: 0.72rem !important;
  height: 44px !important;
}
</style>
