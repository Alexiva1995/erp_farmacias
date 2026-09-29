<script setup>
import { ref, computed, watch } from 'vue'
import axios from '@axios'
import Swal from 'sweetalert2'
import { toast } from '@/plugins/sweetalert'
import { useAbility } from '@casl/vue'
import { importTabs, fileSchemas } from './importSchemas'

// --- Control de Acceso (CASL) ---
const ability = useAbility()

// --- Estado Reactivo ---
const activeTab = ref('external_catalog')
const selectedFile = ref(null)
const uploading = ref(false)
const progress = ref(0)
const isDragging = ref(false)
const fileInputRef = ref(null)
const cutoffDate = ref(new Date().toISOString().substring(0, 10))
const isInitialLoad = ref(true)

// Persistencia segura del último resultado
let initialSavedStats = null
try {
  const raw = localStorage.getItem('last_import_result')
  if (raw) initialSavedStats = JSON.parse(raw)
} catch {
  initialSavedStats = null
}

const lastImportResult = ref(initialSavedStats)
const tabs = importTabs

// --- Computed Properties ---
const currentSchema = computed(() => fileSchemas[activeTab.value] ?? [])
const currentFilePattern = computed(() => tabs.find(t => t.value === activeTab.value)?.filePattern ?? '')
const fileSizeKb = computed(() => selectedFile.value ? (selectedFile.value.size / 1024).toFixed(2) : '0')

const fileNameMismatch = computed(() => {
  if (!selectedFile.value || activeTab.value === 'external_catalog') return false
  return !selectedFile.value.name.toLowerCase().includes(activeTab.value.toLowerCase())
})

const acceptedFileTypes = computed(() => {
  if (activeTab.value === 'external_catalog') {
    return '.xlsx, .xls, .csv, text/csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel'
  }
  return '.csv, text/plain, text/csv'
})

// --- Watchers y Control de Archivos ---
watch(activeTab, () => {
  clearFile()
})

const clearReport = () => {
  lastImportResult.value = null
  try {
    localStorage.removeItem('last_import_result')
  } catch {}
}

const clearFile = () => {
  selectedFile.value = null
  if (fileInputRef.value) fileInputRef.value.value = ''
}

const handleFileSelect = event => {
  const file = event.target.files?.[0]
  if (file) selectedFile.value = file
}

const handleDrop = event => {
  isDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (!file) return

  const isExcel = file.name.endsWith('.xlsx') || file.name.endsWith('.xls')
  const isCsv = file.type === 'text/csv' || file.name.endsWith('.csv') || file.type === 'text/plain'

  if (activeTab.value === 'external_catalog') {
    if (isExcel || isCsv) selectedFile.value = file
    else toast.error('Formato no válido. Solo se admiten archivos Excel (.xlsx, .xls) o CSV.')
  } else {
    if (isCsv) selectedFile.value = file
    else toast.error('Formato no válido. Solo se admiten archivos CSV.')
  }
}

const downloadTemplate = () => {
  const headers = currentSchema.value.map(col => col.field).join(',')
  const blob = new Blob([headers + '\n'], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', currentFilePattern.value)
  link.click()
  URL.revokeObjectURL(url)
}

// --- Procesamiento de Importación con Confirmación ---
const confirmAndImport = async () => {
  if (!selectedFile.value) {
    toast.error('Por favor, selecciona un archivo válido.')
    return
  }

  const result = await Swal.fire({
    title: '¿Confirmar Importación Masiva?',
    text: `Se procesará el archivo "${selectedFile.value.name}" para el módulo de ${tabs.find(t => t.value === activeTab.value)?.title}.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#E20074',
    cancelButtonColor: '#7A0099',
    confirmButtonText: 'Sí, Procesar',
    cancelButtonText: 'Cancelar',
  })

  if (!result.isConfirmed) return

  await executeImport()
}

const executeImport = async () => {
  uploading.value = true
  progress.value = 0
  lastImportResult.value = null

  const formData = new FormData()
  const isExternal = activeTab.value === 'external_catalog'
  const endpoint = isExternal ? '/import-external-catalog' : '/import-csv'

  formData.append('file', selectedFile.value)
  if (isExternal) {
    formData.append('cutoff_date', cutoffDate.value)
    formData.append('is_initial_load', isInitialLoad.value ? '1' : '0')
  } else {
    formData.append('type', activeTab.value)
  }

  try {
    const response = await axios.post(endpoint, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: evt => {
        if (evt.lengthComputable) {
          progress.value = Math.round((evt.loaded / evt.total) * 100)
        }
      },
    })

    const stats = response.data?.data ?? {}
    lastImportResult.value = stats
    try {
      localStorage.setItem('last_import_result', JSON.stringify(stats))
    } catch {}

    Swal.fire({
      icon: 'success',
      title: 'Importación Completada',
      html: `
        <div style="text-align:left;font-size:0.95rem;line-height:1.7;">
          <p class="mb-1"><strong>Total Filas:</strong> ${Number(stats.total_rows ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-primary"><strong>Nuevos Registros:</strong> ${Number(stats.created ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-info"><strong>Actualizados:</strong> ${Number(stats.updated ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-1 text-success"><strong>Homologados con Master:</strong> ${Number(stats.matched_with_master ?? 0).toLocaleString('es-VE')}</p>
          <p class="mb-0 text-warning"><strong>Stock Ingresado:</strong> ${Number(stats.total_stock ?? 0).toLocaleString('es-VE')} uds.</p>
        </div>
      `,
      confirmButtonText: 'Aceptar',
      confirmButtonColor: '#E20074',
    })

    toast.success(response.data?.message ?? 'Datos importados correctamente.')
    clearFile()
  } catch (err) {
    const message = err.response?.data?.message ?? 'Error crítico al procesar el archivo. Verifique el formato.'
    toast.error(message)
  } finally {
    uploading.value = false
    setTimeout(() => { progress.value = 0 }, 1200)
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
              icon="tabler-database-import"
              color="primary"
            />
            Importación Masiva de Datos
          </VCardTitle>
          <VCardSubtitle class="text-body-2">
            Carga de información inicial y sincronización de catálogos para el ERP.
          </VCardSubtitle>

          <template #append>
            <VBtn
              variant="outlined"
              color="primary"
              size="small"
              prepend-icon="tabler-download"
              :disabled="uploading"
              @click="downloadTemplate"
            >
              Descargar Plantilla
            </VBtn>
          </template>
        </VCardItem>

        <VTabs
          v-model="activeTab"
          color="primary"
          show-arrows
          class="border-b"
        >
          <VTab
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
            :disabled="uploading"
          >
            <VIcon
              start
              :icon="tab.icon"
            />
            {{ tab.title }}
          </VTab>
        </VTabs>

        <VCardText class="pt-4">
          <!-- Parámetros de Configuración del Catálogo Externo -->
          <VCard
            v-if="activeTab === 'external_catalog'"
            variant="outlined"
            class="mb-6"
          >
            <VCardItem class="pb-2">
              <VCardTitle class="text-subtitle-1 d-flex align-center gap-2">
                <VIcon
                  icon="tabler-adjustments-horizontal"
                  size="20"
                  color="primary"
                />
                Parámetros de Cálculo y Homologación
              </VCardTitle>
            </VCardItem>

            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextField
                    v-model="cutoffDate"
                    type="date"
                    label="Fecha de Corte"
                    variant="outlined"
                    density="comfortable"
                    hide-details="auto"
                    persistent-hint
                    hint="Calcula los meses transcurridos para el promedio mensual de ventas"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                  class="d-flex align-center"
                >
                  <VSwitch
                    v-model="isInitialLoad"
                    color="primary"
                    label="¿Es primera carga del año?"
                    density="comfortable"
                    hide-details="auto"
                    persistent-hint
                    hint="Activo: distribuye venta acumulada. Inactivo: procesa solo incrementos"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- Especificación de Columnas Requeridas -->
          <div class="mb-4">
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-subtitle-2 font-weight-medium">
                Estructura del archivo esperado:
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  class="ms-1"
                >
                  {{ currentFilePattern }}
                </VChip>
              </span>
            </div>

            <div style="max-height: 220px; overflow-y: auto;" class="border rounded">
              <VTable density="compact">
                <thead>
                  <tr>
                    <th class="text-left">Columna</th>
                    <th class="text-left">Condición</th>
                    <th class="text-left">Descripción</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="col in currentSchema"
                    :key="col.field"
                  >
                    <td><code>{{ col.field }}</code></td>
                    <td>
                      <VChip
                        size="x-small"
                        :color="col.required ? 'error' : 'secondary'"
                        variant="tonal"
                      >
                        {{ col.required ? 'Obligatorio' : 'Opcional' }}
                      </VChip>
                    </td>
                    <td class="text-caption">{{ col.desc }}</td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </div>

          <VDivider class="my-4" />

          <!-- Zona de Carga / Drag & Drop -->
          <div
            class="d-flex flex-column align-center justify-center rounded pa-6 border-dashed"
            :style="{
              borderWidth: '2px',
              borderColor: isDragging ? 'rgb(var(--v-theme-primary))' : 'rgba(var(--v-border-color), 0.35)',
              backgroundColor: isDragging ? 'rgba(var(--v-theme-primary), 0.05)' : 'transparent',
              minHeight: '200px'
            }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
          >
            <VIcon
              :icon="selectedFile ? 'tabler-file-check' : (activeTab === 'external_catalog' ? 'tabler-file-spreadsheet' : 'tabler-file-type-csv')"
              size="48"
              :color="selectedFile ? 'success' : 'primary'"
              class="mb-2"
            />

            <template v-if="!selectedFile">
              <span class="text-body-1 font-weight-medium mb-1">
                Arrastra y suelta tu archivo aquí
              </span>
              <span class="text-caption text-disabled mb-4">
                {{ activeTab === 'external_catalog' ? 'Formatos soportados: .xlsx, .xls, .csv' : 'Formato soportado: .csv' }}
              </span>
            </template>

            <template v-else>
              <span class="text-body-1 font-weight-bold mb-1">{{ selectedFile.name }}</span>
              <span class="text-caption text-medium-emphasis mb-2">{{ fileSizeKb }} KB</span>
              
              <VAlert
                v-if="fileNameMismatch"
                type="warning"
                variant="tonal"
                density="compact"
                class="mb-3 text-start"
                style="max-width: 480px"
              >
                Advertencia: El nombre no coincide con el estándar esperado (<strong>{{ currentFilePattern }}</strong>).
              </VAlert>
            </template>

            <input
              ref="fileInputRef"
              type="file"
              :accept="acceptedFileTypes"
              class="d-none"
              @change="handleFileSelect"
            >

            <div class="d-flex gap-3 flex-wrap justify-center align-center">
              <VBtn
                color="secondary"
                variant="outlined"
                prepend-icon="tabler-upload"
                :disabled="uploading"
                @click="fileInputRef?.click()"
              >
                Seleccionar Archivo
              </VBtn>

              <VBtn
                v-if="selectedFile"
                color="error"
                variant="text"
                icon="tabler-trash"
                size="small"
                :disabled="uploading"
                @click="clearFile"
              />

              <VBtn
                v-if="ability.can('import', 'Configuration') || true"
                color="primary"
                prepend-icon="tabler-database-import"
                :disabled="!selectedFile || uploading"
                :loading="uploading"
                @click="confirmAndImport"
              >
                Ejecutar Importación
              </VBtn>
            </div>

            <!-- Progreso de Transferencia -->
            <div
              v-if="uploading"
              class="w-100 mt-4 text-center"
              style="max-width: 380px"
            >
              <VProgressLinear
                v-model="progress"
                color="primary"
                height="8"
                rounded
                striped
              />
              <span class="text-caption text-medium-emphasis mt-1 d-block">
                Subiendo archivo: {{ progress }}%
              </span>
            </div>
          </div>

          <!-- Reporte de Resultados Permanentes -->
          <VCard
            v-if="lastImportResult"
            variant="tonal"
            color="success"
            class="mt-6 border"
          >
            <VCardItem class="pb-2">
              <VCardTitle class="d-flex align-center justify-space-between text-subtitle-1 text-success">
                <div class="d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-circle-check"
                    size="22"
                    color="success"
                  />
                  <span>Resumen de la Última Importación</span>
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
                  md="2"
                >
                  <div class="pa-2 bg-surface rounded text-center border">
                    <div class="text-caption text-medium-emphasis">Total Filas</div>
                    <div class="text-body-1 font-weight-bold">{{ Number(lastImportResult.total_rows ?? 0).toLocaleString('es-VE') }}</div>
                  </div>
                </VCol>

                <VCol
                  cols="6"
                  sm="4"
                  md="2"
                >
                  <div class="pa-2 bg-surface rounded text-center border">
                    <div class="text-caption text-primary">Nuevos</div>
                    <div class="text-body-1 font-weight-bold text-primary">{{ Number(lastImportResult.created ?? 0).toLocaleString('es-VE') }}</div>
                  </div>
                </VCol>

                <VCol
                  cols="6"
                  sm="4"
                  md="2"
                >
                  <div class="pa-2 bg-surface rounded text-center border">
                    <div class="text-caption text-info">Actualizados</div>
                    <div class="text-body-1 font-weight-bold text-info">{{ Number(lastImportResult.updated ?? 0).toLocaleString('es-VE') }}</div>
                  </div>
                </VCol>

                <VCol
                  cols="6"
                  sm="4"
                  md="2"
                >
                  <div class="pa-2 bg-surface rounded text-center border">
                    <div class="text-caption text-success">Con Master</div>
                    <div class="text-body-1 font-weight-bold text-success">{{ Number(lastImportResult.matched_with_master ?? 0).toLocaleString('es-VE') }}</div>
                  </div>
                </VCol>

                <VCol
                  cols="6"
                  sm="4"
                  md="2"
                >
                  <div class="pa-2 bg-surface rounded text-center border">
                    <div class="text-caption text-secondary">Lotes</div>
                    <div class="text-body-1 font-weight-bold text-secondary">{{ Number(lastImportResult.lots_updated ?? 0).toLocaleString('es-VE') }}</div>
                  </div>
                </VCol>

                <VCol
                  cols="6"
                  sm="4"
                  md="2"
                >
                  <div class="pa-2 bg-surface rounded text-center border">
                    <div class="text-caption text-warning">Total Stock</div>
                    <div class="text-body-1 font-weight-bold text-warning">{{ Number(lastImportResult.total_stock ?? 0).toLocaleString('es-VE') }}</div>
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
