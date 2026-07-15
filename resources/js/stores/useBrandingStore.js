import { defineStore } from 'pinia'
import axios from '@axios'
import { ref } from 'vue'

export const useBrandingStore = defineStore('branding', () => {
  const settings = ref({
    app_name: 'Tova - Cerebro Operativo',
    app_rif: '',
    app_logo: '',
    app_favicon: '',
    primary_color: '#E20074',
    secondary_color: '#7A0099',
    tertiary_color: '#F5C842',
    footer_text: 'Todos los derechos reservados de Tova',
    business_type: 'pharmacy',
    ecommerce_menu: [],
    hero_title: 'YOUR NEW BOMB NUDES',
    hero_subtitle: 'Tonos sofisticados, texturas sedosas y fórmulas de alta gama diseñadas para realzar tu belleza natural con un acabado impecable de pasarela.',
    hero_tagline: 'NUEVA COLECCIÓN',
    hero_image: null,
    hero_button_text: 'COMPRAR AHORA',
    section2_title: 'MEET YOUR DONE-IN-ONE TINTED MOISTURIZER',
    section2_subtitle: 'Nuestra fórmula ultraligera que unifica el tono de la piel, hidrata profundamente y aporta una luminosidad natural y fresca durante todo el día. Disponible en 25 tonos flexibles.',
    section2_tagline: 'PIEL RADIANTE',
    section2_image: null,
    section2_button_text: 'DESCUBRIR TONOS',
    section3_title: "SUN STALK'R SOUFFLÉ PRESSED MOUSSE BRONZER",
    section3_subtitle: 'El bronceador definitivo que aporta calidez instantánea a tu rostro con un acabado sedoso y de larga duración. Su textura mousse prensada se funde perfectamente sobre la piel sin esfuerzo.',
    section3_tagline: 'EFECTO SOL',
    section3_image: null,
    section3_button_text: 'COMPRAR BRONCEADOR',
    cyclic_inventory_mode: 'double',
    cyclic_inventory_barcode_required: true,
  })

  const isLoading = ref(false)
  const isLoaded = ref(false)
  const exchangeRates = ref([])
  let fetchPromise = null

  const fetchSettings = async () => {
    if (isLoaded.value && settings.value.app_rif) {
      return
    }
    
    if (fetchPromise) {
      return fetchPromise
    }

    fetchPromise = (async () => {
      isLoading.value = true
      try {
        const response = await axios.get('/public/general-settings')
        settings.value = { ...settings.value, ...response.data.data }
        if (response.data?.data?.business_type) {
          localStorage.setItem('business_type', response.data.data.business_type)
        }

        // Obtener tasas de cambio desde el API público
        try {
          const ratesResponse = await axios.get('/public/exchange-rates')
          exchangeRates.value = ratesResponse.data
        } catch (e) {
          console.warn('Error fetching exchange rates in branding store:', e)
        }

        // Aplicar colores dinámicamente al DOM
        applyThemeColors()
        isLoaded.value = true
      } catch (error) {
        // Silenciamos el 401 (Unauthorized) ya que es normal en accesos públicos / antes del login
        if (error?.response?.status !== 401) {
          console.error('Error fetching branding settings:', error)
        }
      } finally {
        isLoading.value = false
        fetchPromise = null
      }
    })()

    return fetchPromise
  }

  const applyThemeColors = () => {
    // Si estamos en la página del e-commerce público, evitamos alterar el tema del ERP
    if (window.location.pathname.includes('/tova-store')) {
      return
    }

    const root = document.documentElement

    // Para el ERP usamos colores de marca que mantengan legibilidad
    // Si el color secundario (oscuro) o primario original existe, los aplicamos al ERP de forma legible
    const primary = settings.value.secondary_color || '#E20074' // Usamos el color secundario como primario en el ERP para evitar fondos claros invisibles
    const secondary = settings.value.tertiary_color || '#7A0099'

    if (primary) {
      const rgb = hexToRgb(primary)
      root.style.setProperty('--v-global-theme-primary', rgb)
      root.style.setProperty('--v-theme-primary', rgb)
      root.style.setProperty('--v-theme-primary-darken-1', rgb)
      root.style.setProperty('--v-theme-gradient-end', rgb)
    }

    if (secondary) {
      const rgb = hexToRgb(secondary)
      root.style.setProperty('--v-global-theme-secondary', rgb)
      root.style.setProperty('--v-theme-secondary', rgb)
      root.style.setProperty('--v-theme-gradient-start', rgb)
    }

    const start = secondary || '#7A0099'
    const end   = primary   || '#E20074'
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
    exchangeRates,
    isLoading,
    isLoaded,
    fetchSettings,
    hexToRgb,
  }
})
