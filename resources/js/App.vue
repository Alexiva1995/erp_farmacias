<script setup>
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import {
  initConfigStore,
  useConfigStore,
} from '@core/stores/config'
import { useBrandingStore } from '@/stores/useBrandingStore'
import { useRoute } from 'vue-router'
import { onMounted, watch } from 'vue'

// Rutas públicas que no requieren autenticación
const PUBLIC_PATHS = ['/tova-store', '/reservar', '/login', '/p/suppliers/upload']

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()
const brandingStore = useBrandingStore()
const route = useRoute()

onMounted(async () => {
  try {
    await brandingStore.fetchSettings()
    
    // Solo aplicar favicon y título del cliente si es la raíz (/) o una ruta pública
    const isPublicRoute = route.path === '/' || PUBLIC_PATHS.some(path => route.path && route.path.startsWith(path))
    
    if (isPublicRoute) {
      if (brandingStore.settings.app_name) {
        document.title = brandingStore.settings.app_name
      }
      if (brandingStore.settings.app_favicon) {
        const favicon = document.querySelector('link[rel="icon"]')
        if (favicon) {
          favicon.href = brandingStore.settings.app_favicon
        }
      }
    } else {
      // Forzar valores predeterminados de Tova en la administración interna
      document.title = 'Tova - Cerebro Operativo'
      const favicon = document.querySelector('link[rel="icon"]')
      if (favicon) {
        favicon.href = '/favicon.ico'
      }
    }
  } catch (error) {
    // Silenciar fallos
  }
})

// Observar cambios en el nombre de la app para actualizar el título únicamente en rutas públicas
watch(() => brandingStore.settings.app_name, (newName) => {
  const isPublicRoute = route.path === '/' || PUBLIC_PATHS.some(path => route.path && route.path.startsWith(path))
  if (newName && isPublicRoute) {
    document.title = newName
  }
})
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <VApp>
      <RouterView />

      <ScrollToTop />
    </VApp>
  </VLocaleProvider>
</template>
