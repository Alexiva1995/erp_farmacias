<script setup>
import { ref, computed } from 'vue'
import axios from '@axios'
import Swal from 'sweetalert2'
import { toast } from '@/plugins/sweetalert'
import { useAbility } from '@casl/vue'

// --- Control de Acceso CASL ---
const ability = useAbility()

// --- Estado Reactivo ---
const productsFile = ref(null)
const lotsFile = ref(null)
const syncMaster = ref(true)
const uploading = ref(false)
const uploadProgress = ref(0)

const productsInputRef = ref(null)
const lotsInputRef = ref(null)
const isDraggingProducts = ref(false)
const isDraggingLots = ref(false)

// Persistencia del último resultado
let initialStats = null
try {
  const raw = localStorage.getItem('last_hybrid_import_result')
  if (raw) initialStats = JSON.parse(raw)
} catch {
  initialStats = null
}
const lastResult = ref(initialStats)

// --- Computed ---
const productsFileSize = computed(() => {
  return productsFile.value ? (productsFile.value.size / 1024).toFixed(2) : '0'
})

const lotsFileSize = computed(() => {
  return lotsFile.value ? (lotsFile.value.size / 1024).toFixed(2) : '0'
})

const canExecute = computed(() => {
  return productsFile.value !== null && lotsFile.value !== null && !uploading.value
})

// --- Control de Archivos ---
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

// --- Procesamiento de Importación ---
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
            Onboarding e Importación Híbrida (Productos + Lotes)
          </VCardTitle>
          <VCardSubtitle class="text-body-2">
            Migración e integración unificada desde el sistema legado hacia el ERP con homologación en Catálogo Maestro.
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="pt-2">
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
                <li><strong>Catálogo Maestro:</strong> Si el código de barra existe en el Master, se asigna su ID oficial y relaciones. Si no existe, se registra automáticamente en el Master para unificarlo sin afectar las tiendas matriz.</li>
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
              Procesar Onboarding Híbrido
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

          <!-- Resumen de Última Ejecución -->
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
                  <span>Resultado del Último Onboarding Híbrido</span>
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
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
