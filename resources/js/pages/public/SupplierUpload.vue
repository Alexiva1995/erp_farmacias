<script setup>
import axios from '@/plugins/axios'
import logo from '@images/logo.png'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const token = route.params.token

const supplierName = ref('')
const lastUpload = ref(null)
const loading = ref(true)
const isError = ref(false)
const errorMessage = ref('')
const isSuccess = ref(false)
const uploading = ref(false)

const file = ref(null)
const file2 = ref(null)
const showSecondFile = ref(false)
const hasSecondaryStructure = ref(false)
const file2Structure = ref('secondary')
const exchangeRate = ref(null)

const fetchSupplier = async () => {
  try {
    const response = await axios.get(`/public/suppliers/upload/${token}`)
    supplierName.value = response.data.data.name
    lastUpload.value = response.data.data.last_upload
    hasSecondaryStructure.value = Boolean(response.data.data.has_secondary_structure)
    file2Structure.value = hasSecondaryStructure.value ? 'secondary' : 'primary'

    if (response.data.data.exchange_rate && (exchangeRate.value === null || exchangeRate.value === '')) {
      exchangeRate.value = response.data.data.exchange_rate
    }
    loading.value = false
  } catch (error) {
    isError.value = true
    errorMessage.value = error.response?.data?.message || 'Enlace no válido o expirado.'
    loading.value = false
  }
}

const handleUpload = async () => {
  if (!file.value || !exchangeRate.value) return

  uploading.value = true
  isError.value = false

  const formData = new FormData()
  if (file.value && Array.isArray(file.value)) {
    formData.append('file', file.value[0])
  } else {
    formData.append('file', file.value)
  }

  if (showSecondFile.value && file2.value) {
    if (Array.isArray(file2.value)) {
      formData.append('file_2', file2.value[0])
    } else {
      formData.append('file_2', file2.value)
    }
    formData.append('file_2_structure', file2Structure.value)
  }

  formData.append('exchange_rate', exchangeRate.value)

  try {
    await axios.post(`/public/suppliers/upload/${token}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    isSuccess.value = true
    uploading.value = false
    // Refrescar información para tener la nueva fecha de última carga
    fetchSupplier()
  } catch (error) {
    isError.value = true
    errorMessage.value = error.response?.data?.message || 'Error al procesar el archivo. Por favor, contacte con el personal de la farmacia.'
    uploading.value = false
  }
}

const toggleSecondFile = () => {
  showSecondFile.value = !showSecondFile.value
  if (!showSecondFile.value) {
    file2.value = null
  }
}

const resetForm = () => {
  file.value = null
  file2.value = null
  showSecondFile.value = false
  isSuccess.value = false
  isError.value = false
  errorMessage.value = ''
  fetchSupplier()
}

const formatDate = (dateString) => {
  if (!dateString) return 'Nunca'
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date)
}

onMounted(fetchSupplier)
</script>

<template>
  <div class="public-upload-container d-flex align-center justify-center fill-height pa-4">
    <VCard width="540" class="pa-8 text-center upload-card elevation-4">
      <div v-if="loading" class="py-12">
        <VProgressCircular indeterminate color="primary" size="64" />
        <p class="mt-4 text-h6 text-medium-emphasis">Cargando portal de proveedor...</p>
      </div>

      <div v-else-if="isSuccess">
        <VIcon size="90" color="success" class="mb-4">tabler-circle-check</VIcon>
        <h2 class="text-h4 font-weight-bold mb-2">¡Carga Exitosa!</h2>
        <p class="text-body-1 text-medium-emphasis mb-6">
          Hemos recibido su lista de precios correctamente. El sistema ha procesado los productos.
        </p>
        
        <VBtn
          block
          variant="tonal"
          color="success"
          @click="resetForm"
          class="mb-4"
        >
          <VIcon start>tabler-plus</VIcon>
          Subir otra lista
        </VBtn>
        
        <p class="text-caption text-disabled">Ya puede cerrar esta ventana o subir una nueva lista.</p>
      </div>

      <div v-else-if="isError">
        <VIcon size="90" color="error" class="mb-4">tabler-alert-circle</VIcon>
        <h2 class="text-h4 font-weight-bold mb-2">Ocurrió un Problema</h2>
        <p class="text-body-1 mb-6 text-error">{{ errorMessage }}</p>
        <p class="text-body-2 text-medium-emphasis">
          Si el problema persiste, por favor contacte con el personal de la farmacia para asistencia.
        </p>
      </div>

      <div v-else>
        <div class="mb-6">
          <div class="d-flex justify-center mb-4">
            <VImg
              :src="logo"
              max-width="180"
              alt="Logo Farmacia"
            />
          </div>
          <h1 class="text-h4 font-weight-bold mb-1">Hola, {{ supplierName }}</h1>
          <p class="text-body-1 text-medium-emphasis mb-0">Suba su lista de precios actualizada</p>
          <div v-if="lastUpload" class="mt-2 text-caption text-secondary">
            <VIcon size="14" class="me-1">tabler-history</VIcon>
            Última subida: {{ formatDate(lastUpload) }}
          </div>
        </div>

        <VForm @submit.prevent="handleUpload">
          <!-- Primer Listado -->
          <VFileInput
            v-model="file"
            label="Lista de Precios Principal (Formato 1)"
            placeholder="Suba su archivo .xlsx, .xls o .csv"
            prepend-icon=""
            prepend-inner-icon="tabler-file-spreadsheet"
            accept=".xlsx,.xls,.csv"
            variant="outlined"
            class="mb-3"
            required
            :disabled="uploading"
          />

          <!-- Botón para Agregar / Quitar Segundo Listado -->
          <div class="d-flex justify-start mb-4">
            <VBtn
              variant="text"
              color="primary"
              size="small"
              class="px-1 text-none font-weight-medium"
              :disabled="uploading"
              @click="toggleSecondFile"
            >
              <VIcon start size="18">{{ showSecondFile ? 'tabler-minus' : 'tabler-plus' }}</VIcon>
              {{ showSecondFile ? 'Quitar segundo listado' : 'Agregar segundo listado (Opcional)' }}
            </VBtn>
          </div>

          <!-- Segundo Listado (Condicional / Opcional) -->
          <VExpandTransition>
            <div v-if="showSecondFile" class="mb-4 text-start">
              <VFileInput
                v-model="file2"
                label="Segundo Listado Adicional (Opcional)"
                placeholder="Suba el segundo archivo .xlsx, .xls o .csv"
                prepend-icon=""
                prepend-inner-icon="tabler-file-spreadsheet"
                accept=".xlsx,.xls,.csv"
                variant="outlined"
                clearable
                class="mb-3"
                :disabled="uploading"
              />

              <!-- Selector de Mapeo para el Segundo Archivo si existe segundo formato -->
              <div v-if="hasSecondaryStructure" class="mt-2 mb-2 pa-3 bg-var-theme-background rounded-lg border">
                <span class="text-caption font-weight-bold d-block mb-2 text-high-emphasis">
                  Seleccione el formato del segundo archivo:
                </span>
                <VRadioGroup
                  v-model="file2Structure"
                  inline
                  hide-details
                  density="compact"
                >
                  <VRadio
                    value="secondary"
                    label="Formato 2 (Secundario)"
                    color="primary"
                  />
                  <VRadio
                    value="primary"
                    label="Formato 1 (Principal)"
                    color="primary"
                  />
                </VRadioGroup>
              </div>
            </div>
          </VExpandTransition>

          <VTextField
            v-model="exchangeRate"
            label="Tasa de Cambio (BS/USD)"
            placeholder="Ej: 785.07"
            prepend-inner-icon="tabler-currency-dollar"
            variant="outlined"
            type="number"
            step="0.0001"
            class="mb-6"
            hint="Tasa oficial del día cargada automáticamente (editable si aplica)"
            persistent-hint
            required
            :disabled="uploading"
          />

          <VBtn
            block
            size="large"
            color="primary"
            type="submit"
            :loading="uploading"
            :disabled="!file || !exchangeRate"
          >
            <VIcon start>tabler-cloud-upload</VIcon>
            Enviar {{ showSecondFile && file2 ? 'Listados' : 'Lista de Precios' }}
          </VBtn>
        </VForm>

        <p class="mt-8 text-caption text-disabled mb-0">
          Protegido por el sistema de gestión ERP
        </p>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.public-upload-container {
  background: #f4f5fa;
  min-block-size: 100vh;
}

.upload-card {
  border-radius: 16px;
  background: #ffffff;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.04);
}

:deep(.v-field) {
  border-radius: 8px;
}
</style>

<route lang="yaml">
meta:
  layout: blank
</route>
