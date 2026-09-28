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
    fiscal_printer_serial: '',
    default_currency: 'COP',
  }

  const form = reactive({ ...initialValues })
  const initialSnapshot = ref('')

  const logoFile = ref(null)
  const logoPreview = ref('')
  const currentSavedLogo = ref('')

  const faviconFile = ref(null)
  const faviconPreview = ref('')
  const currentSavedFavicon = ref('')

  const signatureStampFile = ref(null)
  const signatureStampPreview = ref('')
  const currentSavedSignatureStamp = ref('')

  // Evaluar estado de cambios no guardados
  const isDirty = computed(() => {
    const formState = JSON.stringify(form)
    return (
      formState !== initialSnapshot.value ||
      logoFile.value !== null ||
      faviconFile.value !== null ||
      signatureStampFile.value !== null
    )
  })

  // Manejar selección de logotipo
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

  // Manejar selección de favicon
  const handleFaviconSelect = (file) => {
    if (!file) return

    if (!file.type.startsWith('image/')) {
      toast.error('Formato no válido. Ingrese un favicon ICO o PNG.')
      return
    }

    if (file.size > 2 * 1024 * 1024) {
      toast.error('El favicon no debe superar los 2MB.')
      return
    }

    if (faviconPreview.value && faviconPreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(faviconPreview.value)
    }

    faviconFile.value = file
    faviconPreview.value = URL.createObjectURL(file)
  }

  // Manejar selección de firma y sello
  const handleSignatureStampSelect = (file) => {
    if (!file) return

    if (!file.type.startsWith('image/')) {
      toast.error('Formato no válido. Ingrese una imagen PNG o WEBP.')
      return
    }

    if (file.size > 5 * 1024 * 1024) {
      toast.error('La imagen no debe superar los 5MB.')
      return
    }

    if (signatureStampPreview.value && signatureStampPreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(signatureStampPreview.value)
    }

    signatureStampFile.value = file
    signatureStampPreview.value = URL.createObjectURL(file)
  }

  // Quitar logotipo actual
  const removeLogo = () => {
    if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(logoPreview.value)
    }
    logoFile.value = null
    logoPreview.value = ''
  }

  // Quitar favicon actual
  const removeFavicon = () => {
    if (faviconPreview.value && faviconPreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(faviconPreview.value)
    }
    faviconFile.value = null
    faviconPreview.value = ''
  }

  // Quitar firma y sello actual
  const removeSignatureStamp = () => {
    if (signatureStampPreview.value && signatureStampPreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(signatureStampPreview.value)
    }
    signatureStampFile.value = null
    signatureStampPreview.value = ''
  }

  // Restablecer formulario a valores iniciales
  const resetForm = () => {
    try {
      const parsed = JSON.parse(initialSnapshot.value)
      form.app_name = parsed.app_name || ''
      form.app_rif = parsed.app_rif || ''
      form.address = parsed.address || ''
      form.fiscal_printer_serial = parsed.fiscal_printer_serial || ''
      form.default_currency = parsed.default_currency || 'COP'
    } catch {
      form.app_name = ''
      form.app_rif = ''
      form.address = ''
      form.fiscal_printer_serial = ''
      form.default_currency = 'COP'
    }
    removeLogo()
    removeFavicon()
    removeSignatureStamp()
    logoPreview.value = currentSavedLogo.value
    faviconPreview.value = currentSavedFavicon.value
    signatureStampPreview.value = currentSavedSignatureStamp.value
  }

  // Cargar configuración desde el backend
  const loadSettings = async () => {
    isPageLoading.value = true
    try {
      await brandingStore.fetchSettings(true)
      form.app_name = brandingStore.settings.app_name || ''
      form.app_rif = brandingStore.settings.app_rif || ''
      form.address = brandingStore.settings.address || ''
      form.fiscal_printer_serial = brandingStore.settings.fiscal_printer_serial || ''
      form.default_currency = brandingStore.settings.default_currency || 'COP'

      currentSavedLogo.value = brandingStore.settings.app_logo || ''
      logoPreview.value = currentSavedLogo.value

      currentSavedFavicon.value = brandingStore.settings.app_favicon || ''
      faviconPreview.value = currentSavedFavicon.value

      currentSavedSignatureStamp.value = brandingStore.settings.app_signature_stamp || ''
      signatureStampPreview.value = currentSavedSignatureStamp.value

      initialSnapshot.value = JSON.stringify({
        app_name: form.app_name,
        app_rif: form.app_rif,
        address: form.address,
        fiscal_printer_serial: form.fiscal_printer_serial,
        default_currency: form.default_currency,
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
    formData.append('fiscal_printer_serial', form.fiscal_printer_serial ? form.fiscal_printer_serial.trim() : '')
    formData.append('default_currency', form.default_currency || 'COP')

    if (logoFile.value) {
      formData.append('app_logo', logoFile.value)
    }

    if (faviconFile.value) {
      formData.append('app_favicon', faviconFile.value)
    }

    if (signatureStampFile.value) {
      formData.append('app_signature_stamp', signatureStampFile.value)
    }

    try {
      await axios.post('/general-settings', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      toast.success('Configuración institucional guardada exitosamente.')
      await loadSettings()
      logoFile.value = null
      faviconFile.value = null
      signatureStampFile.value = null
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
    if (faviconPreview.value && faviconPreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(faviconPreview.value)
    }
    if (signatureStampPreview.value && signatureStampPreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(signatureStampPreview.value)
    }
  })

  return {
    form,
    logoFile,
    logoPreview,
    faviconFile,
    faviconPreview,
    signatureStampFile,
    signatureStampPreview,
    isLoading,
    isPageLoading,
    isDirty,
    handleLogoSelect,
    handleFaviconSelect,
    handleSignatureStampSelect,
    removeLogo,
    removeFavicon,
    removeSignatureStamp,
    resetForm,
    saveSettings,
  }
}
