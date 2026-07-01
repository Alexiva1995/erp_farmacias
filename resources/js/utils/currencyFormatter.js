// src/utils/currencyFormatter.js

/**
 * Formatea un valor numérico como una cantidad de moneda.
 * El símbolo de la moneda se coloca después del monto.
 *
 * @param {number} value - El valor numérico a formatear.
 * @param {string} currency - El código de la moneda ('USD', 'VES', 'COP').
 * @returns {string} El valor formateado con el símbolo de la moneda.
 */
import { useBrandingStore } from '@/stores/useBrandingStore';

/** Locale con miles en . y decimales en , (ej. 1.234.567,89) */
const LOCALE_NUMBERS = 'es-ES';

export const formatCurrency = (value, currency) => {
  const numericValue = typeof value === 'string' ? parseFloat(value) : value;

  if (typeof numericValue !== 'number' || isNaN(numericValue)) {
    return '0,00';
  }

  let val = numericValue;
  let activeCurrency = currency;

  try {
    const brandingStore = useBrandingStore();
    const defaultCurrency = brandingStore.settings?.default_currency;
    
    if (defaultCurrency === 'COP') {
      if (activeCurrency === 'USD' || !activeCurrency) {
        const copRateObj = brandingStore.exchangeRates?.find(r => r.currency_code === 'COP');
        const rate = copRateObj ? parseFloat(copRateObj.rate) : 4000;
        val = numericValue * rate;
        activeCurrency = 'COP';
      }
    }
  } catch (e) {
    // Si se llama fuera de un contexto activo de Pinia / Vue
  }

  let currencySymbol = '';
  let digital = 2;

  if (activeCurrency === 'BS' || activeCurrency === 'Bs') {
    currencySymbol = ' Bs';
  } else if (activeCurrency === 'COP') {
    currencySymbol = ' COP';
    digital = 0; // COP sin decimales
  } else if (activeCurrency === 'USD') {
    currencySymbol = ' USD';
  } else {
    currencySymbol = '';
  }

  const formatter = new Intl.NumberFormat(LOCALE_NUMBERS, {
    minimumFractionDigits: digital,
    maximumFractionDigits: digital,
    useGrouping: true,
  });

  const formattedValue = formatter.format(val);
  return `${formattedValue}${currencySymbol}`;
};

/**
 * Formatea solo el monto numérico, sin símbolo ni código de moneda.
 * @param {number} value - El valor numérico a formatear.
 * @param {string} currency - El código de la moneda (para locale y decimales).
 * @returns {string} El valor formateado sin moneda.
 */
export const formatAmountOnly = (value, currency) => {
  const numericValue = typeof value === 'string' ? parseFloat(value) : value;

  if (typeof numericValue !== 'number' || isNaN(numericValue)) {
    return '0,00';
  }

  let val = numericValue;
  let activeCurrency = currency;

  try {
    const brandingStore = useBrandingStore();
    const defaultCurrency = brandingStore.settings?.default_currency;
    if (defaultCurrency === 'COP') {
      if (activeCurrency === 'USD' || !activeCurrency) {
        const copRateObj = brandingStore.exchangeRates?.find(r => r.currency_code === 'COP');
        const rate = copRateObj ? parseFloat(copRateObj.rate) : 4000;
        val = numericValue * rate;
        activeCurrency = 'COP';
      }
    }
  } catch (e) {}

  let digital = 2;
  if (activeCurrency === 'COP') {
    digital = 0; // COP sin decimales
  }
  const formatter = new Intl.NumberFormat(LOCALE_NUMBERS, {
    minimumFractionDigits: digital,
    maximumFractionDigits: digital,
    useGrouping: true,
  });
  return formatter.format(val);
};
