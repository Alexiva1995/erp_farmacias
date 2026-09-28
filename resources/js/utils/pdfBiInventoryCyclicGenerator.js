import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'

/**
 * Generador de Reporte Ejecutivo en PDF para BI - Inventario Cíclico
 *
 * @param {Object} payload Datos completos del dashboard
 * @param {Object} filters Filtros aplicados
 */
export function generateBiInventoryCyclicPdf(payload = {}, filters = {}) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const pageWidth = doc.internal.pageSize.getWidth()

  // Paleta corporativa ERP
  const primaryColor = [226, 0, 116] // #E20074
  const darkColor = [47, 43, 61]
  const grayLight = [248, 249, 250]

  // 1. Encabezado Corporativo
  doc.setFillColor(...primaryColor)
  doc.rect(0, 0, pageWidth, 18, 'F')

  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(13)
  doc.text('ERP FARMACIAS — REPORTE DE INVENTARIO CÍCLICO & ERI (BI)', 14, 12)

  // 2. Metadatos
  doc.setTextColor(...darkColor)
  doc.setFontSize(9)
  doc.setFont('helvetica', 'normal')

  const now = new Date()
  const dateStr = now.toLocaleDateString('es-ES', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })

  doc.text(`Fecha de emisión: ${dateStr}`, 14, 25)
  doc.text(`Período evaluado: ${filters.startDate || 'N/A'} hasta ${filters.endDate || 'N/A'}`, 14, 30)

  // 3. Tarjetas de Resumen KPI
  const kpis = payload.kpis || {}
  const kpiY = 35
  doc.setFillColor(...grayLight)
  doc.roundedRect(14, kpiY, pageWidth - 28, 20, 2, 2, 'F')
  doc.setDrawColor(220, 220, 220)
  doc.roundedRect(14, kpiY, pageWidth - 28, 20, 2, 2, 'S')

  doc.setFontSize(8)
  doc.setFont('helvetica', 'bold')
  doc.setTextColor(40, 199, 111) // Success
  doc.text('PRECISIÓN (ERI)', 20, kpiY + 7)
  doc.setFontSize(12)
  doc.text(`${kpis.eri ?? 100}%`, 20, kpiY + 14)

  doc.setFontSize(8)
  doc.setTextColor(255, 76, 81) // Error
  doc.text('PÉRDIDA NETA', 65, kpiY + 7)
  doc.setFontSize(12)
  doc.text(`$${Number(kpis.net_loss ?? 0).toFixed(2)}`, 65, kpiY + 14)

  doc.setFontSize(8)
  doc.setTextColor(255, 159, 67) // Warning
  doc.text('TASA DE ERROR', 115, kpiY + 7)
  doc.setFontSize(12)
  doc.text(`${kpis.error_rate ?? 0}%`, 115, kpiY + 14)

  doc.setFontSize(8)
  doc.setTextColor(0, 186, 209) // Info
  doc.text('SKUs AUDITADOS', 160, kpiY + 7)
  doc.setFontSize(12)
  doc.text(`${(kpis.total_counted_skus ?? 0).toLocaleString()} SKUs`, 160, kpiY + 14)

  let currentY = kpiY + 26

  // 4. Tabla: Top 10 Mayores Faltantes
  const topMissing = payload.deviations?.top_missing?.categories || []
  const topMissingSeries = payload.deviations?.top_missing?.series?.[0]?.data || []
  const missingRows = topMissing.map((name, idx) => [
    idx + 1,
    name,
    Math.abs(topMissingSeries[idx] ?? 0),
    'Faltante',
  ])

  doc.setFontSize(10)
  doc.setFont('helvetica', 'bold')
  doc.setTextColor(...darkColor)
  doc.text('1. TOP DESVIACIONES NEGATIVAS (MAYORES FALTANTES)', 14, currentY)

  autoTable(doc, {
    startY: currentY + 3,
    head: [['#', 'Producto / Medicamento', 'Unidades', 'Tipo']],
    body: missingRows.length ? missingRows : [['-', 'Sin faltantes registrados en el período', '-', '-']],
    theme: 'grid',
    headStyles: { fillColor: [255, 76, 81], textColor: 255, fontStyle: 'bold', fontSize: 8 },
    styles: { fontSize: 8, cellPadding: 2 },
    columnStyles: {
      0: { halign: 'center', cellWidth: 10 },
      1: { cellWidth: 120 },
      2: { halign: 'right', cellWidth: 25 },
      3: { halign: 'center', cellWidth: 25 },
    },
    margin: { left: 14, right: 14 },
  })

  currentY = doc.lastAutoTable.finalY + 8

  // 5. Tabla: Cruce de Códigos (Sustituciones Detectadas)
  const substitutions = payload.substitutions || []
  const subRows = substitutions.map((sub, idx) => [
    idx + 1,
    sub.category || 'General',
    sub.product_a || 'N/A',
    sub.discrepancy_a ?? 0,
    sub.product_b || 'N/A',
    sub.discrepancy_b ?? 0,
    sub.confidence || 'Alta',
  ])

  doc.setFontSize(10)
  doc.setFont('helvetica', 'bold')
  doc.setTextColor(...darkColor)
  doc.text('2. CUADRANTE DE CRUCE DE CÓDIGOS (POSIBLES SUSTITUCIONES)', 14, currentY)

  autoTable(doc, {
    startY: currentY + 3,
    head: [['#', 'Categoría', 'Producto A (Faltante)', 'Cant.', 'Producto B (Sobrante)', 'Cant.', 'Confianza']],
    body: subRows.length ? subRows : [['-', '-', 'No se detectaron cruces de códigos en este período', '-', '-', '-', '-']],
    theme: 'grid',
    headStyles: { fillColor: primaryColor, textColor: 255, fontStyle: 'bold', fontSize: 8 },
    styles: { fontSize: 8, cellPadding: 2 },
    columnStyles: {
      0: { halign: 'center', cellWidth: 8 },
      1: { cellWidth: 28 },
      2: { cellWidth: 50 },
      3: { halign: 'center', cellWidth: 12 },
      4: { cellWidth: 50 },
      5: { halign: 'center', cellWidth: 12 },
      6: { halign: 'center', cellWidth: 22 },
    },
    margin: { left: 14, right: 14 },
  })

  // Pie de Página
  const totalPages = doc.internal.getNumberOfPages()
  for (let i = 1; i <= totalPages; i++) {
    doc.setPage(i)
    doc.setFontSize(8)
    doc.setTextColor(150, 150, 150)
    doc.text(`Página ${i} de ${totalPages} — Sistema de Gestión Farmacéutica ERP`, pageWidth / 2, 290, { align: 'center' })
  }

  // Descarga del documento
  doc.save(`reporte_inventario_ciclico_${filters.startDate || 'inicio'}_${filters.endDate || 'fin'}.pdf`)
}
