<script setup>
import { ref, onMounted } from 'vue'
import { useBrandingStore } from '@/stores/useBrandingStore'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

const brandingStore = useBrandingStore()
const isLoading = ref(false)

const form = ref({
  app_name: '',
  app_rif: '',
  primary_color: '#E20074',
  secondary_color: '#7A0099',
  tertiary_color: '#F5C842',
  footer_text: '',
  default_currency: 'COP',
})

const logoFile = ref(null)
const faviconFile = ref(null)
const logoPreview = ref('')
const faviconPreview = ref('')

const handleLogoUpload = (e) => {
  const file = e.target.files[0]
  if (file) {
    logoFile.value = file
    logoPreview.value = URL.createObjectURL(file)
  }
}

const handleFaviconUpload = (e) => {
  const file = e.target.files[0]
  if (file) {
    faviconFile.value = file
    faviconPreview.value = URL.createObjectURL(file)
  }
}

const saveBranding = async () => {
  isLoading.value = true
  
  const formData = new FormData()
  formData.append('app_name', form.value.app_name || '')
  formData.append('app_rif', form.value.app_rif || '')
  formData.append('primary_color', form.value.primary_color)
  formData.append('secondary_color', form.value.secondary_color)
  formData.append('tertiary_color', form.value.tertiary_color)
  formData.append('footer_text', form.value.footer_text || '')
  formData.append('default_currency', form.value.default_currency || 'COP')
  
  if (logoFile.value) {
    formData.append('app_logo', logoFile.value)
  }
  
  if (faviconFile.value) {
    formData.append('app_favicon', faviconFile.value)
  }

  try {
    await axios.post('/general-settings', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    
    toast.success('Marca actualizada correctamente')
    await brandingStore.fetchSettings()
  } catch (error) {
    console.error('Error saving branding:', error)
    toast.error('Error al actualizar la marca')
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await brandingStore.fetchSettings()
  form.value = {
    app_name: brandingStore.settings.app_name,
    app_rif: brandingStore.settings.app_rif,
    primary_color: brandingStore.settings.primary_color,
    secondary_color: brandingStore.settings.secondary_color,
    tertiary_color: brandingStore.settings.tertiary_color || '#F5C842',
    footer_text: brandingStore.settings.footer_text,
    default_currency: brandingStore.settings.default_currency || 'COP',
  }
  logoPreview.value = brandingStore.settings.app_logo
  faviconPreview.value = brandingStore.settings.app_favicon
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard>
        <VCardItem>
          <VCardTitle class="text-h5 font-weight-bold">Configuraciones Generales</VCardTitle>
          <VCardSubtitle class="text-muted">Gestión de la marca y elementos visuales (Logo y Favicon) exclusivos para el e-commerce y los PDFs generados de la tienda</VCardSubtitle>
        </VCardItem>
        <VCardText>
          <VForm @submit.prevent="saveBranding">
            <VRow>
              <!-- Bloque 1: Datos de la Tienda -->
              <VCol cols="12" md="7">
                <VCard variant="outlined" class="p-5 rounded-xl h-100 border-opacity-30">
                  <h3 class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center gap-2">
                    <VIcon icon="tabler-settings" size="20" color="primary" />
                    Información de la Tienda
                  </h3>
                  <VRow>
                    <VCol cols="12">
                      <VTextField
                        v-model="form.app_name"
                        label="Nombre de la Tienda E-commerce"
                        placeholder="Ej: TOVA Beauty & Gems"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol cols="12" sm="6">
                      <VTextField
                        v-model="form.app_rif"
                        label="RIF de la Empresa"
                        placeholder="Ej: J-12345678-9"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol cols="12" sm="6">
                      <VSelect
                        v-model="form.default_currency"
                        :items="['COP', 'USD', 'BS']"
                        label="Moneda del Sistema"
                        placeholder="Selecciona moneda"
                        variant="outlined"
                      />
                    </VCol>
                  </VRow>
                </VCard>
              </VCol>

              <!-- Bloque 2: Identidad Visual (Logos) -->
              <VCol cols="12" md="5">
                <VCard variant="outlined" class="p-5 rounded-xl h-100 border-opacity-30">
                  <h3 class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center gap-2">
                    <VIcon icon="tabler-photo" size="20" color="primary" />
                    Multimedia
                  </h3>
                  
                  <!-- Logo -->
                  <div class="mb-5">
                    <VLabel class="mb-2 text-caption font-weight-bold text-muted">LOGO DE LA TIENDA</VLabel>
                    <div class="d-flex align-center gap-4">
                      <div class="border rounded pa-2 bg-light d-flex align-center justify-center" style="width: 100px; height: 50px; overflow: hidden;">
                        <img v-if="logoPreview" :src="logoPreview" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
                        <span v-else class="text-caption text-muted">Sin Logo</span>
                      </div>
                      <VFileInput
                        label="Subir Logo"
                        accept="image/*"
                        density="compact"
                        variant="outlined"
                        hide-details
                        prepend-icon=""
                        prepend-inner-icon="tabler-camera"
                        @change="handleLogoUpload"
                      />
                    </div>
                  </div>

                  <!-- Favicon -->
                  <div>
                    <VLabel class="mb-2 text-caption font-weight-bold text-muted">FAVICON (PESTAÑA)</VLabel>
                    <div class="d-flex align-center gap-4">
                      <div class="border rounded pa-2 bg-light d-flex align-center justify-center" style="width: 50px; height: 50px;">
                        <VAvatar v-if="faviconPreview" :image="faviconPreview" size="32" rounded />
                        <span v-else class="text-caption text-muted">Sin Icono</span>
                      </div>
                      <VFileInput
                        label="Subir Favicon"
                        accept="image/*"
                        density="compact"
                        variant="outlined"
                        hide-details
                        prepend-icon=""
                        prepend-inner-icon="tabler-app-window"
                        @change="handleFaviconUpload"
                      />
                    </div>
                  </div>
                </VCard>
              </VCol>

              <!-- Bloque 3: Colores Exclusivos del E-commerce -->
              <VCol cols="12">
                <VCard variant="outlined" class="p-5 rounded-xl border-opacity-30">
                  <h3 class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center gap-2">
                    <VIcon icon="tabler-palette" size="20" color="primary" />
                    Paleta de Colores (Tienda)
                  </h3>
                  <VRow>
                    <VCol cols="12" md="4">
                      <VLabel class="mb-2 text-caption font-weight-bold text-muted">COLOR PRIMARIO</VLabel>
                      <div class="d-flex align-center gap-3">
                        <input
                          v-model="form.primary_color"
                          type="color"
                          class="color-picker-premium"
                        />
                        <VTextField
                          v-model="form.primary_color"
                          density="comfortable"
                          variant="outlined"
                          hide-details
                        />
                      </div>
                    </VCol>
                    <VCol cols="12" md="4">
                      <VLabel class="mb-2 text-caption font-weight-bold text-muted">COLOR SECUNDARIO</VLabel>
                      <div class="d-flex align-center gap-3">
                        <input
                          v-model="form.secondary_color"
                          type="color"
                          class="color-picker-premium"
                        />
                        <VTextField
                          v-model="form.secondary_color"
                          density="comfortable"
                          variant="outlined"
                          hide-details
                        />
                      </div>
                    </VCol>
                    <VCol cols="12" md="4">
                      <VLabel class="mb-2 text-caption font-weight-bold text-muted">COLOR TERCIARIO</VLabel>
                      <div class="d-flex align-center gap-3">
                        <input
                          v-model="form.tertiary_color"
                          type="color"
                          class="color-picker-premium"
                        />
                        <VTextField
                          v-model="form.tertiary_color"
                          density="comfortable"
                          variant="outlined"
                          hide-details
                        />
                      </div>
                    </VCol>
                  </VRow>
                </VCard>
              </VCol>

              <!-- Bloque 4: Footer Corporativo Estático -->
              <VCol cols="12">
                <VCard variant="flat" class="bg-grey-lighten-4 p-4 rounded-xl d-flex align-center justify-space-between flex-wrap gap-4">
                  <div>
                    <p class="text-caption text-muted mb-1 font-weight-bold text-uppercase tracking-wider">PIE DE PÁGINA CORPORATIVO INAMOVIBLE</p>
                    <p class="text-body-2 mb-0 text-dark font-weight-medium">
                      © {{ new Date().getFullYear() }} {{ form.app_name || 'TOVA' }}. Todos los derechos reservados.
                    </p>
                  </div>
                  <div class="text-md-right">
                    <span class="text-caption text-muted d-block">DISEÑO Y DESARROLLO</span>
                    <a href="https://tovaerp.com/" target="_blank" class="text-primary font-weight-bold text-decoration-none text-subtitle-2 d-flex align-center gap-1">
                      Tova tu Cerebro Operativo 
                      <VIcon icon="tabler-external-link" size="14" />
                    </a>
                  </div>
                </VCard>
              </VCol>

              <!-- Guardar Cambios -->
              <VCol cols="12" class="d-flex justify-end mt-2">
                <VBtn
                  type="submit"
                  :loading="isLoading"
                  color="primary"
                  size="large"
                  rounded="pill"
                  class="px-8 shadow-sm"
                  prepend-icon="tabler-device-floppy"
                >
                  Guardar Cambios
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
.color-picker-premium {
  width: 56px;
  height: 44px;
  border: 1px solid rgba(0,0,0,0.12);
  border-radius: 8px;
  cursor: pointer;
  padding: 0;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  transition: transform 0.2s;
}
.color-picker-premium:hover {
  transform: scale(1.05);
}

.bg-light {
  background-color: #f8f9fa;
}
</style>
