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
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
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

/**
 * Calcula el stock válido excluyendo lotes vencidos.
 * @param {Object} product 
 * @returns {number}
 */
export const calculateValidStock = (product) => {
  if (!product.lots || !Array.isArray(product.lots)) return 0;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return product.lots
    .filter((lot) => lot.expiration_date && new Date(lot.expiration_date) >= today)
    .reduce((sum, lot) => sum + Number(lot.quantity || 0), 0);
};

/**
 * Obtiene la fecha de próximo vencimiento válida.
 * @param {Object} product 
 * @returns {string} (YYYY-MM-DD o mensaje informativo)
 */
export const nextExpirationDate = (product) => {
  if (!product.lots || !Array.isArray(product.lots) || product.lots.length === 0) return "N/A";
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validLots = product.lots.filter((lot) => {
    if (!lot.expiration_date) return false;
    const expirationDate = new Date(lot.expiration_date);
    return !isNaN(expirationDate.getTime()) && expirationDate >= today;
  });
  if (validLots.length === 0) return "Todos expiraron";
  validLots.sort((a, b) => new Date(a.expiration_date) - new Date(b.expiration_date));
  return validLots[0].expiration_date;
};
