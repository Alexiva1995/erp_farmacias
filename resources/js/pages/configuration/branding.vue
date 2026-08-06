<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useBrandingStore } from '@/stores/useBrandingStore'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'
import BrandingFormFields from '@/components/BrandingFormFields.vue'
import EcommerceOrdersTable from '@/components/EcommerceOrdersTable.vue'

const brandingStore = useBrandingStore()
const isLoading = ref(false)
const isPageLoading = ref(true)

const form = reactive({
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
  section3_title: '',
  section3_subtitle: '',
  section3_tagline: '',
  section3_button_text: '',
})

const logoFile = ref(null)
const faviconFile = ref(null)
const heroImageFile = ref(null)
const section2ImageFile = ref(null)
const section3ImageFile = ref(null)

const logoPreview = ref('')
const faviconPreview = ref('')
const heroImagePreview = ref('')
const section2ImagePreview = ref('')
const section3ImagePreview = ref('')

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

const handleSection3ImageUpload = (e) => {
  const file = e.target.files[0]
  if (file) {
    section3ImageFile.value = file
    section3ImagePreview.value = URL.createObjectURL(file)
  }
}

const saveBranding = async () => {
  isLoading.value = true
  
  const formData = new FormData()
  formData.append('app_name', form.app_name || '')
  formData.append('app_rif', form.app_rif || '')
  formData.append('primary_color', form.primary_color)
  formData.append('secondary_color', form.secondary_color)
  formData.append('tertiary_color', form.tertiary_color)
  formData.append('footer_text', form.footer_text || '')
  formData.append('default_currency', form.default_currency || 'COP')
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

  if (section3ImageFile.value) {
    formData.append('section3_image', section3ImageFile.value)
  }

  try {
    await axios.post('/general-settings', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    
    toast.success('Marca y secciones del e-commerce actualizadas correctamente')
    await brandingStore.fetchSettings(true)
  } catch (error) {
    console.error('Error saving branding:', error)
    toast.error('Error al actualizar los datos')
  } finally {
    isLoading.value = false
  }
}

// ——— Métodos de Gestión de Pedidos de E-commerce ———
const adminOrders = ref([])
const actionLoadingId = ref(null)

const fetchAdminOrders = async () => {
  try {
    const { data } = await axios.get('/ecommerce/admin/orders')
    if (data.success) {
      adminOrders.value = data.data
    }
  } catch (error) {
    console.error('Error fetching admin orders:', error)
  }
}

const approveOrder = (orderId) => {
  toast.confirm('¿Desea aprobar este pedido y confirmar el pago?', async () => {
    actionLoadingId.value = orderId
    try {
      const { data } = await axios.post(`/ecommerce/admin/orders/${orderId}/approve`)
      if (data.success) {
        toast.success('Pedido aprobado y marcado como pagado exitosamente')
        await fetchAdminOrders()
      }
    } catch (error) {
      console.error('Error approving order:', error)
      toast.error('Error al aprobar el pedido')
    } finally {
      actionLoadingId.value = null
    }
  })
}

const cancelOrder = (orderId) => {
  toast.confirm('¿Desea rechazar este pedido y revertir el inventario?', async () => {
    actionLoadingId.value = orderId
    try {
      const { data } = await axios.post(`/ecommerce/admin/orders/${orderId}/cancel`)
      if (data.success) {
        toast.success('Pedido rechazado y stock devuelto exitosamente')
        await fetchAdminOrders()
      }
    } catch (error) {
      console.error('Error cancelling order:', error)
      toast.error('Error al rechazar el pedido')
    } finally {
      actionLoadingId.value = null
    }
  })
}

onMounted(async () => {
  isPageLoading.value = true
  try {
    // Forzamos la obtención fresca de datos del backend
    await brandingStore.fetchSettings(true)
    await fetchAdminOrders()
    Object.assign(form, {
      app_name: brandingStore.settings.app_name || '',
      app_rif: brandingStore.settings.app_rif || '',
      primary_color: brandingStore.settings.primary_color || '#E20074',
      secondary_color: brandingStore.settings.secondary_color || '#7A0099',
      tertiary_color: brandingStore.settings.tertiary_color || '#F5C842',
      footer_text: brandingStore.settings.footer_text || '',
      default_currency: brandingStore.settings.default_currency || 'COP',
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
    logoPreview.value = brandingStore.settings.app_logo
    faviconPreview.value = brandingStore.settings.app_favicon
    heroImagePreview.value = brandingStore.settings.hero_image
    section2ImagePreview.value = brandingStore.settings.section2_image
    section3ImagePreview.value = brandingStore.settings.section3_image
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
      <!-- Skeleton Loader Shimmer Efecto Moderno -->
      <div v-if="isPageLoading" class="shimmer-container border pa-6 rounded-lg bg-white d-flex flex-column gap-6">
        <div class="shimmer-block h-8 rounded w-25 mb-2"></div>
        <div class="shimmer-block h-4 rounded w-50 mb-6"></div>
        <div class="border rounded pa-6 d-flex flex-column gap-4">
          <div class="shimmer-block h-6 rounded w-33 mb-2"></div>
          <VRow>
            <VCol cols="12" md="4"><div class="shimmer-block h-12 rounded"></div></VCol>
            <VCol cols="12" md="4"><div class="shimmer-block h-12 rounded"></div></VCol>
            <VCol cols="12" md="4"><div class="shimmer-block h-12 rounded"></div></VCol>
          </VRow>
        </div>
      </div>

      <VCard v-else class="rounded-lg shadow-soft border-0" variant="flat">
        <VCardItem class="px-0 pt-0 pb-6">
          <VCardTitle class="text-h4 font-weight-light text-uppercase tracking-wider text-primary-gradient">
            Configuraciones Generales
          </VCardTitle>
          <VCardSubtitle class="text-muted text-caption mt-1">
            Gestión de identidad visual, colores y logos exclusivos del e-commerce y reportes PDF de la tienda
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="px-0">
          <VForm @submit.prevent="saveBranding">
            <VRow>
              <!-- Formulario modular de campos de Branding -->
              <VCol cols="12">
                <BrandingFormFields
                  :form="form"
                  :logo-preview="logoPreview"
                  :favicon-preview="faviconPreview"
                  :hero-image-preview="heroImagePreview"
                  :section2-image-preview="section2ImagePreview"
                  :section3-image-preview="section3ImagePreview"
                  :is-loading="isLoading"
                  @upload-logo="handleLogoUpload"
                  @upload-favicon="handleFaviconUpload"
                  @upload-hero="handleHeroImageUpload"
                  @upload-section2="handleSection2ImageUpload"
                  @upload-section3="handleSection3ImageUpload"
                />
              </VCol>

              <!-- Sección modular de Aprobación de Pedidos de E-commerce -->
              <VCol cols="12" class="mt-6">
                <EcommerceOrdersTable
                  :admin-orders="adminOrders"
                  :action-loading-id="actionLoadingId"
                  @approve="approveOrder"
                  @cancel="cancelOrder"
                />
              </VCol>

              <!-- Botón Guardar Cambios -->
              <VCol cols="12" class="d-flex justify-end mt-4">
                <VBtn
                  type="submit"
                  :loading="isLoading"
                  :disabled="isLoading"
                  color="primary"
                  size="large"
                  class="rounded-md px-10 tracking-widest text-uppercase font-weight-bold shadow-md hover-scale"
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
.shadow-soft {
  box-shadow: 0 4px 18px 0 rgba(0,0,0,0.04) !important;
}

.hover-scale {
  transition: transform 0.2s;
}

.hover-scale:hover {
  transform: translateY(-2px);
}

.text-primary-gradient {
  background: var(--brand-gradient, linear-gradient(135deg, #7a0099, #e20074));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

/* --- Shimmer Skeleton Loader --- */
.shimmer-container {
  overflow: hidden;
}

.shimmer-block {
  background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer-animation 1.5s infinite;
}

@keyframes shimmer-animation {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
</style>
