// Composable para gestionar la carga, dirty checking y persistencia de configuración general
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'
import { toast, Swal } from '@/plugins/sweetalert'

export const useGeneralSettings = () => {
  const defaultSettings = {
    fiscal_mode: 'demo',
    enable_ce: false,
    blind_cash_closure: false,
    tpv_mode: 'complete',
  }

  const initialForm = ref({ ...defaultSettings })
  const form = ref({ ...defaultSettings })
  const isLoading = ref(true)
  const isSaving = ref(false)
  const hasError = ref(false)

  // Comparación reactiva para detección de cambios sin guardar
  const isDirty = computed(() => {
    return JSON.stringify(form.value) !== JSON.stringify(initialForm.value)
  })

  // Obtener parámetros de configuración general desde la API
  const fetchSettings = async () => {
    isLoading.value = true
    hasError.value = false
    try {
      const response = await axios.get('/general-settings', {
        params: {
          only: 'fiscal_mode,special_taxpayer_status,enable_ce,all_foreign_sales_spe,blind_cash_closure,tpv_mode',
        },
      })
      const settings = response.data?.data || {}

      const parsedData = {
        fiscal_mode: settings.fiscal_mode ?? 'demo',
        enable_ce: Boolean(settings.enable_ce || settings.special_taxpayer_status === 'activa'),
        blind_cash_closure: Boolean(settings.blind_cash_closure),
        tpv_mode: settings.tpv_mode ?? 'complete',
      }

      form.value = { ...parsedData }
      initialForm.value = { ...parsedData }
    } catch (error) {
      console.error('Error cargando configuración general:', error)
      hasError.value = true
      toast.error('Error al cargar la configuración general del sistema')
    } finally {
      isLoading.value = false
    }
  }

  // Reestablecer formulario al estado inicial
  const resetForm = () => {
    form.value = { ...initialForm.value }
  }

  // Persistir ajustes validados en backend
  const saveSettings = async () => {
    if (!isDirty.value || isSaving.value) return

    // Validar confirmación de seguridad si se activa producción fiscal
    if (
      initialForm.value.fiscal_mode === 'demo' &&
      form.value.fiscal_mode === 'activa'
    ) {
      const result = await Swal.fire({
        title: '¿Activar Modo Fiscal de Producción?',
        text: 'Las operaciones generarán documentos fiscales reales vinculados a la impresora fiscal SENIAT.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E20074',
        cancelButtonColor: '#808390',
        confirmButtonText: 'Sí, activar producción',
        cancelButtonText: 'Cancelar',
      })

      if (!result.isConfirmed) return
    }

    isSaving.value = true
    try {
      await axios.post('/general-settings', {
        fiscal_mode: form.value.fiscal_mode,
        enable_ce: form.value.enable_ce,
        special_taxpayer_status: form.value.enable_ce ? 'activa' : 'desactivada',
        all_foreign_sales_spe: form.value.enable_ce,
        blind_cash_closure: form.value.blind_cash_closure,
        tpv_mode: form.value.tpv_mode,
      })

      initialForm.value = { ...form.value }
      toast.success('Configuración actualizada exitosamente')
    } catch (error) {
      console.error('Error al guardar configuración general:', error)
      toast.error('Error al actualizar la configuración')
    } finally {
      isSaving.value = false
    }
  }

  return {
    form,
    initialForm,
    isLoading,
    isSaving,
    hasError,
    isDirty,
    fetchSettings,
    resetForm,
    saveSettings,
  }
}
