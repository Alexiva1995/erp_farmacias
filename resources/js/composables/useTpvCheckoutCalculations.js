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
    return Number(Math.round(num + 'e+2') + 'e-2')
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
    if (fromCurrency === 'USD' && toCurrency === 'COP' && rates['COPC']) {
      return rates['COPC']
    }
    return rates[toCurrency] || 0
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
    } catch (error) {
      toast.error('No se pudieron cargar las tasas de cambio.')
      console.error('Error fetching exchange rates:', error)
      ratesLoaded.value = false
    }
  }

  onMounted(() => {
    fetchExchangeRates()
  })

  const roundedTotalAmountToPay = computed(() => {
    let baseAmount = props.totalAmount
    if (appliesSpecialTax.value) {
      baseAmount += specialTaxAmount.value
    }
    if (props.selectedCurrency === 'COP') {
      return roundUpToNearestHundred(baseAmount)
    }
    return parseFloat(baseAmount.toFixed(2))
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
    const targetCurrency = currency

    if (baseCurrency === targetCurrency) {
      return remainingAmount.value
    }
    if (!ratesLoaded.value) {
      return 0
    }

    const rate = getEffectiveRate(baseCurrency, targetCurrency)
    if (rate <= 0) {
      console.warn(`No hay tasa de cambio de ${baseCurrency} a ${targetCurrency}`)
      return 0
    }

    let converted = remainingAmount.value * rate
    return parseFloat(converted.toFixed(2))
  }

  const changeAmount = computed(() => {
    let totalToPay = props.totalAmount
    if (appliesSpecialTax.value) {
      totalToPay += specialTaxAmount.value
    }

    if (props.selectedCurrency === 'COP') {
      totalToPay = roundUpToNearestHundred(totalToPay)
    } else {
      totalToPay = roundToTwoDecimalPlaces(totalToPay)
    }

    const diff = totalPaidAmount.value - totalToPay
    return Math.max(0, roundToTwoDecimalPlaces(diff))
  })

  const changeAmountInUsd = computed(() => {
    const cashPaymentsInUSD = payments.value.filter(
      (p) => p.method === 'cash_usd' && p.currency === 'USD'
    )
    if (cashPaymentsInUSD.length === 0) return 0

    let totalCashPaidInUSD = 0
    cashPaymentsInUSD.forEach((p) => {
      totalCashPaidInUSD += Number(p.amount) || 0
    })

    let totalOrdenEnUSD
    if (props.selectedCurrency === 'USD') {
      totalOrdenEnUSD = props.totalAmount
    } else {
      const rate = getEffectiveRate('USD', props.selectedCurrency)
      if (rate <= 0) return 0
      totalOrdenEnUSD = props.totalAmount / rate
    }

    const diff = totalCashPaidInUSD - totalOrdenEnUSD
    return Math.max(0, roundToTwoDecimalPlaces(diff))
  })

  const changeAmountInCop = computed(() => {
    const vueltoEnMonedaOrden = changeAmount.value
    if (props.selectedCurrency === 'COP') {
      return vueltoEnMonedaOrden
    }

    const rate = getEffectiveRate(props.selectedCurrency, 'COP')
    if (rate > 0) {
      const vueltoConvertido = vueltoEnMonedaOrden * rate
      return roundUpToNearestHundred(vueltoConvertido)
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
