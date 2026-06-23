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
  hero_title: '',
  hero_subtitle: '',
  hero_tagline: '',
  hero_button_text: '',
  section2_title: '',
  section2_subtitle: '',
  section2_tagline: '',
  section2_button_text: '',
})

const logoFile = ref(null)
const faviconFile = ref(null)
const heroImageFile = ref(null)
const section2ImageFile = ref(null)

const logoPreview = ref('')
const faviconPreview = ref('')
const heroImagePreview = ref('')
const section2ImagePreview = ref('')

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

const handleHeroImageUpload = (e) => {
  const file = e.target.files[0]
  if (file) {
    heroImageFile.value = file
    heroImagePreview.value = URL.createObjectURL(file)
  }
}

const handleSection2ImageUpload = (e) => {
  const file = e.target.files[0]
  if (file) {
    section2ImageFile.value = file
    section2ImagePreview.value = URL.createObjectURL(file)
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
  formData.append('hero_title', form.value.hero_title || '')
  formData.append('hero_subtitle', form.value.hero_subtitle || '')
  formData.append('hero_tagline', form.value.hero_tagline || '')
  formData.append('hero_button_text', form.value.hero_button_text || '')
  formData.append('section2_title', form.value.section2_title || '')
  formData.append('section2_subtitle', form.value.section2_subtitle || '')
  formData.append('section2_tagline', form.value.section2_tagline || '')
  formData.append('section2_button_text', form.value.section2_button_text || '')
  
  if (logoFile.value) {
    formData.append('app_logo', logoFile.value)
  }
  
  if (faviconFile.value) {
    formData.append('app_favicon', faviconFile.value)
  }

  if (heroImageFile.value) {
    formData.append('hero_image', heroImageFile.value)
  }

  if (section2ImageFile.value) {
    formData.append('section2_image', section2ImageFile.value)
  }

  try {
    await axios.post('/general-settings', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    
    toast.success('Marca y secciones del e-commerce actualizadas correctamente')
    await brandingStore.fetchSettings()
  } catch (error) {
    console.error('Error saving branding:', error)
    toast.error('Error al actualizar los datos')
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
    hero_title: brandingStore.settings.hero_title || '',
    hero_subtitle: brandingStore.settings.hero_subtitle || '',
    hero_tagline: brandingStore.settings.hero_tagline || '',
    hero_button_text: brandingStore.settings.hero_button_text || '',
    section2_title: brandingStore.settings.section2_title || '',
    section2_subtitle: brandingStore.settings.section2_subtitle || '',
    section2_tagline: brandingStore.settings.section2_tagline || '',
    section2_button_text: brandingStore.settings.section2_button_text || '',
  }
  logoPreview.value = brandingStore.settings.app_logo
  faviconPreview.value = brandingStore.settings.app_favicon
  heroImagePreview.value = brandingStore.settings.hero_image
  section2ImagePreview.value = brandingStore.settings.section2_image
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard class="rounded-0 border-0" variant="flat">
        <VCardItem class="px-0 pt-0 pb-6">
          <VCardTitle class="text-h4 font-weight-light text-uppercase tracking-wider">Configuraciones Generales</VCardTitle>
          <VCardSubtitle class="text-muted text-caption mt-1">
            Gestión de identidad visual, colores y logos exclusivos del e-commerce y reportes PDF de la tienda
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="px-0">
          <VForm @submit.prevent="saveBranding">
            <VRow>
              <!-- Sección Izquierda: Campos e Información -->
              <VCol cols="12" md="7" class="d-flex flex-column gap-6">
                <!-- Información General -->
                <div class="border pa-6 rounded-0">
                  <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-6 d-flex align-center gap-2">
                    <VIcon icon="tabler-settings" size="18" class="text-primary" />
                    Información de la Tienda
                  </h3>
                  <VRow>
                    <VCol cols="12">
                      <VTextField
                        v-model="form.app_name"
                        label="Nombre de la Tienda E-commerce"
                        placeholder="Ej: TOVA Beauty & Gems"
                        variant="outlined"
                        density="comfortable"
                        persistent-placeholder
                      />
                    </VCol>
                    <VCol cols="12" sm="6">
                      <VTextField
                        v-model="form.app_rif"
                        label="RIF de la Empresa"
                        placeholder="Ej: J-12345678-9"
                        variant="outlined"
                        density="comfortable"
                        persistent-placeholder
                      />
                    </VCol>
                    <VCol cols="12" sm="6">
                      <VSelect
                        v-model="form.default_currency"
                        :items="['COP', 'USD', 'BS']"
                        label="Moneda del Sistema"
                        placeholder="Selecciona moneda"
                        variant="outlined"
                        density="comfortable"
                      />
                    </VCol>
                  </VRow>
                </div>

                <!-- Paleta de Colores -->
                <div class="border pa-6 rounded-0">
                  <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-6 d-flex align-center gap-2">
                    <VIcon icon="tabler-palette" size="18" class="text-primary" />
                    Paleta de Colores (Tienda)
                  </h3>
                  <VRow class="gap-y-4">
                    <VCol cols="12" sm="4">
                      <VLabel class="mb-2 text-caption font-weight-bold text-muted text-uppercase tracking-wider">Color Primario</VLabel>
                      <div class="d-flex align-center gap-2">
                        <input
                          v-model="form.primary_color"
                          type="color"
                          class="color-picker-premium"
                        />
                        <VTextField
                          v-model="form.primary_color"
                          density="compact"
                          variant="outlined"
                          hide-details
                          class="font-mono text-uppercase"
                        />
                      </div>
                    </VCol>
                    <VCol cols="12" sm="4">
                      <VLabel class="mb-2 text-caption font-weight-bold text-muted text-uppercase tracking-wider">Color Secundario</VLabel>
                      <div class="d-flex align-center gap-2">
                        <input
                          v-model="form.secondary_color"
                          type="color"
                          class="color-picker-premium"
                        />
                        <VTextField
                          v-model="form.secondary_color"
                          density="compact"
                          variant="outlined"
                          hide-details
                          class="font-mono text-uppercase"
                        />
                      </div>
                    </VCol>
                    <VCol cols="12" sm="4">
                      <VLabel class="mb-2 text-caption font-weight-bold text-muted text-uppercase tracking-wider">Color Terciario</VLabel>
                      <div class="d-flex align-center gap-2">
                        <input
                          v-model="form.tertiary_color"
                          type="color"
                          class="color-picker-premium"
                        />
                        <VTextField
                          v-model="form.tertiary_color"
                          density="compact"
                          variant="outlined"
                          hide-details
                          class="font-mono text-uppercase"
                        />
                      </div>
                    </VCol>
                  </VRow>
                </div>
              </VCol>

              <!-- Sección Derecha: Multimedia / Identidad Visual -->
              <VCol cols="12" md="5">
                <div class="border pa-6 rounded-0 h-100">
                  <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-6 d-flex align-center gap-2">
                    <VIcon icon="tabler-photo" size="18" class="text-primary" />
                    Identidad Multimedia
                  </h3>
                  
                  <!-- Logo de la Tienda -->
                  <div class="mb-8">
                    <VLabel class="mb-3 text-caption font-weight-bold text-muted text-uppercase tracking-wider">Logo de la Tienda</VLabel>
                    <div class="d-flex align-start gap-4">
                      <div class="border rounded-0 bg-light d-flex align-center justify-center border-dashed" style="width: 120px; height: 70px; overflow: hidden;">
                        <img v-if="logoPreview" :src="logoPreview" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
                        <span v-else class="text-caption text-muted text-uppercase tracking-wider">Sin Logo</span>
                      </div>
                      <div class="flex-grow-1">
                        <VFileInput
                          label="Cargar nuevo logo"
                          accept="image/*"
                          density="comfortable"
                          variant="outlined"
                          hide-details
                          prepend-icon=""
                          prepend-inner-icon="tabler-upload"
                          @change="handleLogoUpload"
                        />
                        <span class="text-xxs text-muted mt-1 d-block">Formatos recomendados: PNG, SVG transparentes.</span>
                      </div>
                    </div>
                  </div>

                  <!-- Favicon de la Tienda -->
                  <div class="mb-8">
                    <VLabel class="mb-3 text-caption font-weight-bold text-muted text-uppercase tracking-wider">Favicon (Icono de pestaña)</VLabel>
                    <div class="d-flex align-start gap-4">
                      <div class="border rounded-0 bg-light d-flex align-center justify-center border-dashed" style="width: 60px; height: 60px; overflow: hidden;">
                        <img v-if="faviconPreview" :src="faviconPreview" style="max-width: 32px; max-height: 32px; object-fit: contain;" />
                        <span v-else class="text-caption text-muted text-center text-xxs tracking-tighter">Sin Icono</span>
                      </div>
                      <div class="flex-grow-1">
                        <VFileInput
                          label="Cargar favicon"
                          accept="image/*"
                          density="comfortable"
                          variant="outlined"
                          hide-details
                          prepend-icon=""
                          prepend-inner-icon="tabler-upload"
                          @change="handleFaviconUpload"
                        />
                        <span class="text-xxs text-muted mt-1 d-block">Formatos recomendados: ICO, PNG de 32x32px.</span>
                      </div>
                    </div>
                  </div>

                  <!-- Banner del Hero (Campaña Editorial) -->
                  <div class="border-top pt-6 mt-6">
                    <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-6 d-flex align-center gap-2">
                      <VIcon icon="tabler-layout" size="18" class="text-primary" />
                      Campaña Hero (Tienda)
                    </h3>

                    <div class="d-flex flex-column gap-4">
                      <!-- Imagen de la Campaña -->
                      <div>
                        <VLabel class="mb-3 text-caption font-weight-bold text-muted text-uppercase tracking-wider">Imagen de Campaña (Derecha)</VLabel>
                        <div class="d-flex align-start gap-4">
                          <div class="border rounded-0 bg-light d-flex align-center justify-center border-dashed" style="width: 120px; height: 70px; overflow: hidden;">
                            <img v-if="heroImagePreview" :src="heroImagePreview" style="max-width: 100%; max-height: 100%; object-fit: cover;" />
                            <span v-else class="text-caption text-muted text-uppercase tracking-wider">Sin Imagen</span>
                          </div>
                          <div class="flex-grow-1">
                            <VFileInput
                              label="Cargar imagen de campaña"
                              accept="image/*"
                              density="comfortable"
                              variant="outlined"
                              hide-details
                              prepend-icon=""
                              prepend-inner-icon="tabler-upload"
                              @change="handleHeroImageUpload"
                            />
                            <span class="text-xxs text-muted mt-1 d-block">Formatos: JPG, PNG, WEBP. Recomendada: 800x800px.</span>
                          </div>
                        </div>
                      </div>

                      <!-- Textos de Campaña -->
                      <VTextField
                        v-model="form.hero_tagline"
                        label="Etiqueta Superior (Tagline)"
                        placeholder="Ej: NUEVA COLECCIÓN"
                        variant="outlined"
                        density="comfortable"
                        persistent-placeholder
                      />

                      <VTextField
                        v-model="form.hero_title"
                        label="Título de Campaña (Serif)"
                        placeholder="Ej: YOUR NEW BOMB NUDES"
                        variant="outlined"
                        density="comfortable"
                        persistent-placeholder
                      />

                      <VTextarea
                        v-model="form.hero_subtitle"
                        label="Subtítulo / Descripción"
                        placeholder="Describe brevemente la colección..."
                        variant="outlined"
                        density="comfortable"
                        rows="3"
                        persistent-placeholder
                      />

                      <VTextField
                        v-model="form.hero_button_text"
                        label="Texto del Botón de Acción"
                        placeholder="Ej: COMPRAR AHORA"
                        variant="outlined"
                        density="comfortable"
                        persistent-placeholder
                      />
                    </div>
                  </div>

                  <!-- Segunda Sección Híbrida (Sección 2) -->
                  <div class="border-top pt-6 mt-6">
                    <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-6 d-flex align-center gap-2">
                      <VIcon icon="tabler-layout-split" size="18" class="text-primary" />
                      Segunda Sección Híbrida
                    </h3>

                    <div class="d-flex flex-column gap-4">
                      <!-- Imagen de la Sección 2 -->
                      <div>
                        <VLabel class="mb-3 text-caption font-weight-bold text-muted text-uppercase tracking-wider">Imagen Destacada (Izquierda)</VLabel>
                        <div class="d-flex align-start gap-4">
                          <div class="border rounded-0 bg-light d-flex align-center justify-center border-dashed" style="width: 120px; height: 70px; overflow: hidden;">
                            <img v-if="section2ImagePreview" :src="section2ImagePreview" style="max-width: 100%; max-height: 100%; object-fit: cover;" />
                            <span v-else class="text-caption text-muted text-uppercase tracking-wider">Sin Imagen</span>
                          </div>
                          <div class="flex-grow-1">
                            <VFileInput
                              label="Cargar imagen destacada"
                              accept="image/*"
                              density="comfortable"
                              variant="outlined"
                              hide-details
                              prepend-icon=""
                              prepend-inner-icon="tabler-upload"
                              @change="handleSection2ImageUpload"
                            />
                            <span class="text-xxs text-muted mt-1 d-block">Formatos: JPG, PNG, WEBP. Recomendada: 800x800px.</span>
                          </div>
                        </div>
                      </div>

                      <!-- Textos de la Sección 2 -->
                      <VTextField
                        v-model="form.section2_tagline"
                        label="Etiqueta Superior (Tagline)"
                        placeholder="Ej: PIEL RADIANTE"
                        variant="outlined"
                        density="comfortable"
                        persistent-placeholder
                      />

                      <VTextField
                        v-model="form.section2_title"
                        label="Título de la Sección (Serif)"
                        placeholder="Ej: MEET YOUR DONE-IN-ONE TINTED MOISTURIZER"
                        variant="outlined"
                        density="comfortable"
                        persistent-placeholder
                      />

                      <VTextarea
                        v-model="form.section2_subtitle"
                        label="Subtítulo / Descripción"
                        placeholder="Describe las virtudes de esta colección..."
                        variant="outlined"
                        density="comfortable"
                        rows="3"
                        persistent-placeholder
                      />

                      <VTextField
                        v-model="form.section2_button_text"
                        label="Texto del Botón de Acción"
                        placeholder="Ej: DESCUBRIR TONOS"
                        variant="outlined"
                        density="comfortable"
                        persistent-placeholder
                      />
                    </div>
                  </div>
                </div>
              </VCol>

              <!-- Botón Guardar Cambios -->
              <VCol cols="12" class="d-flex justify-end mt-4">
                <VBtn
                  type="submit"
                  :loading="isLoading"
                  color="primary"
                  size="large"
                  class="rounded-0 px-10 tracking-widest text-uppercase"
                  prepend-icon="tabler-device-floppy"
                >
                  Guardar Configuración
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
