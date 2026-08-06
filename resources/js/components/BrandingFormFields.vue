<script setup>
import { ref } from 'vue'

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  logoPreview: {
    type: String,
    default: '',
  },
  faviconPreview: {
    type: String,
    default: '',
  },
  heroImagePreview: {
    type: String,
    default: '',
  },
  section2ImagePreview: {
    type: String,
    default: '',
  },
  section3ImagePreview: {
    type: String,
    default: '',
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'upload-logo',
  'upload-favicon',
  'upload-hero',
  'upload-section2',
  'upload-section3',
])
</script>

<template>
  <div class="border pa-6 rounded-lg bg-white d-flex flex-column gap-4 shadow-sm hover-card transition-all">
    <!-- Información General -->
    <div>
      <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider d-flex align-center gap-2 mb-4">
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
            :disabled="isLoading"
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
            :disabled="isLoading"
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
            :disabled="isLoading"
          />
        </VCol>
      </VRow>
    </div>

    <!-- Paleta de Colores -->
    <VDivider class="my-4" />
    <div>
      <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider d-flex align-center gap-2 mb-4">
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
            :disabled="isLoading"
          >
            <template #prepend-inner>
              <input
                v-model="form.primary_color"
                type="color"
                class="color-picker-premium"
                :disabled="isLoading"
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
            :disabled="isLoading"
          >
            <template #prepend-inner>
              <input
                v-model="form.secondary_color"
                type="color"
                class="color-picker-premium"
                :disabled="isLoading"
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
            :disabled="isLoading"
          >
            <template #prepend-inner>
              <input
                v-model="form.tertiary_color"
                type="color"
                class="color-picker-premium"
                :disabled="isLoading"
              />
            </template>
          </VTextField>
        </VCol>
      </VRow>
    </div>

    <!-- Identidad Multimedia e Imagen Corporativa -->
    <VDivider class="my-4" />
    <div>
      <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider d-flex align-center gap-2 mb-6">
        <VIcon icon="tabler-photo" size="18" class="text-primary" />
        Identidad Multimedia e Imagen Corporativa
      </h3>
      
      <VRow class="mb-2">
        <!-- Logo de la Tienda -->
        <VCol cols="12" md="6">
          <div class="d-flex align-start gap-4">
            <div class="border rounded-lg bg-light d-flex align-center justify-center border-dashed image-preview-box" style="width: 120px; height: 70px; overflow: hidden; flex-shrink: 0;">
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
                :disabled="isLoading"
                @change="(e) => emit('upload-logo', e)"
              />
              <span class="text-xxs text-muted mt-1 d-block">Formatos recomendados: PNG, SVG transparentes.</span>
            </div>
          </div>
        </VCol>

        <!-- Favicon de la Tienda -->
        <VCol cols="12" md="6">
          <div class="d-flex align-start gap-4">
            <div class="border rounded-lg bg-light d-flex align-center justify-center border-dashed image-preview-box" style="width: 60px; height: 60px; overflow: hidden; flex-shrink: 0;">
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
                :disabled="isLoading"
                @change="(e) => emit('upload-favicon', e)"
              />
              <span class="text-xxs text-muted mt-1 d-block">Formatos recomendados: ICO, PNG de 32x32px.</span>
            </div>
          </div>
        </VCol>
      </VRow>

      <!-- Banner del Hero (Campaña Editorial) -->
      <VDivider class="my-6" />
      <div>
        <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-6 d-flex align-center gap-2">
          <VIcon icon="tabler-layout" size="18" class="text-primary" />
          Campaña Hero (Tienda)
        </h3>

        <div class="d-flex flex-column gap-4">
          <div>
            <div class="d-flex align-start gap-4">
              <div class="border rounded-lg bg-light d-flex align-center justify-center border-dashed image-preview-box" style="width: 120px; height: 70px; overflow: hidden;">
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
                  :disabled="isLoading"
                  @change="(e) => emit('upload-hero', e)"
                />
                <span class="text-xxs text-muted mt-1 d-block">Formatos: JPG, PNG, WEBP. Recomendada: 800x800px.</span>
              </div>
            </div>
          </div>

          <VTextField
            v-model="form.hero_tagline"
            label="Etiqueta Superior (Tagline)"
            placeholder="Ej: NUEVA COLECCIÓN"
            variant="outlined"
            density="comfortable"
            persistent-placeholder
            :disabled="isLoading"
          />

          <VTextField
            v-model="form.hero_title"
            label="Título de Campaña (Serif)"
            placeholder="Ej: YOUR NEW BOMB NUDES"
            variant="outlined"
            density="comfortable"
            persistent-placeholder
            :disabled="isLoading"
          />

          <VTextarea
            v-model="form.hero_subtitle"
            label="Subtítulo / Descripción"
            placeholder="Describe breves de la colección..."
            variant="outlined"
            density="comfortable"
            rows="3"
            persistent-placeholder
            :disabled="isLoading"
          />

          <VTextField
            v-model="form.hero_button_text"
            label="Texto del Botón de Acción"
            placeholder="Ej: COMPRAR AHORA"
            variant="outlined"
            density="comfortable"
            persistent-placeholder
            :disabled="isLoading"
          />
        </div>
      </div>

      <!-- Segunda Sección Híbrida -->
      <VDivider class="my-6" />
      <div>
        <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-6 d-flex align-center gap-2">
          <VIcon icon="tabler-layout" size="18" class="text-primary" />
          Segunda Sección Híbrida (Sección 2)
        </h3>

        <div class="d-flex flex-column gap-4">
          <div>
            <div class="d-flex align-start gap-4">
              <div class="border rounded-lg bg-light d-flex align-center justify-center border-dashed image-preview-box" style="width: 120px; height: 70px; overflow: hidden;">
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
                  :disabled="isLoading"
                  @change="(e) => emit('upload-section2', e)"
                />
                <span class="text-xxs text-muted mt-1 d-block">Formatos: JPG, PNG, WEBP. Recomendada: 800x800px.</span>
              </div>
            </div>
          </div>

          <VTextField
            v-model="form.section2_tagline"
            label="Etiqueta Superior (Tagline)"
            placeholder="Ej: PIEL RADIANTE"
            variant="outlined"
            density="comfortable"
            persistent-placeholder
            :disabled="isLoading"
          />

          <VTextField
            v-model="form.section2_title"
            label="Título de la Sección (Serif)"
            placeholder="Ej: MEET YOUR DONE-IN-ONE TINTED MOISTURIZER"
            variant="outlined"
            density="comfortable"
            persistent-placeholder
            :disabled="isLoading"
          />

          <VTextarea
            v-model="form.section2_subtitle"
            label="Subtítulo / Descripción"
            placeholder="Describe las virtudes de esta colección..."
            variant="outlined"
            density="comfortable"
            rows="3"
            persistent-placeholder
            :disabled="isLoading"
          />

          <VTextField
            v-model="form.section2_button_text"
            label="Texto del Botón de Acción"
            placeholder="Ej: DESCUBRIR TONOS"
            variant="outlined"
            density="comfortable"
            persistent-placeholder
            :disabled="isLoading"
          />
        </div>
      </div>

      <!-- Tercera Sección Híbrida -->
      <VDivider class="my-6" />
      <div>
        <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-6 d-flex align-center gap-2">
          <VIcon icon="tabler-layout" size="18" class="text-primary" />
          Tercera Sección Híbrida (Sección 3)
        </h3>

        <div class="d-flex flex-column gap-4">
          <div>
            <div class="d-flex align-start gap-4">
              <div class="border rounded-lg bg-light d-flex align-center justify-center border-dashed image-preview-box" style="width: 120px; height: 70px; overflow: hidden;">
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
                  :disabled="isLoading"
                  @change="(e) => emit('upload-section3', e)"
                />
                <span class="text-xxs text-muted mt-1 d-block">Formatos: JPG, PNG, WEBP. Recomendada: 800x800px.</span>
              </div>
            </div>
          </div>

          <VTextField
            v-model="form.section3_tagline"
            label="Etiqueta Superior (Tagline)"
            placeholder="Ej: EFECTO SOL"
            variant="outlined"
            density="comfortable"
            persistent-placeholder
            :disabled="isLoading"
          />

          <VTextField
            v-model="form.section3_title"
            label="Título de la Sección (Serif)"
            placeholder="Ej: SUN STALK'R SOUFFLÉ PRESSED MOUSSE BRONZER"
            variant="outlined"
            density="comfortable"
            persistent-placeholder
            :disabled="isLoading"
          />

          <VTextarea
            v-model="form.section3_subtitle"
            label="Subtítulo / Descripción"
            placeholder="Describe las virtudes de esta colección..."
            variant="outlined"
            density="comfortable"
            rows="3"
            persistent-placeholder
            :disabled="isLoading"
          />

          <VTextField
            v-model="form.section3_button_text"
            label="Texto del Botón de Acción"
            placeholder="Ej: COMPRAR BRONCEADOR"
            variant="outlined"
            density="comfortable"
            persistent-placeholder
            :disabled="isLoading"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.color-picker-premium {
  width: 32px;
  height: 32px;
  border: 1px solid rgba(0,0,0,0.15);
  border-radius: 6px;
  cursor: pointer;
  padding: 0;
  margin-right: 8px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.08);
  transition: transform 0.2s, box-shadow 0.2s;
}
.color-picker-premium:hover {
  transform: scale(1.1);
  box-shadow: 0 3px 8px rgba(0,0,0,0.15);
}
.bg-light {
  background-color: #f8f9fa;
}
.hover-card {
  transition: all 0.3s ease;
}
.hover-card:hover {
  box-shadow: 0 8px 24px 0 rgba(0,0,0,0.06) !important;
}
.image-preview-box {
  transition: transform 0.2s;
}
.image-preview-box:hover {
  transform: scale(1.03);
}
</style>
