/**
 * Formatea una fecha a YYYY-MM-DD (UTC).
 * @param {string|Date} dateString 
 * @returns {string}
 */
export const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return "Fecha inválida";
    const year = date.getUTCFullYear();
    const month = (date.getUTCMonth() + 1).toString().padStart(2, "0");
    const day = date.getUTCDate().toString().padStart(2, "0");
    return `${year}-${month}-${day}`;
  } catch (error) {
    return "Fecha inválida";
  }
};

/**
 * Formatea un precio a formato moneda bolivares/dólares (VE).
 * @param {number} price 
 * @returns {string}
 */
export const formatPrice = (price) => {
  const numPrice = Number(price);
  if (isNaN(numPrice)) return "0,00";
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(numPrice);
};

/**
 * Formatea un stock total sumando las cantidades de los lotes.
 * @param {Object} product 
 * @returns {number}
 */
export const calculateStock = (product) => {
  if (!product.lots || !Array.isArray(product.lots)) return 0;
  return product.lots.reduce((sum, lot) => sum + Number(lot.quantity || 0), 0);
};

/**
 * Formatea un string de mes YYYY-MM a "Mes Año" (ej. "Enero 2025").
 * @param {string} monthStr 
 * @returns {string}
 */
export const formatMonth = (monthStr) => {
  if (!monthStr) return "";
  const [year, month] = monthStr.split("-");
  return new Date(year, month - 1).toLocaleString("es-CO", {
    month: "long",
    year: "numeric",
  });
};
