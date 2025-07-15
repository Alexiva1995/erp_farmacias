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
  let digital=0;

  if (currency === 'BS') {
    locale = 'es-VE';
    currencySymbol = ' BS';
  } else if (currency === 'COP') {
    locale = 'es-CO';
    currencySymbol = ' COP';
  } else if (currency === 'USD') {
    locale = 'en-US';
    currencySymbol = ' USD';
  } else {
    locale = 'en-US';
    currencySymbol = ' USD';
  }


  if (currency === 'COP') { 
      digital = 0;
  }else{
      digital = 2;
  }


  const formatter = new Intl.NumberFormat(locale, {
    minimumFractionDigits: digital,
    maximumFractionDigits: digital,
    useGrouping: true,
  });

  const formattedValue = formatter.format(value);

  return `${formattedValue}${currencySymbol}`;
};
