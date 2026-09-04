<script setup>
import { ref, computed, watch } from 'vue'
import axios from '@axios'
import Swal from 'sweetalert2'
import { toast } from '@/plugins/sweetalert'
import { importTabs, fileSchemas } from './importSchemas'

// --- Estado ---
const activeTab = ref('clientes')
const selectedFile = ref(null)
const uploading = ref(false)
const progress = ref(0)
const isDragging = ref(false)
const fileInputRef = ref(null)
const cutoffDate = ref(new Date().toISOString().substring(0, 10))
const isInitialLoad = ref(true)
const lastImportResult = ref(null)
const tabs = importTabs

// --- Computed ---
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

// --- Watchers & Métodos ---
watch(activeTab, () => {
  clearFile()
  lastImportResult.value = null
})

// --- Métodos ---

/** Limpia el archivo seleccionado y resetea el input via template ref (sin tocar el DOM) */
const clearFile = () => {
  selectedFile.value = null
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}

/** Selecciona el archivo desde el input nativo */
const handleFileSelect = (e) => {
  const file = e.target.files[0]
  if (file) {
    selectedFile.value = file
  }
}

/** Maneja el evento de arrastre sobre la zona de drop */
const handleDrop = (e) => {
  isDragging.value = false
  const file = e.dataTransfer?.files[0]
  if (!file) return

  const isExcel = file.name.endsWith('.xlsx') || file.name.endsWith('.xls')
  const isCsv = file.type === 'text/csv' || file.name.endsWith('.csv') || file.type === 'text/plain'

  if (activeTab.value === 'external_catalog') {
    if (isExcel || isCsv) {
      selectedFile.value = file
    } else {
      toast.error('Solo se admiten archivos Excel (.xlsx, .xls) o CSV.')
    }
  } else {
    if (isCsv) {
      selectedFile.value = file
    } else {
      toast.error('Solo se admiten archivos CSV.')
    }
  }
}

/** Genera y descarga un CSV de plantilla con las columnas del tab activo */
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

/** Envía el archivo al servidor con progreso real reportado por axios */
const triggerImport = async () => {
  if (!selectedFile.value) {
    toast.error('Por favor, selecciona un archivo válido.')
    return
  }

  uploading.value = true
  progress.value = 0
  lastImportResult.value = null

  const formData = new FormData()

  if (activeTab.value === 'external_catalog') {
    formData.append('file', selectedFile.value)
    formData.append('cutoff_date', cutoffDate.value)
    formData.append('is_initial_load', isInitialLoad.value ? '1' : '0')

    try {
      const response = await axios.post('/import-external-catalog', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (evt) => {
          if (evt.lengthComputable) {
            progress.value = Math.round((evt.loaded / evt.total) * 100)
          }
        },
      })

      const stats = response.data?.data ?? {}
      lastImportResult.value = stats

      Swal.fire({
        icon: 'success',
        title: '¡Importación Completada con Éxito!',
        html: `
          <div style="text-align: left; font-size: 0.95rem; line-height: 1.6;">
            <p style="margin-bottom: 8px;"><strong>Total filas procesadas:</strong> ${stats.total_rows ?? 0}</p>
            <p style="margin-bottom: 8px; color: #7367F0;"><strong>Nuevos productos creados:</strong> ${stats.created ?? 0}</p>
            <p style="margin-bottom: 8px; color: #00CFE8;"><strong>Productos actualizados:</strong> ${stats.updated ?? 0}</p>
            <p style="margin-bottom: 8px; color: #28C76F;"><strong>Homologados con Catálogo Maestro:</strong> ${stats.matched_with_master ?? 0}</p>
            <p style="margin-bottom: 8px;"><strong>Lotes e inventario actualizados:</strong> ${stats.lots_updated ?? 0}</p>
            <p style="margin-bottom: 0; color: #FF9F43;"><strong>Unidades totales de stock:</strong> ${Number(stats.total_stock ?? 0).toLocaleString('es-VE')}</p>
          </div>
        `,
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#7367F0',
      })

      toast.success(response.data.message ?? 'Catálogo y ventas procesados exitosamente.')
      clearFile()
    } catch (err) {
      const message = err.response?.data?.message ?? 'Error al procesar el archivo. Verifica la estructura.'
      toast.error(message)
    } finally {
      uploading.value = false
      setTimeout(() => { progress.value = 0 }, 1500)
    }
  } else {
    formData.append('type', activeTab.value)
    formData.append('file', selectedFile.value)

    try {
      const response = await axios.post('/import-csv', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (evt) => {
          if (evt.lengthComputable) {
            progress.value = Math.round((evt.loaded / evt.total) * 100)
          }
        },
      })

      toast.success(response.data.message ?? 'Importación procesada exitosamente.')
      clearFile()
    } catch (err) {
      const message = err.response?.data?.message ?? 'Error al procesar el archivo CSV. Verifica la estructura.'
      toast.error(message)
    } finally {
      uploading.value = false
      setTimeout(() => { progress.value = 0 }, 1500)
    }
  }
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard>
        <!-- Encabezado semántico con acción secundaria integrada -->
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-database-import"
              color="primary"
            />
            Módulo de Importación de Datos (Onboarding)
          </VCardTitle>
          <VCardSubtitle>
            Carga de forma masiva la información inicial de tu negocio en formato CSV.
          </VCardSubtitle>

          <template #append>
            <!-- Descarga la plantilla del tab activo dinámicamente -->
            <VBtn
              variant="tonal"
              color="success"
              size="small"
              prepend-icon="tabler-download"
              @click="downloadTemplate"
            >
              Descargar Plantilla
            </VBtn>
          </template>
        </VCardItem>

        <!-- Tabs de entidades -->
        <VTabs
          v-model="activeTab"
          color="primary"
          grow
        >
          <VTab
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
          >
            <VIcon
              start
              :icon="tab.icon"
            />
            {{ tab.title }}
          </VTab>
        </VTabs>

        <VDivider />

        <VCardText class="mt-4">
          <!-- Tabla del esquema requerido para el tab activo -->
          <div class="mb-4">
            <h3 class="text-subtitle-1 font-weight-bold mb-2">
              Estructura requerida del archivo
              <code class="text-caption ms-1 pa-1 rounded bg-surface">{{ currentFilePattern }}</code>
            </h3>

            <VTable
              density="compact"
              class="border rounded"
            >
              <thead>
                <tr>
                  <th class="text-left font-weight-bold">
                    Columna (Cabecera)
                  </th>
                  <th class="text-left font-weight-bold">
                    Requerido
                  </th>
                  <th class="text-left font-weight-bold">
                    Descripción
                  </th>
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
                    >
                      {{ col.required ? 'Obligatorio' : 'Opcional' }}
                    </VChip>
                  </td>
                  <td>{{ col.desc }}</td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <VDivider class="my-4" />

          <!-- Opciones específicas para Catálogo Externo / Excel -->
          <div
            v-if="activeTab === 'external_catalog'"
            class="mb-6 pa-4 bg-var-theme-background rounded border"
          >
            <h4 class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center gap-2">
              <VIcon icon="tabler-settings" size="18" color="primary" />
              Parámetros de Cálculo y Homologación
            </h4>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="cutoffDate"
                  type="date"
                  label="Fecha de Corte del Archivo"
                  hint="Determina los meses transcurridos en el año para el promedio mensual de venta"
                  persistent-hint
                  density="compact"
                />
              </VCol>
              <VCol cols="12" md="6" class="d-flex align-center">
                <VSwitch
                  v-model="isInitialLoad"
                  color="primary"
                  label="¿Es la primera carga del año?"
                  hint="Si está activo, divide la venta acumulada entre los meses transcurridos. Si no, calcula solo el incremento."
                  persistent-hint
                />
              </VCol>
            </VRow>
          </div>

          <!-- Zona de Drag & Drop con estados visuales reactivos -->
          <div
            class="drop-zone d-flex flex-column align-center justify-center rounded"
            :class="{
              'drop-zone--active': isDragging,
              'drop-zone--has-file': selectedFile,
            }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
          >
            <VIcon
              :icon="selectedFile ? 'tabler-file-check' : (activeTab === 'external_catalog' ? 'tabler-file-spreadsheet' : 'tabler-file-type-csv')"
              size="52"
              :color="selectedFile ? 'success' : 'primary'"
              class="mb-3"
            />

            <!-- Estado: sin archivo -->
            <template v-if="!selectedFile">
              <span class="text-body-1 font-weight-bold mb-1">
                Arrastra tu archivo aquí
              </span>
              <span class="text-caption text-disabled mb-4">
                o haz clic en "Buscar Archivo" · {{ activeTab === 'external_catalog' ? 'Admite Excel (.xlsx, .xls) o .csv' : 'Solo se admiten archivos .csv' }}
              </span>
            </template>

            <!-- Estado: archivo seleccionado -->
            <template v-else>
              <span class="text-body-1 font-weight-bold mb-1">
                {{ selectedFile.name }}
              </span>
              <span class="text-caption text-medium-emphasis mb-2">
                {{ fileSizeKb }} KB
              </span>

              <!-- Advertencia no bloqueante si el nombre no coincide con el tab -->
              <VAlert
                v-if="fileNameMismatch"
                type="warning"
                variant="tonal"
                density="compact"
                class="mb-3 text-left"
                style="max-width: 420px"
              >
                El nombre del archivo no coincide con el tipo
                <strong>{{ currentFilePattern }}</strong>. Verifica que cargaste el archivo correcto.
              </VAlert>
            </template>

            <!-- Input nativo oculto, controlado exclusivamente via template ref -->
            <input
              id="csv-file-input"
              ref="fileInputRef"
              type="file"
              :accept="acceptedFileTypes"
              class="d-none"
              @change="handleFileSelect"
            >

            <div class="d-flex gap-3 flex-wrap justify-center align-center">
              <VBtn
                color="secondary"
                variant="tonal"
                prepend-icon="tabler-upload"
                :disabled="uploading"
                @click="fileInputRef?.click()"
              >
                Buscar Archivo
              </VBtn>

              <!-- Limpiar selección (solo visible con archivo) -->
              <VBtn
                v-if="selectedFile"
                color="error"
                variant="text"
                icon="tabler-x"
                size="small"
                :disabled="uploading"
                @click="clearFile"
              />

              <VBtn
                color="primary"
                prepend-icon="tabler-database-import"
                :disabled="!selectedFile || uploading"
                :loading="uploading"
                @click="triggerImport"
              >
                Comenzar Importación
              </VBtn>
            </div>

            <!-- Barra de progreso REAL alimentada por onUploadProgress de axios -->
            <VProgressLinear
              v-if="uploading"
              v-model="progress"
              color="primary"
              height="8"
              striped
              class="mt-4 rounded progress-bar"
            />
            <span
              v-if="uploading"
              class="text-caption text-medium-emphasis mt-1"
            >
              Procesando archivo... {{ progress }}%
            </span>
          </div>

          <!-- Resumen de resultados permanente de la última importación -->
          <VCard
            v-if="lastImportResult"
            variant="tonal"
            color="success"
            class="mt-6 border"
          >
            <VCardItem>
              <VCardTitle class="d-flex align-center justify-space-between text-success">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-circle-check" size="24" color="success" />
                  <span>Reporte de Importación Completada</span>
                </div>
                <VBtn
                  size="x-small"
                  variant="text"
                  color="success"
                  icon="tabler-x"
                  @click="lastImportResult = null"
                />
              </VCardTitle>
            </VCardItem>
            <VCardText class="pt-0">
              <VRow dense>
                <VCol cols="6" sm="4" md="2">
                  <div class="pa-3 bg-surface rounded text-center border">
                    <div class="text-caption text-medium-emphasis">Total Filas</div>
                    <div class="text-h6 font-weight-black text-high-emphasis">{{ lastImportResult.total_rows }}</div>
                  </div>
                </VCol>
                <VCol cols="6" sm="4" md="2">
                  <div class="pa-3 bg-surface rounded text-center border">
                    <div class="text-caption text-primary font-weight-bold">Creados</div>
                    <div class="text-h6 font-weight-black text-primary">{{ lastImportResult.created }}</div>
                  </div>
                </VCol>
                <VCol cols="6" sm="4" md="2">
                  <div class="pa-3 bg-surface rounded text-center border">
                    <div class="text-caption text-info font-weight-bold">Actualizados</div>
                    <div class="text-h6 font-weight-black text-info">{{ lastImportResult.updated }}</div>
                  </div>
                </VCol>
                <VCol cols="6" sm="4" md="2">
                  <div class="pa-3 bg-surface rounded text-center border">
                    <div class="text-caption text-success font-weight-bold">Con Master</div>
                    <div class="text-h6 font-weight-black text-success">{{ lastImportResult.matched_with_master }}</div>
                  </div>
                </VCol>
                <VCol cols="6" sm="4" md="2">
                  <div class="pa-3 bg-surface rounded text-center border">
                    <div class="text-caption text-secondary font-weight-bold">Lotes</div>
                    <div class="text-h6 font-weight-black text-secondary">{{ lastImportResult.lots_updated ?? 0 }}</div>
                  </div>
                </VCol>
                <VCol cols="6" sm="4" md="2">
                  <div class="pa-3 bg-surface rounded text-center border">
                    <div class="text-caption text-warning font-weight-bold">Total Stock</div>
                    <div class="text-h6 font-weight-black text-warning">{{ Number(lastImportResult.total_stock ?? 0).toLocaleString('es-VE') }}</div>
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

<style scoped>
/* Zona de drop con transición de estado */
.drop-zone {
  border: 2px dashed rgba(var(--v-border-color), 0.35);
  padding: 2rem 1.5rem;
  transition: border-color 0.2s ease, background-color 0.2s ease;
  min-height: 220px;
}

/* Estado activo durante el arrastre del archivo */
.drop-zone--active {
  border-color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.06);
}

/* Estado con archivo correctamente seleccionado */
.drop-zone--has-file {
  border-color: rgb(var(--v-theme-success));
  background-color: rgba(var(--v-theme-success), 0.04);
}

/* Barra de progreso con ancho máximo contenido */
.progress-bar {
  width: 100%;
  max-width: 360px;
}
</style>
