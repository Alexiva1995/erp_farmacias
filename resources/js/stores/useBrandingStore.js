import { defineStore } from 'pinia'
import axios from '@axios'
import { ref, computed } from 'vue'

export const useBrandingStore = defineStore('branding', () => {
  const settings = ref({
    app_name: 'Tova - Cerebro Operativo',
    app_rif: '',
    address: '',
    fiscal_printer_serial: '',
    fiscal_machine_type: 'pnp',
    app_logo: '',
    app_favicon: '',
    primary_color: '#E20074',
    secondary_color: '#7A0099',
    tertiary_color: '#F5C842',
    footer_text: 'Todos los derechos reservados de Tova',
    business_type: localStorage.getItem('business_type') || 'pharmacy',
    default_currency: 'USD',
    enable_product_types: true,
    enabled_product_types: ['redundantes', 'col', 'iva', 'exento', 'novaventa', 'eliminados', 'pvp', 'ingredients', 'mixed'],
    enable_favorites:  true,
    enable_bulk_toggle_active: true,
    enable_variations: true,
    enable_merge:      false,
    enable_groups:     true,
    enabled_modules: ['pharmacy'],
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
    cyclic_inventory_scope: 'all',
    cyclic_inventory_daily_quota: 50,
    tpv_payment_methods: null,
    expense_mode: 'real',
    expense_auto_approve: false,
    enable_invoices: true,
    enable_cashea: false,
    cashea_mode: 'manual',
    cashea_installments_count: 3,
    cashea_installment_days: 14,
    enable_invoice_locations: true,
    enable_reservations: true,
    enable_dishes: false,
    enable_tables: false,
    enable_kds: false,
    enable_modifiers: false,
    enable_split_check: false,
    enable_tips: false,
    default_service_fee_pct: 10.00,
    enable_sub_recipes: false,
    enabled_telegram_views: ['configuration', 'generales', 'farmacia', 'restaurante', 'cosmeticos', 'alquileres'],
    is_tenant: false,
    is_master: true,
  })

  const isLoading = ref(false)
  const isLoaded = ref(false)
  const exchangeRates = ref([])
  let fetchPromise = null

  const isRestaurant = computed(() => settings.value.business_type === 'restaurant')

  const labels = computed(() => {
    const isRest = isRestaurant.value
    return {
      businessName: isRest ? 'Datos del Restaurante' : 'Datos de la Farmacia',
      catalog: isRest ? 'Menú y Platos' : 'Catálogo de Medicamentos',
      products: isRest ? 'Insumos y Mercancía' : 'Medicamentos y Productos',
      suppliers: isRest ? 'Proveedores de Insumos' : 'Droguerías y Proveedores',
      laboratories: isRest ? 'Marcas / Líneas' : 'Laboratorios',
    }
  })

  const fetchSettings = async (force = false) => {
    if (!force && isLoaded.value && settings.value.app_rif) {
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

        // Obtener configuración de arranque de módulos
        try {
          const bootstrapResponse = await axios.get('/public/bootstrap-config')
          if (bootstrapResponse.data?.data) {
            settings.value.business_type = bootstrapResponse.data.data.business_type
            settings.value.enabled_modules = bootstrapResponse.data.data.enabled_modules
            settings.value.is_tenant = Boolean(bootstrapResponse.data.data.is_tenant)
            settings.value.is_master = Boolean(bootstrapResponse.data.data.is_master)
            localStorage.setItem('business_type', bootstrapResponse.data.data.business_type)
          }
        } catch (e) {
          console.warn('Error fetching bootstrap config:', e)
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
    if (window.location.pathname.includes('/tova-store')) {
      return
    }

    const root = document.documentElement
    const primary = settings.value.secondary_color || '#E20074'
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

  const hexToRgb = (hex) => {
    const r = parseInt(hex.slice(1, 3), 16)
    const g = parseInt(hex.slice(3, 5), 16)
    const b = parseInt(hex.slice(5, 7), 16)
    return `${r}, ${g}, ${b}`
  }

  const updatePaymentMethods = (methods) => {
    settings.value.tpv_payment_methods = methods
  }

  const fetchExchangeRates = async () => {
    try {
      const ratesResponse = await axios.get('/public/exchange-rates')
      exchangeRates.value = ratesResponse.data
    } catch (e) {
      if (e?.response?.status !== 401) {
        console.warn('Error fetching exchange rates:', e)
      }
    }
  }

  /**
   * Aplicar una plantilla de vertical de negocio con 1 clic
   */
  const applyVerticalPreset = async (verticalKey, preserveIdentity = true) => {
    isLoading.value = true
    try {
      const response = await axios.post('/general-settings/apply-preset', {
        vertical: verticalKey,
        preserve_identity: preserveIdentity,
      })
      if (response.data?.data) {
        settings.value = { ...settings.value, ...response.data.data }
        localStorage.setItem('business_type', response.data.data.business_type)
        applyThemeColors()
      }
      return response.data
    } finally {
      isLoading.value = false
    }
  }

  return {
    settings,
    exchangeRates,
    isLoading,
    isLoaded,
    isRestaurant,
    labels,
    fetchSettings,
    fetchExchangeRates,
    updatePaymentMethods,
    applyVerticalPreset,
    hexToRgb,
  }
})
