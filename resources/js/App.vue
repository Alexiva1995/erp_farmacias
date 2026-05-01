<script setup>
import { useTheme } from 'vuetify'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import {
  initConfigStore,
  useConfigStore,
} from '@core/stores/config'
import { useBrandingStore } from '@/stores/useBrandingStore'
import { onMounted, watch } from 'vue'
import { hexToRgb } from '@core/utils/colorConverter'

const { global } = useTheme()

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()
const brandingStore = useBrandingStore()

onMounted(async () => {
  await brandingStore.fetchSettings()
  
  // Actualizar título y favicon
  if (brandingStore.settings.app_name) {
    document.title = brandingStore.settings.app_name
  }
  
  if (brandingStore.settings.app_favicon) {
    const favicon = document.querySelector('link[rel="icon"]')
    if (favicon) {
      favicon.href = brandingStore.settings.app_favicon
    }
  }
})

// Observar cambios en el nombre de la app para actualizar el título
watch(() => brandingStore.settings.app_name, (newName) => {
  if (newName) document.title = newName
})

// Inyectar estilos dinámicos globales para asegurar que los colores de branding se apliquen correctamente
watch(() => [brandingStore.settings.primary_color, brandingStore.settings.secondary_color], ([newPrimary, newSecondary]) => {
  if (newPrimary) {
    const primaryRgb = brandingStore.hexToRgb(newPrimary)
    const secondaryRgb = newSecondary ? brandingStore.hexToRgb(newSecondary) : '130, 134, 139'
    
    let styleTag = document.getElementById('dynamic-branding-styles')
    if (!styleTag) {
      styleTag = document.createElement('style')
      styleTag.id = 'dynamic-branding-styles'
      document.head.appendChild(styleTag)
    }

    styleTag.innerHTML = `
      :root, .v-application, .v-theme--light, .v-theme--dark {
        --v-theme-primary: ${primaryRgb} !important;
        --v-global-theme-primary: ${primaryRgb} !important;
        --v-theme-secondary: ${secondaryRgb} !important;
        --v-global-theme-secondary: ${secondaryRgb} !important;
        
        /* Color de texto sobre primario (blanco por defecto para morado/azul) */
        --v-theme-on-primary: 255, 255, 255 !important;
      }
      
      /* Forzar colores en elementos que usan la variable directamente */
      .text-primary { color: rgb(${primaryRgb}) !important; }
      .bg-primary { background-color: rgb(${primaryRgb}) !important; }
    `
  }
}, { immediate: true })
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <!-- ℹ️ This is required to set the background color of active nav link based on currently active global theme's primary -->
    <VApp :style="{
      '--v-global-theme-primary': brandingStore.settings.primary_color ? brandingStore.hexToRgb(brandingStore.settings.primary_color) : hexToRgb(global.current.value.colors.primary),
      '--v-theme-primary': brandingStore.settings.primary_color ? brandingStore.hexToRgb(brandingStore.settings.primary_color) : hexToRgb(global.current.value.colors.primary),
    }">
      <RouterView />

      <ScrollToTop />
    </VApp>
  </VLocaleProvider>
</template>
