<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert"
import { useBrandingStore } from "@/stores/useBrandingStore"
import ProductSettingsCard from '@/components/configuration/ProductSettingsCard.vue'
import ProductTypesCard from '@/components/configuration/ProductTypesCard.vue'
import ProductFormFieldsCard from '@/components/configuration/ProductFormFieldsCard.vue'

const brandingStore = useBrandingStore()

// Estados reactivos de la UI
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')

// Campos de configuración
const enableProductTypes  = ref(true)
const enabledProductTypes = ref([])
const enableFavorites     = ref(true)
const enableVariations    = ref(true)
const enableMerge         = ref(false)
const enableGroups        = ref(true)
const enableExpirations   = ref(true)
const enableBrandGroups   = ref(false)
const enableDonations     = ref(true)
const enableLocations     = ref(true)
const enableOptimization  = ref(true)
const enableDishes        = ref(true)
const traceabilityMode    = ref('units')
const productFormFields   = ref([])

// Carga inicial optimizada mediante filtro 'only'
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''

  const fields = [
    'enable_product_types',
    'enabled_product_types',
    'enable_favorites',
    'enable_variations',
    'enable_merge',
    'enable_groups',
    'enable_expirations',
    'enable_brand_groups',
    'enable_donations',
    'enable_locations',
    'enable_optimization',
    'enable_dishes',
    'traceability_mode',
    'product_form_fields'
  ].join(',')

  try {
    const response = await axios.get('/general-settings', {
      params: { only: fields }
    })
    const settings = response.data.data
    if (settings) {
      enableProductTypes.value  = settings.enable_product_types  ?? true
      enabledProductTypes.value = settings.enabled_product_types ?? []
      enableFavorites.value     = settings.enable_favorites      ?? true
      enableVariations.value    = settings.enable_variations     ?? true
      enableMerge.value         = settings.enable_merge          ?? false
      enableGroups.value        = settings.enable_groups         ?? true
      enableExpirations.value   = settings.enable_expirations    ?? true
      enableBrandGroups.value   = settings.enable_brand_groups   ?? false
      enableDonations.value     = settings.enable_donations      ?? true
      enableLocations.value     = settings.enable_locations      ?? true
      enableOptimization.value  = settings.enable_optimization   ?? true
      enableDishes.value        = settings.enable_dishes         ?? true
      traceabilityMode.value    = settings.traceability_mode     ?? 'units'
      productFormFields.value   = settings.product_form_fields   ?? []
    }
  } catch (error) {
    console.error("Error cargando configuración de productos:", error)
    hasError.value = true
    errorMessage.value = "No se pudo cargar la configuración de productos. Verifique su conexión e intente de nuevo."
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

// Control de guardado con debounce
let saveDebounceTimer = null
const updateSettings = () => {
  if (isLoading.value) return
  isSaving.value = true
  if (saveDebounceTimer) clearTimeout(saveDebounceTimer)

  saveDebounceTimer = setTimeout(async () => {
    try {
      await axios.post('/general-settings', {
        enable_product_types:  enableProductTypes.value,
        enabled_product_types: enabledProductTypes.value,
        enable_favorites:      enableFavorites.value,
        enable_variations:     enableVariations.value,
        enable_merge:          enableMerge.value,
        enable_groups:         enableGroups.value,
        enable_expirations:    enableExpirations.value,
        enable_brand_groups:   enableBrandGroups.value,
        enable_donations:      enableDonations.value,
        enable_locations:      enableLocations.value,
        enable_optimization:   enableOptimization.value,
        enable_dishes:         enableDishes.value,
        traceability_mode:     traceabilityMode.value,
        product_form_fields:   productFormFields.value,
      })
      await brandingStore.fetchSettings()
      toast.success("Configuración de productos actualizada exitosamente")
    } catch (error) {
      console.error("Error al guardar configuración de productos:", error)
      toast.error("Error al actualizar la configuración")
    } finally {
      isSaving.value = false
    }
  }, 300)
}

onMounted(fetchSettings)
</script>

<template>
  <div class="position-relative">
    <!-- Barra de procesamiento superior -->
    <VProgressLinear
      v-if="isSaving"
      color="primary"
      indeterminate
      height="4"
      class="position-absolute top-0 left-0 right-0"
      style="z-index: 99;"
    />

    <!-- Banner de Error con Reintento -->
    <VAlert
      v-if="hasError"
      type="error"
      variant="tonal"
      class="mb-6 rounded-lg"
      closable
    >
      <template #title> Error de Carga </template>
      {{ errorMessage }}
      <template #append>
        <VBtn color="error" variant="text" size="small" @click="fetchSettings">
          Reintentar
        </VBtn>
      </template>
    </VAlert>

    <!-- Skeletons durante Carga Inicial -->
    <div v-if="isLoading" class="d-flex flex-column gap-6">
      <VCard class="mb-6 rounded-lg border shadow-sm">
        <VCardItem class="py-5">
          <VSkeletonLoader type="article, grid" height="150" />
        </VCardItem>
      </VCard>
      <VCard class="mb-6 rounded-lg border shadow-sm">
        <VCardItem class="py-5">
          <VSkeletonLoader type="article, grid" height="150" />
        </VCardItem>
      </VCard>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div v-else-if="!hasError">
      <!-- Configuración de Características de Productos -->
      <ProductSettingsCard
        v-model:enable-product-types="enableProductTypes"
        v-model:enable-favorites="enableFavorites"
        v-model:enable-variations="enableVariations"
        v-model:enable-merge="enableMerge"
        v-model:enable-groups="enableGroups"
        v-model:enable-expirations="enableExpirations"
        v-model:enable-donations="enableDonations"
        v-model:enable-brand-groups="enableBrandGroups"
        v-model:enable-locations="enableLocations"
        v-model:enable-optimization="enableOptimization"
        v-model:enable-dishes="enableDishes"
        v-model:traceability-mode="traceabilityMode"
        :is-saving="isSaving"
        @change="updateSettings"
      />

      <!-- Tipos de Productos Habilitados -->
      <ProductTypesCard
        v-if="enableProductTypes"
        v-model:enabled-product-types="enabledProductTypes"
        :is-saving="isSaving"
        @change="updateSettings"
      />

      <!-- Campos de Formulario de Productos -->
      <ProductFormFieldsCard
        v-model:product-form-fields="productFormFields"
        :is-saving="isSaving"
        @change="updateSettings"
      />
    </div>
  </div>
</template>
