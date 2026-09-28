// Composable para gestionar la configuración y visibilidad de los módulos del CRM
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'

export const useCrmSettings = () => {
  const brandingStore = useBrandingStore()

  const defaultViews = ['clients', 'chronic_clients', 'companies', 'doctors', 'lottery']
  const enabledCrmViews = ref([...defaultViews])
  const initialViews = ref([...defaultViews])

  const isLoading = ref(true)
  const isSaving = ref(false)
  const hasError = ref(false)
  const errorMessage = ref('')

  // Catálogo completo de vistas disponibles del CRM
  const availableCrmViews = [
    {
      key: 'clients',
      title: 'Clientes',
      description: 'Gestión de ficha de clientes, historial de compras y cuentas corrientes.',
      icon: 'tabler-users',
      category: 'General',
    },
    {
      key: 'chronic_clients',
      title: 'Pacientes Crónicos',
      description: 'Fidelización de pacientes crónicos, cuotas periódicas y clasificación.',
      icon: 'tabler-heart-handshake',
      category: 'Fidelización',
    },
    {
      key: 'companies',
      title: 'Convenios / Empresas',
      description: 'Acuerdos corporativos institucionales y descuentos empresariales en TPV.',
      icon: 'tabler-building',
      category: 'Corporativo',
    },
    {
      key: 'doctors',
      title: 'Médicos Tratantes',
      description: 'Registro de médicos, recetas prescritas y comisiones por consulta.',
      icon: 'tabler-stethoscope',
      category: 'Salud',
    },
    {
      key: 'lottery',
      title: 'Sorteos y Lotería',
      description: 'Campañas de fidelización mediante tickets, premios y rifas promocionales.',
      icon: 'tabler-ticket',
      category: 'Promociones',
    },
  ]

  // Métricas reactivas
  const totalCount = computed(() => availableCrmViews.length)
  const activeCount = computed(() => enabledCrmViews.value.length)
  const activePercentage = computed(() => {
    if (totalCount.value === 0) return 0
    return Math.round((activeCount.value / totalCount.value) * 100)
  })

  const allEnabled = computed(() => activeCount.value === totalCount.value)
  const noneEnabled = computed(() => activeCount.value === 0)

  // Comparación reactiva para detección de cambios sin guardar (Dirty State)
  const isDirty = computed(() => {
    const current = [...enabledCrmViews.value].sort().join(',')
    const initial = [...initialViews.value].sort().join(',')
    return current !== initial
  })

  // Cargar configuración desde backend
  const fetchSettings = async () => {
    isLoading.value = true
    hasError.value = false
    errorMessage.value = ''

    try {
      const response = await axios.get('/general-settings', {
        params: { only: 'enabled_crm_views' },
      })

      const settings = response.data?.data || {}
      if (Array.isArray(settings.enabled_crm_views)) {
        enabledCrmViews.value = [...settings.enabled_crm_views]
        initialViews.value = [...settings.enabled_crm_views]
      }
    } catch (error) {
      console.error('Error cargando configuración de vistas CRM:', error)
      hasError.value = true
      errorMessage.value = 'No se pudo cargar la configuración de vistas del CRM. Verifique su conexión.'
      toast.error('Error al cargar la configuración')
    } finally {
      isLoading.value = false
    }
  }

  // Alternar selección de una vista
  const toggleCrmView = (key) => {
    if (isSaving.value || isLoading.value) return

    const updated = [...enabledCrmViews.value]
    const index = updated.indexOf(key)

    if (index > -1) {
      updated.splice(index, 1)
    } else {
      updated.push(key)
    }

    enabledCrmViews.value = updated
  }

  // Habilitar o deshabilitar todas las vistas con confirmación
  const setAllViews = async (enable) => {
    if (isSaving.value || isLoading.value) return

    if (!enable) {
      const confirmed = await confirmDialog({
        title: '¿Desactivar todas las vistas del CRM?',
        text: 'Se ocultarán todos los accesos directos al CRM en la barra de navegación para todos los usuarios.',
        icon: 'warning',
        confirmButtonText: 'Sí, desactivar todas',
        cancelButtonText: 'Cancelar',
      })

      if (!confirmed) return
    }

    enabledCrmViews.value = enable ? availableCrmViews.map((v) => v.key) : []
  }

  // Reestablecer a estado inicial guardado
  const resetSettings = () => {
    enabledCrmViews.value = [...initialViews.value]
  }

  // Persistir ajustes en el servidor
  const saveSettings = async () => {
    if (!isDirty.value || isSaving.value) return

    isSaving.value = true
    try {
      await axios.post('/general-settings', {
        enabled_crm_views: enabledCrmViews.value,
      })

      initialViews.value = [...enabledCrmViews.value]
      await brandingStore.fetchSettings()
      toast.success('Configuración de vistas del CRM guardada exitosamente')
    } catch (error) {
      console.error('Error al guardar configuración del CRM:', error)
      toast.error('Error al actualizar la configuración del CRM')
    } finally {
      isSaving.value = false
    }
  }

  return {
    enabledCrmViews,
    availableCrmViews,
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
    toggleCrmView,
    setAllViews,
    resetSettings,
    saveSettings,
  }
}
