<script setup>
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import {
  initConfigStore,
  useConfigStore,
} from '@core/stores/config'
import { useBrandingStore } from '@/stores/useBrandingStore'
import { onMounted, watch } from 'vue'

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
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <VApp>
      <RouterView />

      <ScrollToTop />
    </VApp>
  </VLocaleProvider>
</template>
