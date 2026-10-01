<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useBrandingStore } from '@/stores/useBrandingStore'
import { ECOMMERCE_PRESETS } from '@/composables/useEcommercePresets'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

const brandingStore = useBrandingStore()
const isLoading = ref(false)
const isPageLoading = ref(true)
const activeTab = ref('branding')
const selectedPreset = ref(null)

const presetOptions = [
  { title: 'Mini Market & Bodegón', value: 'minimarket' },
  { title: 'Farmacia & Salud Integral', value: 'pharmacy' },
  { title: 'Belleza & Cosmética', value: 'beauty' },
]

const form = reactive({
  primary_color: '#E20074',
  secondary_color: '#7A0099',
  tertiary_color: '#F5C842',
  footer_text: '',
  hero_title: '',
  hero_subtitle: '',
  hero_tagline: '',
  hero_button_text: '',
  section2_title: '',
  section2_subtitle: '',
  section2_tagline: '',
  section2_button_text: '',
  section3_title: '',
  section3_subtitle: '',
  section3_tagline: '',
  section3_button_text: '',
  shipping_methods: {
    local: {
      pickup: {
        id: 'pickup',
        title: 'Recogida en Tienda',
        enabled: true,
        cost: 0,
        estimated_time: 'Inmediato / Horario comercial',
        description: 'Retira tu pedido directamente en nuestra sucursal principal sin costo adicional.'
      },
      delivery: {
        id: 'delivery',
        title: 'Envío a Domicilio / Delivery Local',
        enabled: true,
        cost: 2.0,
        estimated_time: '1 a 3 horas',
        description: 'Servicio de entrega local motorizado directo a tu ubicación.'
      }
    },
    national: {
      mrw: {
        id: 'mrw',
        title: 'MRW (Nacional)',
        enabled: true,
        cost: 0,
        is_cod: true,
        estimated_time: '24 a 48 horas hábiles',
        description: 'Envío nacional a través de agencia MRW con cobro en destino.'
      },
      tealca: {
        id: 'tealca',
        title: 'Tealca (Nacional)',
        enabled: true,
        cost: 0,
        is_cod: true,
        estimated_time: '24 a 48 horas hábiles',
        description: 'Envío nacional a través de agencia Tealca con cobro en destino.'
      },
      zoom: {
        id: 'zoom',
        title: 'ZOOM (Nacional)',
        enabled: true,
        cost: 0,
        is_cod: true,
        estimated_time: '24 a 48 horas hábiles',
        description: 'Envío nacional asegurado a través de encomiendas ZOOM.'
      }
    }
  }
})

const heroImageFile = ref(null)
const section2ImageFile = ref(null)
const section3ImageFile = ref(null)

const heroImagePreview = ref('')
const section2ImagePreview = ref('')
const section3ImagePreview = ref('')

const applyPreset = (presetKey) => {
  if (!presetKey || !ECOMMERCE_PRESETS[presetKey]) return
  const preset = ECOMMERCE_PRESETS[presetKey]
  
  form.hero_tagline = preset.hero_tagline
  form.hero_title = preset.hero_title
  form.hero_subtitle = preset.hero_subtitle
  form.hero_button_text = preset.hero_button_text

  form.section2_tagline = preset.section2_tagline
  form.section2_title = preset.section2_title
  form.section2_subtitle = preset.section2_subtitle
  form.section2_button_text = preset.section2_button_text

  form.section3_tagline = preset.section3_tagline
  form.section3_title = preset.section3_title
  form.section3_subtitle = preset.section3_subtitle
  form.section3_button_text = preset.section3_button_text

  toast.success(`Plantilla "${preset.label}" cargada en el formulario`)
}

const handleImageUpload = (e, targetFileRef, targetPreviewRef) => {
  const file = e.target.files?.[0]
  if (file) {
    if (!file.type.startsWith('image/')) {
      toast.error('Por favor seleccione un archivo de imagen válido (PNG, JPG, WEBP, SVG)')
      return
    }
    if (file.size > 5 * 1024 * 1024) {
      toast.error('La imagen no debe superar los 5MB')
      return
    }
    targetFileRef.value = file
    targetPreviewRef.value = URL.createObjectURL(file)
  }
}

const saveEcommerceSettings = async () => {
  isLoading.value = true
  
  const formData = new FormData()
  formData.append('primary_color', form.primary_color)
  formData.append('secondary_color', form.secondary_color)
  formData.append('tertiary_color', form.tertiary_color)
  formData.append('footer_text', form.footer_text || '')
  formData.append('hero_title', form.hero_title || '')
  formData.append('hero_subtitle', form.hero_subtitle || '')
  formData.append('hero_tagline', form.hero_tagline || '')
  formData.append('hero_button_text', form.hero_button_text || '')
  formData.append('section2_title', form.section2_title || '')
  formData.append('section2_subtitle', form.section2_subtitle || '')
  formData.append('section2_tagline', form.section2_tagline || '')
  formData.append('section2_button_text', form.section2_button_text || '')
  formData.append('section3_title', form.section3_title || '')
  formData.append('section3_subtitle', form.section3_subtitle || '')
  formData.append('section3_tagline', form.section3_tagline || '')
  formData.append('section3_button_text', form.section3_button_text || '')
  formData.append('ecommerce_shipping_methods', JSON.stringify(form.shipping_methods))
  
  if (heroImageFile.value) {
    formData.append('hero_image', heroImageFile.value)
  }
  
  if (section2ImageFile.value) {
    formData.append('section2_image', section2ImageFile.value)
  }

  if (section3ImageFile.value) {
    formData.append('section3_image', section3ImageFile.value)
  }

  try {
    await axios.post('/general-settings', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    
    toast.success('Configuraciones del E-commerce actualizadas exitosamente')
    await brandingStore.fetchSettings(true)
  } catch (error) {
    console.error('Error saving e-commerce settings:', error)
    toast.error('Error al actualizar la configuración del E-commerce')
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  isPageLoading.value = true
  try {
    await brandingStore.fetchSettings(true)
    Object.assign(form, {
      primary_color: brandingStore.settings.primary_color || '#E20074',
      secondary_color: brandingStore.settings.secondary_color || '#7A0099',
      tertiary_color: brandingStore.settings.tertiary_color || '#F5C842',
      footer_text: brandingStore.settings.footer_text || '',
      hero_title: brandingStore.settings.hero_title || '',
      hero_subtitle: brandingStore.settings.hero_subtitle || '',
      hero_tagline: brandingStore.settings.hero_tagline || '',
      hero_button_text: brandingStore.settings.hero_button_text || '',
      section2_title: brandingStore.settings.section2_title || '',
      section2_subtitle: brandingStore.settings.section2_subtitle || '',
      section2_tagline: brandingStore.settings.section2_tagline || '',
      section2_button_text: brandingStore.settings.section2_button_text || '',
      section3_title: brandingStore.settings.section3_title || '',
      section3_subtitle: brandingStore.settings.section3_subtitle || '',
      section3_tagline: brandingStore.settings.section3_tagline || '',
      section3_button_text: brandingStore.settings.section3_button_text || '',
    })

    if (brandingStore.settings.ecommerce_shipping_methods) {
      let loadedShipping = brandingStore.settings.ecommerce_shipping_methods
      if (typeof loadedShipping === 'string') {
        try { loadedShipping = JSON.parse(loadedShipping) } catch (e) {}
      }
      if (loadedShipping && typeof loadedShipping === 'object') {
        if (loadedShipping.local) {
          form.shipping_methods.local = { ...form.shipping_methods.local, ...loadedShipping.local }
        }
        if (loadedShipping.national) {
          form.shipping_methods.national = { ...form.shipping_methods.national, ...loadedShipping.national }
        }
      }
    }

    heroImagePreview.value = brandingStore.settings.hero_image || ''
    section2ImagePreview.value = brandingStore.settings.section2_image || ''
    section3ImagePreview.value = brandingStore.settings.section3_image || ''
  } catch (error) {
    console.error('Error loading settings:', error)
  } finally {
    isPageLoading.value = false
  }
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <!-- Skeleton Loader -->
      <VCard
        v-if="isPageLoading"
        variant="outlined"
        class="pa-6 rounded-lg bg-surface"
      >
        <VSkeletonLoader type="heading" class="w-25 mb-2" />
        <VSkeletonLoader type="subtitle" class="w-50 mb-6" />
        <VRow>
          <VCol cols="12" md="4"><VSkeletonLoader type="image" /></VCol>
          <VCol cols="12" md="8"><VSkeletonLoader type="paragraph, actions" /></VCol>
        </VRow>
      </VCard>

      <!-- Panel Principal de Configuración E-commerce -->
      <VCard
        v-else
        variant="flat"
        class="rounded-lg border shadow-sm"
      >
        <VCardItem class="px-6 pt-6 pb-3">
          <div class="d-flex align-center justify-space-between flex-wrap gap-4">
            <div class="d-flex align-center gap-3">
              <VAvatar color="primary" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-shopping-bag" size="28" />
              </VAvatar>
              <div>
                <VCardTitle class="text-h5 font-weight-bold">
                  Configuración del E-commerce
                </VCardTitle>
                <VCardSubtitle class="text-caption text-medium-emphasis mt-1">
                  Gestión de banners, secciones promocionales, paleta de colores y plantillas comerciales para la tienda en línea.
                </VCardSubtitle>
              </div>
            </div>

            <!-- Selector de Tipo / Plantilla Preset -->
            <div style="min-width: 280px;">
              <VSelect
                v-model="selectedPreset"
                :items="presetOptions"
                label="Cargar Plantilla de Tienda"
                placeholder="Seleccione (ej: Mini Market)"
                prepend-inner-icon="tabler-template"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                @update:model-value="applyPreset"
              />
            </div>
          </div>
        </VCardItem>

        <VDivider />

        <VCardText class="px-6 py-5">
          <VForm @submit.prevent="saveEcommerceSettings">
            <!-- Tabs de Navegación por Secciones -->
            <VTabs v-model="activeTab" color="primary" class="mb-6">
              <VTab value="branding">
                <VIcon icon="tabler-palette" class="me-2" />
                Colores y Estilo
              </VTab>
              <VTab value="hero">
                <VIcon icon="tabler-layout-board" class="me-2" />
                Campaña Principal (Hero)
              </VTab>
              <VTab value="sections">
                <VIcon icon="tabler-layout-grid" class="me-2" />
                Secciones Promocionales (2 y 3)
              </VTab>
              <VTab value="shipping">
                <VIcon icon="tabler-truck-delivery" class="me-2" />
                Métodos de Envío
              </VTab>
            </VTabs>

            <VWindow v-model="activeTab">
              <!-- TAB 1: Colores y Pie de Página -->
              <VWindowItem value="branding">
                <VCard variant="outlined" class="pa-5 rounded-lg border">
                  <div class="text-subtitle-1 font-weight-bold mb-4 text-primary d-flex align-center gap-2">
                    <VIcon icon="tabler-palette" size="20" />
                    Paleta de Colores de la Vitrina Digital
                  </div>

                  <VRow>
                    <VCol cols="12" sm="4">
                      <VTextField
                        v-model="form.primary_color"
                        label="Color Primario"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :disabled="isLoading"
                      >
                        <template #prepend-inner>
                          <input
                            v-model="form.primary_color"
                            type="color"
                            class="cursor-pointer border rounded me-2"
                            style="width: 26px; height: 26px; padding: 0;"
                            :disabled="isLoading"
                          />
                        </template>
                      </VTextField>
                    </VCol>

                    <VCol cols="12" sm="4">
                      <VTextField
                        v-model="form.secondary_color"
                        label="Color Secundario"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :disabled="isLoading"
                      >
                        <template #prepend-inner>
                          <input
                            v-model="form.secondary_color"
                            type="color"
                            class="cursor-pointer border rounded me-2"
                            style="width: 26px; height: 26px; padding: 0;"
                            :disabled="isLoading"
                          />
                        </template>
                      </VTextField>
                    </VCol>

                    <VCol cols="12" sm="4">
                      <VTextField
                        v-model="form.tertiary_color"
                        label="Color Terciario / Acento"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :disabled="isLoading"
                      >
                        <template #prepend-inner>
                          <input
                            v-model="form.tertiary_color"
                            type="color"
                            class="cursor-pointer border rounded me-2"
                            style="width: 26px; height: 26px; padding: 0;"
                            :disabled="isLoading"
                          />
                        </template>
                      </VTextField>
                    </VCol>

                    <VCol cols="12" class="mt-2">
                      <VTextarea
                        v-model="form.footer_text"
                        label="Texto del Pie de Página de la Tienda"
                        placeholder="Ej: © 2026 Tu Tienda Online. Todos los derechos reservados."
                        rows="2"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :disabled="isLoading"
                      />
                    </VCol>
                  </VRow>
                </VCard>
              </VWindowItem>

              <!-- TAB 2: Campaña Hero -->
              <VWindowItem value="hero">
                <VCard variant="outlined" class="pa-5 rounded-lg border">
                  <div class="text-subtitle-1 font-weight-bold mb-4 text-primary d-flex align-center gap-2">
                    <VIcon icon="tabler-layout-board" size="20" />
                    Banner Principal (Hero Section)
                  </div>

                  <VRow>
                    <!-- Previsualización y Carga de Imagen -->
                    <VCol cols="12" md="4">
                      <div class="border rounded-lg pa-3 text-center bg-surface h-100 d-flex flex-column justify-space-between">
                        <div>
                          <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
                            Imagen del Banner Principal
                          </div>
                          <VImg
                            v-if="heroImagePreview"
                            :src="heroImagePreview"
                            height="180"
                            cover
                            class="rounded-lg mb-3"
                          />
                          <div
                            v-else
                            class="d-flex align-center justify-center bg-grey-100 rounded-lg mb-3"
                            style="height: 180px;"
                          >
                            <VIcon icon="tabler-photo-off" size="44" class="text-medium-emphasis" />
                          </div>
                        </div>

                        <VFileInput
                          label="Cargar Imagen Hero"
                          accept="image/*"
                          variant="outlined"
                          density="compact"
                          hide-details="auto"
                          prepend-icon=""
                          prepend-inner-icon="tabler-upload"
                          :disabled="isLoading"
                          @change="(e) => handleImageUpload(e, heroImageFile, heroImagePreview)"
                        />
                      </div>
                    </VCol>

                    <!-- Textos del Banner -->
                    <VCol cols="12" md="8">
                      <VRow>
                        <VCol cols="12">
                          <VTextField
                            v-model="form.hero_tagline"
                            label="Etiqueta Superior (Tagline)"
                            placeholder="Ej: NUEVA COLECCIÓN / OFERTAS DEL DÍA"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading"
                          />
                        </VCol>
                        <VCol cols="12">
                          <VTextField
                            v-model="form.hero_title"
                            label="Título Principal"
                            placeholder="Ej: CALIDAD Y FRESCURA EN CADA COMPRA"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading"
                          />
                        </VCol>
                        <VCol cols="12">
                          <VTextarea
                            v-model="form.hero_subtitle"
                            label="Descripción / Subtítulo"
                            placeholder="Descripción de la campaña promocional..."
                            rows="3"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading"
                          />
                        </VCol>
                        <VCol cols="12">
                          <VTextField
                            v-model="form.hero_button_text"
                            label="Texto del Botón de Acción"
                            placeholder="Ej: EXPLORAR CATÁLOGO"
                            prepend-inner-icon="tabler-arrow-narrow-right"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading"
                          />
                        </VCol>
                      </VRow>
                    </VCol>
                  </VRow>
                </VCard>
              </VWindowItem>

              <!-- TAB 3: Secciones Promocionales (2 y 3) -->
              <VWindowItem value="sections">
                <VRow>
                  <!-- Sección 2 -->
                  <VCol cols="12" md="6">
                    <VCard variant="outlined" class="pa-5 rounded-lg border h-100 d-flex flex-column justify-space-between">
                      <div>
                        <div class="text-subtitle-1 font-weight-bold mb-3 text-primary d-flex align-center gap-2">
                          <VIcon icon="tabler-layout-grid" size="18" />
                          Segunda Sección Destacada
                        </div>

                        <VImg
                          v-if="section2ImagePreview"
                          :src="section2ImagePreview"
                          height="140"
                          cover
                          class="rounded-lg mb-3"
                        />
                        <div
                          v-else
                          class="d-flex align-center justify-center bg-grey-100 rounded-lg mb-3"
                          style="height: 140px;"
                        >
                          <VIcon icon="tabler-photo-off" size="36" class="text-medium-emphasis" />
                        </div>

                        <VFileInput
                          label="Cargar Imagen Sección 2"
                          accept="image/*"
                          variant="outlined"
                          density="compact"
                          hide-details="auto"
                          prepend-icon=""
                          prepend-inner-icon="tabler-upload"
                          class="mb-3"
                          :disabled="isLoading"
                          @change="(e) => handleImageUpload(e, section2ImageFile, section2ImagePreview)"
                        />

                        <VTextField
                          v-model="form.section2_tagline"
                          label="Tagline / Etiqueta"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                          class="mb-3"
                          :disabled="isLoading"
                        />

                        <VTextField
                          v-model="form.section2_title"
                          label="Título de la Sección"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                          class="mb-3"
                          :disabled="isLoading"
                        />

                        <VTextarea
                          v-model="form.section2_subtitle"
                          label="Descripción"
                          rows="2"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                          class="mb-3"
                          :disabled="isLoading"
                        />
                      </div>

                      <VTextField
                        v-model="form.section2_button_text"
                        label="Texto del Botón"
                        prepend-inner-icon="tabler-arrow-narrow-right"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :disabled="isLoading"
                      />
                    </VCard>
                  </VCol>

                  <!-- Sección 3 -->
                  <VCol cols="12" md="6">
                    <VCard variant="outlined" class="pa-5 rounded-lg border h-100 d-flex flex-column justify-space-between">
                      <div>
                        <div class="text-subtitle-1 font-weight-bold mb-3 text-primary d-flex align-center gap-2">
                          <VIcon icon="tabler-layout-grid" size="18" />
                          Tercera Sección Destacada
                        </div>

                        <VImg
                          v-if="section3ImagePreview"
                          :src="section3ImagePreview"
                          height="140"
                          cover
                          class="rounded-lg mb-3"
                        />
                        <div
                          v-else
                          class="d-flex align-center justify-center bg-grey-100 rounded-lg mb-3"
                          style="height: 140px;"
                        >
                          <VIcon icon="tabler-photo-off" size="36" class="text-medium-emphasis" />
                        </div>

                        <VFileInput
                          label="Cargar Imagen Sección 3"
                          accept="image/*"
                          variant="outlined"
                          density="compact"
                          hide-details="auto"
                          prepend-icon=""
                          prepend-inner-icon="tabler-upload"
                          class="mb-3"
                          :disabled="isLoading"
                          @change="(e) => handleImageUpload(e, section3ImageFile, section3ImagePreview)"
                        />

                        <VTextField
                          v-model="form.section3_tagline"
                          label="Tagline / Etiqueta"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                          class="mb-3"
                          :disabled="isLoading"
                        />

                        <VTextField
                          v-model="form.section3_title"
                          label="Título de la Sección"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                          class="mb-3"
                          :disabled="isLoading"
                        />

                        <VTextarea
                          v-model="form.section3_subtitle"
                          label="Descripción"
                          rows="2"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                          class="mb-3"
                          :disabled="isLoading"
                        />
                      </div>

                      <VTextField
                        v-model="form.section3_button_text"
                        label="Texto del Botón"
                        prepend-inner-icon="tabler-arrow-narrow-right"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :disabled="isLoading"
                      />
                    </VCard>
                  </VCol>
                </VRow>
              </VWindowItem>

              <!-- TAB 4: Métodos de Envío (Locales y Nacionales) -->
              <VWindowItem value="shipping">
                <VRow>
                  <!-- BLOQUE 1: ENVÍOS LOCALES -->
                  <VCol cols="12">
                    <div class="d-flex align-center gap-2 mb-3">
                      <VAvatar color="info" variant="tonal" size="32" class="rounded">
                        <VIcon icon="tabler-map-pin" size="20" />
                      </VAvatar>
                      <div>
                        <div class="text-subtitle-1 font-weight-bold text-info">
                          Envíos Locales / Retiro
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          Opciones de entrega física inmediata o delivery en tu zona o ciudad.
                        </div>
                      </div>
                    </div>
                  </VCol>

                  <!-- 1.1 Recogida en Tienda -->
                  <VCol cols="12" md="6">
                    <VCard variant="outlined" class="pa-5 rounded-lg border h-100">
                      <div class="d-flex align-center justify-space-between mb-4">
                        <div class="d-flex align-center gap-2">
                          <VIcon icon="tabler-building-store" size="24" color="primary" />
                          <span class="text-subtitle-1 font-weight-bold">Recogida en Tienda</span>
                        </div>
                        <VSwitch
                          v-model="form.shipping_methods.local.pickup.enabled"
                          color="success"
                          hide-details
                          inset
                          :disabled="isLoading"
                        />
                      </div>

                      <VTextField
                        v-model="form.shipping_methods.local.pickup.title"
                        label="Nombre del Método"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-3"
                        :disabled="isLoading || !form.shipping_methods.local.pickup.enabled"
                      />

                      <VRow class="mb-1">
                        <VCol cols="6">
                          <VTextField
                            v-model.number="form.shipping_methods.local.pickup.cost"
                            label="Costo ($ USD)"
                            type="number"
                            step="0.01"
                            min="0"
                            prepend-inner-icon="tabler-currency-dollar"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading || !form.shipping_methods.local.pickup.enabled"
                          />
                        </VCol>
                        <VCol cols="6">
                          <VTextField
                            v-model="form.shipping_methods.local.pickup.estimated_time"
                            label="Tiempo Estimado"
                            placeholder="Inmediato"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading || !form.shipping_methods.local.pickup.enabled"
                          />
                        </VCol>
                      </VRow>

                      <VTextarea
                        v-model="form.shipping_methods.local.pickup.description"
                        label="Instrucciones para el Cliente"
                        rows="2"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mt-2"
                        :disabled="isLoading || !form.shipping_methods.local.pickup.enabled"
                      />
                    </VCard>
                  </VCol>

                  <!-- 1.2 Envío a Domicilio / Delivery Local -->
                  <VCol cols="12" md="6">
                    <VCard variant="outlined" class="pa-5 rounded-lg border h-100">
                      <div class="d-flex align-center justify-space-between mb-4">
                        <div class="d-flex align-center gap-2">
                          <VIcon icon="tabler-moped" size="24" color="info" />
                          <span class="text-subtitle-1 font-weight-bold">Delivery Local</span>
                        </div>
                        <VSwitch
                          v-model="form.shipping_methods.local.delivery.enabled"
                          color="success"
                          hide-details
                          inset
                          :disabled="isLoading"
                        />
                      </div>

                      <VTextField
                        v-model="form.shipping_methods.local.delivery.title"
                        label="Nombre del Método"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-3"
                        :disabled="isLoading || !form.shipping_methods.local.delivery.enabled"
                      />

                      <VRow class="mb-1">
                        <VCol cols="6">
                          <VTextField
                            v-model.number="form.shipping_methods.local.delivery.cost"
                            label="Costo ($ USD)"
                            type="number"
                            step="0.01"
                            min="0"
                            prepend-inner-icon="tabler-currency-dollar"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading || !form.shipping_methods.local.delivery.enabled"
                          />
                        </VCol>
                        <VCol cols="6">
                          <VTextField
                            v-model="form.shipping_methods.local.delivery.estimated_time"
                            label="Tiempo Estimado"
                            placeholder="1 a 3 horas"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading || !form.shipping_methods.local.delivery.enabled"
                          />
                        </VCol>
                      </VRow>

                      <VTextarea
                        v-model="form.shipping_methods.local.delivery.description"
                        label="Instrucciones para el Cliente"
                        rows="2"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mt-2"
                        :disabled="isLoading || !form.shipping_methods.local.delivery.enabled"
                      />
                    </VCard>
                  </VCol>

                  <!-- BLOQUE 2: ENVÍOS NACIONALES -->
                  <VCol cols="12" class="mt-4">
                    <div class="d-flex align-center gap-2 mb-3">
                      <VAvatar color="warning" variant="tonal" size="32" class="rounded">
                        <VIcon icon="tabler-truck" size="20" />
                      </VAvatar>
                      <div>
                        <div class="text-subtitle-1 font-weight-bold text-warning">
                          Envíos Nacionales (Empresas de Encomienda)
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          Empresas de transporte nacional para envíos a cualquier estado o ciudad.
                        </div>
                      </div>
                    </div>
                  </VCol>

                  <!-- 2.1 MRW -->
                  <VCol cols="12" md="4">
                    <VCard variant="outlined" class="pa-5 rounded-lg border h-100">
                      <div class="d-flex align-center justify-space-between mb-4">
                        <div class="d-flex align-center gap-2">
                          <VIcon icon="tabler-package" size="24" color="warning" />
                          <span class="text-subtitle-1 font-weight-bold">MRW</span>
                        </div>
                        <VSwitch
                          v-model="form.shipping_methods.national.mrw.enabled"
                          color="success"
                          hide-details
                          inset
                          :disabled="isLoading"
                        />
                      </div>

                      <VTextField
                        v-model="form.shipping_methods.national.mrw.title"
                        label="Nombre del Método"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-3"
                        :disabled="isLoading || !form.shipping_methods.national.mrw.enabled"
                      />

                      <VRow class="mb-1">
                        <VCol cols="6">
                          <VTextField
                            v-model.number="form.shipping_methods.national.mrw.cost"
                            label="Costo Adicional ($)"
                            type="number"
                            step="0.01"
                            min="0"
                            prepend-inner-icon="tabler-currency-dollar"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading || !form.shipping_methods.national.mrw.enabled"
                          />
                        </VCol>
                        <VCol cols="6">
                          <VTextField
                            v-model="form.shipping_methods.national.mrw.estimated_time"
                            label="Tiempo Estimado"
                            placeholder="24-48 horas"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading || !form.shipping_methods.national.mrw.enabled"
                          />
                        </VCol>
                      </VRow>

                      <VCheckbox
                        v-model="form.shipping_methods.national.mrw.is_cod"
                        label="Cobro en Destino (Flete Pagado por Cliente)"
                        density="compact"
                        color="warning"
                        hide-details
                        class="mb-2"
                        :disabled="isLoading || !form.shipping_methods.national.mrw.enabled"
                      />

                      <VTextarea
                        v-model="form.shipping_methods.national.mrw.description"
                        label="Instrucciones de Envío"
                        rows="2"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :disabled="isLoading || !form.shipping_methods.national.mrw.enabled"
                      />
                    </VCard>
                  </VCol>

                  <!-- 2.2 Tealca -->
                  <VCol cols="12" md="4">
                    <VCard variant="outlined" class="pa-5 rounded-lg border h-100">
                      <div class="d-flex align-center justify-space-between mb-4">
                        <div class="d-flex align-center gap-2">
                          <VIcon icon="tabler-truck-loading" size="24" color="info" />
                          <span class="text-subtitle-1 font-weight-bold">Tealca</span>
                        </div>
                        <VSwitch
                          v-model="form.shipping_methods.national.tealca.enabled"
                          color="success"
                          hide-details
                          inset
                          :disabled="isLoading"
                        />
                      </div>

                      <VTextField
                        v-model="form.shipping_methods.national.tealca.title"
                        label="Nombre del Método"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-3"
                        :disabled="isLoading || !form.shipping_methods.national.tealca.enabled"
                      />

                      <VRow class="mb-1">
                        <VCol cols="6">
                          <VTextField
                            v-model.number="form.shipping_methods.national.tealca.cost"
                            label="Costo Adicional ($)"
                            type="number"
                            step="0.01"
                            min="0"
                            prepend-inner-icon="tabler-currency-dollar"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading || !form.shipping_methods.national.tealca.enabled"
                          />
                        </VCol>
                        <VCol cols="6">
                          <VTextField
                            v-model="form.shipping_methods.national.tealca.estimated_time"
                            label="Tiempo Estimado"
                            placeholder="24-48 horas"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading || !form.shipping_methods.national.tealca.enabled"
                          />
                        </VCol>
                      </VRow>

                      <VCheckbox
                        v-model="form.shipping_methods.national.tealca.is_cod"
                        label="Cobro en Destino (Flete Pagado por Cliente)"
                        density="compact"
                        color="info"
                        hide-details
                        class="mb-2"
                        :disabled="isLoading || !form.shipping_methods.national.tealca.enabled"
                      />

                      <VTextarea
                        v-model="form.shipping_methods.national.tealca.description"
                        label="Instrucciones de Envío"
                        rows="2"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :disabled="isLoading || !form.shipping_methods.national.tealca.enabled"
                      />
                    </VCard>
                  </VCol>

                  <!-- 2.3 ZOOM -->
                  <VCol cols="12" md="4">
                    <VCard variant="outlined" class="pa-5 rounded-lg border h-100">
                      <div class="d-flex align-center justify-space-between mb-4">
                        <div class="d-flex align-center gap-2">
                          <VIcon icon="tabler-cube-send" size="24" color="primary" />
                          <span class="text-subtitle-1 font-weight-bold">ZOOM</span>
                        </div>
                        <VSwitch
                          v-model="form.shipping_methods.national.zoom.enabled"
                          color="success"
                          hide-details
                          inset
                          :disabled="isLoading"
                        />
                      </div>

                      <VTextField
                        v-model="form.shipping_methods.national.zoom.title"
                        label="Nombre del Método"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-3"
                        :disabled="isLoading || !form.shipping_methods.national.zoom.enabled"
                      />

                      <VRow class="mb-1">
                        <VCol cols="6">
                          <VTextField
                            v-model.number="form.shipping_methods.national.zoom.cost"
                            label="Costo Adicional ($)"
                            type="number"
                            step="0.01"
                            min="0"
                            prepend-inner-icon="tabler-currency-dollar"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading || !form.shipping_methods.national.zoom.enabled"
                          />
                        </VCol>
                        <VCol cols="6">
                          <VTextField
                            v-model="form.shipping_methods.national.zoom.estimated_time"
                            label="Tiempo Estimado"
                            placeholder="24-48 horas"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :disabled="isLoading || !form.shipping_methods.national.zoom.enabled"
                          />
                        </VCol>
                      </VRow>

                      <VCheckbox
                        v-model="form.shipping_methods.national.zoom.is_cod"
                        label="Cobro en Destino (Flete Pagado por Cliente)"
                        density="compact"
                        color="primary"
                        hide-details
                        class="mb-2"
                        :disabled="isLoading || !form.shipping_methods.national.zoom.enabled"
                      />

                      <VTextarea
                        v-model="form.shipping_methods.national.zoom.description"
                        label="Instrucciones de Envío"
                        rows="2"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :disabled="isLoading || !form.shipping_methods.national.zoom.enabled"
                      />
                    </VCard>
                  </VCol>
                </VRow>
              </VWindowItem>
            </VWindow>

            <VDivider class="my-6" />

            <!-- Botón de Guardado -->
            <div class="d-flex justify-end">
              <VBtn
                type="submit"
                color="primary"
                size="large"
                prepend-icon="tabler-device-floppy"
                :loading="isLoading"
                :disabled="isLoading"
                class="px-8"
              >
                Guardar Configuración E-commerce
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
