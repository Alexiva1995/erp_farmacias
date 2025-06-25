import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export function generateDonationPDF(donationData) {
  const doc = new jsPDF();
  const { institution, products } = donationData;

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
  doc.text(institution.toUpperCase(), 35, señoresYPosition);

  const bodyYPosition = señoresYPosition + 15;
  doc.setFont('helvetica', 'normal');
  const bodyText = `Reciba un saludo cordial de parte de la FARMACIA BARRIO SUCRE 2024 C.A J-505406957, por medio del presente expreso mi voluntad de donar a favor ${institution.toUpperCase()} siguientes medicamentos detallados en la lista adjunta, que beneficiaría a los pacientes o familiares de dicha institución.`;
  const splitBody = doc.splitTextToSize(bodyText, pageWidth - 30);
  doc.text(splitBody, 15, bodyYPosition);

  const tableYPosition = bodyYPosition + (splitBody.length * 5) + 5;
  const tableColumn = ["NOMBRE", "UNIDADES", "Costo Unitario", "TOTAL"];
  const tableRows = [];
  let totalValue = 0;

  products.forEach(prod => {
    const price = parseFloat(prod.cost_per_unit || 0); 
    const quantity = parseInt(prod.expired_quantity || 0);
    const total = price * quantity;
    totalValue += total;
    const productData = [prod.product_name, quantity, price.toFixed(2), total.toFixed(2)];
    tableRows.push(productData);
  });
  
  tableRows.push([{ content: 'TOTAL', colSpan: 3, styles: { halign: 'right', fontStyle: 'bold' } }, { content: totalValue.toFixed(2), styles: { fontStyle: 'bold' } }]);

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
  
  doc.save(`Donativo-${institution.replace(/[\s\W]/g, '_')}-${today.toISOString().split('T')[0]}.pdf`);
}
