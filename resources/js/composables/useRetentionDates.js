// Composable para gestión de fechas y quincenas fiscales SENIAT
import { ref } from "vue";

export function useRetentionDates() {
  const startDate = ref("");
  const endDate = ref("");
  const selectedPreset = ref("fortnight_current");

  // Aplica preajuste de fechas (Quincena Actual o Pasada)
  const applyDatePreset = (presetKey) => {
    selectedPreset.value = presetKey;
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const day = now.getDate();

    const currentMonthStr = String(month + 1).padStart(2, "0");
    const lastDayCurrentMonth = new Date(year, month + 1, 0).getDate();

    if (presetKey === "fortnight_current") {
      if (day <= 15) {
        startDate.value = `${year}-${currentMonthStr}-01`;
        endDate.value = `${year}-${currentMonthStr}-15`;
      } else {
        startDate.value = `${year}-${currentMonthStr}-16`;
        endDate.value = `${year}-${currentMonthStr}-${lastDayCurrentMonth}`;
      }
    } else if (presetKey === "fortnight_previous") {
      if (day <= 15) {
        const prevMonthDate = new Date(year, month - 1, 1);
        const prevYear = prevMonthDate.getFullYear();
        const prevMonthStr = String(prevMonthDate.getMonth() + 1).padStart(2, "0");
        const lastDayPrevMonth = new Date(prevYear, prevMonthDate.getMonth() + 1, 0).getDate();

        startDate.value = `${prevYear}-${prevMonthStr}-16`;
        endDate.value = `${prevYear}-${prevMonthStr}-${lastDayPrevMonth}`;
      } else {
        startDate.value = `${year}-${currentMonthStr}-01`;
        endDate.value = `${year}-${currentMonthStr}-15`;
      }
    }
  };

  const setFortnightPreset = () => {
    applyDatePreset("fortnight_current");
  };

  // Obtiene la fecha fiscal prevista (formato YYYY-MM-DD)
  const getCalculatedFiscalDateIso = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const day = now.getDate();

    let fiscalDate;
    if (day >= 14 && day <= 17) {
      fiscalDate = new Date(year, month, 15);
    } else if (day >= 30) {
      fiscalDate = new Date(year, month + 1, 0);
    } else if (day >= 1 && day <= 2) {
      fiscalDate = new Date(year, month, 0);
    } else if (day < 14) {
      fiscalDate = new Date(year, month, 15);
    } else {
      fiscalDate = new Date(year, month + 1, 0);
    }

    const y = fiscalDate.getFullYear();
    const m = String(fiscalDate.getMonth() + 1).padStart(2, "0");
    const d = String(fiscalDate.getDate()).padStart(2, "0");

    return `${y}-${m}-${d}`;
  };

  return {
    startDate,
    endDate,
    selectedPreset,
    applyDatePreset,
    setFortnightPreset,
    getCalculatedFiscalDateIso,
  };
}
