import { computed } from 'vue';

export function useCurrencyConverter(exchangeRates) {
  
  const convertAmount = (amountUSD, fromCurrency, toCurrency) => {
    if (!exchangeRates.value || Object.keys(exchangeRates.value).length === 0) {
      return amountUSD;
    }

    const fromCurr = fromCurrency.toUpperCase();
    const toCurr = toCurrency.toUpperCase();

    if (fromCurr === toCurr) return amountUSD;

    if (fromCurr === 'USD') {
      if (toCurr === 'BS') return amountUSD * (exchangeRates.value.BS || 1);
      if (toCurr === 'COP') return amountUSD * (exchangeRates.value.COP || 1);
    }

    if (fromCurr === 'BS') {
      const usdAmount = amountUSD / (exchangeRates.value.BS || 1);
      if (toCurr === 'USD') return usdAmount;
      if (toCurr === 'COP') return usdAmount * (exchangeRates.value.COP || 1);
    }

    if (fromCurr === 'COP') {
      const usdAmount = amountUSD / (exchangeRates.value.COP || 1);
      if (toCurr === 'USD') return usdAmount;
      if (toCurr === 'BS') return usdAmount * (exchangeRates.value.BS || 1);
    }

    return amountUSD;
  };

  const formatCurrency = (amount, currency) => {
    const currencyConfig = {
      'USD': { currency: 'USD', locale: 'en-US' },
      'Bs': { currency: 'VED', locale: 'es-VE' },
      'BS': { currency: 'VED', locale: 'es-VE' },
      'COP': { currency: 'COP', locale: 'es-CO' }
    };

    const config = currencyConfig[currency.toUpperCase()] || currencyConfig['USD'];
    
    return new Intl.NumberFormat(config.locale, {
      style: 'currency',
      currency: config.currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  };

  const getConvertedAmount = (invoice, amountUSD) => {
    const convertedAmount = convertAmount(amountUSD, 'USD', invoice.currency);
    return formatCurrency(convertedAmount, invoice.currency);
  };

  const getRate = computed(() => (currency) => {
    return exchangeRates.value[currency.toUpperCase()] || 1;
  });

  const hasRates = computed(() => {
    return exchangeRates.value && Object.keys(exchangeRates.value).length > 0;
  });

  return {
    convertAmount,
    formatCurrency,
    getConvertedAmount,
    getRate,
    hasRates
  }
}
