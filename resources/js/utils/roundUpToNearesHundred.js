export const roundUpToNearestHundred = (value) => {
  if (!value || isNaN(value)) return 0;
  const cleanVal = Math.round(Number(value) * 100) / 100;
  const roundedInt = Math.round(cleanVal);
  if (roundedInt % 100 === 0) {
    return roundedInt;
  }
  return Math.ceil(roundedInt / 100) * 100;
};
