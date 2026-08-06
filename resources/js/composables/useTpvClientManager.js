import { ref, reactive } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

export function useTpvClientManager({
  selectedClient,
  showRegisterClientModal,
  hasOpenOrder,
  openOrderData,
  selectedDisplayCurrency,
  addOrden,
  handleAddQuotationProducts,
  pendingQuotationProducts,
} = {}) {
  const clientIdentification = ref('')
  const companies = ref([])

  const newClientFormData = ref({
    id: null,
    identification_type: '',
    identification: '',
    name: '',
    last_name: '',
    email: '',
    phone: '',
    birthdate: '',
    company_id: null,
    address: '',
    is_spe: false,
  })

  const newClientFormErrors = reactive({
    id: '',
    identification: '',
    identification_type: '',
    name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    birthdate: '',
    company_id: '',
    is_spe: '',
  })

  const consultAllcomapanies = async () => {
    try {
      const companiesResponse = await axios.get('/crm/companies')
      companies.value = companiesResponse.data.data
    } catch (error) {
      console.error('Error al cargar empresas:', error)
    }
  }

  const clearFormErrors = () => {
    Object.keys(newClientFormErrors).forEach((key) => {
      newClientFormErrors[key] = ''
    })
  }

  const limpiarDatosFormulario = () => {
    newClientFormData.value = {
      id: null,
      identification_type: '',
      identification: '',
      name: '',
      last_name: '',
      email: '',
      phone: '',
      birthdate: '',
      company_id: null,
      address: '',
      is_spe: false,
    }
  }

  const cargarErrores = (errores) => {
    Object.keys(newClientFormErrors).forEach((key) => {
      newClientFormErrors[key] = errores[key] ? errores[key].join(', ') : ''
    })
  }

  const handleCloseRegisterModal = () => {
    if (showRegisterClientModal) showRegisterClientModal.value = false
    limpiarDatosFormulario()
    clearFormErrors()
  }

  const handleSaveNewClient = async (formData) => {
    try {
      const clientId = newClientFormData.value.id
      const url = clientId ? `/crm/clients/edit/${clientId}` : '/crm/clients'
      const respuesApi = await axios.post(url, formData)
      if (respuesApi.status === 200) {
        toast.success(clientId ? 'Cliente actualizado' : 'Cliente creado')
        handleCloseRegisterModal()
        if (typeof addOrden === 'function') {
          addOrden(respuesApi.data.data.id)
        }
      }
    } catch (error) {
      toast.error('Error al crear el cliente')
      if (error.response?.data?.data?.errors) {
        cargarErrores(error.response.data.data.errors)
      }
    }
  }

  const handleEditCliente = (client) => {
    newClientFormData.value = {
      ...newClientFormData.value,
      ...client,
      identification: client.identification,
      identification_type: client.identification_type,
    }
    if (showRegisterClientModal) showRegisterClientModal.value = true
  }

  const verifyClient = async (identification) => {
    clientIdentification.value = identification

    if (!identification) {
      toast.warning('Por favor, ingrese un número de identificación.')
      return false
    }

    try {
      const response = await axios.get(`/tpv/order/client/${identification}`)
      const responseData = response.data.data

      if (responseData.found === false) {
        toast.info('Consultando identidad...')
        try {
          const cneResponse = await axios.post('/crm/clients/cne-verify', {
            identification: identification,
          })
          if (cneResponse.status === 200 && cneResponse.data.data) {
            const cneData = cneResponse.data.data
            newClientFormData.value = {
              ...newClientFormData.value,
              identification: identification,
              identification_type: 'V-',
              name: cneData.name,
              last_name: cneData.last_name,
            }
            toast.success('Datos precargados desde CNE.')
          }
        } catch (cneError) {
          newClientFormData.value = {
            ...newClientFormData.value,
            identification: identification,
            identification_type: 'V-',
          }
        }

        if (showRegisterClientModal) showRegisterClientModal.value = true
        return false
      } else {
        const clientData = responseData.client
        const isInvalidPhone = !clientData.phone || clientData.phone.trim().length < 10 || /^0+$/.test(clientData.phone.trim())

        if (isInvalidPhone) {
          toast.warning('El cliente no tiene un teléfono válido. Por favor, complételo.')
          newClientFormData.value = {
            ...newClientFormData.value,
            ...clientData,
            identification: clientData.identification,
            identification_type: clientData.identification_type,
          }
          if (showRegisterClientModal) showRegisterClientModal.value = true
          return false
        }

        if (selectedClient) selectedClient.value = clientData
        toast.success(`Cliente ${clientData.name} ${clientData.last_name} encontrado.`)

        if (responseData.found_open_order) {
          if (hasOpenOrder) hasOpenOrder.value = true
          if (openOrderData) openOrderData.value = responseData.order
          if (openOrderData?.value?.currency && selectedDisplayCurrency) {
            selectedDisplayCurrency.value = openOrderData.value.currency.toUpperCase()
          }
        } else {
          if (hasOpenOrder) hasOpenOrder.value = false
          if (openOrderData) openOrderData.value = null
          const order = typeof addOrden === 'function' ? await addOrden(clientData.id) : null
          if (order && pendingQuotationProducts?.value?.length > 0) {
            await handleAddQuotationProducts(pendingQuotationProducts.value)
            pendingQuotationProducts.value = []
          }
        }
        return true
      }
    } catch (error) {
      console.error('Error al verificar cliente:', error)
      toast.error('Error al verificar el cliente.')
      return false
    }
  }

  return {
    clientIdentification,
    companies,
    newClientFormData,
    newClientFormErrors,
    consultAllcomapanies,
    clearFormErrors,
    limpiarDatosFormulario,
    handleCloseRegisterModal,
    handleSaveNewClient,
    handleEditCliente,
    verifyClient,
  }
}
