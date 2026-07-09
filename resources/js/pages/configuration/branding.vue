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
  formData.append('section3_title', form.value.section3_title || '')
  formData.append('section3_subtitle', form.value.section3_subtitle || '')
  formData.append('section3_tagline', form.value.section3_tagline || '')
  formData.append('section3_button_text', form.value.section3_button_text || '')
  
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
    await brandingStore.fetchSettings()
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

const approveOrder = async (orderId) => {
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
}

const cancelOrder = async (orderId) => {
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
}

onMounted(async () => {
  await brandingStore.fetchSettings()
  fetchAdminOrders()
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
    section3_title: brandingStore.settings.section3_title || '',
    section3_subtitle: brandingStore.settings.section3_subtitle || '',
    section3_tagline: brandingStore.settings.section3_tagline || '',
    section3_button_text: brandingStore.settings.section3_button_text || '',
  }
  logoPreview.value = brandingStore.settings.app_logo
  faviconPreview.value = brandingStore.settings.app_favicon
  heroImagePreview.value = brandingStore.settings.hero_image
  section2ImagePreview.value = brandingStore.settings.section2_image
  section3ImagePreview.value = brandingStore.settings.section3_image
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
              <!-- Contenedor Único y Unificado de Configuración -->
              <VCol cols="12">
                <div class="border pa-6 rounded-0 bg-white d-flex flex-column gap-2">
                  
                  <!-- Información General -->
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider d-flex align-center gap-2" style="margin-bottom: 10px;">
                      <VIcon icon="tabler-settings" size="18" class="text-primary" />
                      Información de la Tienda
                    </h3>
                    <VRow>
                      <VCol cols="12" md="4">
                        <VTextField
                          v-model="form.app_name"
                          label="Nombre de la Tienda E-commerce"
                          placeholder="Ej: TOVA Beauty & Gems"
                          variant="outlined"
                          density="comfortable"
                          persistent-placeholder
                        />
                      </VCol>
                      <VCol cols="12" sm="6" md="4">
                        <VTextField
                          v-model="form.app_rif"
                          label="RIF de la Empresa"
                          placeholder="Ej: J-12345678-9"
                          variant="outlined"
                          density="comfortable"
                          persistent-placeholder
                        />
                      </VCol>
                      <VCol cols="12" sm="6" md="4">
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
                  <div style="border-top: 1px solid rgba(0,0,0,0.08); padding-top: 15px; margin-top: 15px;">
                    <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider d-flex align-center gap-2" style="margin-bottom: 20px;">
                      <VIcon icon="tabler-palette" size="18" class="text-primary" />
                      Paleta de Colores (Tienda)
                    </h3>
                    <VRow>
                      <VCol cols="12" sm="4">
                        <VTextField
                          v-model="form.primary_color"
                          label="Color Primario"
                          density="comfortable"
                          variant="outlined"
                          class="font-mono text-uppercase"
                        >
                          <template #prepend-inner>
                            <input
                              v-model="form.primary_color"
                              type="color"
                              style="width: 28px; height: 28px; border: 1px solid rgba(0,0,0,0.15); border-radius: 4px; cursor: pointer; padding: 0; margin-right: 8px;"
                            />
                          </template>
                        </VTextField>
                      </VCol>
                      <VCol cols="12" sm="4">
                        <VTextField
                          v-model="form.secondary_color"
                          label="Color Secundario"
                          density="comfortable"
                          variant="outlined"
                          class="font-mono text-uppercase"
                        >
                          <template #prepend-inner>
                            <input
                              v-model="form.secondary_color"
                              type="color"
                              style="width: 28px; height: 28px; border: 1px solid rgba(0,0,0,0.15); border-radius: 4px; cursor: pointer; padding: 0; margin-right: 8px;"
                            />
                          </template>
                        </VTextField>
                      </VCol>
                      <VCol cols="12" sm="4">
                        <VTextField
                          v-model="form.tertiary_color"
                          label="Color Terciario"
                          density="comfortable"
                          variant="outlined"
                          class="font-mono text-uppercase"
                        >
                          <template #prepend-inner>
                            <input
                              v-model="form.tertiary_color"
                              type="color"
                              style="width: 28px; height: 28px; border: 1px solid rgba(0,0,0,0.15); border-radius: 4px; cursor: pointer; padding: 0; margin-right: 8px;"
                            />
                          </template>
                        </VTextField>
                      </VCol>
                    </VRow>
                  </div>

                  <!-- Identidad Multimedia e Imagen Corporativa -->
                  <div style="border-top: 1px solid rgba(0,0,0,0.08); padding-top: 15px; margin-top: 15px;">
                    <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider d-flex align-center gap-2" style="margin-bottom: 20px;">
                      <VIcon icon="tabler-photo" size="18" class="text-primary" />
                      Identidad Multimedia e Imagen Corporativa
                    </h3>
                    
                    <VRow class="mb-2">
                      <!-- Logo de la Tienda -->
                      <VCol cols="12" md="6">
                        <div class="d-flex align-start gap-4">
                          <div class="border rounded-0 bg-light d-flex align-center justify-center border-dashed" style="width: 120px; height: 70px; overflow: hidden; flex-shrink: 0;">
                            <img v-if="logoPreview" :src="logoPreview" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
                            <span v-else class="text-caption text-muted text-uppercase tracking-wider">Sin Logo</span>
                          </div>
                          <div class="flex-grow-1">
                            <VFileInput
                              label="Logo de la Tienda"
                              placeholder="Cargar nuevo logo"
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
                      </VCol>

                      <!-- Favicon de la Tienda -->
                      <VCol cols="12" md="6">
                        <div class="d-flex align-start gap-4">
                          <div class="border rounded-0 bg-light d-flex align-center justify-center border-dashed" style="width: 60px; height: 60px; overflow: hidden; flex-shrink: 0;">
                            <img v-if="faviconPreview" :src="faviconPreview" style="max-width: 32px; max-height: 32px; object-fit: contain;" />
                            <span v-else class="text-caption text-muted text-center text-xxs tracking-tighter">Sin Icono</span>
                          </div>
                          <div class="flex-grow-1">
                            <VFileInput
                              label="Favicon (Icono de pestaña)"
                              placeholder="Cargar favicon"
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
                      </VCol>
                    </VRow>

                    <!-- Banner del Hero (Campaña Editorial) -->
                    <div class="border-top pt-6 mt-6">
                      <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-6 d-flex align-center gap-2">
                        <VIcon icon="tabler-layout" size="18" class="text-primary" />
                        Campaña Hero (Tienda)
                      </h3>

                      <div class="d-flex flex-column gap-4">
                        <!-- Imagen de la Campaña -->
                        <div>
                          <div class="d-flex align-start gap-4">
                            <div class="border rounded-0 bg-light d-flex align-center justify-center border-dashed" style="width: 120px; height: 70px; overflow: hidden;">
                              <img v-if="heroImagePreview" :src="heroImagePreview" style="max-width: 100%; max-height: 100%; object-fit: cover;" />
                              <span v-else class="text-caption text-muted text-uppercase tracking-wider">Sin Imagen</span>
                            </div>
                            <div class="flex-grow-1">
                              <VFileInput
                                label="Imagen de Campaña (Derecha)"
                                placeholder="Cargar imagen de campaña"
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
                          placeholder="Describe breves de la colección..."
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
                        <VIcon icon="tabler-layout" size="18" class="text-primary" />
                        Segunda Sección Híbrida (Sección 2)
                      </h3>

                      <div class="d-flex flex-column gap-4">
                        <!-- Imagen de la Sección 2 -->
                        <div>
                          <div class="d-flex align-start gap-4">
                            <div class="border rounded-0 bg-light d-flex align-center justify-center border-dashed" style="width: 120px; height: 70px; overflow: hidden;">
                              <img v-if="section2ImagePreview" :src="section2ImagePreview" style="max-width: 100%; max-height: 100%; object-fit: cover;" />
                              <span v-else class="text-caption text-muted text-uppercase tracking-wider">Sin Imagen</span>
                            </div>
                            <div class="flex-grow-1">
                              <VFileInput
                                label="Imagen Destacada (Izquierda) - Sección 2"
                                placeholder="Cargar imagen destacada"
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

                    <!-- Tercera Sección Híbrida (Sección 3) -->
                    <div class="border-top pt-6 mt-6">
                      <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-6 d-flex align-center gap-2">
                        <VIcon icon="tabler-layout" size="18" class="text-primary" />
                        Tercera Sección Híbrida (Sección 3)
                      </h3>

                      <div class="d-flex flex-column gap-4">
                        <!-- Imagen de la Sección 3 -->
                        <div>
                          <div class="d-flex align-start gap-4">
                            <div class="border rounded-0 bg-light d-flex align-center justify-center border-dashed" style="width: 120px; height: 70px; overflow: hidden;">
                              <img v-if="section3ImagePreview" :src="section3ImagePreview" style="max-width: 100%; max-height: 100%; object-fit: cover;" />
                              <span v-else class="text-caption text-muted text-uppercase tracking-wider">Sin Imagen</span>
                            </div>
                            <div class="flex-grow-1">
                              <VFileInput
                                label="Imagen Destacada (Derecha) - Sección 3"
                                placeholder="Cargar imagen destacada"
                                accept="image/*"
                                density="comfortable"
                                variant="outlined"
                                hide-details
                                prepend-icon=""
                                prepend-inner-icon="tabler-upload"
                                @change="handleSection3ImageUpload"
                              />
                              <span class="text-xxs text-muted mt-1 d-block">Formatos: JPG, PNG, WEBP. Recomendada: 800x800px.</span>
                            </div>
                          </div>
                        </div>

                        <!-- Textos de la Sección 3 -->
                        <VTextField
                          v-model="form.section3_tagline"
                          label="Etiqueta Superior (Tagline)"
                          placeholder="Ej: EFECTO SOL"
                          variant="outlined"
                          density="comfortable"
                          persistent-placeholder
                        />

                        <VTextField
                          v-model="form.section3_title"
                          label="Título de la Sección (Serif)"
                          placeholder="Ej: SUN STALK'R SOUFFLÉ PRESSED MOUSSE BRONZER"
                          variant="outlined"
                          density="comfortable"
                          persistent-placeholder
                        />

                        <VTextarea
                          v-model="form.section3_subtitle"
                          label="Subtítulo / Descripción"
                          placeholder="Describe las virtudes de esta colección..."
                          variant="outlined"
                          density="comfortable"
                          rows="3"
                          persistent-placeholder
                        />

                        <VTextField
                          v-model="form.section3_button_text"
                          label="Texto del Botón de Acción"
                          placeholder="Ej: COMPRAR BRONCEADOR"
                          variant="outlined"
                          density="comfortable"
                          persistent-placeholder
                        />
                      </div>
                    </div>
                  </div>

                </div>
              </VCol>

              <!-- Sección de Aprobación de Pedidos de E-commerce -->
              <VCol v-if="brandingStore.settings.business_type !== 'minimarket'" cols="12" class="mt-8">
                <div class="border pa-6 rounded-0 bg-white d-flex flex-column gap-4">
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider d-flex align-center gap-2" style="margin-bottom: 5px;">
                      <VIcon icon="tabler-shopping-cart" size="18" class="text-primary" />
                      Aprobación de Pedidos E-commerce
                    </h3>
                    <p class="text-muted text-caption mb-6">
                      Listado de compras web en estado pendiente. Aquí puedes revisar los métodos de pago (Zelle, Pago Móvil a tasa Binance) y aprobar o rechazar para procesar el despacho.
                    </p>

                    <!-- Tabla de pedidos -->
                    <div v-if="adminOrders.length" class="border rounded-0 overflow-hidden">
                      <v-table class="text-left" style="width: 100%;">
                        <thead>
                          <tr style="background-color: #FAFAFA; border-bottom: 2px solid #E8E8E8;">
                            <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase">Pedido</th>
                            <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase">Cliente</th>
                            <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase">Contacto</th>
                            <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase">Método</th>
                            <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase text-right">Total</th>
                            <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase">Estado</th>
                            <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase text-center">Acciones</th>
                          </tr>
                        </thead>
                        <tbody>
                          <template v-for="order in adminOrders" :key="order.id">
                            <tr style="border-bottom: 1px solid #E8E8E8;">
                              <td class="py-4 px-4 text-xs font-weight-bold">#{{ order.id }}</td>
                              <td class="py-4 px-4 text-xs">
                                <div class="font-weight-bold">{{ order.customer_name }}</div>
                                <div class="text-xxs text-muted">{{ order.customer_email || 'Sin email' }}</div>
                              </td>
                              <td class="py-4 px-4 text-xs">
                                <div>{{ order.customer_phone || '-' }}</div>
                                <div class="text-xxs text-muted" style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                  {{ order.shipping_address }}
                                </div>
                              </td>
                              <td class="py-4 px-4 text-xs font-weight-bold text-uppercase">
                                <span>{{ order.payment_method === 'pago_movil' ? 'Pago Móvil' : order.payment_method }}</span>
                              </td>
                              <td class="py-4 px-4 text-xs text-right font-weight-bold">${{ Number(order.total_amount).toFixed(2) }}</td>
                              <td class="py-4 px-4 text-xs">
                                <VChip
                                  size="x-small"
                                  variant="tonal"
                                  class="rounded-0 text-uppercase"
                                  :color="order.status === 'Paid' ? 'success' : (order.status === 'Cancelled' ? 'error' : 'warning')"
                                >
                                  {{ order.status === 'Pending' ? 'Pendiente' : (order.status === 'Paid' ? 'Aprobado' : 'Cancelado') }}
                                </VChip>
                              </td>
                              <td class="py-4 px-4 text-center">
                                <div v-if="order.status === 'Pending'" class="d-flex align-center justify-center gap-2">
                                  <VBtn
                                    size="x-small"
                                    color="success"
                                    variant="flat"
                                    class="rounded-0"
                                    :loading="actionLoadingId === order.id"
                                    @click="approveOrder(order.id)"
                                  >
                                    Aprobar
                                  </VBtn>
                                  <VBtn
                                    size="x-small"
                                    color="error"
                                    variant="outlined"
                                    class="rounded-0"
                                    :loading="actionLoadingId === order.id"
                                    @click="cancelOrder(order.id)"
                                  >
                                    Rechazar
                                  </VBtn>
                                </div>
                                <span v-else class="text-xxs text-muted font-weight-bold text-uppercase">Procesado</span>
                              </td>
                            </tr>
                            <!-- Detalles de productos comprados en el pedido -->
                            <tr style="background-color: #FCFCFC; border-bottom: 1px solid #E8E8E8;">
                              <td colspan="7" class="py-2 px-6 text-xxs text-muted">
                                <span class="font-weight-bold text-uppercase tracking-wider mr-2">Detalles:</span>
                                <span v-for="(item, index) in order.items" :key="item.id">
                                  {{ item.product_name }} <span v-if="item.variant_value">({{ item.variant_value }})</span> x{{ item.quantity }} (${{ Number(item.price).toFixed(2) }})<span v-if="index < order.items.length - 1">, </span>
                                </span>
                              </td>
                            </tr>
                          </template>
                        </tbody>
                      </v-table>
                    </div>
                    <div v-else class="text-center py-8 border border-dashed rounded-0 bg-light">
                      <span class="text-caption text-muted text-uppercase tracking-wider">No hay pedidos registrados en la tienda actualmente</span>
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
