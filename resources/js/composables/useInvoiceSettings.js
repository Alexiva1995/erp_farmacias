import { ref, computed } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'

/**
 * Composable que centraliza la lógica de configuración del módulo de facturas.
 * Expone estado reactivo, banderas de carga y las acciones de fetch/save.
 */
export function useInvoiceSettings() {
  const brandingStore = useBrandingStore()

  // --- Estado reactivo ---
  const isLoading   = ref(false)
  const isSaving    = ref(false)
  const saveTimeout = ref(null)

  const enableInvoices          = ref(true)
  const enableInvoiceLocations  = ref(true)

  // Snapshot para detectar cambios pendientes
  const savedSnapshot = ref({ enableInvoices: true, enableInvoiceLocations: true })

  // Computed: ¿hay cambios sin guardar?
  const hasPendingChanges = computed(() => {
    return (
      enableInvoices.value !== savedSnapshot.value.enableInvoices ||
      enableInvoiceLocations.value !== savedSnapshot.value.enableInvoiceLocations
    )
  })

  /**
   * Obtiene únicamente los campos de facturación aprovechando el filtro
   * ?only= que el GeneralSettingResource ya soporta.
   */
  const fetchSettings = async () => {
    isLoading.value = true
    try {
      // Solicitamos SOLO los 2 campos necesarios — evita traer >140 campos innecesarios
      const { data } = await axios.get('/general-settings', {
        params: { only: 'enable_invoices,enable_invoice_locations' },
      })
      const settings = data.data

      enableInvoices.value         = settings.enable_invoices         ?? true
      enableInvoiceLocations.value = settings.enable_invoice_locations ?? true

      // Actualizar snapshot
      savedSnapshot.value = {
        enableInvoices:         enableInvoices.value,
        enableInvoiceLocations: enableInvoiceLocations.value,
      }
    } catch {
      toast.error('Error al cargar la configuración de facturas.')
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Guarda los ajustes con debounce de 400 ms para evitar condiciones de
   * carrera si el usuario alterna múltiples switches rápidamente.
   */
  const saveSettings = () => {
    clearTimeout(saveTimeout.value)
    saveTimeout.value = setTimeout(async () => {
      isSaving.value = true
      try {
        await axios.post('/general-settings', {
          enable_invoices:          enableInvoices.value,
          enable_invoice_locations: enableInvoiceLocations.value,
        })

        // Sincronizar store de branding y snapshot local
        await brandingStore.fetchSettings(true)
        savedSnapshot.value = {
          enableInvoices:         enableInvoices.value,
          enableInvoiceLocations: enableInvoiceLocations.value,
        }

        toast.success('Configuración de facturación guardada.')
      } catch {
        toast.error('Error al guardar la configuración.')
      } finally {
        isSaving.value = false
      }
    }, 400)
  }

  return {
    // Estado
    isLoading,
    isSaving,
    enableInvoices,
    enableInvoiceLocations,
    hasPendingChanges,
    // Acciones
    fetchSettings,
    saveSettings,
  }
}
