import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

const LOGO = "/images/logoDonative.png";
const LOGO_WIDTH = 90;
const LOGO_HEIGHT = 35;

export default function pdfPayslipsGenerator(data, type) {
  const doc = new jsPDF({
    orientation: "l",
  });

  const pageWidth = doc.internal.pageSize.getWidth();
  const xPosition = (pageWidth - LOGO_WIDTH) / 2;

  try {
    doc.addImage(LOGO, "PNG", xPosition, 5, LOGO_WIDTH, LOGO_HEIGHT);
  } catch (error) {
    console.error("jsPDF no pudo añadir la imagen del logo.", error);
  }

  doc.setFont("helvetica", "bold");
  doc.setFontSize(10);
  doc.setTextColor(0, 0, 0);
  doc.text("FARMACIA BARRIO SUCRE 2024 C.A", 15, 20);
  doc.text("J505406957", 15, 25);
  doc.text("Tipo de Nomina: Cada 15 dias", 15, 30);
  doc.text(`Periodo: ${data.period}`, 15, 35);
  doc.setFont("helvetica", "normal");

  const formatBs = (amount) => {
    const newAmount = toNum(amount)

    return (
      new Intl.NumberFormat("es-VE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(newAmount) + " Bs."
    );
  };

  let hasMoreThanYear = false
  const isDecember = new Date().getMonth() === 11

  const toNum = (v) => {
    const n = v >> 0;
    return n === n ? n : Number(v) || 0;
  };

  const calcVouchers = (
    food,
    transport,
    perf,
    inv,
    sales,
    salesGr,
    assProd,
    earn,
    vacBonus,
    vac,
    fam,
    salary,
    social,
    employ,
    housing,
    daysOff,
    loans,
    settlement
  ) => {
    const pos =
      (toNum(food) +
        toNum(transport) +
        toNum(perf) +
        toNum(inv) +
        toNum(sales) +
        toNum(salesGr) +
        toNum(assProd) +
        toNum(earn) +
        toNum(vacBonus) +
        toNum(vac) +
        toNum(fam) +
        toNum(salary)) 
    const neg =
      (toNum(social) +
        toNum(employ) +
        toNum(housing) +
        toNum(daysOff) +
        toNum(loans) +
        toNum(settlement)) 

    return {
      positive: Math.round(pos * 100) / 100,
      negative: Math.round(neg * 100) / 100,
    };
  };
  

  const tableYPosition = 50;
  const tableRows = [];

  let totalPayslip = 0;
  let totalSalaryVoucher = 0;
  let totalFoodVoucher = 0;
  let totalTransportationVoucher = 0;
  let totalPerformanceVoucher = 0;
  let totalInvoiceVoucher = 0;
  let totalSalesVoucher = 0;
  let totalSalesGrowthVoucher = 0;
  let totalAssignedProductsVoucher = 0;
  let totalEarningsVoucher = 0;
  let totalVacationBonusVoucher = 0;
  let totalVacationVoucher = 0;
  let totalFamilySupportVoucher = 0;
  let totalPositiveVouchers = 0;
  let totalSocialSecurityVoucher = 0;
  let totalEmploymentVoucher = 0;
  let totalHousingPropertyBenefitsVoucher = 0;
  let totalDaysNotWorkedVoucher = 0;
  let totalLoansVoucher = 0;
  let totalSettlementVoucher = 0;
  let totalNegativeVouchers = 0;

  data.results.forEach((record) => {
    const {
      id,
      name,
      last_name,
      identification,
      role,
      food_voucher,
      transportation_voucher,
      performance_voucher,
      invoice_voucher,
      sales_voucher,
      sales_growth_voucher,
      assigned_products_voucher,
      base_salary_voucher,
      salary_to_pay_voucher,
      earnings_voucher,
      vacation_voucher,
      vacation_bonus_voucher,
      settlement_voucher,
      family_support_voucher,
      social_security_voucher,
      employment_voucher,
      housing_property_benefits_voucher,
      days_not_worked_voucher,
      loans_voucher,
      active_years
    } = record;
    if (isDecember) {
      totalEarningsVoucher += toNum(earnings_voucher);
    }

    if (active_years - 1 >= 2) {
      if (!hasMoreThanYear) hasMoreThanYear = true
      totalVacationBonusVoucher += toNum(vacation_bonus_voucher);
      totalVacationVoucher += toNum(vacation_voucher);
    }

    const { positive: positive_vouchers, negative: negative_vouchers } =
      calcVouchers(
        food_voucher,
        transportation_voucher,
        performance_voucher,
        invoice_voucher,
        sales_voucher,
        sales_growth_voucher,
        assigned_products_voucher,
        type === 'full' && isDecember ? earnings_voucher : 0,
        type === 'full' && hasMoreThanYear ? vacation_bonus_voucher : 0,
        type === 'full' && hasMoreThanYear ? vacation_voucher : 0,
        family_support_voucher,
        salary_to_pay_voucher,
        social_security_voucher,
        employment_voucher,
        housing_property_benefits_voucher,
        days_not_worked_voucher,
        loans_voucher,
        settlement_voucher
      );
    const total = toNum(positive_vouchers - negative_vouchers)
    totalSalaryVoucher += toNum(salary_to_pay_voucher);
    totalFoodVoucher += toNum(food_voucher);
    totalTransportationVoucher += toNum(transportation_voucher);
    totalPerformanceVoucher += toNum(performance_voucher);
    totalInvoiceVoucher += toNum(invoice_voucher);
    totalSalesVoucher += toNum(sales_voucher);
    totalSalesGrowthVoucher += toNum(sales_growth_voucher);
    totalAssignedProductsVoucher += toNum(assigned_products_voucher);
    totalFamilySupportVoucher += toNum(family_support_voucher);
    totalPositiveVouchers += positive_vouchers;
    totalSocialSecurityVoucher += toNum(social_security_voucher);
    totalEmploymentVoucher += toNum(employment_voucher);
    totalHousingPropertyBenefitsVoucher += toNum(
      housing_property_benefits_voucher
    );
    totalDaysNotWorkedVoucher += toNum(days_not_worked_voucher);
    totalLoansVoucher += toNum(loans_voucher);
    totalSettlementVoucher += toNum(settlement_voucher);
    totalNegativeVouchers += negative_vouchers;
    totalPayslip += total;

    const recordData = [
      // COD
      {
        content: id,
        styles: { halign: "right" },
      },
      // Nombre
      `${name} ${last_name}`,
      // Cedula
      `V${identification}`,
      // Cargo
      role,
      // Salario mensual
      {
        content: formatBs(base_salary_voucher),
        styles: { halign: "right" },
      },
      // Sueldo a pagar
      {
        content: formatBs(salary_to_pay_voucher),
        styles: { halign: "right" },
      },
      // Bono de alimentacion
      {
        content: formatBs(food_voucher),
        styles: { halign: "right" },
      },
      // Bono de transporte
      type === "full"
        ? {
            content: formatBs(transportation_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Bono de rendimiento
      type === "full"
        ? {
            content: formatBs(performance_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Bono de facturas
      type === "full"
        ? {
            content: formatBs(invoice_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Bono de ventas
      type === "full"
        ? {
            content: formatBs(sales_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Bono de crecimiento de ventas
      type === "full"
        ? {
            content: formatBs(sales_growth_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Bono de productos asignados
      type === "full"
        ? {
            content: formatBs(assigned_products_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Utilidades
      type === "full" && isDecember
        ? {
            content: formatBs(earnings_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Bono Vacacional
      type === "full" && hasMoreThanYear
        ? {
            content: formatBs(vacation_bonus_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Vacaciones
      type === "full" && hasMoreThanYear
        ? {
            content: formatBs(vacation_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Bono de ayuda familiar
      type === "full"
        ? {
            content: formatBs(family_support_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Sueldo + asignaciones
      {
        content: formatBs(positive_vouchers),
        styles: { halign: "right" },
      },
      // Seguro social
      {
        content: formatBs(social_security_voucher),
        styles: { halign: "right" },
      },
      // Prestacional de empleo
      {
        content: formatBs(employment_voucher),
        styles: { halign: "right" },
      },
      // Prest. Vivienda y Habitat
      {
        content: formatBs(housing_property_benefits_voucher),
        styles: { halign: "right" },
      },
      // Dias no trabajados
      {
        content: formatBs(days_not_worked_voucher),
        styles: { halign: "right" },
      },
      // Prestamos
      type === "full"
        ? {
            content: formatBs(loans_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Liquidacion
      type === "full"
        ? {
            content: formatBs(settlement_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Total deducciones
      {
        content: formatBs(negative_vouchers),
        styles: { halign: "right" },
      },
      // Neto a pagar
      {
        content: formatBs(total),
        styles: { halign: "right" },
      },
    ];
    const filteredData = recordData.filter((cell) => cell != null);
    tableRows.push(filteredData);
  });

  const headers = [
    { title: 'COD', render: true },
    { title: 'Nombre del Trabajador', render: true },
    { title: 'Cédula', render: true },
    { title: 'Cargo', render: true },
    { title: 'Salario Mensual', render: true },
    { title: 'Sueldo a Pagar', render: true },
    { title: 'Bono de alimentación', render: true },
    { title: 'Bono de Transporte', render: type === "full" },
    { title: 'Bono de Rendimiento', render: type === "full" },
    { title: 'Bono de Facturas', render: type === "full" },
    { title: 'Bono de Ventas', render: type === "full" },
    { title: 'Bono de Crecimiento de Ventas', render: type === "full" },
    { title: 'Bono de Productos Asignados', render: type === "full" },
    { title: 'Utilidades', render: type === "full" && isDecember },
    { title: 'Bono Vacacional', render: type === "full" && hasMoreThanYear },
    { title: 'Vacaciones', render: type === "full" && hasMoreThanYear },
    { title: 'Bono de Ayuda familiar', render: type === "full" },
    { title: 'Sueldo + Asignaciones', render: true },
    { title: 'Seguro Social 4%', render: true },
    { title: 'Prestacional de Empleo', render: true },
    { title: 'Prest. Vivienda y Habitat', render: true },
    { title: 'Dias NO Trabajados', render: true },
    { title: 'Prestamos', render: type === "full" },
    { title: 'Liquidación', render: type === "full" },
    { title: 'Total Deducciones', render: true },
    { title: 'NETO A PAGAR', render: true }
  ];

  const tableColumn = headers.filter((h) => h.render).map((h) => h.title);

  // Fila del totales
  const row = [
    // Sueldo a pagar
    {
      content: formatBs(totalSalaryVoucher),
      colSpan: 6,
      styles: { halign: "right", fontStyle: "bold" },
    },
    // Bono de alimentación
    {
      content: formatBs(totalFoodVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    // Bono de transporte
    type === "full"
      ? {
          content: formatBs(totalTransportationVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Bono de rendimiento
    type === "full"
      ? {
          content: formatBs(totalPerformanceVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Bono de facturas
    type === "full"
      ? {
          content: formatBs(totalInvoiceVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Bono de ventas
    type === "full"
      ? {
          content: formatBs(totalSalesVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Bono de crecimiento de ventas
    type === "full"
      ? {
          content: formatBs(totalSalesGrowthVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Bono de productos asignados
    type === "full"
      ? {
          content: formatBs(totalAssignedProductsVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Bono de utilidades
    type === "full" && isDecember
      ? {
          content: formatBs(totalEarningsVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Bono vacacional
    type === "full" && hasMoreThanYear
      ? {
          content: formatBs(totalVacationBonusVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Vacaciones
    type === "full" && hasMoreThanYear
      ? {
          content: formatBs(totalVacationVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Bono de ayuda familiar
    type === "full"
      ? {
          content: formatBs(totalFamilySupportVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Sueldo + asignaciones
    {
      content: formatBs(totalPositiveVouchers),
      styles: { halign: "right", fontStyle: "bold" },
    },
    // Seguro social
    {
      content: formatBs(totalSocialSecurityVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    // Prestacional de empleo
    {
      content: formatBs(totalEmploymentVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    // Prest. Vivienda y Habitat
    {
      content: formatBs(totalHousingPropertyBenefitsVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    // Dias no trabajados
    {
      content: formatBs(totalDaysNotWorkedVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    // Prestamos
    {
      content: formatBs(totalLoansVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    // Liquidacion
    type === "full"
      ? {
          content: formatBs(totalSettlementVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Total deducciones
    type === "full"
      ? {
          content: formatBs(totalNegativeVouchers),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
      // Neto a pagar
    {
      content: formatBs(totalPayslip),
      styles: { halign: "right", fontStyle: "bold" },
    },
  ];
  const filteredRow = row.filter((cell) => cell != null);
  tableRows.push(filteredRow);

  tableRows.push([
    {
      content: "Total Sueldos:",
      colSpan: type === "legal" ? 4 : 11 + (isDecember ? 1 : 0) + (hasMoreThanYear ? 2 : 0),
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: formatBs(totalPositiveVouchers),
      colSpan: 4,
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: "Total Deducción:",
      colSpan: type === "legal" ? 4 : 6,
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: formatBs(totalNegativeVouchers),
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: formatBs(totalPayslip),
      styles: { halign: "right", fontStyle: "bold" },
    },
  ]);

  tableRows.push([
    {
      content: "Total a Pagar en Nomina:",
      colSpan: 3,
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: ":",
      colSpan: type === "legal" ? 11 : 23,
      styles: { halign: "right", fontStyle: "bold" },
    },
  ]);

  tableRows.push([
    {
      content: formatBs(totalPayslip),
      colSpan: 3,
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: ":",
      colSpan: type === "legal" ? 11 : 23,
      styles: { halign: "right", fontStyle: "bold" },
    },
  ]);

  autoTable(doc, {
    head: [tableColumn],
    body: tableRows,
    startY: tableYPosition,
    theme: "grid",
    headStyles: {
      fillColor: [220, 220, 220],
      textColor: 0,
      fontStyle: "bold",
      halign: "center",
    },
    styles: { fontSize: type === "legal" ? 6 : 4, cellPadding: 2 },
    columnStyles: {
      0: { cellWidth: 10 },
      1: { halign: "center" },
      2: { halign: "right" },
      3: { halign: "right" },
      4: { halign: "right" },
      5: { halign: "right" },
    },
  });

  // 7. Pie de página
  const pageCount = doc.internal.getNumberOfPages();
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i);
    doc.setFontSize(10);
    doc.text(
      `Página ${i} de ${pageCount}`,
      pageWidth / 2,
      doc.internal.pageSize.height - 10,
      { align: "center" }
    );
  }

  // 8. Guardar PDF
  const fileName = `${data.name}.pdf`;
  doc.save(fileName);
}
