// Composable para gestión de fechas y quincenas fiscales SENIAT
import { computed, ref } from "vue";

export function useRetentionDates() {
  const startDate = ref("");
  const endDate = ref("");
  const selectedPreset = ref("fortnight_current");

  const now = new Date();
  const currentYear = now.getFullYear();

  const monthsOfYear = [
    { name: "Enero", abbr: "Ene", index: 0 },
    { name: "Febrero", abbr: "Feb", index: 1 },
    { name: "Marzo", abbr: "Mar", index: 2 },
    { name: "Abril", abbr: "Abr", index: 3 },
    { name: "Mayo", abbr: "May", index: 4 },
    { name: "Junio", abbr: "Jun", index: 5 },
    { name: "Julio", abbr: "Jul", index: 6 },
    { name: "Agosto", abbr: "Ago", index: 7 },
    { name: "Septiembre", abbr: "Sep", index: 8 },
    { name: "Octubre", abbr: "Oct", index: 9 },
    { name: "Noviembre", abbr: "Nov", index: 10 },
    { name: "Diciembre", abbr: "Dic", index: 11 },
  ];

  const datePresets = computed(() => {
    const list = [
      { title: "Quincena Actual", value: "fortnight_current" },
      { title: "Quincena Pasada", value: "fortnight_previous" },
    ];

    monthsOfYear.forEach((m) => {
      const mStr = String(m.index + 1).padStart(2, "0");
      const lastDay = new Date(currentYear, m.index + 1, 0).getDate();

      list.push({
        title: `1ra Quincena ${m.name}`,
        value: `q_${currentYear}_${m.index}_1`,
        startDate: `${currentYear}-${mStr}-01`,
        endDate: `${currentYear}-${mStr}-15`,
      });

      list.push({
        title: `2da Quincena ${m.name}`,
        value: `q_${currentYear}_${m.index}_2`,
        startDate: `${currentYear}-${mStr}-16`,
        endDate: `${currentYear}-${mStr}-${lastDay}`,
      });
    });

    return list;
  });

  // Aplica preajuste de fechas (Quincena Actual, Pasada o específica del año)
  const applyDatePreset = (presetKey) => {
    selectedPreset.value = presetKey;
    const n = new Date();
    const year = n.getFullYear();
    const month = n.getMonth();
    const day = n.getDate();

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
    } else {
      const match = datePresets.value.find((p) => p.value === presetKey);
      if (match && match.startDate && match.endDate) {
        startDate.value = match.startDate;
        endDate.value = match.endDate;
      }
    }
  };

  const setFortnightPreset = () => {
    applyDatePreset("fortnight_current");
  };

  // Obtiene la fecha fiscal prevista (formato YYYY-MM-DD)
  const getCalculatedFiscalDateIso = () => {
    const n = new Date();
    const year = n.getFullYear();
    const month = n.getMonth();
    const day = n.getDate();

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
    datePresets,
    applyDatePreset,
    setFortnightPreset,
    getCalculatedFiscalDateIso,
  };
}
