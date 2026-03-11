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
const exchangeRate = ref(null)

const fetchSupplier = async () => {
  try {
    const response = await axios.get(`/public/suppliers/upload/${token}`)
    supplierName.value = response.data.data.name
    lastUpload.value = response.data.data.last_upload
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
  formData.append('file', file.value)
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

const resetForm = () => {
  file.value = null
  exchangeRate.value = null
  isSuccess.value = false
  isError.value = false
  errorMessage.value = ''
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
  <div class="public-upload-container d-flex align-center justify-center fill-height">
    <VCard width="500" class="pa-8 text-center glass-card">
      <div v-if="loading" class="py-12">
        <VProgressCircular indeterminate color="primary" size="64" />
        <p class="mt-4 text-h6">Cargando portal de proveedor...</p>
      </div>

      <div v-else-if="isSuccess">
        <VIcon size="100" color="success" class="mb-4">tabler-circle-check</VIcon>
        <h2 class="text-h4 font-weight-bold mb-2">¡Carga Exitosa!</h2>
        <p class="text-body-1 mb-6">
          Hemos recibido su lista de precios correctamente. El sistema la procesará automáticamente.
        </p>
        
        <VBtn
          block
          variant="tonal"
          color="success"
          @click="resetForm"
          class="mb-4"
        >
          <VIcon start>tabler-plus</VIcon>
          Subir otro archivo
        </VBtn>
        
        <p class="text-caption">Ya puede cerrar esta ventana o subir una nueva lista.</p>
      </div>

      <div v-else-if="isError">
        <VIcon size="100" color="error" class="mb-4">tabler-alert-circle</VIcon>
        <h2 class="text-h4 font-weight-bold mb-2">Ocurrió un Problema</h2>
        <p class="text-body-1 mb-6 text-error">{{ errorMessage }}</p>
        <p class="text-body-2 font-weight-medium">
          Si el problema persiste, por favor contacte con el personal de la farmacia para asistencia.
        </p>
      </div>

      <div v-else>
        <div class="mb-8">
          <div class="d-flex justify-center mb-6">
            <VImg
              :src="logo"
              max-width="180"
              alt="Logo Farmacia"
            />
          </div>
          <h1 class="text-h4 font-weight-bold mb-1">Hola, {{ supplierName }}</h1>
          <p class="text-body-1 text-secondary mb-0">Suba su lista de precios actualizada</p>
          <div v-if="lastUpload" class="mt-2 text-caption text-secondary">
            <VIcon size="14" class="me-1">tabler-history</VIcon>
            Última subida: {{ formatDate(lastUpload) }}
          </div>
        </div>

        <VForm @submit.prevent="handleUpload">
          <VFileInput
            v-model="file"
            label="Seleccionar archivo Excel"
            placeholder="Suba su archivo .xlsx o .xls"
            prepend-icon="tabler-file-spreadsheet"
            accept=".xlsx,.xls,.csv"
            variant="outlined"
            class="mb-4"
            required
            :disabled="uploading"
          />

          <VTextField
            v-model="exchangeRate"
            label="Tasa de Cambio (BS/USD)"
            placeholder="Ej: 45.50"
            prepend-inner-icon="tabler-currency-dollar"
            variant="outlined"
            type="number"
            step="0.01"
            class="mb-6"
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
            Enviar Lista de Precios
          </VBtn>
        </VForm>

        <p class="mt-8 text-caption text-secondary">
          Protegido por el sistema de gestión Alexiva
        </p>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.public-upload-container {
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  min-block-size: 100vh;
}

.glass-card {
  border: 1px solid rgba(255, 255, 255, 10%);
  border-radius: 24px;
  backdrop-filter: blur(10px);
  background: rgba(255, 255, 255, 5%);
  box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 37%);
}

.text-h4 {
  color: #f8fafc;
}

.text-body-1 {
  color: #cbd5e1;
}

:deep(.v-field) {
  border-radius: 12px;
}
</style>

<route lang="yaml">
meta:
  layout: blank
</route>
