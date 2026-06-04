import { defineStore } from 'pinia'
import axios from '@axios'
import { ref } from 'vue'

export const useBrandingStore = defineStore('branding', () => {
  const settings = ref({
    app_name: 'ERP Farmacia',
    app_rif: '',
    app_logo: '',
    app_favicon: '',
    primary_color: '#E20074',
    secondary_color: '#7A0099',
    footer_text: 'Todos los derechos reservados de Tova',
    business_type: 'pharmacy',
  })

  const isLoading = ref(false)

  const fetchSettings = async () => {
    isLoading.value = true
    try {
      const response = await axios.get('/general-settings')
      settings.value = { ...settings.value, ...response.data.data }

      // Aplicar colores dinámicamente al DOM
      applyThemeColors()
    } catch (error) {
      console.error('Error fetching branding settings:', error)
    } finally {
      isLoading.value = false
    }
  }

  const applyThemeColors = () => {
    const root = document.documentElement

    if (settings.value.primary_color) {
      const rgb = hexToRgb(settings.value.primary_color)
      root.style.setProperty('--v-global-theme-primary', rgb)
      root.style.setProperty('--v-theme-primary', rgb)
      root.style.setProperty('--v-theme-primary-darken-1', rgb)
      root.style.setProperty('--v-theme-gradient-end', rgb)
    }

    if (settings.value.secondary_color) {
      const rgb = hexToRgb(settings.value.secondary_color)
      root.style.setProperty('--v-global-theme-secondary', rgb)
      root.style.setProperty('--v-theme-secondary', rgb)
      root.style.setProperty('--v-theme-gradient-start', rgb)
    } else if (settings.value.primary_color) {
      const rgb = hexToRgb(settings.value.primary_color)
      root.style.setProperty('--v-theme-gradient-start', rgb)
    }

    // Actualizar --brand-gradient con los colores reales del negocio
    // Esto es lo que usan todos los .header-gradient del sistema
    const start = settings.value.secondary_color || '#7A0099'
    const end   = settings.value.primary_color   || '#E20074'
    document.documentElement.style.setProperty(
      '--brand-gradient',
      `linear-gradient(135deg, ${start}, ${end})`
    )
  }

  // Helper: convierte HEX a formato RGB que usa Vuetify 3 en CSS vars
  const hexToRgb = (hex) => {
    const r = parseInt(hex.slice(1, 3), 16)
    const g = parseInt(hex.slice(3, 5), 16)
    const b = parseInt(hex.slice(5, 7), 16)
    return `${r}, ${g}, ${b}`
  }

  return {
    settings,
    isLoading,
    fetchSettings,
    hexToRgb,
  }
})
