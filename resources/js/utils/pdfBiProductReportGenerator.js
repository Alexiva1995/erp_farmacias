import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

/**
 * Generador de Reporte Ejecutivo en PDF para BI de Productos
 * Utiliza paleta corporativa (#E20074 / Magenta primario y tonos oscuros).
 *
 * @param {Object} payload Datos agregados del dashboard
 * @param {Object} filters Filtros de consulta aplicados
 */
export function generateBiProductExecutivePdf(payload = {}, filters = {}) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
  const pageWidth = doc.internal.pageSize.getWidth();

  // Color primario ERP: #E20074 (226, 0, 116)
  const primaryColor = [226, 0, 116];
  const darkColor = [47, 43, 61];
  const grayLight = [248, 249, 250];

  // 1. Encabezado Corporativo
  doc.setFillColor(...primaryColor);
  doc.rect(0, 0, pageWidth, 18, 'F');

  doc.setTextColor(255, 255, 255);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(14);
  doc.text('ERP FARMACIAS — REPORTE EJECUTIVO DE PRODUCTOS (BI)', 14, 12);

  // 2. Metadatos de Generación y Filtros
  doc.setTextColor(...darkColor);
  doc.setFontSize(9);
  doc.setFont('helvetica', 'normal');

  const now = new Date();
  const dateStr = now.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

  doc.text(`Fecha de emisión: ${dateStr}`, 14, 26);
  doc.text(`Período evaluado: ${filters.start_date || 'N/A'} hasta ${filters.end_date || 'N/A'}`, 14, 31);
  if (filters.search) {
    doc.text(`Criterio de búsqueda: "${filters.search}"`, 14, 36);
  }

  // 3. Tarjetas de Resumen KPI
  const kpiY = filters.search ? 42 : 37;
  doc.setFillColor(...grayLight);
  doc.roundedRect(14, kpiY, pageWidth - 28, 20, 2, 2, 'F');
  doc.setDrawColor(220, 220, 220);
  doc.roundedRect(14, kpiY, pageWidth - 28, 20, 2, 2, 'S');

  const q4 = payload.quadrant4 || {};
  const pareto = payload.quadrant1?.pareto?.percent ?? 0;

  doc.setFontSize(8);
  doc.setFont('helvetica', 'bold');
  doc.setTextColor(255, 76, 81); // Error
  doc.text('RUPTURA DE STOCK', 20, kpiY + 7);
  doc.setFontSize(12);
  doc.text(`${q4.out_of_stock ?? 0} SKUs`, 20, kpiY + 14);

  doc.setFontSize(8);
  doc.setTextColor(255, 159, 67); // Warning
  doc.text('STOCK CRÍTICO (<7d)', 65, kpiY + 7);
  doc.setFontSize(12);
  doc.text(`${q4.critical_stock ?? 0} SKUs`, 65, kpiY + 14);

  doc.setFontSize(8);
  doc.setTextColor(...primaryColor);
  doc.text('CONCENTRACIÓN PARETO', 115, kpiY + 7);
  doc.setFontSize(12);
  doc.text(`${pareto}% Margen`, 115, kpiY + 14);

  doc.setFontSize(8);
  doc.setTextColor(0, 186, 209); // Info
  doc.text('COBERTURA PROMEDIO', 160, kpiY + 7);
  doc.setFontSize(12);
  doc.text(`${Math.round(q4.avg_inventory_days ?? 0)} Días`, 160, kpiY + 14);

  let currentY = kpiY + 26;

  // 4. Tabla: Top 5 Productos por Volumen de Demanda
  const topVolume = payload.quadrant1?.top_volume?.data || payload.quadrant1?.top_volume || [];
  const topVolumeRows = (Array.isArray(topVolume) ? topVolume : []).slice(0, 5).map((item, idx) => [
    idx + 1,
    item.name || 'Desconocido',
    item.laboratory_name || 'S/L',
    Math.trunc(item.total_sold ?? 0).toLocaleString(),
    `$${Number(item.total_revenue ?? 0).toFixed(2)}`,
  ]);

  doc.setFontSize(10);
  doc.setFont('helvetica', 'bold');
  doc.setTextColor(...darkColor);
  doc.text('1. TOP PRODUCTOS POR DEMANDA (VOLUMEN)', 14, currentY);

  autoTable(doc, {
    startY: currentY + 3,
    head: [['#', 'Producto', 'Laboratorio', 'Unidades', 'Ingresos']],
    body: topVolumeRows.length ? topVolumeRows : [['-', 'Sin datos registrados en el período', '-', '-', '-']],
    theme: 'grid',
    headStyles: { fillColor: primaryColor, textColor: 255, fontStyle: 'bold', fontSize: 8 },
    styles: { fontSize: 8, cellPadding: 2 },
    columnStyles: {
      0: { halign: 'center', cellWidth: 10 },
      1: { cellWidth: 80 },
      2: { cellWidth: 40 },
      3: { halign: 'right', cellWidth: 25 },
      4: { halign: 'right', cellWidth: 25 },
    },
    margin: { left: 14, right: 14 },
  });

  currentY = doc.lastAutoTable.finalY + 8;

  // 5. Tabla: Top 5 Productos por Facturación y Margen
  const topRevenue = payload.quadrant1?.top_revenue?.data || payload.quadrant1?.top_revenue || [];
  const topRevenueRows = (Array.isArray(topRevenue) ? topRevenue : []).slice(0, 5).map((item, idx) => [
    idx + 1,
    item.name || 'Desconocido',
    item.laboratory_name || 'S/L',
    `$${Number(item.total_revenue ?? 0).toFixed(2)}`,
    `$${Number(item.total_margin ?? 0).toFixed(2)}`,
  ]);

  doc.setFontSize(10);
  doc.setFont('helvetica', 'bold');
  doc.setTextColor(...darkColor);
  doc.text('2. TOP PRODUCTOS POR FACTURACIÓN Y MARGEN', 14, currentY);

  autoTable(doc, {
    startY: currentY + 3,
    head: [['#', 'Producto', 'Laboratorio', 'Venta Bruta', 'Margen Bruto']],
    body: topRevenueRows.length ? topRevenueRows : [['-', 'Sin datos registrados en el período', '-', '-', '-']],
    theme: 'grid',
    headStyles: { fillColor: [40, 199, 111], textColor: 255, fontStyle: 'bold', fontSize: 8 },
    styles: { fontSize: 8, cellPadding: 2 },
    columnStyles: {
      0: { halign: 'center', cellWidth: 10 },
      1: { cellWidth: 80 },
      2: { cellWidth: 40 },
      3: { halign: 'right', cellWidth: 25 },
      4: { halign: 'right', cellWidth: 25 },
    },
    margin: { left: 14, right: 14 },
  });

  currentY = doc.lastAutoTable.finalY + 8;

  // 6. Tabla: Segmentación ABC de Inventario
  const abcList = payload.quadrant2?.abc || [];
  const abcRows = (Array.isArray(abcList) ? abcList : []).map(item => [
    `Clase ${item.type || '?'}`,
    `${item.count ?? 0} SKUs`,
    `$${Number(item.revenue ?? 0).toFixed(2)}`,
    item.obsolete_count > 0 ? `${item.obsolete_count} SKUs ($${Number(item.obsolete_value ?? 0).toFixed(2)})` : 'Sin riesgo',
  ]);

  doc.setFontSize(10);
  doc.setFont('helvetica', 'bold');
  doc.setTextColor(...darkColor);
  doc.text('3. MATRIZ ABC DE INVENTARIO Y CAPITAL INMOVILIZADO', 14, currentY);

  autoTable(doc, {
    startY: currentY + 3,
    head: [['Segmento', 'Cantidad SKUs', 'Valorización en Stock', 'Capital Inmóvil (>90d)']],
    body: abcRows.length ? abcRows : [['-', 'Sin existencias valorizadas', '-', '-']],
    theme: 'grid',
    headStyles: { fillColor: [0, 186, 209], textColor: 255, fontStyle: 'bold', fontSize: 8 },
    styles: { fontSize: 8, cellPadding: 2 },
    columnStyles: {
      0: { halign: 'center', cellWidth: 25 },
      1: { halign: 'center', cellWidth: 35 },
      2: { halign: 'right', cellWidth: 55 },
      3: { halign: 'left', cellWidth: 65 },
    },
    margin: { left: 14, right: 14 },
  });

  // Pie de Página
  const totalPages = doc.internal.getNumberOfPages();
  for (let i = 1; i <= totalPages; i++) {
    doc.setPage(i);
    doc.setFontSize(8);
    doc.setTextColor(150, 150, 150);
    doc.text(`Página ${i} de ${totalPages} — Sistema de Gestión Farmacéutica ERP`, pageWidth / 2, 290, { align: 'center' });
  }

  // Descarga del documento
  doc.save(`reporte_ejecutivo_bi_productos_${filters.start_date || 'inicio'}_${filters.end_date || 'fin'}.pdf`);
}
