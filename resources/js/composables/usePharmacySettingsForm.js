import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useBrandingStore } from '@/stores/useBrandingStore'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

export function usePharmacySettingsForm() {
  const brandingStore = useBrandingStore()
  const isLoading = ref(false)
  const isPageLoading = ref(true)

  const initialValues = {
    app_name: '',
    app_rif: '',
    address: '',
  }

  const form = reactive({ ...initialValues })
  const initialSnapshot = ref('')

  const logoFile = ref(null)
  const logoPreview = ref('')
  const currentSavedLogo = ref('')

  // Evaluar estado de cambios no guardados
  const isDirty = computed(() => {
    const formState = JSON.stringify(form)
    return formState !== initialSnapshot.value || logoFile.value !== null
  })

  // Manejar selección de imagen
  const handleLogoSelect = (file) => {
    if (!file) return

    if (!file.type.startsWith('image/')) {
      toast.error('Formato no válido. Ingrese una imagen PNG, JPG, WEBP o SVG.')
      return
    }

    if (file.size > 5 * 1024 * 1024) {
      toast.error('El tamaño de la imagen no debe superar los 5MB.')
      return
    }

    if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(logoPreview.value)
    }

    logoFile.value = file
    logoPreview.value = URL.createObjectURL(file)
  }

  // Quitar logotipo actual
  const removeLogo = () => {
    if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(logoPreview.value)
    }
    logoFile.value = null
    logoPreview.value = ''
  }

  // Restablecer formulario a valores iniciales
  const resetForm = () => {
    try {
      const parsed = JSON.parse(initialSnapshot.value)
      form.app_name = parsed.app_name || ''
      form.app_rif = parsed.app_rif || ''
      form.address = parsed.address || ''
    } catch {
      form.app_name = ''
      form.app_rif = ''
      form.address = ''
    }
    removeLogo()
    logoPreview.value = currentSavedLogo.value
  }

  // Cargar configuración desde el backend
  const loadSettings = async () => {
    isPageLoading.value = true
    try {
      await brandingStore.fetchSettings(true)
      form.app_name = brandingStore.settings.app_name || ''
      form.app_rif = brandingStore.settings.app_rif || ''
      form.address = brandingStore.settings.address || ''
      currentSavedLogo.value = brandingStore.settings.app_logo || ''
      logoPreview.value = currentSavedLogo.value
      initialSnapshot.value = JSON.stringify({
        app_name: form.app_name,
        app_rif: form.app_rif,
        address: form.address,
      })
    } catch (error) {
      console.error('Error al cargar configuración:', error)
      toast.error('No se pudo cargar la configuración de la farmacia.')
    } finally {
      isPageLoading.value = false
    }
  }

  // Persistir cambios en el backend
  const saveSettings = async () => {
    if (!form.app_name || !form.app_name.trim()) {
      toast.error('El nombre comercial de la farmacia es obligatorio.')
      return
    }

    isLoading.value = true
    const formData = new FormData()
    formData.append('app_name', form.app_name.trim())
    formData.append('app_rif', form.app_rif ? form.app_rif.trim() : '')
    formData.append('address', form.address ? form.address.trim() : '')

    if (logoFile.value) {
      formData.append('app_logo', logoFile.value)
    }

    try {
      await axios.post('/general-settings', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      toast.success('Configuración de farmacia guardada exitosamente.')
      await loadSettings()
      logoFile.value = null
    } catch (error) {
      console.error('Error al guardar configuración:', error)
      const errorMsg = error?.response?.data?.message || 'Error al persistir los cambios.'
      toast.error(errorMsg)
    } finally {
      isLoading.value = false
    }
  }

  onMounted(() => {
    loadSettings()
  })

  onUnmounted(() => {
    if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(logoPreview.value)
    }
  })

  return {
    form,
    logoFile,
    logoPreview,
    isLoading,
    isPageLoading,
    isDirty,
    handleLogoSelect,
    removeLogo,
    resetForm,
    saveSettings,
  }
}
