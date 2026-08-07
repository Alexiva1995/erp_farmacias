import { ref, computed, nextTick } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { formatCurrency } from '@/utils/currencyFormatter'
import { BASE64_LOGO_DATA } from '@/constants/logo.js'

export function useTpvCheckoutManager({
  props,
  emit,
  payments,
  remainingAmount,
  getConvertedRemainingAmount,
  getEffectiveRate,
  isCashMethod,
  requiresReference,
  hasMissingReferences,
  appliesSpecialTax,
  specialTaxAmount,
  roundedTotalAmountToPay,
  changeAmount,
  changeAmountInCop,
  changeAmountInUsd,
  exchangeRates,
  currentProgress,
}) {
  const issubmitting = ref(false)
  const receiptOrderData = ref(null)
  const receiptOrderProducts = ref([])
  const receiptPayments = ref([])

  const getProductPrice = (product, currency = null) => {
    const targetCurrency = currency || props.selectedCurrency
    const priceKey = `pivot_price_${targetCurrency.toLowerCase()}`
    if (product[priceKey] !== undefined) {
      return Number(product[priceKey])
    }
    const rate = getEffectiveRate(props.selectedCurrency, targetCurrency)
    if (rate > 0) {
      return Number(product.pivot?.price || product.price || 0) * rate
    }
    return Number(product.pivot?.price || product.price || 0)
  }

  const selectPaymentMethod = (methodValue, currency = null) => {
    const targetCurrency = currency || props.selectedCurrency
    const existingIndex = payments.value.findIndex(
      (p) => p.method === methodValue && p.currency === targetCurrency
    )

    if (existingIndex !== -1) {
      payments.value.splice(existingIndex, 1)
      toast.info('Método de pago removido.')
      return
    }

    if (remainingAmount.value <= 0) {
      toast.error('El monto total ya ha sido cubierto.')
      return
    }

    const newPayment = {
      method: methodValue,
      amount: null,
      reference: null,
      currency: targetCurrency,
      debounceTimeout: null,
      inputAmount: null,
      _isEditing: false,
      _isInputActive: false,
      _isReferenceActive: false,
      _referenceError: false,
      _amountConfirmed: false,
      _amountError: false,
    }

    payments.value.push(newPayment)
    const availablePayment = payments.value[payments.value.length - 1]

    if (methodValue === 'balance') {
      const clientBalance = props.orderData.client?.balance || 0
      if (clientBalance <= 0) {
        toast.error('El cliente no tiene saldo disponible.')
        availablePayment.method = null
        return
      }
      let remainingAmountInOrderCurrency = remainingAmount.value
      let rateToUSD = props.selectedCurrency === 'USD' ? 1 : getEffectiveRate(props.selectedCurrency, 'USD')
      if (!rateToUSD) {
        toast.error('No se encontró la tasa de cambio.')
        availablePayment.method = null
        return
      }
      const remainingAmountInUSD = remainingAmountInOrderCurrency / rateToUSD
      const amountToUse = Math.min(remainingAmountInUSD, clientBalance)
      availablePayment.amount = parseFloat(amountToUse.toFixed(2))
      availablePayment.inputAmount = availablePayment.amount
      availablePayment._isInputActive = false
    } else if (methodValue === 'credit') {
      availablePayment.amount = remainingAmount.value
      availablePayment.inputAmount = remainingAmount.value
      availablePayment._isInputActive = false
      availablePayment._isReferenceActive = false
    } else {
      const isCash = isCashMethod(methodValue)
      const defaultAmount = isCash ? null : getConvertedRemainingAmount(targetCurrency)

      availablePayment.inputAmount = defaultAmount !== null && defaultAmount > 0 ? defaultAmount : ''
      availablePayment.amount = defaultAmount !== null && defaultAmount > 0 ? defaultAmount : null
      availablePayment._isInputActive = true

      nextTick(() => {
        const paymentIndex = payments.value.indexOf(availablePayment)
        const input = document.querySelector(`.payment-input[data-payment-index="${paymentIndex}"]`)
        if (input) {
          input.focus()
          if (defaultAmount !== null && defaultAmount > 0) input.select()
        }
      })
    }

    const isCard = ['debit_card', 'credit_card', 'card'].includes(methodValue)
    if (!requiresReference(methodValue, targetCurrency)) {
      availablePayment.reference = null
    } else {
      availablePayment._isReferenceActive = true
      availablePayment.reference = ''

      if (isCard && targetCurrency === 'BS') {
        let lastRef = localStorage.getItem('tpv_last_card_reference_bs')
        if (!lastRef) {
          const prevPayment = payments.value.find((p) => isCard && p.currency === 'BS' && p.reference)
          if (prevPayment && prevPayment.reference) lastRef = prevPayment.reference
        }
        if (lastRef) {
          const cleanLastRef = lastRef.trim()
          const parsed = parseInt(cleanLastRef, 10)
          if (!isNaN(parsed)) {
            availablePayment.reference = (parsed + 1).toString().padStart(cleanLastRef.length, '0')
          } else {
            availablePayment.reference = cleanLastRef
          }
        }
      }
    }
  }

  const selectQuickCash = (cashAmount, currency) => {
    let methodValue = 'cash'
    if (currency === 'USD') methodValue = 'cash_usd'
    if (currency === 'BS') methodValue = 'cash_bs'
    if (currency === 'COP') methodValue = 'cash_cop'

    const existingCash = payments.value.find((p) => p.method === methodValue && p.currency === currency)
    if (existingCash) {
      const currentVal = Number(existingCash.amount) || 0
      const newVal = currentVal + cashAmount
      existingCash.amount = newVal
      existingCash.inputAmount = newVal.toString()
      existingCash._amountConfirmed = true
      toast.success(`Entregado billete acumulado de ${formatCurrency(newVal, currency)}`)
    } else {
      const newPayment = {
        method: methodValue,
        amount: cashAmount,
        inputAmount: cashAmount.toString(),
        reference: null,
        currency: currency,
        debounceTimeout: null,
        _isEditing: false,
        _isInputActive: false,
        _isReferenceActive: false,
        _referenceError: false,
        _amountConfirmed: true,
        _amountError: false,
      }
      payments.value.push(newPayment)
      toast.success(`Entregado billete de ${formatCurrency(cashAmount, currency)}`)
    }
  }

  const confirmPaymentComplete = (payment) => {
    if (payment._isInputActive) {
      const numValue = parseFloat(payment.inputAmount)
      if (isNaN(numValue) || numValue <= 0) {
        toast.error('Por favor ingrese un monto válido.')
        payment._amountError = true
        return
      }

      if (!isCashMethod(payment.method)) {
        const previousAmount = payment._previousAmount !== undefined ? payment._previousAmount : (payment.amount || 0)
        let remainingInPaymentCurrency = getConvertedRemainingAmount(payment.currency)

        if (previousAmount > 0) {
          if (payment.currency === props.selectedCurrency) {
            remainingInPaymentCurrency += previousAmount
          } else {
            const rateToBase = getEffectiveRate(payment.currency, props.selectedCurrency)
            const rateToPayment = getEffectiveRate(props.selectedCurrency, payment.currency)
            if (rateToBase > 0 && rateToPayment > 0) {
              remainingInPaymentCurrency += (previousAmount * rateToBase) * rateToPayment
            }
          }
        }

        if (numValue > remainingInPaymentCurrency) {
          toast.error(`El monto no puede exceder el restante: ${formatCurrency(remainingInPaymentCurrency, payment.currency)}`)
          return
        }
      }

      payment.amount = numValue
      payment.inputAmount = numValue.toString()
    }

    if (!payment.amount || payment.amount <= 0) {
      toast.error('Por favor ingrese un monto válido.')
      if (payment._isInputActive) {
        payment._amountError = true
        nextTick(() => {
          const paymentIndex = payments.value.indexOf(payment)
          const input = document.querySelector(`.payment-input[data-payment-index="${paymentIndex}"]`)
          if (input) input.focus()
        })
      }
      return
    }

    if (requiresReference(payment.method, payment.currency)) {
      if (!payment.reference || payment.reference.trim() === '') {
        toast.error('Por favor ingrese la referencia del pago.')
        payment._referenceError = true
        payment._isReferenceActive = true
        payment._amountConfirmed = true
        nextTick(() => {
          const paymentIndex = payments.value.indexOf(payment)
          const referenceInput = document.querySelector(`.payment-reference-input[data-payment-index="${paymentIndex}"]`)
          if (referenceInput) {
            referenceInput.focus()
            referenceInput.select()
          }
        })
        return
      }
      payment._referenceError = false
      if (payment.reference && ['debit_card', 'credit_card', 'card'].includes(payment.method) && payment.currency === 'BS') {
        const cleanRef = payment.reference.trim()
        if (cleanRef) {
          localStorage.setItem('tpv_last_card_reference_bs', cleanRef)
        }
      }
    }

    payment._isInputActive = false
    payment._isReferenceActive = false
    payment._referenceError = false
    payment._amountError = false
    payment._amountConfirmed = false
    payment._previousAmount = undefined
  }

  const handleCompletePurchase = async () => {
    if (issubmitting.value) return
    issubmitting.value = true

    try {
      const validPayments = payments.value
        .map((p) => {
          const rawAmount = p.amount !== null && p.amount !== undefined && p.amount !== '' ? p.amount : p.inputAmount
          return {
            method: p.method,
            amount: parseFloat(rawAmount),
            reference: p.reference || null,
            currency: p.currency || props.selectedCurrency || 'COP',
          }
        })
        .filter((p) => p.method && !isNaN(p.amount) && p.amount > 0)

      if (validPayments.length === 0) {
        toast.warning('Debe ingresar y confirmar al menos un método de pago con un monto mayor a 0.')
        issubmitting.value = false
        return
      }

      let rawOrder = props.orderData
      if (rawOrder && typeof rawOrder === 'object' && 'value' in rawOrder) {
        rawOrder = rawOrder.value
      }
      let orderIdNum = null
      if (typeof rawOrder === 'object' && rawOrder !== null) {
        orderIdNum = rawOrder.id || rawOrder.order_id
      } else if (typeof rawOrder === 'number' || (typeof rawOrder === 'string' && !isNaN(rawOrder))) {
        orderIdNum = rawOrder
      }

      if (!orderIdNum || orderIdNum === '[object Object]' || orderIdNum === 'undefined') {
        console.error("ID de orden no válido detectado:", props.orderData)
        toast.error("No hay una orden activa seleccionada para completar.")
        issubmitting.value = false
        return
      }

      const payload = {
        order_id: orderIdNum,
        payments: validPayments,
        total_amount: roundedTotalAmountToPay.value,
        changeAmount: changeAmount.value,
        changeAmountInCop: changeAmountInCop?.value || 0,
        changeAmountUSD: changeAmountInUsd?.value || 0,
        currency: props.selectedCurrency,
        applies_spe_tax: appliesSpecialTax.value,
        spe_tax_amount: specialTaxAmount.value,
      }

      const response = await axios.post(`/tpv/orders/${orderIdNum}/complete`, payload)

      const isSuccess = response.data?.status === 'success' || response.data?.success === true || response.status === 200

      if (isSuccess) {
        toast.success('Venta completada exitosamente.')
        const resData = response.data?.data || response.data
        receiptOrderData.value = resData.orderCompletada || resData.order || props.orderData
        receiptOrderProducts.value = resData.products || props.orderProducts
        receiptPayments.value = validPayments
        currentProgress.value = 100
        emit('purchase-completed', response.data)
      } else {
        toast.error(response.data?.message || 'Error al procesar la compra.')
      }
    } catch (error) {
      console.error('Error al completar la compra:', error)
      toast.error('Error de servidor al procesar la compra.')
    } finally {
      issubmitting.value = false
    }
  }

  return {
    issubmitting,
    receiptOrderData,
    receiptOrderProducts,
    receiptPayments,
    getProductPrice,
    selectPaymentMethod,
    selectQuickCash,
    confirmPaymentComplete,
    handleCompletePurchase,
  }
}
