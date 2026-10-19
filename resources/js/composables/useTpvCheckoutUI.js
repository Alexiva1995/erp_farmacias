import { computed } from 'vue'

export function useTpvCheckoutUI({
  props,
  payments,
  brandingStore,
  selectedCurrencyTab,
  currentProgress,
  requiresReference,
}) {
  const fallbackPaymentMethods = {
    COP: [
      { label: "Efectivo", value: "cash_cop" },
      { label: "Transferencia", value: "bank_transfer" },
    ],
    BS: [
      { label: "Efectivo", value: "cash_bs" },
      { label: "P. Móvil", value: "mobile_payment" },
      { label: "Transf.", value: "bank_transfer_bs" },
      { label: "Débito", value: "debit_card" },
      { label: "T. Crédito", value: "credit_card" },
      { label: "Crédito", value: "credit" },
    ],
    USD: [
      { label: "Efectivo", value: "cash_usd" },
      { label: "Binance", value: "binance" },
      { label: "PayPal", value: "paypal" },
      { label: "Crédito", value: "credit" },
      { label: "Saldo", value: "balance" },
    ],
  }

  const normalizeMethodValue = (val, currencyKey) => {
    if (val === "cash") {
      if (currencyKey === "USD") return "cash_usd"
      if (currencyKey === "BS") return "cash_bs"
      if (currencyKey === "COP") return "cash_cop"
    }
    if (val === "bank_transfer" && currencyKey === "BS") {
      return "bank_transfer_bs"
    }
    return val
  }

  const isFifthForeignSale = computed(() => {
    return (props.foreignOrdersCount + 1) % 5 === 0
  })

  const shouldApplySpeRules = computed(() => {
    return isFifthForeignSale.value || props.allForeignSalesSpe
  })

  const allCurrenciesList = [
    { label: 'Pesos Colombianos (COP)', value: 'COP' },
    { label: 'Bolívares (BS)', value: 'BS' },
    { label: 'Dólares (USD)', value: 'USD' },
  ]

  const currencies = computed(() => {
    const configured = brandingStore.settings?.tpv_payment_methods
    if (!configured) return allCurrenciesList

    const enabled = allCurrenciesList.filter((c) => {
      const curObj = configured[c.value]
      if (!curObj) return false
      if (typeof curObj === 'object' && !Array.isArray(curObj) && curObj.enabled !== undefined) {
        return !!curObj.enabled
      }
      if (Array.isArray(curObj)) {
        return curObj.length > 0 && curObj.some((m) => m.enabled !== false)
      }
      return true
    })

    return enabled.length > 0 ? enabled : allCurrenciesList
  })

  const paymentMethodsByCurrency = computed(() => {
    const configured = brandingStore.settings?.tpv_payment_methods
    if (!configured) return fallbackPaymentMethods

    const result = {}
    ;['COP', 'BS', 'USD'].forEach((currencyKey) => {
      const curObj = configured[currencyKey]
      let rawMethods = []
      if (Array.isArray(curObj)) {
        rawMethods = curObj
      } else if (curObj && Array.isArray(curObj.methods)) {
        rawMethods = curObj.methods
      } else {
        rawMethods = fallbackPaymentMethods[currencyKey] || []
      }

      const shortenLabel = (lbl, val) => {
        if (!lbl) return ''
        const lower = lbl.toLowerCase()
        if (lower.includes('pago móvil') || lower.includes('pago movil')) return 'P. Móvil'
        if (lower.includes('débito') || lower.includes('debito')) return 'Débito'
        if (val === 'credit_card' || (lower.includes('tarjeta') && (lower.includes('crédito') || lower.includes('credito')))) return 'T. Crédito'
        if (val === 'credit' || lower.includes('crédito') || lower.includes('credito')) return 'Crédito'
        if (lower.includes('transferencia')) return 'Transf.'
        if (lower.includes('efectivo')) return 'Efectivo'
        return lbl
      }

      const enabledMethods = rawMethods
        .filter((m) => m.enabled !== false)
        .map((m) => {
          const normalizedVal = normalizeMethodValue(m.value, currencyKey)
          return {
            ...m,
            label: shortenLabel(m.label, normalizedVal),
            value: normalizedVal,
          }
        })

      if (currencyKey === 'BS' && !rawMethods.some((m) => m.value === 'credit')) {
        enabledMethods.push({ label: 'Crédito', value: 'credit', enabled: true })
      }

      if (currencyKey === 'USD' && props.orderData?.client?.balance > 0) {
        if (!enabledMethods.some((m) => m.value === 'balance')) {
          enabledMethods.push({ label: 'Saldo', value: 'balance', enabled: true })
        }
      }

      result[currencyKey] = enabledMethods
    })

    return result
  })

  const continueButtonText = computed(() => {
    return currentProgress.value === 100 ? 'Finalizar' : 'Continuar'
  })

  const getPaymentMethodLabel = (methodValue, currency) => {
    if (methodValue === 'balance') return 'Saldo'
    if (!methodValue) return 'N/A'
    const methodsForCurrency = paymentMethodsByCurrency.value[currency]
    if (methodsForCurrency) {
      const foundMethod = methodsForCurrency.find((m) => m.value === methodValue)
      if (foundMethod) return foundMethod.label
    }
    for (const key in paymentMethodsByCurrency.value) {
      const methods = paymentMethodsByCurrency.value[key]
      const foundMethod = methods.find((m) => m.value === methodValue)
      if (foundMethod) return foundMethod.label
    }
    return methodValue.replace(/_/g, ' ').toUpperCase()
  }

  const getPaymentMethodIcon = (methodValue) => {
    const icons = {
      cash: 'tabler-cash',
      cash_bs: 'tabler-cash',
      cash_cop: 'tabler-cash',
      cash_usd: 'tabler-cash',
      mobile_payment: 'tabler-device-mobile',
      bank_transfer: 'tabler-building-bank',
      bank_transfer_bs: 'tabler-building-bank',
      bank_transfer_usd: 'tabler-building-bank',
      debit_card: 'tabler-credit-card',
      credit_card: 'tabler-credit-card',
      card: 'tabler-credit-card',
      binance: 'tabler-currency-bitcoin',
      paypal: 'tabler-brand-paypal',
      zelle: 'tabler-send',
      credit: 'tabler-file-invoice',
      balance: 'tabler-wallet',
    }
    return icons[methodValue] || 'tabler-wallet'
  }

  const isPaymentMethodActive = (methodValue, currency) => {
    return Array.isArray(payments.value) && payments.value.some(
      (p) => p.method === methodValue && p.currency === currency && (p.amount > 0 || p._isInputActive)
    )
  }

  const isPaymentMethodAdded = () => false

  const isLastPaymentAdded = (payment) => {
    if (!Array.isArray(payments.value)) return false
    const paymentsWithMethod = payments.value.filter((p) => p.method)
    if (paymentsWithMethod.length === 0) return false
    const lastPayment = paymentsWithMethod[paymentsWithMethod.length - 1]
    return payments.value.indexOf(payment) === payments.value.indexOf(lastPayment)
  }

  const getAvailableMethodsForCurrency = (currency) => {
    const methods = paymentMethodsByCurrency.value[currency] || []
    return methods.filter((m) => {
      if (m.value === 'balance') {
        return currency === 'USD' && props.orderData?.client?.balance > 0
      }
      if (m.value === 'credit') {
        return Array.isArray(payments.value) && payments.value.length === 1 && !payments.value[0].method
      }
      return true
    })
  }

  const hasMissingReferences = () => {
    return Array.isArray(payments.value) && payments.value.some(
      (p) => p.method && requiresReference(p.method, p.currency) && (!p.reference || p.reference.trim() === '')
    )
  }

  return {
    isFifthForeignSale,
    shouldApplySpeRules,
    currencies,
    paymentMethodsByCurrency,
    continueButtonText,
    getPaymentMethodLabel,
    getPaymentMethodIcon,
    isPaymentMethodActive,
    isPaymentMethodAdded,
    isLastPaymentAdded,
    getAvailableMethodsForCurrency,
    hasMissingReferences,
  }
}
