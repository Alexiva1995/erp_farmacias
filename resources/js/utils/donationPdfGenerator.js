import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export function generateDonationPDF(donationData) {
  const doc = new jsPDF();
  const { institution, products, exchangeRateBs } = donationData;

  const logoSrc = '/images/logoDonative.png';
  
  const pageWidth = doc.internal.pageSize.getWidth();
  const logoWidth = 90;
  const logoHeight = 35; 
  const xPosition = (pageWidth - logoWidth) / 2;

  try {
    doc.addImage(logoSrc, 'PNG', xPosition, 15, logoWidth, logoHeight);
  } catch (error) {
    console.error("jsPDF no pudo añadir la imagen del logo.", error);
  }
  
  const dateYPosition = 60; 
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(10);
  doc.setTextColor(0, 0, 0); 
  const today = new Date();
  const dateStr = `La Fría, ${today.getDate()}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
  doc.text(dateStr, pageWidth - 15, dateYPosition, { align: 'right' });

  const señoresYPosition = dateYPosition + 15;
  doc.setFontSize(11);
  doc.text('SEÑORES:', 15, señoresYPosition);
  doc.setFont('helvetica', 'bold');
  const institutionYPosition = señoresYPosition + 5;
  doc.text(institution.toUpperCase(), 15, institutionYPosition);

  const bodyYPosition = institutionYPosition + 10;
  doc.setFont('helvetica', 'normal');
  const bodyText = `Reciba un saludo cordial de parte de la FARMACIA BARRIO SUCRE 2024 C.A J-505406957, por medio del presente expreso mi voluntad de donar a favor ${institution.toUpperCase()} siguientes medicamentos detallados en la lista adjunta, que beneficiaría a los pacientes o familiares de dicha institución.`;
  const splitBody = doc.splitTextToSize(bodyText, pageWidth - 30);
  doc.text(splitBody, 15, bodyYPosition);

  const tableYPosition = bodyYPosition + (splitBody.length * 5) + 5;
  const tableColumn = ["NOMBRE", "UNIDADES", "Costo Unitario (Bs.)", "TOTAL (Bs.)"];
  const tableRows = [];
  let totalValue = 0;

  // Función para formatear números en bolívares
  const formatBs = (amount) => {
    return new Intl.NumberFormat('es-VE', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount) + ' Bs.';
  };

  products.forEach(prod => {
    // Usar el precio en bolívares que viene del backend
    const priceBs = parseFloat(prod.cost_per_unit_bs || 0); 
    const quantity = parseInt(prod.expired_quantity || 0);
    const total = priceBs * quantity;
    totalValue += total;
    
    const productData = [
      prod.product_name || prod.product?.name || 'N/A', 
      quantity, 
      formatBs(priceBs),
      formatBs(total)
    ];
    tableRows.push(productData);
  });
  
  // Fila del total
  tableRows.push([
    { content: 'TOTAL', colSpan: 3, styles: { halign: 'right', fontStyle: 'bold' } }, 
    { content: formatBs(totalValue), styles: { fontStyle: 'bold' } }
  ]);

  autoTable(doc, {
    head: [tableColumn],
    body: tableRows,
    startY: tableYPosition,
    theme: 'grid',
    headStyles: { fillColor: [220, 220, 220], textColor: 0, fontStyle: 'bold', halign: 'center' },
    styles: { fontSize: 9, cellPadding: 2 },
    columnStyles: {
        0: { cellWidth: 'auto' },
        1: { halign: 'center' },
        2: { halign: 'right' },
        3: { halign: 'right' }
    }
  });

  let finalY = doc.lastAutoTable.finalY + 15;
  doc.text('Sin otro en particular, y agradeciendo toda su colaboración al respecto.', 15, finalY);

  finalY += 25;
  doc.line(80, finalY, 130, finalY);
  
  // Incluir información de la tasa de cambio en el pie de página (opcional)
  if (exchangeRateBs) {
    finalY += 10;
    doc.setFontSize(8);
    doc.setTextColor(100, 100, 100);
    doc.text(`Tasa de cambio aplicada: 1 USD = ${exchangeRateBs} Bs.`, 15, finalY);
  }
  
  doc.save(`Donativo-${institution.replace(/[\s\W]/g, '_')}-${today.toISOString().split('T')[0]}.pdf`);
}
