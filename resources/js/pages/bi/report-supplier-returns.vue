<script setup>
/**
 * Reporte de Devoluciones a Proveedores.
 * Muestra lotes con vencimiento <= 90 días agrupados por laboratorio.
 * Permite descargar una carta profesional de canje preventivo en PDF.
 */
import { onMounted, onUnmounted, ref, computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useSupplierReturnsStore } from '@/stores/supplier-returns-store'
import pdfSupplierReturnsGenerator from '@/utils/pdfSupplierReturnsGenerator'
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
const buyerName       = ref('Encargada de Compras')
const expandedGroups  = ref([])

const showMessage = (msg, color = 'success') => {
  snackbar.value = { show: true, message: msg, color }
}

// ── Computed derivados del store ──────────────────────────────────────────────
const groups   = computed(() => data.value?.groups   ?? [])
const summary  = computed(() => data.value?.summary  ?? {})
const metadata = computed(() => data.value?.metadata ?? {})

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
    title: 'Unidades', icon: 'tabler-packages', color: 'error',
    value: Number(summary.value.total_units ?? 0).toLocaleString('es-VE'), suffix: ' U.',
    desc: 'Total unidades en riesgo',
  },
  {
    title: 'Monto en riesgo', icon: 'tabler-cash-off', color: 'secondary',
    value: `$${Number(summary.value.total_amount ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`,
    suffix: '', desc: 'Costo de inventario afectado',
  },
])

// ── Watchers ──────────────────────────────────────────────────────────────────

// Debounce de 500ms en el campo de búsqueda de texto
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

/** Abre el diálogo de confirmación del PDF si hay datos disponibles */
const downloadPdf = () => {
  if (!hasGroups.value) {
    showMessage('No hay datos disponibles para generar el PDF.', 'warning')
    return
  }
  buyerNameDialog.value = true
}

/** Genera y descarga el PDF; bloquea el botón durante la operación */
const confirmDownloadPdf = async () => {
  buyerNameDialog.value = false
  pdfGenerating.value   = true
  try {
    // Usar setTimeout para liberar el hilo UI antes de la operación pesada de jsPDF
    await new Promise(resolve => setTimeout(resolve, 50))
    pdfSupplierReturnsGenerator(data.value, { buyerName: buyerName.value })
    showMessage('PDF generado exitosamente.', 'success')
  } catch (err) {
    console.error('[SupplierReturns] Error generando PDF:', err)
    showMessage('Error al generar el PDF. Intenta de nuevo.', 'error')
  } finally {
    pdfGenerating.value = false
  }
}

/** Reinicia los filtros y emite toast informativo */
const resetFilters = () => {
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
        Descargar PDF
      </VBtn>
    </div>

    <!-- ─── Panel de Filtros ──────────────────────────────────────────────────── -->
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

    <!-- ─── KPI Cards ─────────────────────────────────────────────────────────── -->
    <VRow class="mb-5">
      <VCol
        v-for="(kpi, i) in kpiCards"
        :key="i"
        cols="12" sm="6" md="3"
      >
        <VCard class="rounded-lg border shadow-sm h-100 kpi-card">
          <VCardText class="pa-4 d-flex align-center">
            <!-- Skeleton durante carga -->
            <template v-if="loading">
              <VSkeletonLoader type="avatar" width="48" height="48" class="me-4 rounded-lg flex-shrink-0" />
              <div class="flex-grow-1">
                <VSkeletonLoader type="text" width="60%" class="mb-1" />
                <VSkeletonLoader type="heading" width="85%" />
              </div>
            </template>

            <!-- Contenido real -->
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

    <!-- ─── Skeleton de paneles durante primera carga ─────────────────────────── -->
    <template v-if="loading">
      <VCard v-for="n in 3" :key="`skel-${n}`" class="rounded-lg border mb-3 shadow-sm">
        <VCardText class="pa-4">
          <VSkeletonLoader type="list-item-avatar-two-line" />
        </VCardText>
      </VCard>
    </template>

    <!-- ─── Estado vacío ──────────────────────────────────────────────────────── -->
    <VCard v-else-if="!groups.length" class="rounded-lg border shadow-sm">
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

    <!-- ─── Grupos por Laboratorio ────────────────────────────────────────────── -->
    <template v-else>
      <!-- Chips de contexto -->
      <div class="mb-3 d-flex align-center gap-2 flex-wrap">
        <VChip color="warning" variant="tonal" size="small" prepend-icon="tabler-clock-exclamation">
          {{ groups.length }} {{ groups.length === 1 ? 'laboratorio afectado' : 'laboratorios afectados' }}
        </VChip>
        <VChip color="error" variant="tonal" size="small" prepend-icon="tabler-alert-triangle">
          Horizonte: 90 días — corte {{ metadata.cutoff_date }}
        </VChip>
      </div>

      <!-- Acordeón de laboratorios -->
      <VExpansionPanels v-model="expandedGroups" multiple class="return-panels">
        <VExpansionPanel
          v-for="(group, gIdx) in groups"
          :key="group.laboratory_id"
          :value="gIdx"
          class="rounded-lg border mb-3 shadow-sm"
          elevation="0"
        >
          <!-- Cabecera del panel -->
          <VExpansionPanelTitle class="pa-4">
            <div class="d-flex align-center gap-3 w-100 flex-wrap">
              <VAvatar color="primary" variant="tonal" size="36" rounded="md">
                <VIcon icon="tabler-building-factory" size="20" />
              </VAvatar>
              <div class="flex-grow-1">
                <span class="font-weight-black text-uppercase text-body-2">
                  {{ group.laboratory_name }}
                </span>
                <div class="d-flex gap-2 flex-wrap mt-1">
                  <VChip size="x-small" variant="tonal" color="warning">
                    {{ group.products_count }} {{ group.products_count === 1 ? 'producto' : 'productos' }}
                  </VChip>
                  <VChip size="x-small" variant="tonal" color="error">
                    {{ Number(group.total_units).toLocaleString('es-VE') }} unidades
                  </VChip>
                  <VChip size="x-small" variant="tonal" color="secondary">
                    ${{ Number(group.total_amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                  </VChip>
                </div>
              </div>
            </div>
          </VExpansionPanelTitle>

          <!-- Tabla de lotes — componente extraído -->
          <VExpansionPanelText class="pa-0">
            <VDivider />
            <SupplierReturnsLotTable :lots="group.lots" />
          </VExpansionPanelText>
        </VExpansionPanel>
      </VExpansionPanels>
    </template>

    <!-- ─── Diálogo: firma antes de generar PDF ───────────────────────────────── -->
    <VDialog v-model="buyerNameDialog" max-width="420" persistent>
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
            autofocus
          />
        </VCardText>
        <VCardActions class="pa-4 pt-0 d-flex gap-2 justify-end">
          <VBtn variant="text" color="secondary" @click="buyerNameDialog = false">
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
/* Tarjeta KPI con micro-interacción hover */
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

/* Paneles de expansión sin bordes dobles */
.return-panels .v-expansion-panel {
  border-radius: 8px !important;
}
</style>
