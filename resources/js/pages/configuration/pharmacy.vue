<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useBrandingStore } from '@/stores/useBrandingStore'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

const brandingStore = useBrandingStore()
const isLoading = ref(false)
const isPageLoading = ref(true)

const form = reactive({
  app_name: '',
  app_rif: '',
  address: '',
})

const logoFile = ref(null)
const logoPreview = ref('')
const fileInputRef = ref(null)

const handleLogoUpload = (e) => {
  const file = e.target.files[0]
  if (file) {
    if (!file.type.startsWith('image/')) {
      toast.error('Por favor seleccione un archivo de imagen válido (PNG, JPG, WEBP, SVG)')
      return
    }

    if (file.size > 5 * 1024 * 1024) {
      toast.error('El tamaño de la imagen no debe superar los 5MB')
      return
    }

    logoFile.value = file
    logoPreview.value = URL.createObjectURL(file)
  }
}

const triggerFileInput = () => {
  if (fileInputRef.value) {
    fileInputRef.value.click()
  }
}

const removeLogo = () => {
  logoFile.value = null
  logoPreview.value = ''
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}

const savePharmacySettings = async () => {
  if (!form.app_name || !form.app_name.trim()) {
    toast.error('El nombre de la farmacia es obligatorio')
    return
  }

  isLoading.value = true

  const formData = new FormData()
  formData.append('app_name', form.app_name.trim())
  formData.append('app_rif', form.app_rif ? form.app_rif.trim() : '')
  formData.append('address', form.address ? form.address.trim() : '')

  if (logoFile.value) {
    formData.append('app_logo', logoFile.value)
  }

  try {
    await axios.post('/general-settings', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    toast.success('Datos de la farmacia guardados exitosamente. Se aplicarán en todos los PDFs y reportes.')
    await brandingStore.fetchSettings(true)
  } catch (error) {
    console.error('Error al guardar datos de la farmacia:', error)
    const errorMsg = error?.response?.data?.message || 'Error al guardar la configuración'
    toast.error(errorMsg)
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  isPageLoading.value = true
  try {
    await brandingStore.fetchSettings(true)
    form.app_name = brandingStore.settings.app_name || ''
    form.app_rif = brandingStore.settings.app_rif || ''
    form.address = brandingStore.settings.address || ''
    logoPreview.value = brandingStore.settings.app_logo || ''
  } catch (error) {
    console.error('Error al cargar configuraciones:', error)
  } finally {
    isPageLoading.value = false
  }
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <!-- Skeleton Loader -->
      <div v-if="isPageLoading" class="border pa-6 rounded-lg bg-surface d-flex flex-column gap-6">
        <VSkeletonLoader type="heading" class="w-25" />
        <VSkeletonLoader type="subtitle" class="w-50 mb-4" />
        <VRow>
          <VCol cols="12" md="7">
            <VSkeletonLoader type="paragraph, actions" />
          </VCol>
          <VCol cols="12" md="5">
            <VSkeletonLoader type="image" />
          </VCol>
        </VRow>
      </div>

      <VCard v-else class="rounded-lg shadow-soft border-0" variant="flat">
        <VCardItem class="px-6 pt-6 pb-2">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-building-store" size="26" />
            </VAvatar>
            <div>
              <VCardTitle class="text-h5 font-weight-bold">
                Datos de la Farmacia (Identidad y PDFs)
              </VCardTitle>
              <VCardSubtitle class="text-muted text-caption mt-1">
                Configure el nombre comercial, RIF y logotipo oficial que se estamparán en todos los reportes, nóminas, liquidaciones y documentos PDF.
              </VCardSubtitle>
            </div>
          </div>
        </VCardItem>

        <VDivider class="my-3" />

        <VCardText class="px-6 py-4">
          <VForm @submit.prevent="savePharmacySettings">
            <VRow>
              <!-- Columna Izquierda: Formulario de Campos -->
              <VCol cols="12" md="7">
                <VCard variant="outlined" class="pa-5 rounded-lg mb-4 border">
                  <div class="text-subtitle-1 font-weight-bold mb-4 text-primary d-flex align-center gap-2">
                    <VIcon icon="tabler-id" size="20" />
                    Información Institucional
                  </div>

                  <VRow>
                    <!-- Nombre de la Farmacia -->
                    <VCol cols="12">
                      <VLabel class="mb-1 text-body-2 font-weight-medium">
                        Nombre de la Farmacia <span class="text-error">*</span>
                      </VLabel>
                      <VTextField
                        v-model="form.app_name"
                        placeholder="Ej: FARMACIA BARRIO SUCRE 2024, C.A."
                        prepend-inner-icon="tabler-building"
                        density="comfortable"
                        variant="outlined"
                        :rules="[v => !!v || 'El nombre es obligatorio']"
                        hide-details="auto"
                      />
                      <span class="text-caption text-muted">
                        Este nombre encabezará todas las nóminas, retenciones y reportes descargables.
                      </span>
                    </VCol>

                    <!-- RIF (Opcional) -->
                    <VCol cols="12">
                      <div class="d-flex align-center justify-space-between mb-1">
                        <VLabel class="text-body-2 font-weight-medium">
                          RIF / Identificación Fiscal
                        </VLabel>
                        <VChip size="x-small" color="secondary" variant="tonal">
                          Opcional
                        </VChip>
                      </div>
                      <VTextField
                        v-model="form.app_rif"
                        placeholder="Ej: J-50540695-7 (o déjelo vacío si no aplica)"
                        prepend-inner-icon="tabler-receipt-tax"
                        density="comfortable"
                        variant="outlined"
                        clearable
                        hide-details="auto"
                      />
                      <span class="text-caption text-muted">
                        Si se deja en blanco, la etiqueta de RIF no se mostrará en los documentos generados.
                      </span>
                    </VCol>

                    <!-- Dirección Fiscal / Referencia -->
                    <VCol cols="12">
                      <VLabel class="mb-1 text-body-2 font-weight-medium">
                        Dirección Fiscal / Ubicación
                      </VLabel>
                      <VTextarea
                        v-model="form.address"
                        rows="2"
                        placeholder="Ej: Calle Principal Local 05 Sector Barrio Sucre, La Fría, Táchira"
                        prepend-inner-icon="tabler-map-pin"
                        density="comfortable"
                        variant="outlined"
                        hide-details="auto"
                      />
                    </VCol>
                  </VRow>
                </VCard>

                <!-- Sección de Carga de Logotipo -->
                <VCard variant="outlined" class="pa-5 rounded-lg border">
                  <div class="text-subtitle-1 font-weight-bold mb-3 text-primary d-flex align-center gap-2">
                    <VIcon icon="tabler-photo" size="20" />
                    Logotipo Corporativo
                  </div>

                  <p class="text-caption text-muted mb-4">
                    Suba el logo oficial en formato PNG transparente, JPG o WEBP. Se optimizará automáticamente para la resolución de impresión de los PDFs.
                  </p>

                  <input
                    ref="fileInputRef"
                    type="file"
                    accept="image/png, image/jpeg, image/webp, image/svg+xml"
                    class="d-none"
                    @change="handleLogoUpload"
                  >

                  <div class="d-flex align-center gap-4 flex-wrap">
                    <VBtn
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-upload"
                      @click="triggerFileInput"
                    >
                      {{ logoPreview ? 'Cambiar Logotipo' : 'Seleccionar Logotipo' }}
                    </VBtn>

                    <VBtn
                      v-if="logoPreview"
                      variant="text"
                      color="error"
                      prepend-icon="tabler-trash"
                      @click="removeLogo"
                    >
                      Quitar Logo
                    </VBtn>
                  </div>
                </VCard>
              </VCol>

              <!-- Columna Derecha: Vista Previa en Vivo de PDF -->
              <VCol cols="12" md="5">
                <VCard variant="outlined" class="pa-5 rounded-lg border bg-surface h-100 d-flex flex-column">
                  <div class="text-subtitle-1 font-weight-bold mb-2 text-secondary d-flex align-center gap-2">
                    <VIcon icon="tabler-file-type-pdf" size="20" />
                    Vista Previa en Membrete PDF
                  </div>
                  <span class="text-caption text-muted mb-4">
                    Así se visualizará la cabecera institucional en las órdenes, cartas, liquidaciones y nóminas:
                  </span>

                  <!-- Simulación Hoja de Papel PDF -->
                  <div class="pdf-preview-sheet pa-4 rounded border bg-white flex-grow-1 shadow-sm d-flex flex-column justify-space-between">
                    <div>
                      <!-- Membrete Simulado -->
                      <div class="d-flex align-start justify-space-between pb-3 border-b">
                        <!-- Logo -->
                        <div class="pdf-logo-box d-flex align-center justify-center">
                          <img
                            v-if="logoPreview"
                            :src="logoPreview"
                            alt="Logo Farmacia"
                            class="pdf-preview-logo"
                          >
                          <div v-else class="text-center pa-2 text-muted border border-dashed rounded w-100">
                            <VIcon icon="tabler-photo-off" size="24" class="mb-1" />
                            <div style="font-size: 9px;">Sin logotipo cargado</div>
                          </div>
                        </div>

                        <!-- Datos Empresa -->
                        <div class="text-right pl-3" style="max-width: 60%;">
                          <div class="font-weight-bold text-uppercase" style="font-size: 11px; line-height: 1.2; color: #111;">
                            {{ form.app_name || 'NOMBRE DE LA FARMACIA' }}
                          </div>
                          <div v-if="form.app_rif" class="text-muted font-weight-medium mt-1" style="font-size: 9.5px;">
                            R.I.F. Nº {{ form.app_rif }}
                          </div>
                          <div v-if="form.address" class="text-muted mt-1" style="font-size: 8px; line-height: 1.2;">
                            {{ form.address }}
                          </div>
                        </div>
                      </div>

                      <!-- Línea y Título de Documento Simulado -->
                      <div class="text-center mt-4 py-2 border-t border-b bg-light">
                        <span class="font-weight-bold text-uppercase tracking-wider" style="font-size: 10px; color: #2b6cb0;">
                          DOCUMENTO / REPORTE OFICIAL
                        </span>
                      </div>

                      <!-- Contenido Placeholder Simulado -->
                      <div class="mt-4 opacity-40">
                        <div class="shimmer-line w-100 mb-2" style="height: 6px; background: #e2e8f0;"></div>
                        <div class="shimmer-line w-75 mb-2" style="height: 6px; background: #e2e8f0;"></div>
                        <div class="shimmer-line w-90 mb-2" style="height: 6px; background: #e2e8f0;"></div>
                      </div>
                    </div>

                    <div class="text-center pt-4 border-t text-muted" style="font-size: 7.5px;">
                      Documento generado automáticamente con la identidad configurada.
                    </div>
                  </div>
                </VCard>
              </VCol>

              <!-- Botón de Guardado -->
              <VCol cols="12" class="d-flex justify-end mt-4">
                <VBtn
                  type="submit"
                  :loading="isLoading"
                  :disabled="isLoading"
                  color="primary"
                  size="large"
                  prepend-icon="tabler-device-floppy"
                  class="rounded-md px-8 text-uppercase font-weight-bold shadow-md"
                >
                  Guardar Datos de Farmacia
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.shadow-soft {
  box-shadow: 0 4px 18px 0 rgba(0, 0, 0, 0.04) !important;
}

.pdf-preview-sheet {
  min-height: 280px;
  background-color: #ffffff;
  color: #1a202c;
  font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
}

.pdf-logo-box {
  width: 110px;
  min-height: 45px;
}

.pdf-preview-logo {
  max-width: 110px;
  max-height: 55px;
  object-fit: contain;
}

.border-b {
  border-bottom: 1px solid #e2e8f0;
}

.border-t {
  border-top: 1px solid #e2e8f0;
}
</style>