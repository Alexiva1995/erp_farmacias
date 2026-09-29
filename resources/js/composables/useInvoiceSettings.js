import { ref, computed } from 'vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'
import { useAbility } from '@casl/vue'

/**
 * Composable que centraliza la lógica de configuración del módulo de facturas.
 * Expone estado reactivo, banderas de carga y las acciones de fetch/save con soporte CASL y confirmación.
 */
export function useInvoiceSettings() {
  const brandingStore = useBrandingStore()
  const ability = useAbility()

  // --- Estado reactivo ---
  const isLoading = ref(false)
  const isSaving = ref(false)
  const saveTimeout = ref(null)

  const enableInvoices = ref(true)
  const enableInvoiceLocations = ref(true)

  // Snapshot para detectar cambios pendientes y revertir en caso de error
  const savedSnapshot = ref({ enableInvoices: true, enableInvoiceLocations: true })

  // Permiso CASL para modificar configuraciones
  const canManageSettings = computed(() => {
    return ability.can('manage', 'settings') || ability.can('update', 'settings') || ability.can('manage', 'all')
  })

  // Computed: ¿hay cambios sin guardar?
  const hasPendingChanges = computed(() => {
    return (
      enableInvoices.value !== savedSnapshot.value.enableInvoices ||
      enableInvoiceLocations.value !== savedSnapshot.value.enableInvoiceLocations
    )
  })

  /**
   * Obtiene únicamente los campos de facturación aprovechando el filtro
   * ?only= que el GeneralSettingResource soporta.
   */
  const fetchSettings = async () => {
    isLoading.value = true
    try {
      const { data } = await axios.get('/general-settings', {
        params: { only: 'enable_invoices,enable_invoice_locations' },
      })
      const settings = data.data

      enableInvoices.value = settings.enable_invoices ?? true
      enableInvoiceLocations.value = settings.enable_invoice_locations ?? true

      savedSnapshot.value = {
        enableInvoices: enableInvoices.value,
        enableInvoiceLocations: enableInvoiceLocations.value,
      }
    } catch {
      toast.error('Error al cargar la configuración de facturas.')
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Ejecuta la persistencia en el backend.
   */
  const executeSave = async () => {
    isSaving.value = true
    try {
      await axios.post('/general-settings', {
        enable_invoices: enableInvoices.value,
        enable_invoice_locations: enableInvoiceLocations.value,
      })

      // Sincronizar store de branding y snapshot local
      await brandingStore.fetchSettings(true)
      savedSnapshot.value = {
        enableInvoices: enableInvoices.value,
        enableInvoiceLocations: enableInvoiceLocations.value,
      }

      toast.success('Configuración de facturación guardada.')
    } catch {
      // Revertir a snapshot previo en caso de fallo
      enableInvoices.value = savedSnapshot.value.enableInvoices
      enableInvoiceLocations.value = savedSnapshot.value.enableInvoiceLocations
      toast.error('Error al guardar la configuración.')
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Maneja el cambio de valores aplicando validación de permisos,
   * diálogo de confirmación en acciones críticas y debounce.
   */
  const handleSettingChange = async (key, newValue) => {
    if (!canManageSettings.value) {
      toast.error('No posees permisos para modificar la configuración.')
      // Revertir cambio en interfaz
      if (key === 'enableInvoices') {
        enableInvoices.value = savedSnapshot.value.enableInvoices
      } else if (key === 'enableInvoiceLocations') {
        enableInvoiceLocations.value = savedSnapshot.value.enableInvoiceLocations
      }
      return
    }

    // Confirmación modal si se desactiva el módulo completo
    if (key === 'enableInvoices' && !newValue) {
      const confirmed = await confirmDialog({
        title: '¿Desactivar Módulo de Facturas?',
        text: 'Esta acción ocultará el módulo de facturación para todos los usuarios del sistema.',
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar',
        icon: 'warning',
      })

      if (!confirmed) {
        enableInvoices.value = true
        return
      }
    }

    clearTimeout(saveTimeout.value)
    saveTimeout.value = setTimeout(executeSave, 400)
  }

  return {
    // Estado
    isLoading,
    isSaving,
    enableInvoices,
    enableInvoiceLocations,
    hasPendingChanges,
    canManageSettings,
    // Acciones
    fetchSettings,
    handleSettingChange,
  }
}

