// src/utils/currencyFormatter.js

/**
 * Formatea un valor numérico como una cantidad de moneda.
 * El símbolo de la moneda se coloca después del monto.
 *
 * @param {number} value - El valor numérico a formatear.
 * @param {string} currency - El código de la moneda ('USD', 'VES', 'COP').
 * @returns {string} El valor formateado con el símbolo de la moneda.
 */
/** Locale con miles en . y decimales en , (ej. 1.234.567,89) */
const LOCALE_NUMBERS = 'es-ES';

export const formatCurrency = (value, currency) => {
  if (typeof value !== 'number' || isNaN(value)) {
    value = 0;
  }

  let currencySymbol = '';
  let digital = 2;

  if (currency === 'BS' || currency === 'Bs') {
    currencySymbol = ' Bs';
  } else if (currency === 'COP') {
    currencySymbol = ' COP';
    digital = 0; // COP sin decimales
  } else if (currency === 'USD') {
    currencySymbol = ' USD';
  } else {
    currencySymbol = '';
  }

  const formatter = new Intl.NumberFormat(LOCALE_NUMBERS, {
    minimumFractionDigits: digital,
    maximumFractionDigits: digital,
    useGrouping: true,
  });

  const formattedValue = formatter.format(value);
  return `${formattedValue}${currencySymbol}`;
};

/**
 * Formatea solo el monto numérico, sin símbolo ni código de moneda.
 * @param {number} value - El valor numérico a formatear.
 * @param {string} currency - El código de la moneda (para locale y decimales).
 * @returns {string} El valor formateado sin moneda.
 */
export const formatAmountOnly = (value, currency) => {
  if (typeof value !== 'number' || isNaN(value)) {
    value = 0;
  }
  let digital = 2;
  if (currency === 'COP') {
    digital = 0; // COP sin decimales
  }
  const formatter = new Intl.NumberFormat(LOCALE_NUMBERS, {
    minimumFractionDigits: digital,
    maximumFractionDigits: digital,
    useGrouping: true,
  });
  return formatter.format(value);
};
