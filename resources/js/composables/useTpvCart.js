import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

export function useTpvCart({
  hasOpenOrder,
  openOrderData,
  orderItems,
  selectedDisplayCurrency,
  brandingStore,
  addOrden,
  fetchOpenOrder,
  getItemPriceByCurrency,
  formatOrderItemForFrontend,
  selectedDiscountType,
  selectedDoctorOffer,
  prescriptionFile,
  activePrescriptionOffers,
  validateAndApplyDoctorDiscount,
  validateAndApplyPrescriptionDiscount,
}) {
  const updateOrderItemQuantity = async ({ productId, quantity, orderDetailId }) => {
    if (quantity <= 0) return

    if (!hasOpenOrder.value || !openOrderData.value?.id) {
      toast.error('Debe haber una orden abierta para modificar productos.')
      return
    }

    try {
      let currentItem = orderItems.value.find((item) => item.order_detail_id === orderDetailId)
      if (!currentItem) {
        currentItem = orderItems.value.find((item) => item.product_id === productId)
      }
      if (!currentItem) {
        toast.error('Producto no encontrado en la orden.')
        return
      }

      let computedTotalQuantity = quantity
      if (!currentItem.pack_id && !currentItem.is_dish && orderDetailId) {
        const otherItemsQuantity = orderItems.value
          .filter(
            (item) =>
              item.product_id === productId &&
              !item.pack_id &&
              item.order_detail_id !== orderDetailId
          )
          .reduce((sum, item) => sum + item.selectedQuantity, 0)
        computedTotalQuantity = otherItemsQuantity + quantity
      }

      const basePriceForPayload = currentItem.is_dish
        ? (currentItem.basePrice || currentItem.original_price_usd || currentItem.price || 0)
        : (currentItem.basePrice || currentItem.price)

      const payload = {
        product_id: currentItem.is_dish ? null : productId,
        dish_id: currentItem.is_dish ? currentItem.dish_id : null,
        quantity: computedTotalQuantity,
        price_usd_unit: basePriceForPayload,
        price_at_product: basePriceForPayload,
        currency_at_order: selectedDisplayCurrency.value,
        pack_id: currentItem.pack_id || null,
      }

      // 1. ACTUALIZACIÓN OPTIMISTA INMEDIATA (Respuesta instantánea a 0ms en pantalla)
      const previousQuantity = currentItem.selectedQuantity
      currentItem.selectedQuantity = quantity

      // 2. Envío asíncrono al backend en segundo plano
      axios.post(`/tpv/orders/${openOrderData.value.id}/items`, payload)
        .then((response) => {
          const updatedItem = response.data?.data?.order_item
          if (updatedItem) {
            const idx = orderItems.value.findIndex((i) => i.order_detail_id === (updatedItem.id || orderDetailId))
            if (idx !== -1) {
              orderItems.value[idx] = formatOrderItemForFrontend(updatedItem)
            }
          }
        })
        .catch((error) => {
          console.error('Error al actualizar cantidad:', error)
          // Revertir a la cantidad previa si el servidor falla
          currentItem.selectedQuantity = previousQuantity
          toast.error(error.response?.data?.message || 'Error al actualizar cantidad')
        })
    } catch (error) {
      console.error('Error al procesar la actualización:', error)
    }
  }


  const handleSaveOrderItemNote = async ({ product, notes }) => {
    if (!hasOpenOrder.value || !openOrderData.value?.id) {
      toast.error('Debe haber una orden abierta para agregar notas.')
      return
    }
    try {
      const basePriceForPayload = product.is_dish
        ? (product.basePrice || product.original_price_usd || product.price || 0)
        : (product.basePrice || product.price)

      const payload = {
        product_id: product.is_dish ? null : product.product_id,
        dish_id: product.is_dish ? product.dish_id : null,
        quantity: product.selectedQuantity,
        price_usd_unit: basePriceForPayload,
        price_at_product: basePriceForPayload,
        currency_at_order: selectedDisplayCurrency.value,
        pack_id: product.pack_id || null,
        notes: notes,
      }

      await axios.post(`/tpv/orders/${openOrderData.value.id}/items`, payload)
      await fetchOpenOrder()
      toast.success('Nota del plato guardada exitosamente.')
    } catch (error) {
      console.error('Error al guardar anotación:', error)
      toast.error(error.response?.data?.message || 'Error al guardar la nota.')
    }
  }

  const addProductToOrder = async ({ productId, quantity, packId = null, customPrice = null, productData = null }) => {
    if (quantity <= 0) {
      toast.error('La cantidad a agregar debe ser mayor que cero.')
      return
    }

    if (!hasOpenOrder.value || !openOrderData.value?.id) {
      const isSimpleTpv = brandingStore.settings?.tpv_mode === 'simple'
      if (isSimpleTpv) {
        try {
          const checkClientResp = await axios.get('/tpv/order/client/99999999')
          const genericClientId = checkClientResp.data?.data?.client?.id
          if (!genericClientId) {
            toast.error('El cliente genérico 99999999 no está disponible.')
            return
          }
          const newOrder = await addOrden(genericClientId)
          if (!newOrder) {
            toast.error('No se pudo iniciar la orden automática en modo simple.')
            return
          }
        } catch (err) {
          toast.error('Debe haber una orden abierta para agregar productos.')
          return
        }
      } else {
        toast.error('Debe haber una orden abierta para agregar productos.')
        return
      }
    }

    try {
      // Si el catálogo ya pasó el objeto completo del producto, no hace falta un GET extra
      let productDetails = productData
      if (!productDetails || (productDetails.lots_sum_quantity === undefined && productDetails.valid_stock_sum === undefined)) {
        const response = await axios.get(`/product/${productId}`)
        productDetails = response.data
      }

      const availableQuantity = parseInt(productDetails.valid_stock_sum ?? productDetails.lots_sum_quantity ?? 0)

      const currentItemInOrder = orderItems.value.find((item) => item.product_id === productId)
      const currentQuantityInOrder = currentItemInOrder ? currentItemInOrder.selectedQuantity : 0
      const newTotalQuantity = currentQuantityInOrder + quantity

      if (quantity > availableQuantity) {
        toast.error(`No hay suficiente stock para "${productDetails.name}". Disponible: ${availableQuantity}. Solicitado: ${quantity}.`)
        return
      }

      let priceInSelectedCurrency
      if (customPrice !== null) {
        const customPriceUSD = parseFloat(customPrice)
        if (selectedDisplayCurrency.value === 'USD') {
          priceInSelectedCurrency = customPriceUSD
        } else if (selectedDisplayCurrency.value === 'BS') {
          const rate = productDetails.sale_price > 0 ? productDetails.price_bs / productDetails.sale_price : 1
          priceInSelectedCurrency = customPriceUSD * rate
        } else if (selectedDisplayCurrency.value === 'COP') {
          const rate = productDetails.sale_price > 0 ? productDetails.price_cop / productDetails.sale_price : 1
          priceInSelectedCurrency = customPriceUSD * rate
        } else {
          priceInSelectedCurrency = customPriceUSD
        }
      } else {
        priceInSelectedCurrency = getItemPriceByCurrency(productDetails, selectedDisplayCurrency.value)
      }

      const payload = {
        product_id: productDetails.id,
        quantity: newTotalQuantity,
        price_usd_unit: customPrice !== null ? parseFloat(customPrice) : productDetails.sale_price,
        price_at_product: priceInSelectedCurrency,
        tax_rate_at_order: productDetails.iva === 1 ? 0.16 : 0,
        currency_at_order: selectedDisplayCurrency.value,
        pack_id: packId,
      }

      // ── Optimistic UI: Actualización instantánea en la interfaz de usuario ──
      const isExisting = orderItems.value.some((item) => item.product_id === productDetails.id)
      const formattedOptimisticItem = formatOrderItemForFrontend({
        id: currentItemInOrder?.order_detail_id || 'temp_' + Date.now(),
        quantity: newTotalQuantity,
        price_usd_unit: payload.price_usd_unit,
        price_at_product: payload.price_at_product,
        discount_percentage: currentItemInOrder?.discount_percentage || 0,
        discount_type: currentItemInOrder?.discount_type || null,
        product: {
          id: productDetails.id,
          name: productDetails.name,
          active_ingredient: productDetails.active_ingredient,
          barcode: productDetails.barcode,
          price: productDetails.price ?? productDetails.sale_price,
          price_bs: productDetails.price_bs,
          price_cop: productDetails.price_cop,
          unit_cost: productDetails.unit_cost,
          valid_stock_sum: availableQuantity,
          lots_sum_quantity: availableQuantity,
          laboratory: productDetails.laboratory,
          iva: productDetails.iva,
          tax_rate: productDetails.tax_rate,
        }
      }, (from, to) => 1)

      if (isExisting) {
        orderItems.value = orderItems.value.map((item) =>
          item.product_id === productDetails.id
            ? { ...item, selectedQuantity: newTotalQuantity }
            : item
        )
      } else {
        orderItems.value = [...orderItems.value, formattedOptimisticItem]
      }

      toast.success(
        isExisting
          ? `Cantidad de "${productDetails.name}" incrementada a ${newTotalQuantity}.`
          : `"${productDetails.name}" agregado a la orden.`
      )

      // Guardar en servidor en segundo plano
      const backendResponse = await axios.post(`/tpv/orders/${openOrderData.value.id}/items`, payload)
      
      // Sincronizar silenciosamente los datos finales del backend
      fetchOpenOrder().catch((err) => console.error('Error al sincronizar orden:', err))

      if (selectedDiscountType.value === 'Medico' && selectedDoctorOffer.value) {
        validateAndApplyDoctorDiscount()
      } else if (selectedDiscountType.value === 'Recipe' && prescriptionFile.value && activePrescriptionOffers.value.length > 0) {
        validateAndApplyPrescriptionDiscount()
      }
    } catch (error) {
      console.error('Error al agregar el producto a la orden:', error)
      toast.error(error.response?.data?.message || 'Error al agregar el producto.')
    }
  }


  const addDishToOrder = async (dish) => {
    if (!hasOpenOrder.value || !openOrderData.value?.id) {
      toast.error('Debe haber una orden abierta para agregar platos.')
      return
    }

    const existing = orderItems.value.find((item) => item.dish_id === dish.id)
    const newQty = existing ? existing.selectedQuantity + 1 : 1
    const unitPrice = parseFloat(dish.designated_price) || parseFloat(dish.sale_price) || parseFloat(dish.price) || 0

    try {
      const payload = {
        dish_id: dish.id,
        quantity: newQty,
        price_at_product: unitPrice,
        price_usd_unit: unitPrice,
        currency_at_order: selectedDisplayCurrency.value,
      }

      await axios.post(`/tpv/orders/${openOrderData.value.id}/items`, payload)
      await fetchOpenOrder()
      toast.success(`"${dish.name}" agregado al pedido.`)
    } catch (error) {
      toast.error(error.response?.data?.message || 'Error al agregar el plato.')
    }
  }

  const handleAddDishToOrder = async ({ dish, quantity }) => {
    if (!hasOpenOrder.value || !openOrderData.value?.id) {
      toast.error('Debe haber una orden abierta para agregar platos.')
      return
    }

    const existing = orderItems.value.find((item) => item.dish_id === dish.id)
    const currentQty = existing ? existing.selectedQuantity : 0
    const newQty = currentQty + quantity
    const unitPrice = parseFloat(dish.designated_price) || parseFloat(dish.sale_price) || parseFloat(dish.price) || 0

    try {
      const payload = {
        dish_id: dish.id,
        quantity: newQty,
        price_at_product: unitPrice,
        price_usd_unit: unitPrice,
        currency_at_order: selectedDisplayCurrency.value,
      }

      await axios.post(`/tpv/orders/${openOrderData.value.id}/items`, payload)
      await fetchOpenOrder()
      toast.success(`"${dish.name}" agregado al pedido.`)
    } catch (error) {
      toast.error(error.response?.data?.message || 'Error al agregar el plato.')
    }
  }

  return {
    updateOrderItemQuantity,
    handleSaveOrderItemNote,
    addProductToOrder,
    addDishToOrder,
    handleAddDishToOrder,
  }
}
