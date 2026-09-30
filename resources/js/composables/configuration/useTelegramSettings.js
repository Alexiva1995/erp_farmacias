// Composable para administrar la visibilidad y activación de submódulos de Telegram por negocio
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'

export const useTelegramSettings = () => {
  const brandingStore = useBrandingStore()

  // Submódulos activos por defecto
  const defaultViews = ['configuration', 'generales', 'farmacia', 'restaurante', 'cosmeticos', 'alquileres']
  const enabledTelegramViews = ref([...defaultViews])
  const initialViews = ref([...defaultViews])

  const isLoading = ref(true)
  const isSaving = ref(false)
  const hasError = ref(false)
  const errorMessage = ref('')

  // Catálogo exhaustivo de submenús y secciones de Telegram por negocio
  const availableTelegramViews = [
    {
      key: 'configuration',
      title: 'Credenciales & Webhook',
      description: 'Configuración del BotFather, token secreto, IDs de superusuario y diagnóstico de Webhook.',
      icon: 'tabler-settings-code',
      category: 'Infraestructura',
      route: 'telegram-configuration',
    },
    {
      key: 'generales',
      title: 'Comandos Generales',
      description: 'Alertas globales, resumen operativo diario, estado de sucursales y notificaciones generales.',
      icon: 'tabler-apps',
      category: 'General',
      route: 'telegram-generales',
    },
    {
      key: 'farmacia',
      title: 'Módulo Farmacia',
      description: 'Alertas de stock mínimo, vencimientos próximos, cierres de caja y consultas de fármacos.',
      icon: 'tabler-pill',
      category: 'Negocio',
      route: 'telegram-farmacia',
    },
    {
      key: 'restaurante',
      title: 'Módulo Restaurante',
      description: 'Gestión remota de comandas, reportes de turno, alerta de platos agotados y mesas.',
      icon: 'tabler-tools-kitchen-2',
      category: 'Negocio',
      route: 'telegram-restaurante',
    },
    {
      key: 'cosmeticos',
      title: 'Módulo Cosméticos',
      description: 'Catálogo rápido, stock de belleza, promociones vigentes y alertas de reposición.',
      icon: 'tabler-sparkles',
      category: 'Negocio',
      route: 'telegram-cosmeticos',
    },
    {
      key: 'alquileres',
      title: 'Módulo Alquileres',
      description: 'Disponibilidad de espacios deportivos, reservas confirmadas y recordatorios de pago.',
      icon: 'tabler-calendar-time',
      category: 'Negocio',
      route: 'telegram-alquileres',
    },
  ]

  // Métricas computadas
  const totalCount = computed(() => availableTelegramViews.length)
  const activeCount = computed(() => enabledTelegramViews.value.length)
  const activePercentage = computed(() => {
    if (totalCount.value === 0) return 0
    return Math.round((activeCount.value / totalCount.value) * 100)
  })

  const allEnabled = computed(() => activeCount.value === totalCount.value)
  const noneEnabled = computed(() => activeCount.value === 0)

  // Detección de cambios no guardados (Dirty State)
  const isDirty = computed(() => {
    const current = [...enabledTelegramViews.value].sort().join(',')
    const initial = [...initialViews.value].sort().join(',')
    return current !== initial
  })

  // Carga de configuración desde la API
  const fetchSettings = async () => {
    isLoading.value = true
    hasError.value = false
    errorMessage.value = ''

    try {
      const response = await axios.get('/general-settings', {
        params: { only: 'enabled_telegram_views' },
      })

      const settings = response.data?.data || {}
      if (Array.isArray(settings.enabled_telegram_views)) {
        enabledTelegramViews.value = [...settings.enabled_telegram_views]
        initialViews.value = [...settings.enabled_telegram_views]
      }
    } catch (error) {
      console.error('Error cargando configuración de Telegram:', error)
      hasError.value = true
      errorMessage.value = 'No se pudo sincronizar la configuración de Telegram. Verifique la conexión con el servidor.'
      toast.error('Error al cargar configuración de Telegram')
    } finally {
      isLoading.value = false
    }
  }

  // Alternar habilitación de una sección
  const toggleTelegramView = (key) => {
    if (isSaving.value || isLoading.value) return

    const updated = [...enabledTelegramViews.value]
    const index = updated.indexOf(key)

    if (index > -1) {
      updated.splice(index, 1)
    } else {
      updated.push(key)
    }

    enabledTelegramViews.value = updated
  }

  // Habilitar o deshabilitar todos los submenús
  const setAllViews = async (enable) => {
    if (isSaving.value || isLoading.value) return

    if (!enable) {
      const confirmed = await confirmDialog({
        title: '¿Desactivar todos los submenús de Telegram?',
        text: 'Se ocultarán todas las secciones y comandos del bot de Telegram en el ERP para todos los usuarios.',
        icon: 'warning',
        confirmButtonText: 'Sí, desactivar todos',
        cancelButtonText: 'Cancelar',
      })

      if (!confirmed) return
    }

    enabledTelegramViews.value = enable ? availableTelegramViews.map((v) => v.key) : []
  }

  // Descartar cambios
  const resetSettings = () => {
    enabledTelegramViews.value = [...initialViews.value]
  }

  // Persistir cambios en backend
  const saveSettings = async () => {
    if (!isDirty.value || isSaving.value) return

    isSaving.value = true
    try {
      await axios.post('/general-settings', {
        enabled_telegram_views: enabledTelegramViews.value,
      })

      initialViews.value = [...enabledTelegramViews.value]
      await brandingStore.fetchSettings(true)
      toast.success('Configuración de módulos de Telegram guardada correctamente')
    } catch (error) {
      console.error('Error al guardar configuración de Telegram:', error)
      toast.error('Error al persistir la configuración de Telegram')
    } finally {
      isSaving.value = false
    }
  }

  return {
    enabledTelegramViews,
    availableTelegramViews,
    isLoading,
    isSaving,
    hasError,
    errorMessage,
    isDirty,
    totalCount,
    activeCount,
    activePercentage,
    allEnabled,
    noneEnabled,
    fetchSettings,
    toggleTelegramView,
    setAllViews,
    resetSettings,
    saveSettings,
  }
}
