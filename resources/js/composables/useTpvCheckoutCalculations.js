import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { roundUpToNearestHundred } from '@/utils/roundUpToNearesHundred.js'

export function useTpvCheckoutCalculations(props, payments, brandingStore) {
  const exchangeRates = ref({})
  const ratesLoaded = ref(false)

  const isCredit = (value) => value === 'credit'
  const isCashMethod = (method) => !!method && (method === 'cash' || method.startsWith('cash_'))

  const requiresReference = (method, currency) => {
    if (isCashMethod(method)) return false
    if (isCredit(method) && currency === 'USD') return false
    if (method === 'balance') return false
    return true
  }

  function roundToTwoDecimalPlaces(num) {
    if (!num || isNaN(num)) return 0
    return Math.round((Number(num) + Number.EPSILON) * 100) / 100
  }

  const appliesSpecialTax = computed(() => {
    return (
      props.isSpecialTaxpayer &&
      (props.selectedCurrency === 'USD' || props.selectedCurrency === 'COP')
    )
  })

  const specialTaxAmount = computed(() => {
    if (!appliesSpecialTax.value) return 0
    let tax = props.totalAmount * 0.03
    if (props.selectedCurrency === 'COP') {
      tax = Math.ceil(tax / 100) * 100
    }
    return tax
  })

  const getEffectiveRate = (fromCurrency, toCurrency) => {
    if (fromCurrency === toCurrency) return 1
    const rates = exchangeRates.value?.[fromCurrency]
    if (!rates) return 0
    return rates[toCurrency] || 0
  }

  const getCopChangeRate = () => {
    const copcRate = getEffectiveRate('USD', 'COPC')
    if (copcRate > 0) return copcRate
    return getEffectiveRate('USD', 'COP')
  }

  const totalPaidAmount = computed(() => {
    let currentSum = 0
    payments.value.forEach((payment) => {
      let amount = Number(payment.amount) || 0
      const rate = getEffectiveRate(payment.currency, props.selectedCurrency)
      if (rate > 0 || payment.currency === props.selectedCurrency) {
        currentSum = roundToTwoDecimalPlaces(currentSum + amount * rate)
      }
    })
    return currentSum
  })

  const fetchExchangeRates = async () => {
    ratesLoaded.value = false
    try {
      const response = await axios.get('/public/exchange-rates')
      if (response.status !== 200) {
        throw new Error(`HTTP error! Status: ${response.status}`)
      }

      const apiRates = response.data
      const formattedRates = {}

      apiRates.forEach((rateItem) => {
        const currencyCode = rateItem.currency_code
        const rateValue = parseFloat(rateItem.rate)

        if (!formattedRates['USD']) formattedRates['USD'] = {}
        formattedRates['USD'][currencyCode] = rateValue
        if (!formattedRates[currencyCode]) formattedRates[currencyCode] = {}
        if (rateValue !== 0) {
          formattedRates[currencyCode]['USD'] = 1 / rateValue
        }

        if (formattedRates['COP'] && formattedRates['BS']) {
          formattedRates['COP']['BS'] = parseFloat(
            (formattedRates['COP']['USD'] * formattedRates['USD']['BS']).toFixed(9)
          )
          formattedRates['BS']['COP'] = parseFloat(
            (formattedRates['BS']['USD'] * formattedRates['USD']['COP']).toFixed(9)
          )
        }
      })

      const tpvRateType = brandingStore.settings?.tpv_rate_type
      if (formattedRates['USD']) {
        if (tpvRateType === 'binance' && formattedRates['USD']['BINANCE']) {
          formattedRates['USD']['BS'] = formattedRates['USD']['BINANCE']
        } else if (tpvRateType === 'eur' && formattedRates['USD']['EUR']) {
          formattedRates['USD']['BS'] = formattedRates['USD']['EUR']
        } else if (tpvRateType === 'bcv' && formattedRates['USD']['BCV']) {
          formattedRates['USD']['BS'] = formattedRates['USD']['BCV']
        } else if (!tpvRateType && formattedRates['USD']['EUR']) {
          formattedRates['USD']['BS'] = formattedRates['USD']['EUR']
        }
        if (formattedRates['BS']) {
          formattedRates['BS']['USD'] = 1 / formattedRates['USD']['BS']
        }
        if (formattedRates['COP']) {
          formattedRates['COP']['BS'] =
            parseFloat(formattedRates['USD']['BS']) /
            parseFloat(formattedRates['USD']['COP'])
          formattedRates['BS']['COP'] =
            parseFloat(formattedRates['USD']['COP']) /
            parseFloat(formattedRates['USD']['BS'])
        }
      }

      exchangeRates.value = formattedRates
      ratesLoaded.value = true
      console.warn('[TPV DEBUG TASAS CARGADAS EN MEMORIA]', formattedRates)
    } catch (error) {
      toast.error('No se pudieron cargar las tasas de cambio.')
      console.error('Error fetching exchange rates:', error)
      ratesLoaded.value = false
    }
  }

  onMounted(() => {
    fetchExchangeRates()
  })

  watch(
    () => props.isDialogVisible,
    (visible) => {
      if (visible) {
        fetchExchangeRates()
      }
    },
    { immediate: true }
  )

  const roundedTotalAmountToPay = computed(() => {
    let baseAmount = props.totalAmount
    if (appliesSpecialTax.value) {
      baseAmount += specialTaxAmount.value
    }
    if (props.selectedCurrency === 'COP') {
      return roundUpToNearestHundred(baseAmount)
    }
    return roundToTwoDecimalPlaces(baseAmount)
  })

  const remainingAmount = computed(() => {
    let totalWithDiscount = props.totalAmount
    if (appliesSpecialTax.value) {
      totalWithDiscount += specialTaxAmount.value
    }

    const rawDifference = totalWithDiscount - totalPaidAmount.value
    if (rawDifference < 0) return 0

    if (props.selectedCurrency === 'COP') {
      return roundUpToNearestHundred(rawDifference)
    }
    return roundToTwoDecimalPlaces(rawDifference)
  })

  const getConvertedRemainingAmount = (currency) => {
    const baseCurrency = props.selectedCurrency
    let targetCurrency = currency ? String(currency).toUpperCase().trim() : ''
    const match = targetCurrency.match(/(USD|COP|BS)/)
    if (match) targetCurrency = match[1]

    if (baseCurrency === targetCurrency) {
      return remainingAmount.value
    }

    if (!ratesLoaded.value) return 0

    // 1. Si la moneda base del pedido es USD
    if (baseCurrency === 'USD') {
      const rateKey = targetCurrency === 'COP' ? 'COP' : targetCurrency
      const usdRates = exchangeRates.value?.['USD']
      const rate = usdRates ? (usdRates[rateKey] || 0) : 0
      if (rate <= 0) return 0
      const result = remainingAmount.value * rate
      const finalVal = targetCurrency === 'COP' ? roundUpToNearestHundred(result) : roundToTwoDecimalPlaces(result)

      console.warn(`[TPV DEBUG RESTANTE] Moneda: ${targetCurrency} | Restante USD: ${remainingAmount.value} | Tasa Usada (${rateKey}): ${rate} | Resultado: ${finalVal}`)

      return finalVal
    }

    // 2. Si la moneda objetivo es USD
    if (targetCurrency === 'USD') {
      const rateToUsd = getEffectiveRate(baseCurrency, 'USD')
      if (rateToUsd > 0) {
        return roundToTwoDecimalPlaces(remainingAmount.value * rateToUsd)
      }
      const rateFromUsd = getEffectiveRate('USD', baseCurrency)
      if (rateFromUsd > 0) {
        return roundToTwoDecimalPlaces(remainingAmount.value / rateFromUsd)
      }
      return 0
    }

    // 3. Conversiones entre otras monedas pivotando a USD
    let rateToUsd = getEffectiveRate(baseCurrency, 'USD')
    if (rateToUsd <= 0) {
      const rateFromUsd = getEffectiveRate('USD', baseCurrency)
      if (rateFromUsd > 0) rateToUsd = 1 / rateFromUsd
    }
    const remainingInUsd = remainingAmount.value * rateToUsd
    const rateUsdToTarget = getEffectiveRate('USD', targetCurrency)
    const result = remainingInUsd * rateUsdToTarget

    if (targetCurrency === 'COP') {
      return roundUpToNearestHundred(result)
    }
    return roundToTwoDecimalPlaces(result)
  }

  const changeAmountInCop = computed(() => {
    let totalPaidInUsd = 0
    let totalPaidInCop = 0

    payments.value.forEach((payment) => {
      let amount = Number(payment.amount) || 0
      if (payment.currency === 'COP') {
        totalPaidInCop += amount
      } else if (payment.currency === 'USD') {
        totalPaidInUsd += amount
      } else {
        const rateToUsd = getEffectiveRate(payment.currency, 'USD')
        if (rateToUsd > 0) {
          totalPaidInUsd += amount * rateToUsd
        }
      }
    })

    const totalToPayUsd = props.totalAmount + (appliesSpecialTax.value ? specialTaxAmount.value : 0)

    // Caso 1: Los pagos en USD superan el total de la orden -> Usar tasa COPC
    if (totalPaidInUsd > totalToPayUsd) {
      const copChangeRate = getCopChangeRate()
      const changeInUsd = totalPaidInUsd - totalToPayUsd
      const changeInCopFromUsd = changeInUsd * copChangeRate
      const totalCopChange = totalPaidInCop + changeInCopFromUsd
      if (totalCopChange <= 0) return 0
      return roundUpToNearestHundred(totalCopChange)
    }

    // Caso 2: Los pagos en USD NO superan la orden -> Calcular restante en COP con tasa estándar y obtener vuelto sobre pagos en COP
    const remainingUsdToPay = Math.max(0, totalToPayUsd - totalPaidInUsd)
    const standardCopRate = getEffectiveRate('USD', 'COP')
    const remainingCopToPay = roundUpToNearestHundred(remainingUsdToPay * standardCopRate)
    const diffCop = totalPaidInCop - remainingCopToPay
    if (diffCop <= 0) return 0
    return roundUpToNearestHundred(diffCop)
  })

  const changeAmount = computed(() => {
    if (props.selectedCurrency === 'COP') {
      return changeAmountInCop.value
    }

    let totalPaidInUsd = 0
    payments.value.forEach((payment) => {
      let amount = Number(payment.amount) || 0
      if (payment.currency === 'USD') {
        totalPaidInUsd += amount
      } else if (payment.currency !== 'COP') {
        const rateToUsd = getEffectiveRate(payment.currency, 'USD')
        if (rateToUsd > 0) totalPaidInUsd += amount * rateToUsd
      }
    })
    const totalToPayUsd = props.totalAmount + (appliesSpecialTax.value ? specialTaxAmount.value : 0)

    const isUsdSurplus = totalPaidInUsd > totalToPayUsd
    const effectiveRate = isUsdSurplus ? getCopChangeRate() : getEffectiveRate('USD', 'COP')

    if (effectiveRate > 0 && changeAmountInCop.value > 0) {
      return roundToTwoDecimalPlaces(changeAmountInCop.value / effectiveRate)
    }
    const diff = totalPaidAmount.value - roundedTotalAmountToPay.value
    return Math.max(0, roundToTwoDecimalPlaces(diff))
  })

  const changeAmountInUsd = computed(() => {
    if (props.selectedCurrency === 'USD') {
      return changeAmount.value
    }
    const rateToUsd = getEffectiveRate(props.selectedCurrency, 'USD')
    if (rateToUsd > 0) {
      return roundToTwoDecimalPlaces(changeAmount.value * rateToUsd)
    }
    const rateFromUsd = getEffectiveRate('USD', props.selectedCurrency)
    if (rateFromUsd > 0) {
      return roundToTwoDecimalPlaces(changeAmount.value / rateFromUsd)
    }
    return 0
  })

  const showChangeAmount = computed(() => {
    const hasRelevantCashPayment = Array.isArray(payments.value) && payments.value.some(
      (payment) =>
        (payment.method === 'cash_usd' && payment.currency === 'USD') ||
        (payment.method === 'cash_bs' && payment.currency === 'BS') ||
        (payment.method === 'cash_cop' && payment.currency === 'COP')
    )
    return hasRelevantCashPayment && changeAmount.value > 0
  })

  return {
    exchangeRates,
    ratesLoaded,
    isCredit,
    isCashMethod,
    requiresReference,
    roundToTwoDecimalPlaces,
    appliesSpecialTax,
    specialTaxAmount,
    getEffectiveRate,
    totalPaidAmount,
    fetchExchangeRates,
    roundedTotalAmountToPay,
    remainingAmount,
    getConvertedRemainingAmount,
    changeAmount,
    changeAmountInUsd,
    changeAmountInCop,
    showChangeAmount,
  }
}
