<script setup>
import { ref, computed } from 'vue'
import axios from '@axios'
import Swal from 'sweetalert2'
import { toast } from '@/plugins/sweetalert'
import { useAbility } from '@casl/vue'

// --- Control de Acceso CASL ---
const ability = useAbility()

// --- Pestaña Principal Activa ---
const activeTab = ref('products_lots')

// ==========================================
// SECCIÓN 1: PRODUCTOS Y LOTES
// ==========================================
const productsFile = ref(null)
const lotsFile = ref(null)
const syncMaster = ref(true)
const uploading = ref(false)
const uploadProgress = ref(0)

const productsInputRef = ref(null)
const lotsInputRef = ref(null)
const isDraggingProducts = ref(false)
const isDraggingLots = ref(false)

// Persistencia del último resultado de productos
let initialStats = null
try {
  const raw = localStorage.getItem('last_hybrid_import_result')
  if (raw) initialStats = JSON.parse(raw)
} catch {
  initialStats = null
}
const lastResult = ref(initialStats)

const productsFileSize = computed(() => {
  return productsFile.value ? (productsFile.value.size / 1024).toFixed(2) : '0'
})

const lotsFileSize = computed(() => {
  return lotsFile.value ? (lotsFile.value.size / 1024).toFixed(2) : '0'
})

const canExecute = computed(() => {
  return productsFile.value !== null && lotsFile.value !== null && !uploading.value
})

const clearProductsFile = () => {
  productsFile.value = null
  if (productsInputRef.value) productsInputRef.value.value = ''
}

const clearLotsFile = () => {
  lotsFile.value = null
  if (lotsInputRef.value) lotsInputRef.value.value = ''
}

const clearAll = () => {
  clearProductsFile()
  clearLotsFile()
}

const clearReport = () => {
  lastResult.value = null
  try {
    localStorage.removeItem('last_hybrid_import_result')
  } catch {}
}

const onProductsFileSelected = event => {
  const file = event.target.files?.[0]
  if (file) productsFile.value = file
}

const onLotsFileSelected = event => {
  const file = event.target.files?.[0]
  if (file) lotsFile.value = file
}

const onDropProducts = event => {
  isDraggingProducts.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) {
    if (file.name.endsWith('.xlsx') || file.name.endsWith('.xls') || file.name.endsWith('.csv')) {
      productsFile.value = file
    } else {
      toast.error('Formato no válido. Solo se admiten archivos Excel (.xlsx, .xls) o CSV.')
    }
  }
}

const onDropLots = event => {
  isDraggingLots.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) {
    if (file.name.endsWith('.xlsx') || file.name.endsWith('.xls') || file.name.endsWith('.csv')) {
      lotsFile.value = file
    } else {
      toast.error('Formato no válido. Solo se admiten archivos Excel (.xlsx, .xls) o CSV.')
    }
  }
}

const confirmAndProcess = async () => {
  if (!productsFile.value || !lotsFile.value) {
    toast.error('Debes seleccionar tanto el archivo de Productos como el de Lotes.')
    return
  }

  const result = await Swal.fire({
    title: '¿Iniciar Onboarding e Importación Híbrida?',
    html: `
      <div style="text-align:left; font-size:0.95rem; line-height:1.6;">
        <p class="mb-1"><strong>Archivo Productos:</strong> ${productsFile.value.name}</p>
        <p class="mb-1"><strong>Archivo Lotes:</strong> ${lotsFile.value.name}</p>
        <p class="mb-1 text-primary"><strong>Sincronizar Master:</strong> ${syncMaster.value ? 'SÍ' : 'NO'}</p>
        <p class="mt-2 text-caption text-medium-emphasis">
          Se aplicará la regla estricta de tope de stock: el total de existencias en lotes se limitará automáticamente al stock del listado general.
        </p>
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#E20074',
    cancelButtonColor: '#7A0099',
    confirmButtonText: 'Sí, Iniciar Importación',
    cancelButtonText: 'Cancelar',
  })

  if (!result.isConfirmed) return

  await executeImport()
}

const executeImport = async () => {
  uploading.value = true
  uploadProgress.value = 0
  lastResult.value = null

  const formData = new FormData()
  formData.append('products_file', productsFile.value)
  formData.append('lots_file', lotsFile.value)
  formData.append('sync_master', syncMaster.value ? '1' : '0')

  try {
    const response = await axios.post('/import-hybrid-onboarding', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: evt => {
        if (evt.lengthComputable) {
          uploadProgress.value = Math.round((evt.loaded / evt.total) * 100)
        }
      },
    })

    const stats = response.data?.data ?? {}
    lastResult.value = stats
    try {
      localStorage.setItem('last_hybrid_import_result', JSON.stringify(stats))
    } catch {}

    Swal.fire({
      icon: 'success',
      title: 'Onboarding Híbrido Completado',
      html: `
        <div style="text-align:left;font-size:0.92rem;line-height:1.7;">
          <p class="mb-1"><strong>Total Productos Procesados:</strong> ${Number(stats.total_products ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-primary"><strong>Productos Creados:</strong> ${Number(stats.created ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-info"><strong>Productos Actualizados:</strong> ${Number(stats.updated ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-success"><strong>Homologados con Master:</strong> ${Number(stats.matched_master ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-purple"><strong>Registrados Nuevos en Master:</strong> ${Number(stats.registered_master ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-secondary"><strong>Lotes Creados:</strong> ${Number(stats.total_lots_created ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-warning"><strong>Lotes Reducidos por Tope de Stock:</strong> ${Number(stats.lots_reduced_for_cap ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-info"><strong>Movimientos de Trazabilidad:</strong> ${Number(stats.traceability_movements_created ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-0 text-success"><strong>Stock Total Consolidado:</strong> ${Number(stats.total_consolidated_stock ?? 0).toLocaleString('es-VE')} uds.</p>
        </div>
      `,
      confirmButtonText: 'Aceptar',
      confirmButtonColor: '#E20074',
    })

    toast.success('Onboarding e importación híbrida completados con éxito.')
    clearAll()
  } catch (err) {
    const message = err.response?.data?.message ?? 'Ocurrió un error al procesar los archivos de importación.'
    toast.error(message)
  } finally {
    uploading.value = false
    setTimeout(() => { uploadProgress.value = 0 }, 1500)
  }
}

// ==========================================================================
// SECCIÓN 2: PROVEEDORES Y CUENTAS POR PAGAR (CXP)
// ==========================================================================
const payablesFile = ref(null)
const payablesInputRef = ref(null)
const isDraggingPayables = ref(false)
const analyzingPayables = ref(false)
const processingPayables = ref(false)
const isPayablesModalOpen = ref(false)
const modalActiveTab = ref('new_suppliers')

// Estado del análisis de proveedores
const payablesAnalysis = ref(null)
const newSuppliersList = ref([])
const matchedSuppliersList = ref([])

// Persistencia del último resultado de CXP
let initialPayablesStats = null
try {
  const rawPayables = localStorage.getItem('last_payables_import_result')
  if (rawPayables) initialPayablesStats = JSON.parse(rawPayables)
} catch {
  initialPayablesStats = null
}
const lastPayablesResult = ref(initialPayablesStats)

const payablesFileSize = computed(() => {
  return payablesFile.value ? (payablesFile.value.size / 1024).toFixed(2) : '0'
})

const supplierTypeOptions = [
  { title: 'Inventario (Droguería)', value: 'drogueria' },
  { title: 'Gasto / Servicio (Externo)', value: 'externo' },
]

const clearPayablesFile = () => {
  payablesFile.value = null
  if (payablesInputRef.value) payablesInputRef.value.value = ''
}

const clearPayablesReport = () => {
  lastPayablesResult.value = null
  try {
    localStorage.removeItem('last_payables_import_result')
  } catch {}
}

const onPayablesFileSelected = event => {
  const file = event.target.files?.[0]
  if (file) payablesFile.value = file
}

const onDropPayables = event => {
  isDraggingPayables.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) {
    if (file.name.endsWith('.xlsx') || file.name.endsWith('.xls') || file.name.endsWith('.csv') || file.name.endsWith('.txt')) {
      payablesFile.value = file
    } else {
      toast.error('Formato no válido. Solo se admiten archivos Excel (.xlsx, .xls) o CSV.')
    }
  }
}

// Analizar archivo de proveedores y CXP
const analyzePayablesFile = async () => {
  if (!payablesFile.value) {
    toast.error('Por favor selecciona el archivo de Cuentas por Pagar a Proveedores.')
    return
  }

  analyzingPayables.value = true

  const formData = new FormData()
  formData.append('payables_file', payablesFile.value)

  try {
    const response = await axios.post('/import-hybrid/analyze-payables', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    const data = response.data?.data ?? {}
    payablesAnalysis.value = data

    // Copiar lista de nuevos proveedores permitiendo al usuario configurar el tipo
    newSuppliersList.value = (data.new_suppliers ?? []).map(s => ({
      ...s,
      selected_type: s.suggested_type || 'drogueria',
    }))

    // Copiar lista de coincidentes
    matchedSuppliersList.value = data.matched_suppliers ?? []

    // Determinar pestaña inicial según si hay nuevos o coincidentes
    if (newSuppliersList.value.length > 0) {
      modalActiveTab.value = 'new_suppliers'
    } else {
      modalActiveTab.value = 'matched_suppliers'
    }

    isPayablesModalOpen.value = true
    toast.success('Análisis completado. Por favor revisa las coincidencias y tipos de proveedores.')
  } catch (err) {
    const message = err.response?.data?.message ?? 'Ocurrió un error al analizar el archivo de Cuentas por Pagar.'
    toast.error(message)
  } finally {
    analyzingPayables.value = false
  }
}

// Asignación masiva de tipo para proveedores nuevos
const setAllNewSuppliersType = type => {
  newSuppliersList.value.forEach(s => {
    s.selected_type = type
  })
  toast.info(`Todos los proveedores nuevos fueron marcados como ${type === 'drogueria' ? 'Droguería (Inventario)' : 'Gasto / Externo'}.`)
}

// Ejecutar importación definitiva de proveedores y facturas
const executePayablesImport = async () => {
  processingPayables.value = true

  // Estructurar payload unificado
  const suppliersPayload = []

  // 1. Agregar proveedores nuevos
  newSuppliersList.value.forEach(s => {
    suppliersPayload.push({
      name: s.name,
      rif: s.rif,
      sales_phone: s.sales_phone,
      type: s.selected_type || 'drogueria',
      is_new: true,
      existing_id: null,
      update_data: null,
      invoices: s.invoices || [],
    })
  })

  // 2. Agregar proveedores coincidentes
  matchedSuppliersList.value.forEach(s => {
    suppliersPayload.push({
      name: s.existing_name,
      rif: s.extracted_rif || s.existing_rif,
      sales_phone: s.extracted_phone || s.existing_phone,
      type: s.existing_type || 'drogueria',
      is_new: false,
      existing_id: s.existing_id,
      update_data: s.updates_to_apply || null,
      invoices: s.invoices || [],
    })
  })

  try {
    const response = await axios.post('/import-hybrid/process-payables', {
      suppliers: suppliersPayload,
    })

    const stats = response.data?.data ?? {}
    lastPayablesResult.value = stats
    try {
      localStorage.setItem('last_payables_import_result', JSON.stringify(stats))
    } catch {}

    isPayablesModalOpen.value = false

    Swal.fire({
      icon: 'success',
      title: 'Proveedores y Facturas Importados con Éxito',
      html: `
        <div style="text-align:left;font-size:0.92rem;line-height:1.7;">
          <p class="mb-1 text-primary"><strong>Proveedores Creados:</strong> ${Number(stats.suppliers_created ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-info"><strong>Proveedores Actualizados/Enriquecidos:</strong> ${Number(stats.suppliers_updated ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-success"><strong>Facturas CXP Creadas:</strong> ${Number(stats.invoices_created ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-warning"><strong>Facturas Omitidas (Duplicadas):</strong> ${Number(stats.invoices_skipped_duplicate ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-success"><strong>Monto Total Facturado USD:</strong> $${Number(stats.total_amount_usd ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })}</p>
          <p class="mb-0 text-secondary"><strong>Monto Total Facturado VES:</strong> Bs. ${Number(stats.total_amount_ves ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })}</p>
        </div>
      `,
      confirmButtonText: 'Aceptar',
      confirmButtonColor: '#E20074',
    })

    toast.success('Proveedores y cuentas por pagar creados exitosamente.')
    clearPayablesFile()
  } catch (err) {
    const message = err.response?.data?.message ?? 'Ocurrió un error al importar los proveedores y facturas.'
    toast.error(message)
  } finally {
    processingPayables.value = false
  }
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2 text-h5">
            <VIcon
              icon="tabler-arrows-split-2"
              color="primary"
            />
            Centro de Importación y Onboarding Híbrido
          </VCardTitle>
          <VCardSubtitle class="text-body-2">
            Migración e integración unificada desde el sistema legado: Catálogo de Productos con Lotes y Cuentas por Pagar a Proveedores con Correlación Inteligente.
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="pt-0">
          <!-- Pestañas de Módulos de Importación -->
          <VTabs
            v-model="activeTab"
            color="primary"
            class="mb-6 border-b"
          >
            <VTab value="products_lots">
              <VIcon
                icon="tabler-packages"
                class="me-2"
              />
              1. Catálogo de Productos y Lotes
            </VTab>
            <VTab value="payables_suppliers">
              <VIcon
                icon="tabler-building-bank"
                class="me-2"
              />
              2. Proveedores y Cuentas por Pagar (CXP)
            </VTab>
          </VTabs>

          <VWindow v-model="activeTab">
            <!-- =================================================================== -->
            <!-- PESTAÑA 1: PRODUCTOS Y LOTES -->
            <!-- =================================================================== -->
            <VWindowItem value="products_lots">
              <!-- Alerta informativa de reglas del sistema híbrido -->
              <VAlert
                type="info"
                variant="tonal"
                density="comfortable"
                class="mb-6"
              >
                <div class="d-flex flex-column gap-1">
                  <span class="font-weight-bold">Reglas de Integración y Tope de Stock:</span>
                  <ul class="ms-4 text-caption">
                    <li><strong>Tope de Stock:</strong> La existencia del <em>Listado de Productos</em> es la cantidad máxima autorizada. Si los lotes suman más, el sistema reduce automáticamente las cantidades excedentes.</li>
                    <li><strong>Catálogo Maestro:</strong> Si el código de barra existe en el Master, se asigna su ID oficial y relaciones. Si no existe, se registra automáticamente en el Master.</li>
                    <li><strong>Trazabilidad:</strong> Se procesan fechas de vencimiento reales (`DD/MM/YYYY`) y números de lote de cada producto.</li>
                  </ul>
                </div>
              </VAlert>

              <!-- Parámetros de Sincronización -->
              <VCard
                variant="outlined"
                class="mb-6"
              >
                <VCardItem class="pb-2">
                  <VCardTitle class="text-subtitle-1 d-flex align-center gap-2">
                    <VIcon
                      icon="tabler-cloud-lock"
                      size="20"
                      color="primary"
                    />
                    Configuración del Catálogo Maestro
                  </VCardTitle>
                </VCardItem>

                <VCardText>
                  <VRow>
                    <VCol
                      cols="12"
                      md="8"
                      class="d-flex align-center"
                    >
                      <VSwitch
                        v-model="syncMaster"
                        color="primary"
                        label="Homologar y Registrar en Catálogo Maestro"
                        density="comfortable"
                        hide-details="auto"
                        persistent-hint
                        hint="Consulta IDs unificados y crea en el servidor central los productos no existentes"
                      />
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>

              <!-- Zona de Subida de Ambos Archivos -->
              <VRow>
                <!-- Archivo 1: Listado General de Productos -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <div class="text-subtitle-2 font-weight-medium mb-2 d-flex align-center gap-1">
                    <VIcon
                      icon="tabler-file-spreadsheet"
                      size="18"
                      color="primary"
                    />
                    1. Archivo General de Productos (Existencias y Costos)
                  </div>

                  <div
                    class="d-flex flex-column align-center justify-center rounded pa-6 border-dashed"
                    :style="{
                      borderWidth: '2px',
                      borderColor: isDraggingProducts ? 'rgb(var(--v-theme-primary))' : 'rgba(var(--v-border-color), 0.35)',
                      backgroundColor: isDraggingProducts ? 'rgba(var(--v-theme-primary), 0.05)' : 'transparent',
                      minHeight: '190px'
                    }"
                    @dragover.prevent="isDraggingProducts = true"
                    @dragleave.prevent="isDraggingProducts = false"
                    @drop.prevent="onDropProducts"
                  >
                    <VIcon
                      :icon="productsFile ? 'tabler-file-check' : 'tabler-file-upload'"
                      size="40"
                      :color="productsFile ? 'success' : 'primary'"
                      class="mb-2"
                    />

                    <template v-if="!productsFile">
                      <span class="text-body-2 font-weight-medium mb-1">
                        Arrastra el archivo de Productos
                      </span>
                      <span class="text-caption text-disabled mb-3">
                        Ejemplo: Listado de Productos 03-10-2026.xls
                      </span>
                    </template>
                    <template v-else>
                      <span class="text-body-2 font-weight-bold mb-1 text-center">{{ productsFile.name }}</span>
                      <span class="text-caption text-medium-emphasis mb-2">{{ productsFileSize }} KB</span>
                    </template>

                    <input
                      ref="productsInputRef"
                      type="file"
                      accept=".xlsx, .xls, .csv"
                      class="d-none"
                      @change="onProductsFileSelected"
                    >

                    <div class="d-flex gap-2">
                      <VBtn
                        color="secondary"
                        variant="outlined"
                        size="small"
                        prepend-icon="tabler-upload"
                        :disabled="uploading"
                        @click="productsInputRef?.click()"
                      >
                        {{ productsFile ? 'Cambiar' : 'Seleccionar Archivo' }}
                      </VBtn>

                      <VBtn
                        v-if="productsFile"
                        color="error"
                        variant="text"
                        icon="tabler-trash"
                        size="small"
                        :disabled="uploading"
                        @click="clearProductsFile"
                      />
                    </div>
                  </div>
                </VCol>

                <!-- Archivo 2: Listado Detallado de Lotes -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <div class="text-subtitle-2 font-weight-medium mb-2 d-flex align-center gap-1">
                    <VIcon
                      icon="tabler-packages"
                      size="18"
                      color="info"
                    />
                    2. Archivo Detallado de Lotes (Vencimientos y Cantidades)
                  </div>

                  <div
                    class="d-flex flex-column align-center justify-center rounded pa-6 border-dashed"
                    :style="{
                      borderWidth: '2px',
                      borderColor: isDraggingLots ? 'rgb(var(--v-theme-info))' : 'rgba(var(--v-border-color), 0.35)',
                      backgroundColor: isDraggingLots ? 'rgba(var(--v-theme-info), 0.05)' : 'transparent',
                      minHeight: '190px'
                    }"
                    @dragover.prevent="isDraggingLots = true"
                    @dragleave.prevent="isDraggingLots = false"
                    @drop.prevent="onDropLots"
                  >
                    <VIcon
                      :icon="lotsFile ? 'tabler-file-check' : 'tabler-file-upload'"
                      size="40"
                      :color="lotsFile ? 'success' : 'info'"
                      class="mb-2"
                    />

                    <template v-if="!lotsFile">
                      <span class="text-body-2 font-weight-medium mb-1">
                        Arrastra el archivo de Lotes
                      </span>
                      <span class="text-caption text-disabled mb-3">
                        Ejemplo: Listado de Productos lotes 03-10-2026.xls
                      </span>
                    </template>
                    <template v-else>
                      <span class="text-body-2 font-weight-bold mb-1 text-center">{{ lotsFile.name }}</span>
                      <span class="text-caption text-medium-emphasis mb-2">{{ lotsFileSize }} KB</span>
                    </template>

                    <input
                      ref="lotsInputRef"
                      type="file"
                      accept=".xlsx, .xls, .csv"
                      class="d-none"
                      @change="onLotsFileSelected"
                    >

                    <div class="d-flex gap-2">
                      <VBtn
                        color="secondary"
                        variant="outlined"
                        size="small"
                        prepend-icon="tabler-upload"
                        :disabled="uploading"
                        @click="lotsInputRef?.click()"
                      >
                        {{ lotsFile ? 'Cambiar' : 'Seleccionar Archivo' }}
                      </VBtn>

                      <VBtn
                        v-if="lotsFile"
                        color="error"
                        variant="text"
                        icon="tabler-trash"
                        size="small"
                        :disabled="uploading"
                        @click="clearLotsFile"
                      />
                    </div>
                  </div>
                </VCol>
              </VRow>

              <!-- Botón de Ejecución y Barra de Progreso -->
              <div class="d-flex flex-column align-center justify-center mt-6">
                <VBtn
                  color="primary"
                  size="large"
                  prepend-icon="tabler-player-play"
                  :disabled="!canExecute"
                  :loading="uploading"
                  @click="confirmAndProcess"
                >
                  Procesar Onboarding de Catálogo
                </VBtn>

                <div
                  v-if="uploading"
                  class="w-100 mt-4 text-center"
                  style="max-width: 420px"
                >
                  <VProgressLinear
                    v-model="uploadProgress"
                    color="primary"
                    height="8"
                    rounded
                    striped
                  />
                  <span class="text-caption text-medium-emphasis mt-1 d-block">
                    Subiendo y procesando catálogo: {{ uploadProgress }}%
                  </span>
                </div>
              </div>

              <!-- Resumen de Última Ejecución de Catálogo -->
              <VCard
                v-if="lastResult"
                variant="tonal"
                color="success"
                class="mt-8 border"
              >
                <VCardItem class="pb-2">
                  <VCardTitle class="d-flex align-center justify-space-between text-subtitle-1 text-success">
                    <div class="d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-circle-check"
                        size="22"
                        color="success"
                      />
                      <span>Resultado del Último Onboarding de Catálogo</span>
                    </div>
                    <VBtn
                      size="x-small"
                      variant="text"
                      color="success"
                      icon="tabler-x"
                      @click="clearReport"
                    />
                  </VCardTitle>
                </VCardItem>

                <VCardText>
                  <VRow dense>
                    <VCol
                      cols="6"
                      sm="4"
                      md="3"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-medium-emphasis">Total Procesados</div>
                        <div class="text-body-1 font-weight-bold">{{ Number(lastResult.total_products ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="6"
                      sm="4"
                      md="3"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-primary">Nuevos Creados</div>
                        <div class="text-body-1 font-weight-bold text-primary">{{ Number(lastResult.created ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="6"
                      sm="4"
                      md="3"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-info">Actualizados</div>
                        <div class="text-body-1 font-weight-bold text-info">{{ Number(lastResult.updated ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="6"
                      sm="4"
                      md="3"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-success">Con Master ID</div>
                        <div class="text-body-1 font-weight-bold text-success">{{ Number(lastResult.matched_master ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="6"
                      sm="4"
                      md="3"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-secondary">Lotes Creados</div>
                        <div class="text-body-1 font-weight-bold text-secondary">{{ Number(lastResult.total_lots_created ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="6"
                      sm="4"
                      md="3"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-warning">Lotes Reducidos (Tope)</div>
                        <div class="text-body-1 font-weight-bold text-warning">{{ Number(lastResult.lots_reduced_for_cap ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="6"
                      sm="4"
                      md="3"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-info">Lotes Completados</div>
                        <div class="text-body-1 font-weight-bold text-info">{{ Number(lastResult.lots_extended_for_shortage ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="6"
                      sm="4"
                      md="3"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-primary">Trazabilidad</div>
                        <div class="text-body-1 font-weight-bold text-primary">{{ Number(lastResult.traceability_movements_created ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="6"
                      sm="4"
                      md="3"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-success">Stock Consolidado</div>
                        <div class="text-body-1 font-weight-bold text-success">{{ Number(lastResult.total_consolidated_stock ?? 0).toLocaleString('es-VE') }} uds.</div>
                      </div>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VWindowItem>

            <!-- =================================================================== -->
            <!-- PESTAÑA 2: PROVEEDORES Y CUENTAS POR PAGAR (CXP) -->
            <!-- =================================================================== -->
            <VWindowItem value="payables_suppliers">
              <VAlert
                type="info"
                variant="tonal"
                density="comfortable"
                class="mb-6"
              >
                <div class="d-flex flex-column gap-1">
                  <span class="font-weight-bold">Correlación Inteligente y Creación de Facturas Pendientes (CXP):</span>
                  <ul class="ms-4 text-caption">
                    <li><strong>Correlación por RIF:</strong> El sistema identifica automáticamente a los proveedores existentes comparando su RIF o razón social.</li>
                    <li><strong>Enriquecimiento de Datos:</strong> Si el proveedor ya existe pero carece de RIF o teléfono, el sistema actualizará su ficha con la información del archivo.</li>
                    <li><strong>Clasificación de Nuevos:</strong> Para los proveedores que no existan, podrás definir interactivamente si son de <strong>Inventario (Droguería)</strong> o de <strong>Gastos/Servicios (Externo)</strong>.</li>
                    <li><strong>Facturas Pendientes:</strong> Se crearán automáticamente los registros de facturas por pagar (`invoices`) con sus montos en USD, Bs, fechas de emisión/vencimiento y factores de indexación.</li>
                  </ul>
                </div>
              </VAlert>

              <!-- Zona de Carga de Cuentas por Pagar -->
              <VRow justify="center">
                <VCol
                  cols="12"
                  md="8"
                >
                  <div class="text-subtitle-2 font-weight-medium mb-2 d-flex align-center gap-1">
                    <VIcon
                      icon="tabler-file-invoice"
                      size="18"
                      color="primary"
                    />
                    Archivo de Cuentas por Pagar a Proveedores (CXP)
                  </div>

                  <div
                    class="d-flex flex-column align-center justify-center rounded pa-8 border-dashed"
                    :style="{
                      borderWidth: '2px',
                      borderColor: isDraggingPayables ? 'rgb(var(--v-theme-primary))' : 'rgba(var(--v-border-color), 0.35)',
                      backgroundColor: isDraggingPayables ? 'rgba(var(--v-theme-primary), 0.05)' : 'transparent',
                      minHeight: '210px'
                    }"
                    @dragover.prevent="isDraggingPayables = true"
                    @dragleave.prevent="isDraggingPayables = false"
                    @drop.prevent="onDropPayables"
                  >
                    <VIcon
                      :icon="payablesFile ? 'tabler-file-check' : 'tabler-upload'"
                      size="48"
                      :color="payablesFile ? 'success' : 'primary'"
                      class="mb-2"
                    />

                    <template v-if="!payablesFile">
                      <span class="text-body-1 font-weight-medium mb-1">
                        Arrastra el archivo de Cuentas por Pagar
                      </span>
                      <span class="text-caption text-disabled mb-4">
                        Formatos admitidos: Excel (.xlsx, .xls) o CSV (.csv, .txt)
                      </span>
                    </template>
                    <template v-else>
                      <span class="text-body-1 font-weight-bold mb-1 text-center">{{ payablesFile.name }}</span>
                      <span class="text-caption text-medium-emphasis mb-3">{{ payablesFileSize }} KB</span>
                    </template>

                    <input
                      ref="payablesInputRef"
                      type="file"
                      accept=".xlsx, .xls, .csv, .txt"
                      class="d-none"
                      @change="onPayablesFileSelected"
                    >

                    <div class="d-flex gap-2">
                      <VBtn
                        color="secondary"
                        variant="outlined"
                        size="small"
                        prepend-icon="tabler-upload"
                        :disabled="analyzingPayables || processingPayables"
                        @click="payablesInputRef?.click()"
                      >
                        {{ payablesFile ? 'Cambiar Archivo' : 'Seleccionar Archivo' }}
                      </VBtn>

                      <VBtn
                        v-if="payablesFile"
                        color="error"
                        variant="text"
                        icon="tabler-trash"
                        size="small"
                        :disabled="analyzingPayables || processingPayables"
                        @click="clearPayablesFile"
                      />
                    </div>
                  </div>

                  <!-- Botón de Análisis -->
                  <div class="d-flex justify-center mt-6">
                    <VBtn
                      color="primary"
                      size="large"
                      prepend-icon="tabler-scan-eye"
                      :disabled="!payablesFile || analyzingPayables || processingPayables"
                      :loading="analyzingPayables"
                      @click="analyzePayablesFile"
                    >
                      Analizar Proveedores y CXP
                    </VBtn>
                  </div>
                </VCol>
              </VRow>

              <!-- Resumen de Última Ejecución de CXP -->
              <VCard
                v-if="lastPayablesResult"
                variant="tonal"
                color="success"
                class="mt-8 border"
              >
                <VCardItem class="pb-2">
                  <VCardTitle class="d-flex align-center justify-space-between text-subtitle-1 text-success">
                    <div class="d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-circle-check"
                        size="22"
                        color="success"
                      />
                      <span>Resultado de la Última Importación de Proveedores y CXP</span>
                    </div>
                    <VBtn
                      size="x-small"
                      variant="text"
                      color="success"
                      icon="tabler-x"
                      @click="clearPayablesReport"
                    />
                  </VCardTitle>
                </VCardItem>

                <VCardText>
                  <VRow dense>
                    <VCol
                      cols="6"
                      sm="4"
                      md="2"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-primary">Proveedores Nuevos</div>
                        <div class="text-body-1 font-weight-bold text-primary">{{ Number(lastPayablesResult.suppliers_created ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="6"
                      sm="4"
                      md="2"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-info">Actualizados</div>
                        <div class="text-body-1 font-weight-bold text-info">{{ Number(lastPayablesResult.suppliers_updated ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="6"
                      sm="4"
                      md="2"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-success">Facturas Creadas</div>
                        <div class="text-body-1 font-weight-bold text-success">{{ Number(lastPayablesResult.invoices_created ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="6"
                      sm="4"
                      md="2"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-warning">Duplicadas Omitidas</div>
                        <div class="text-body-1 font-weight-bold text-warning">{{ Number(lastPayablesResult.invoices_skipped_duplicate ?? 0).toLocaleString('es-VE') }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="12"
                      sm="4"
                      md="2"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-success">Total USD</div>
                        <div class="text-body-1 font-weight-bold text-success">${{ Number(lastPayablesResult.total_amount_usd ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}</div>
                      </div>
                    </VCol>

                    <VCol
                      cols="12"
                      sm="4"
                      md="2"
                    >
                      <div class="pa-2 bg-surface rounded text-center border">
                        <div class="text-caption text-secondary">Total VES</div>
                        <div class="text-body-1 font-weight-bold text-secondary">Bs. {{ Number(lastPayablesResult.total_amount_ves ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}</div>
                      </div>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VWindowItem>
          </VWindow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- =================================================================== -->
    <!-- MODAL DE CORRELACIÓN Y CLASIFICACIÓN DE PROVEEDORES Y FACTURAS -->
    <!-- =================================================================== -->
    <VDialog
      v-model="isPayablesModalOpen"
      max-width="1100px"
      persistent
      scrollable
    >
      <VCard>
        <!-- Encabezado del Modal -->
        <VCardItem class="border-b bg-surface pb-3">
          <VCardTitle class="d-flex align-center justify-space-between text-h6">
            <div class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-git-merge"
                color="primary"
                size="26"
              />
              <span>Correlación y Clasificación de Proveedores & CXP</span>
            </div>
            <VBtn
              variant="text"
              color="secondary"
              icon="tabler-x"
              size="small"
              :disabled="processingPayables"
              @click="isPayablesModalOpen = false"
            />
          </VCardTitle>
          <VCardSubtitle class="text-caption">
            Revisa las coincidencias detectadas por RIF y clasifica el tipo de cada proveedor nuevo antes de persistir las facturas pendientes.
          </VCardSubtitle>
        </VCardItem>

        <!-- Cuerpo del Modal -->
        <VCardText
          class="pa-4"
          style="max-height: 65vh"
        >
          <!-- Tarjetas de Resumen General -->
          <VRow
            v-if="payablesAnalysis"
            dense
            class="mb-4"
          >
            <VCol
              cols="6"
              sm="3"
            >
              <VCard
                variant="tonal"
                color="primary"
                class="pa-2 text-center"
              >
                <div class="text-caption">Proveedores Nuevos</div>
                <div class="text-h6 font-weight-bold text-primary">
                  {{ newSuppliersList.length }}
                </div>
              </VCard>
            </VCol>

            <VCol
              cols="6"
              sm="3"
            >
              <VCard
                variant="tonal"
                color="success"
                class="pa-2 text-center"
              >
                <div class="text-caption">Coincidentes en ERP</div>
                <div class="text-h6 font-weight-bold text-success">
                  {{ matchedSuppliersList.length }}
                </div>
              </VCard>
            </VCol>

            <VCol
              cols="6"
              sm="3"
            >
              <VCard
                variant="tonal"
                color="info"
                class="pa-2 text-center"
              >
                <div class="text-caption">Facturas a Generar</div>
                <div class="text-h6 font-weight-bold text-info">
                  {{ payablesAnalysis.summary?.total_invoices ?? 0 }}
                </div>
              </VCard>
            </VCol>

            <VCol
              cols="6"
              sm="3"
            >
              <VCard
                variant="tonal"
                color="warning"
                class="pa-2 text-center"
              >
                <div class="text-caption">Total Deuda USD</div>
                <div class="text-h6 font-weight-bold text-warning">
                  ${{ Number(payablesAnalysis.summary?.total_amount_usd ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                </div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Pestañas internas del Modal -->
          <VTabs
            v-model="modalActiveTab"
            color="primary"
            density="comfortable"
            class="mb-4 border-b"
          >
            <VTab value="new_suppliers">
              <VIcon
                icon="tabler-user-plus"
                class="me-2"
                color="primary"
              />
              Proveedores Nuevos ({{ newSuppliersList.length }})
            </VTab>

            <VTab value="matched_suppliers">
              <VIcon
                icon="tabler-link"
                class="me-2"
                color="success"
              />
              Proveedores Enlazados / Coincidentes ({{ matchedSuppliersList.length }})
            </VTab>
          </VTabs>

          <VWindow v-model="modalActiveTab">
            <!-- Sub-pestaña 1: Proveedores Nuevos -->
            <VWindowItem value="new_suppliers">
              <div
                v-if="newSuppliersList.length > 0"
                class="d-flex flex-column gap-3"
              >
                <!-- Barra de acciones masivas -->
                <div class="d-flex flex-wrap align-center justify-space-between gap-2 pa-2 rounded bg-surface border">
                  <span class="text-caption font-weight-medium">
                    Asignación masiva para los {{ newSuppliersList.length }} proveedores nuevos:
                  </span>
                  <div class="d-flex gap-2">
                    <VBtn
                      size="x-small"
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-packages"
                      @click="setAllNewSuppliersType('drogueria')"
                    >
                      Marcar Todos como Droguería (Inventario)
                    </VBtn>
                    <VBtn
                      size="x-small"
                      variant="tonal"
                      color="secondary"
                      prepend-icon="tabler-receipt-2"
                      @click="setAllNewSuppliersType('externo')"
                    >
                      Marcar Todos como Gasto / Externo
                    </VBtn>
                  </div>
                </div>

                <!-- Tabla de Nuevos Proveedores -->
                <VTable
                  density="compact"
                  class="border rounded"
                >
                  <thead>
                    <tr class="bg-surface">
                      <th class="text-left">RIF Extraído</th>
                      <th class="text-left">Nombre / Razón Social</th>
                      <th class="text-left">Teléfono</th>
                      <th class="text-center">Facturas</th>
                      <th class="text-right">Total USD</th>
                      <th
                        class="text-left"
                        style="min-width: 220px;"
                      >
                        Tipo de Proveedor
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(item, idx) in newSuppliersList"
                      :key="idx"
                    >
                      <td class="font-weight-medium text-caption">
                        <VChip
                          size="x-small"
                          color="primary"
                          variant="tonal"
                        >
                          {{ item.rif || 'S/R' }}
                        </VChip>
                      </td>
                      <td class="text-body-2 font-weight-bold">
                        {{ item.name }}
                      </td>
                      <td class="text-caption text-medium-emphasis">
                        {{ item.sales_phone || 'No especificado' }}
                      </td>
                      <td class="text-center">
                        <VChip
                          size="x-small"
                          color="info"
                          variant="tonal"
                        >
                          {{ item.invoices_count }} fact.
                        </VChip>
                      </td>
                      <td class="text-right text-body-2 font-weight-bold text-success">
                        ${{ Number(item.total_usd || 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                      </td>
                      <td>
                        <VSelect
                          v-model="item.selected_type"
                          :items="supplierTypeOptions"
                          density="compact"
                          variant="outlined"
                          hide-details
                          class="my-1"
                        />
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </div>

              <div
                v-else
                class="text-center py-8 text-medium-emphasis"
              >
                <VIcon
                  icon="tabler-circle-check"
                  size="36"
                  color="success"
                  class="mb-2"
                />
                <div class="text-body-2">No hay proveedores nuevos en el archivo. Todos los proveedores ya existen en el ERP.</div>
              </div>
            </VWindowItem>

            <!-- Sub-pestaña 2: Proveedores Coincidentes -->
            <VWindowItem value="matched_suppliers">
              <div
                v-if="matchedSuppliersList.length > 0"
                class="d-flex flex-column gap-3"
              >
                <VTable
                  density="compact"
                  class="border rounded"
                >
                  <thead>
                    <tr class="bg-surface">
                      <th class="text-left">Proveedor en Archivo</th>
                      <th class="text-left">Coincidencia en ERP</th>
                      <th class="text-center">Tipo de Enlace</th>
                      <th class="text-left">Datos a Enriquecer</th>
                      <th class="text-center">Facturas</th>
                      <th class="text-right">Total USD</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(item, idx) in matchedSuppliersList"
                      :key="idx"
                    >
                      <td>
                        <div class="text-body-2 font-weight-bold">{{ item.extracted_name }}</div>
                        <div class="text-caption text-disabled">RIF: {{ item.extracted_rif || 'S/R' }} | Tel: {{ item.extracted_phone || 'S/T' }}</div>
                      </td>
                      <td>
                        <div class="text-body-2 font-weight-bold text-primary">{{ item.existing_name }}</div>
                        <div class="text-caption text-medium-emphasis">
                          RIF ERP: {{ item.existing_rif || 'Sin RIF previo' }}
                        </div>
                      </td>
                      <td class="text-center">
                        <VChip
                          size="x-small"
                          :color="item.match_reason === 'rif' ? 'success' : 'info'"
                          variant="tonal"
                        >
                          <VIcon
                            :icon="item.match_reason === 'rif' ? 'tabler-id' : 'tabler-file-search'"
                            size="14"
                            class="me-1"
                          />
                          {{ item.match_reason === 'rif' ? 'Por RIF' : 'Por Nombre' }}
                        </VChip>
                      </td>
                      <td>
                        <div
                          v-if="item.updates_to_apply && Object.keys(item.updates_to_apply).length > 0"
                          class="d-flex flex-wrap gap-1"
                        >
                          <VChip
                            v-if="item.updates_to_apply.rif"
                            size="x-small"
                            color="success"
                            variant="outlined"
                          >
                            + RIF: {{ item.updates_to_apply.rif }}
                          </VChip>
                          <VChip
                            v-if="item.updates_to_apply.sales_phone"
                            size="x-small"
                            color="info"
                            variant="outlined"
                          >
                            + Tel: {{ item.updates_to_apply.sales_phone }}
                          </VChip>
                        </div>
                        <span
                          v-else
                          class="text-caption text-disabled"
                        >
                          Ficha completa (sin cambios)
                        </span>
                      </td>
                      <td class="text-center">
                        <VChip
                          size="x-small"
                          color="info"
                          variant="tonal"
                        >
                          {{ item.invoices_count }} fact.
                        </VChip>
                      </td>
                      <td class="text-right text-body-2 font-weight-bold text-success">
                        ${{ Number(item.total_usd || 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </div>

              <div
                v-else
                class="text-center py-8 text-medium-emphasis"
              >
                <VIcon
                  icon="tabler-alert-circle"
                  size="36"
                  color="warning"
                  class="mb-2"
                />
                <div class="text-body-2">No se encontraron coincidencias directas con proveedores existentes.</div>
              </div>
            </VWindowItem>
          </VWindow>
        </VCardText>

        <!-- Pie del Modal con Acciones -->
        <VCardActions class="border-t bg-surface px-4 py-3 d-flex justify-space-between">
          <VBtn
            variant="outlined"
            color="secondary"
            :disabled="processingPayables"
            @click="isPayablesModalOpen = false"
          >
            Cancelar
          </VBtn>

          <VBtn
            color="primary"
            variant="elevated"
            prepend-icon="tabler-check"
            :loading="processingPayables"
            :disabled="processingPayables"
            @click="executePayablesImport"
          >
            Confirmar e Importar Proveedores y Facturas ({{ payablesAnalysis?.summary?.total_invoices ?? 0 }} Facturas)
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VRow>
</template>
