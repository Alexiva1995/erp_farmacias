import { ref } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

export function useTpvRates(brandingStore, isRestaurant, isSportsRental) {
  const exchangeRates = ref({})
  const ratesLoaded = ref(false)

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
          formattedRates['COP']['BS'] =
            parseFloat(formattedRates['USD']['BS']) /
            parseFloat(formattedRates['USD']['COP'])
          formattedRates['BS']['COP'] =
            parseFloat(formattedRates['USD']['COP']) /
            parseFloat(formattedRates['USD']['BS'])
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
        } else if (!tpvRateType) {
          if ((isRestaurant.value || isSportsRental.value) && formattedRates['USD']['BINANCE']) {
            formattedRates['USD']['BS'] = formattedRates['USD']['BINANCE']
          } else if (!(isRestaurant.value || isSportsRental.value) && formattedRates['USD']['EUR']) {
            formattedRates['USD']['BS'] = formattedRates['USD']['EUR']
          }
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
      console.error('[ORDER_USER] Error fetching exchange rates:', error)
      toast.error('No se pudieron cargar las tasas de cambio.')
    }
  }

  const getEffectiveRate = (fromCurrency, toCurrency) => {
    if (fromCurrency === toCurrency) return 1
    const rates = exchangeRates.value?.[fromCurrency]
    if (!rates) return 0
    return rates[toCurrency] || 0
  }

  return {
    exchangeRates,
    ratesLoaded,
    fetchExchangeRates,
    getEffectiveRate,
  }
}
