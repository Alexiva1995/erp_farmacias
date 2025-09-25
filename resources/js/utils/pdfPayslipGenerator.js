import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

const LOGO = "/images/logoDonative.png";
const LOGO_WIDTH = 90;
const LOGO_HEIGHT = 35;

export default function pdfPayslipsGenerator(data, type) {
  const doc = new jsPDF({
    orientation: 'l',
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
    return (
      new Intl.NumberFormat("es-VE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(amount) + " Bs."
    );
  };

  const tableYPosition = 50;
  const tableColumn = type === 'legal' ?
  [
    'COD',
    'Nombre del Trabajador',
    'Cédula',
    'Cargo',
    'Salario Mensual',
    'Sueldo a Pagar',
    'Bono de alimentación',
    'Sueldo + Asignaciones',
    'Seguro Social 4%',
    'Prestacional de Empleo',
    'Prest. Vivienda y Habitat',
    'Dias NO Trabajados',
    'Total Deducciones',
    'NETO A PAGAR'
  ] : [
    'COD',
    'Nombre del Trabajador',
    'Cédula',
    'Cargo',
    'Salario Mensual',
    'Sueldo a Pagar',
    'Bono de alimentación',
    'Bono de Transporte',
    'Bono de Rendimiento',
    'Bono de Facturas',
    'Bono de Ventas',
    'Bono de Crecimiento de Ventas',
    'Bono de Productos Asignados',
    'Utilidades',
    'Bono Vacacional',
    'Vacaciones',
    'Bono de Ayuda familiar',
    'Sueldo + Asignaciones',
    'Seguro Social 4%',
    'Prestacional de Empleo',
    'Prest. Vivienda y Habitat',
    'Dias NO Trabajados',
    'Prestamos',
    'Liquidación',
    'Total Deducciones',
    'NETO A PAGAR'
  ]
  const tableRows = [];
  let totalPayslip = 0
  let totalSalaryVoucher = 0
  let totalFoodVoucher = 0
  let totalTransportationVoucher = 0
  let totalPerformanceVoucher = 0
  let totalInvoiceVoucher = 0
  let totalSalesVoucher = 0
  let totalSalesGrowthVoucher = 0
  let totalAssignedProductsVoucher = 0
  let totalEarningsVoucher = 0
  let totalVacationBonusVoucher = 0
  let totalVacationVoucher = 0
  let totalFamilySupportVoucher = 0
  let totalPositiveVouchers = 0
  let totalSocialSecurityVoucher = 0
  let totalEmploymentVoucher = 0
  let totalHousingPropertyBenefitsVoucher = 0
  let totalDaysNotWorkedVoucher = 0
  let totalLoansVoucher = 0
  let totalSettlementVoucher = 0
  let totalNegativeVouchers = 0

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
      positive_vouchers,
      negative_vouchers,
      total
    } = record;
    totalPayslip += Number(total)
    totalSalaryVoucher += Number(salary_to_pay_voucher)
    totalFoodVoucher += Number(food_voucher)
    totalTransportationVoucher += Number(transportation_voucher)
    totalPerformanceVoucher += Number(performance_voucher)
    totalInvoiceVoucher += Number(invoice_voucher)
    totalSalesVoucher += Number(sales_voucher)
    totalSalesGrowthVoucher += Number(sales_growth_voucher)
    totalAssignedProductsVoucher += Number(assigned_products_voucher)
    totalEarningsVoucher += Number(earnings_voucher)
    totalVacationBonusVoucher += Number(vacation_bonus_voucher)
    totalVacationVoucher += Number(vacation_voucher)
    totalFamilySupportVoucher += Number(family_support_voucher)
    totalPositiveVouchers += Number(positive_vouchers)
    totalSocialSecurityVoucher += Number(social_security_voucher)
    totalEmploymentVoucher += Number(employment_voucher)
    totalHousingPropertyBenefitsVoucher += Number(housing_property_benefits_voucher)
    totalDaysNotWorkedVoucher += Number(days_not_worked_voucher)
    totalLoansVoucher += Number(loans_voucher)
    totalSettlementVoucher += Number(settlement_voucher)
    totalNegativeVouchers += Number(negative_vouchers)

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
      type === "full"
        ? {
            content: formatBs(earnings_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Bono Vacacional
      type === "full"
        ? {
            content: formatBs(vacation_bonus_voucher),
            styles: { halign: "right" },
          }
        : null,
      // Vacaciones
      type === "full"
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
    const filteredData = recordData.filter(cell => cell != null)
    tableRows.push(filteredData);
  });

  // Fila del totales
  const row = [
    {
      content: formatBs(totalSalaryVoucher),
      colSpan: 6,
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: formatBs(totalFoodVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    type === "full"
      ? {
          content: formatBs(totalTransportationVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    type === "full"
      ? {
          content: formatBs(totalPerformanceVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    type === "full"
      ? {
          content: formatBs(totalInvoiceVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    type === "full"
      ? {
          content: formatBs(totalSalesVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    type === "full"
      ? {
          content: formatBs(totalSalesGrowthVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    type === "full"
      ? {
          content: formatBs(totalAssignedProductsVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    type === "full"
      ? {
          content: formatBs(totalEarningsVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    type === "full"
      ? {
          content: formatBs(totalVacationBonusVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    type === "full"
      ? {
          content: formatBs(totalVacationVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    type === "full"
      ? {
          content: formatBs(totalFamilySupportVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    {
      content: formatBs(totalPositiveVouchers),
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: formatBs(totalSocialSecurityVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: formatBs(totalEmploymentVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: formatBs(totalHousingPropertyBenefitsVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: formatBs(totalDaysNotWorkedVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    {
      content: formatBs(totalLoansVoucher),
      styles: { halign: "right", fontStyle: "bold" },
    },
    type === "full"
      ? {
          content: formatBs(totalSettlementVoucher),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    type === "full"
      ? {
          content: formatBs(totalNegativeVouchers),
          styles: { halign: "right", fontStyle: "bold" },
        }
      : null,
    {
      content: formatBs(totalPayslip),
      styles: { halign: "right", fontStyle: "bold" },
    },
  ]
  const filteredRow = row.filter(cell => cell != null)
  tableRows.push(filteredRow);

  tableRows.push([
    { content: 'Total Sueldos:', colSpan: type === 'legal' ? 4 : 14, styles: { halign: "right", fontStyle: "bold" } },
    { content: formatBs(totalPositiveVouchers), colSpan: 4, styles: { halign: "right", fontStyle: "bold" } },
    { content: 'Total Deducción:', colSpan: type === 'legal' ? 4 : 6, styles: { halign: "right", fontStyle: "bold" } },
    { content: formatBs(totalNegativeVouchers), styles: { halign: "right", fontStyle: "bold" } },
    { content: formatBs(totalPayslip), styles: { halign: "right", fontStyle: "bold" } },
  ]);

  tableRows.push([
    { content: 'Total a Pagar en Nomina:', colSpan: 3, styles: { halign: "right", fontStyle: "bold" } },
    { content: ':', colSpan: type === 'legal' ? 11 : 23, styles: { halign: "right", fontStyle: "bold" } },
  ]);

  tableRows.push([
    { content:formatBs(totalPayslip), colSpan: 3, styles: { halign: "right", fontStyle: "bold" } },
    { content: ':', colSpan: type === 'legal' ? 11 : 23, styles: { halign: "right", fontStyle: "bold" } },
  ]);

  autoTable(doc, {
    head: [tableColumn],
    body: tableRows,
    startY: tableYPosition,
    theme: "grid",
    headStyles: { fillColor: [220, 220, 220], textColor: 0, fontStyle: "bold", halign: "center" },
    styles: { fontSize: type === 'legal' ? 6 : 4, cellPadding: 2 },
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
    doc.text(`Página ${i} de ${pageCount}`, pageWidth / 2, doc.internal.pageSize.height - 10, { align: "center" });
  }

  // 8. Guardar PDF
  const fileName = `${data.name}.pdf`;
  doc.save(fileName);
}
