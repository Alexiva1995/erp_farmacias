import { onMounted, onUnmounted } from 'vue'
import axios from '@/plugins/axios'

export function useTpvInit({
  authStore,
  brandingStore,
  fetchGeneralSettings,
  fetchExchangeRates,
  sortBy,
  orderBy,
  tableOptions,
  selectedDisplayCurrency,
  defaultCurrency,
  fetchOpenOrder,
  hasOpenOrder,
  addOrden,
  selectedClient,
  fetchCompanyOffers,
  selectedDiscountType,
  selectedCompany,
  isLoadingInitialOrder,
  fetchSelectOptions,
  fetchProducts,
  consultAllcomapanies,
  fetchDoctorOffers,
  fetchPrescriptionOffers,
  barcodeInputTimer,
}) {
  const startHeartbeat = () => {
    const interval = setInterval(async () => {
      try {
        await axios.get('/api/tpv/heartbeat')
      } catch (error) {
        if (error.response?.status === 419) {
          window.location.reload()
        }
      }
    }, 300000)
    return interval
  }

  let heartbeatInterval

  onMounted(async () => {
    // Obtener primero el orden del usuario antes de activar la tabla para evitar doble llamada
    try {
      const configResponse = await axios.get('/user/config')
      if (configResponse.data.config?.sort_products_orders) {
        const [key, order] = configResponse.data.config.sort_products_orders.split('|')
        sortBy.value = key
        orderBy.value = order
        if (tableOptions && tableOptions.value) {
          tableOptions.value.sortBy = [{ key, order }]
        }
      }
    } catch (err) {
      console.error('[ORDER_USER] Error config usuario', err)
    }

    // 1. Cargar usuario, configuraciones iniciales y tasas de cambio en paralelo
    const initPromises = []
    if (authStore?.fetchUser) {
      initPromises.push(authStore.fetchUser())
    }
    initPromises.push(brandingStore.fetchSettings())
    initPromises.push(fetchGeneralSettings().catch(err => console.error('[ORDER_USER] Error config general', err)))
    // Cargar tasas de cambio antes de abrir la orden para que los precios en COP/BS sean correctos
    if (fetchExchangeRates) {
      initPromises.push(fetchExchangeRates().catch(err => console.error('[ORDER_USER] Error tasas de cambio', err)))
    }
    await Promise.allSettled(initPromises)

    selectedDisplayCurrency.value = 'COP'
    heartbeatInterval = startHeartbeat()

    // 2. Cargar la Orden Abierta (El catálogo de productos se carga automáticamente a través de la tabla VDataTableServer)
    try {
      await fetchOpenOrder()
      const isSimpleTpv = brandingStore.settings?.tpv_mode === 'simple'
      if (isSimpleTpv && !hasOpenOrder.value) {
        try {
          const checkClientResp = await axios.get('/tpv/order/client/99999999')
          if (checkClientResp.data?.data?.client?.id) {
            await addOrden(checkClientResp.data.data.client.id)
          }
        } catch (err) {
          console.error('Error en orden automática simple mode:', err)
        }
      }

      if (selectedClient.value?.company_id) {
        await fetchCompanyOffers(selectedClient.value.company_id).catch(() => {})
        selectedDiscountType.value = 'Empresa'
        selectedCompany.value = selectedClient.value.company_id
      } else {
        await fetchCompanyOffers().catch(() => {})
      }
    } catch (error) {
      console.error('[ORDER_USER] Error al cargar orden abierta:', error)
    } finally {
      isLoadingInitialOrder.value = false
    }

    // 3. Cargar en SEGUNDO PLANO (sin bloquear la interfaz ni la tabla) las listas secundarias
    Promise.allSettled([
      fetchProducts(),
      fetchSelectOptions(),
      consultAllcomapanies(),
      fetchDoctorOffers(),
      fetchPrescriptionOffers()
    ])
  })

  onUnmounted(() => {
    clearInterval(heartbeatInterval)
    if (barcodeInputTimer) clearTimeout(barcodeInputTimer)
  })

  return {
    startHeartbeat,
  }
}
