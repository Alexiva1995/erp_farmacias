// src/utils/currencyFormatter.js

/**
 * Formatea un valor numérico como una cantidad de moneda.
 * El símbolo de la moneda se coloca después del monto.
 *
 * @param {number} value - El valor numérico a formatear.
 * @param {string} currency - El código de la moneda ('USD', 'VES', 'COP').
 * @returns {string} El valor formateado con el símbolo de la moneda.
 */
export const formatCurrency = (value, currency) => {
  if (typeof value !== 'number' || isNaN(value)) {
    value = 0;
  }

  let locale = 'en-US';
  let currencySymbol = '';

  if (currency === 'BS') {
    locale = 'es-VE';
    currencySymbol = ' Bs';
  } else if (currency === 'COP') {
    locale = 'es-CO';
    currencySymbol = ' COP';
  } else if (currency === 'USD') {
    locale = 'en-US';
    currencySymbol = ' $';
  } else {
    locale = 'en-US';
    currencySymbol = '$';
  }

  const formatter = new Intl.NumberFormat(locale, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
    useGrouping: true,
  });

  const formattedValue = formatter.format(value);

  return `${formattedValue}${currencySymbol}`;
};
