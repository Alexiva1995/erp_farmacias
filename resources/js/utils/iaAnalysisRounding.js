/**
 * Redondeo personalizado para el análisis de IA:
 * Si el decimal es > 0.333, redondea hacia arriba (lejos de cero).
 * Si es <= 0.333, redondea hacia abajo (hacia cero).
 * Retorna siempre un entero.
 */
export function roundIaAnalysis(value) {
  if (value === null || value === undefined || value === "") return 0;
  
  const num = parseFloat(value);
  if (isNaN(num)) return 0;
  
  const sign = Math.sign(num);
  const abs = Math.abs(num);
  const floor = Math.floor(abs);
  const decimal = abs - floor;
  
  const roundedAbs = decimal > 0.333 ? Math.ceil(abs) : floor;
  
  // Retornar 0 si el resultado es -0 para evitar visualización extraña
  const result = roundedAbs * sign;
  return result === 0 ? 0 : result;
}
