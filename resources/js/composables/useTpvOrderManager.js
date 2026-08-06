import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

export function useTpvOrderManager({
  hasOpenOrder,
  openOrderData,
  reservedOrderData,
  selectedClient,
  selectedDisplayCurrency,
  defaultCurrency,
  orderItems,
  isFinishingOrder,
  isCurrencyChanging,
  showBuysModal,
  totalOrderAmountWithspecialTaxAmount,
  totalOrderAmount,
  appliesSpecialTax,
  specialTaxAmount,
  currentUser,
  isRestaurant,
  isSportsRental,
  fetchPedidosList,
  updateOrderTotalsInBackend,
  getItemPriceByCurrency,
  roundUpToNearestHundred,
  selectedDiscountType,
  selectedCompanyId,
  activeCompanyOffers,
  selectedDoctorOffer,
  prescriptionFile,
  activePrescriptionOffers,
  currentPrescriptionDiscountPercentage,
  isLoadingInitialOrder,
  foreignOrdersCount,
  getEffectiveRate,
  formatOrderItemForFrontend,
}) {
  const addOrden = async (id, forceCurrency = null) => {
    const params = {
      client_id: id,
      seller_id: currentUser?.value?.id || 3,
      currency: forceCurrency || selectedDisplayCurrency.value,
    }
    try {
      const response = await axios.post('/tpv/orders', params)
      openOrderData.value = response.data.data.order
      selectedClient.value = response.data.data.order.client
      hasOpenOrder.value = true
      toast.success('Orden creada exitosamente.')
      return response.data.data.order
    } catch (error) {
      if (error.response && error.response.status === 401) {
        console.warn('[TPV] Sesión expirada (401). Recargando ventana...')
        window.location.reload()
      } else {
        console.error('Error al agregar la orden:', error)
        const responseData = error.response?.data
        let details = 'Error de red o base de datos.'
        if (responseData) {
          if (responseData.message) details = responseData.message
          else if (responseData.error) details = typeof responseData.error === 'object' ? JSON.stringify(responseData.error) : responseData.error
          else if (responseData.exception) details = responseData.exception
          else details = JSON.stringify(responseData)
        } else if (error.message) {
          details = error.message
        }
        toast.error(`Error al agregar la orden: ${details}`)
      }
      return null
    }
  }

  const fetchOpenOrder = async () => {
    try {
      const sellerId = currentUser?.value?.id || ''
      const response = await axios.get(`/tpv/order/seller/my-open-order?seller_id=${sellerId}`)
      if (response.data.data && response.data.data.order) {
        if (response.data.data.order.pending_order) {
          openOrderData.value = response.data.data.order.pending_order
          reservedOrderData.value = response.data.data.order.reserved_order
          const clientObj = response.data.data.order.pending_order.client
          if (clientObj && clientObj.identification === '99999999') {
            clientObj.name = 'Consumidor'
            clientObj.last_name = 'Final'
          }
          selectedClient.value = clientObj
          hasOpenOrder.value = true
          if (openOrderData.value.currency) {
            selectedDisplayCurrency.value = isSportsRental.value
              ? 'COP'
              : openOrderData.value.currency.toUpperCase()
          }
          if (openOrderData.value.details && formatOrderItemForFrontend) {
            orderItems.value = openOrderData.value.details.map((item) =>
              formatOrderItemForFrontend(item, getEffectiveRate)
            )
          } else {
            orderItems.value = []
          }
        } else {
          hasOpenOrder.value = false
          openOrderData.value = null
          reservedOrderData.value = null
          selectedClient.value = null
          selectedDisplayCurrency.value = getLastCurrency()
          orderItems.value = []
        }
        if (foreignOrdersCount) foreignOrdersCount.value = response.data.data.foreign_orders_count || 0
      } else {
        hasOpenOrder.value = false
        openOrderData.value = null
        reservedOrderData.value = null
        selectedClient.value = null
        selectedDisplayCurrency.value = getLastCurrency()
        orderItems.value = []
        if (foreignOrdersCount) foreignOrdersCount.value = 0
      }
    } catch (error) {
      console.error('Error al verificar orden abierta del vendedor:', error)
      hasOpenOrder.value = false
      openOrderData.value = null
      selectedClient.value = null
      selectedDisplayCurrency.value = defaultCurrency.value
      orderItems.value = []
    } finally {
      if (isLoadingInitialOrder) isLoadingInitialOrder.value = false
      if ((isRestaurant.value || isSportsRental.value) && typeof fetchPedidosList === 'function') {
        fetchPedidosList()
      }
    }
  }

  const reservedOrderCliente = async () => {
    try {
      const response = await axios.get(`/tpv/order/searchReserved`)
      if (response.data && response.data.message) {
        toast.success(response.data.message)
      }
      await fetchOpenOrder()
    } catch (error) {
      if (error.response && error.response.data && error.response.data.message) {
        toast.warning(error.response.data.message)
      } else {
        console.error('Error al verificar la orden reservada:', error)
        toast.error('Ocurrió un error inesperado al procesar la orden.')
      }
    }
  }

  let currencyPatchTimer = null

  const handleCurrencyChanged = async (newCurrency, isCurrencyChangingRef = null) => {
    if (selectedDisplayCurrency.value === newCurrency) return

    // 1. Cambio de moneda instantáneo en memoria para la interfaz
    selectedDisplayCurrency.value = newCurrency

    // 2. Si hay orden abierta, sincronizamos totales localmente e informamos al servidor en segundo plano
    if (hasOpenOrder.value && openOrderData.value?.id) {
      let calculatedTotal = 0
      let calculatedTotalUSD = 0
      let calculatedTotalCost = 0

      orderItems.value.forEach((item) => {
        const qty = item.selectedQuantity || 0
        calculatedTotalCost += (item.unitCost || 0) * qty
        const usdPrice = item.basePrice || (selectedDisplayCurrency.value === 'USD' ? item.price : 0)
        calculatedTotalUSD += usdPrice * qty

        if (newCurrency === 'BS') {
          calculatedTotal += (item.price_bs || 0) * qty
        } else if (newCurrency === 'COP') {
          calculatedTotal += (item.price_cop || 0) * qty
        } else {
          calculatedTotal += usdPrice * qty
        }
      })

      // Actualizar datos locales de la orden inmediatamente
      openOrderData.value.currency = newCurrency
      openOrderData.value.total_amount = calculatedTotal
      openOrderData.value.total_amount_usd = calculatedTotalUSD

      // Debounce de 300ms: si la cajera presiona F8 varias veces seguidas, solo enviamos la última moneda a la BD
      clearTimeout(currencyPatchTimer)
      currencyPatchTimer = setTimeout(async () => {
        try {
          if (!openOrderData.value || !openOrderData.value.id) return
          await axios.patch(`/tpv/orders/${openOrderData.value.id}`, {
            currency: newCurrency,
            total_amount: calculatedTotal,
            total_amount_usd: calculatedTotalUSD,
            total_cost: calculatedTotalCost,
          })
        } catch (error) {
          console.error('Error al actualizar moneda en servidor:', error)
        }
      }, 300)
    }
  }

  const cancelarOrder = async () => {
    try {
      if (!openOrderData.value || !openOrderData.value.id) return
      await axios.patch(`/tpv/orders/${openOrderData.value.id}/abandon`)
      toast.success('Orden abandonada exitosamente.')
      hasOpenOrder.value = false
      openOrderData.value = null
      selectedClient.value = null
      selectedDisplayCurrency.value = getLastCurrency()
      orderItems.value = []
    } catch (error) {
      console.error('Error al abandonar la orden:', error)
      toast.error('Error al abandonar la orden.')
    }
  }

  const reserverOrder = async () => {
    try {
      const response = await axios.patch(`/tpv/order/${openOrderData.value.id}/reserve`)
      hasOpenOrder.value = false
      openOrderData.value = null
      selectedClient.value = null
      selectedDisplayCurrency.value = getLastCurrency()
      orderItems.value = []
      reservedOrderData.value = response.data.data.reserved_order
      toast.success('Orden reservada exitosamente.')
      if (isRestaurant.value) {
        await fetchPedidosList()
      }
    } catch (error) {
      console.error('Error al reservar la orden:', error)
      toast.error('Error al reservar la orden.')
    }
  }

  const finalizeAndCheckPending = (options = {}) => {
    const { clientIdentification, pendingOpenOrder } = options || {}
    showBuysModal.value = false
    hasOpenOrder.value = false
    openOrderData.value = null
    selectedDisplayCurrency.value = getLastCurrency()
    orderItems.value = []
    selectedClient.value = null
    if (clientIdentification) clientIdentification.value = ''
    reservedOrderData.value = null
    if (isRestaurant.value || isSportsRental.value) {
      fetchPedidosList()
    }
  }

  const removeOrderItem = async (productIdToRemove, orderDetailId = null) => {
    if (!hasOpenOrder.value || !openOrderData.value?.id) {
      toast.error('No hay una orden abierta para eliminar productos.')
      return
    }
    try {
      let itemToRemove
      if (orderDetailId) {
        itemToRemove = orderItems.value.find((item) => item.order_detail_id === orderDetailId)
      } else {
        itemToRemove = orderItems.value.find((item) => item.product_id === productIdToRemove)
      }
      if (!itemToRemove?.order_detail_id) {
        toast.error('No se encontró el detalle del producto en la orden para eliminar.')
        return
      }
      await axios.delete(`/tpv/orders/${openOrderData.value.id}/items/${itemToRemove.order_detail_id}`)
      orderItems.value = orderItems.value.filter((item) => item.order_detail_id !== itemToRemove.order_detail_id)
      toast.success('Producto eliminado de la orden.')
    } catch (error) {
      toast.error('Error al eliminar el producto de la orden.')
    }
  }

  const handleBuysCompletion = async (
    orderId,
    paymentsData,
    credit,
    changeAmount,
    changeAmountUSD,
    switchStates,
    changeAmountOrigin = 0
  ) => {
    try {
      isFinishingOrder.value = true
      await updateOrderTotalsInBackend()
      const finalAmount = parseFloat(totalOrderAmountWithspecialTaxAmount.value)
      if (orderItems.value.length > 0 && (finalAmount <= 0 || isNaN(finalAmount))) {
        throw new Error('El monto total calculated es inválido.')
      }

      const balanceUsed = Array.isArray(paymentsData) && paymentsData.some((payment) => payment.type === 'balance' || payment.method === 'balance')
      let currentPercentage = 0
      let currentSourceId = null
      let currentTypeName = null

      if (selectedDiscountType.value === 'Empresa' && selectedCompanyId.value) {
        const offer = activeCompanyOffers.value.find((o) => o.value === selectedCompanyId.value)
        currentPercentage = parseFloat(offer?.current_discount || 0)
        currentSourceId = selectedCompanyId.value
        currentTypeName = 'company'
      } else if (selectedDiscountType.value === 'Medico' && selectedDoctorOffer.value) {
        currentPercentage = parseFloat(selectedDoctorOffer.value.percentage || 0)
        currentSourceId = selectedDoctorOffer.value.id
        currentTypeName = 'doctor'
      } else if (selectedDiscountType.value === 'Recipe' && prescriptionFile.value) {
        currentPercentage = parseFloat(currentPrescriptionDiscountPercentage.value || 0)
        currentSourceId = activePrescriptionOffers.value[0]?.id
        currentTypeName = 'recipe'
      }

      const safeSwitchStates = switchStates || {}
      const taxable_base = (appliesSpecialTax.value || safeSwitchStates.spe_surcharge_rate) ? totalOrderAmount.value : 0.0
      const spe_surcharge_rate = safeSwitchStates.spe_surcharge_rate || (appliesSpecialTax.value ? 3.0 : 0.0)
      const spe_surcharge_amount = (safeSwitchStates.spe_surcharge_rate && !appliesSpecialTax.value)
        ? (totalOrderAmount.value * (safeSwitchStates.spe_surcharge_rate / 100))
        : (appliesSpecialTax.value ? specialTaxAmount.value : 0.0)

      const safeChangeAmount = isNaN(parseFloat(changeAmount)) ? 0 : parseFloat(changeAmount)
      const safeChangeAmountUSD = isNaN(parseFloat(changeAmountUSD)) ? 0 : parseFloat(changeAmountUSD)

      const formData = new FormData()
      formData.append('order_id', orderId)
      formData.append('total_amount', totalOrderAmountWithspecialTaxAmount.value)
      formData.append('currency', selectedDisplayCurrency.value)
      formData.append('client_id', selectedClient.value?.id || '')
      formData.append('seller_id', currentUser.value?.id || '')
      formData.append('balance_used', balanceUsed ? 1 : 0)
      formData.append('generate_invoice', (safeSwitchStates.invoice_switch || safeSwitchStates.generate_invoice) ? 1 : 0)
      formData.append('credit', credit ? 1 : 0)
      formData.append('changeAmount', safeChangeAmount)
      formData.append('changeAmountUSD', safeChangeAmountUSD)
      formData.append('spe', safeSwitchStates.spe ? 1 : 0)
      formData.append('payments', JSON.stringify(paymentsData))
      formData.append('taxable_base', taxable_base)
      formData.append('spe_surcharge_rate', spe_surcharge_rate)
      formData.append('spe_surcharge_amount', spe_surcharge_amount)

      const mappedItems = orderItems.value.map((item) => {
        const isTaxable = item.taxRate !== 0
        const taxRateValue = isTaxable ? 0.16 : 0
        const taxMultiplier = isTaxable ? 1.16 : 1

        let finalPrice = getItemPriceByCurrency(item, selectedDisplayCurrency.value)
        let finalPriceBeforeDiscount = finalPrice
        let dType = null
        let dPercent = 0
        let dSourceId = null

        if (item.discountApplied) {
          dType = item.discountSource || item.discount_type
          dPercent = parseFloat(item.appliedDiscountPercentage || 0)
          dSourceId = item.discountSourceId || item.discount_source_id
          const orig = selectedDisplayCurrency.value === 'BS'
            ? (item.originalPriceBs ?? item.original_price_bs)
            : selectedDisplayCurrency.value === 'COP'
              ? (item.originalPriceCop ?? item.original_price_cop)
              : (item.originalPrice ?? item.original_price_usd)
          if (orig != null) finalPriceBeforeDiscount = orig
        }

        const ivaAmount = finalPrice * taxRateValue
        const finalPriceTax = finalPrice * taxMultiplier
        const finalPriceBeforeDiscountTax = finalPriceBeforeDiscount * taxMultiplier

        const finalIva = selectedDisplayCurrency.value === 'COP'
          ? roundUpToNearestHundred(ivaAmount)
          : parseFloat(ivaAmount.toFixed(2))

        const rawPriceBs = (item.originalPriceBs ?? item.original_price_bs ?? item.basePrice) || 0
        const finalPriceBs = rawPriceBs * (1 - dPercent / 100)
        const finalPriceTaxBs = finalPriceBs * taxMultiplier
        const finalPriceBeforeDiscountTaxBs = rawPriceBs * taxMultiplier

        return {
          order_detail_id: item.order_detail_id,
          unit_cost: finalPrice,
          iva_amount: finalIva,
          price: finalPriceTax,
          tax: item.taxRate,
          price_before_discount: finalPriceBeforeDiscountTax,
          price_bs: finalPriceTaxBs,
          price_before_discount_bs: finalPriceBeforeDiscountTaxBs,
          discount_percentage: dPercent > 0 ? dPercent : null,
          discount_type: dType,
          discount_source_id: dSourceId,
        }
      })
      formData.append('items', JSON.stringify(mappedItems))

      if (selectedDiscountType.value === 'Recipe' && prescriptionFile.value) {
        formData.append('prescription_image', prescriptionFile.value)
      }

      let realOrderId = orderId
      if (realOrderId && typeof realOrderId === 'object') {
        realOrderId = realOrderId.id || realOrderId.order_id || openOrderData.value?.id
      }
      if (!realOrderId) {
        realOrderId = openOrderData.value?.id
      }

      if (!realOrderId || realOrderId === 'undefined') {
        console.warn('[TPV] Intento de completar una orden sin ID válido.')
        toast.error('No hay una orden abierta válida para procesar el pago.')
        return
      }

      const response = await axios.post(`/tpv/orders/${realOrderId}/complete`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      return response.data
    } catch (error) {
      console.error('Error al completar orden:', error)
      toast.error(error.message || 'Error al procesar el pago.')
      throw error
    } finally {
      isFinishingOrder.value = false
    }
  }

  const addReserverOrder = async () => {
    try {
      const response = await axios.patch(`/tpv/order/${openOrderData.value.id}/reserveAdd`)
      const { pending_order, reserved_order } = response.data.data

      if (pending_order?.currency) {
        selectedDisplayCurrency.value = pending_order.currency.toUpperCase()
      }

      if (pending_order) {
        openOrderData.value = pending_order
        selectedClient.value = pending_order.client
        if (pending_order.details) {
          orderItems.value = pending_order.details.map((item) => formatOrderItemForFrontend(item))
        } else {
          orderItems.value = []
        }
        hasOpenOrder.value = true
      }

      reservedOrderData.value = reserved_order
      toast.success('Orden actualizada correctamente.')
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Error al reservar la orden. Inténtalo de nuevo.'
      toast.error(errorMessage)
    }
  }

  const finalizeAndCheckPending = (options = {}) => {
    const { clientIdentification, pendingOpenOrder } = options || {}
    showBuysModal.value = false
    hasOpenOrder.value = false
    openOrderData.value = null
    selectedDisplayCurrency.value = defaultCurrency.value
    orderItems.value = []
    selectedClient.value = null
    if (clientIdentification) clientIdentification.value = ''
    reservedOrderData.value = null
    if (isRestaurant.value || isSportsRental.value) {
      fetchPedidosList()
    }
  }

  return {
    addOrden,
    fetchOpenOrder,
    reservedOrderCliente,
    handleCurrencyChanged,
    cancelarOrder,
    reserverOrder,
    removeOrderItem,
    handleBuysCompletion,
    addReserverOrder,
    finalizeAndCheckPending,
  }
}
