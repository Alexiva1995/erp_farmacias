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
  footer_text: '',
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
  formData.append('footer_text', form.value.footer_text || '')
  
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
    footer_text: brandingStore.settings.footer_text,
  }
  logoPreview.value = brandingStore.settings.app_logo
  faviconPreview.value = brandingStore.settings.app_favicon
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard title="Personalización de Marca">
        <VCardText>
          <VForm @submit.prevent="saveBranding">
            <VRow>
              <!-- Nombre y RIF -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model="form.app_name"
                  label="Nombre del ERP"
                  placeholder="Ej: Mi Farmacia"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="form.app_rif"
                  label="RIF de la Empresa"
                  placeholder="Ej: J-12345678-9"
                />
              </VCol>

              <!-- Colores -->
              <VCol cols="12" md="6">
                <VLabel class="mb-2">Color Primario</VLabel>
                <div class="d-flex align-center gap-4">
                  <input
                    v-model="form.primary_color"
                    type="color"
                    class="color-picker"
                  >
                  <VTextField
                    v-model="form.primary_color"
                    density="compact"
                    hide-details
                  />
                </div>
              </VCol>
              <VCol cols="12" md="6">
                <VLabel class="mb-2">Color Secundario</VLabel>
                <div class="d-flex align-center gap-4">
                  <input
                    v-model="form.secondary_color"
                    type="color"
                    class="color-picker"
                  >
                  <VTextField
                    v-model="form.secondary_color"
                    density="compact"
                    hide-details
                  />
                </div>
              </VCol>

              <!-- Logo -->
              <VCol cols="12" md="6">
                <VLabel class="mb-2">Logo del Sistema</VLabel>
                <div class="d-flex align-start gap-4 flex-column">
                  <VImg
                    v-if="logoPreview"
                    :src="logoPreview"
                    width="150"
                    height="60"
                    contain
                    class="border rounded pa-2 bg-light"
                  />
                  <VFileInput
                    label="Subir Logo"
                    accept="image/*"
                    prepend-icon="tabler-camera"
                    @change="handleLogoUpload"
                  />
                </div>
              </VCol>

              <!-- Favicon -->
              <VCol cols="12" md="6">
                <VLabel class="mb-2">Favicon (Icono de pestaña)</VLabel>
                <div class="d-flex align-start gap-4 flex-column">
                  <VAvatar
                    v-if="faviconPreview"
                    :image="faviconPreview"
                    size="48"
                    rounded
                    class="border pa-1 bg-light"
                  />
                  <VFileInput
                    label="Subir Favicon"
                    accept="image/*"
                    prepend-icon="tabler-app-window"
                    @change="handleFaviconUpload"
                  />
                </div>
              </VCol>

              <!-- Footer -->
              <VCol cols="12">
                <VTextarea
                  v-model="form.footer_text"
                  label="Texto del Footer"
                  placeholder="Todos los derechos reservados..."
                  rows="2"
                />
              </VCol>

              <VCol cols="12" class="d-flex gap-4 mt-4">
                <VBtn
                  type="submit"
                  :loading="isLoading"
                  color="primary"
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
.color-picker {
  width: 50px;
  height: 40px;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
  padding: 0;
  overflow: hidden;
}

.bg-light {
  background-color: #f8f9fa;
}
</style>
