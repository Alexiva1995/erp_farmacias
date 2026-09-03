import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import { applyPremiumHeader, applyFooter } from './pdfBaseStyles'

/**
 * Genera el PDF de Solicitud de Canje Preventivo agrupado por laboratorio.
 * Cada laboratorio recibe su propia sección con la carta formal completa
 * seguida de la tabla de lotes afectados.
 *
 * @param {Object} reportData  - Respuesta del endpoint /bi/supplier-returns
 * @param {Object} options     - Opciones del PDF (nombre del encargado, etc.)
 */
export default function pdfSupplierReturnsGenerator(reportData, options = {}) {
  const { groups = [], metadata = {}, summary = {} } = reportData

  const buyerName = options.buyerName || metadata.buyer_name || 'Encargada de Compras'
  const pharmacyName = metadata.pharmacy_name || 'FARMACIA BARRIO SUCRE 2024, C.A.'
  const pharmacyAddress = metadata.pharmacy_address || 'La Fría, Táchira'
  const pharmacyPhone = metadata.pharmacy_phone || ''
  const today = metadata.generated_at || new Date().toLocaleDateString('es-VE')
  const cutoffDate = metadata.cutoff_date || ''

  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const pageWidth = doc.internal.pageSize.getWidth()
  const margin = 15
  const textWidth = pageWidth - margin * 2

  // Paleta corporativa
  const COLOR_PRIMARY  = [41, 121, 255]
  const COLOR_DARK     = [30, 30, 30]
  const COLOR_GRAY     = [100, 100, 100]
  const COLOR_LIGHT_BG = [245, 247, 255]
  const COLOR_WARNING  = [220, 80, 50]

  // ─── Portada / Resumen Global ────────────────────────────────────────────────
  let y = applyPremiumHeader(
    doc,
    'REPORTE DE CANJE PREVENTIVO — 90 DÍAS',
    `Fecha de corte: ${cutoffDate}`
  )

  // Cuadro de resumen global
  doc.setFillColor(...COLOR_LIGHT_BG)
  doc.roundedRect(margin, y, textWidth, 30, 3, 3, 'F')

  doc.setFont('helvetica', 'bold')
  doc.setFontSize(9)
  doc.setTextColor(...COLOR_DARK)

  const kpis = [
    { label: 'Laboratorios afectados', value: summary.total_laboratories ?? groups.length },
    { label: 'Productos en riesgo',    value: summary.total_products ?? 0 },
    { label: 'Lotes totales',          value: summary.total_lots ?? 0 },
    { label: 'Unidades en riesgo',     value: Number(summary.total_units ?? 0).toLocaleString('es-VE') },
    { label: 'Monto total en riesgo',  value: `$${Number(summary.total_amount ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })}` },
  ]

  const colW = textWidth / kpis.length
  kpis.forEach((kpi, i) => {
    const x = margin + colW * i + colW / 2
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(12)
    doc.setTextColor(...COLOR_PRIMARY)
    doc.text(String(kpi.value), x, y + 12, { align: 'center' })
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(7)
    doc.setTextColor(...COLOR_GRAY)
    doc.text(kpi.label, x, y + 19, { align: 'center' })
  })

  y += 38

  doc.setFontSize(8)
  doc.setTextColor(...COLOR_GRAY)
  doc.setFont('helvetica', 'italic')
  doc.text(
    'Este reporte debe enviarse a cada laboratorio/droguería el primer día de cada mes para gestionar el canje preventivo.',
    margin, y
  )
  y += 10

  // ─── Una sección por laboratorio ────────────────────────────────────────────
  groups.forEach((group, groupIdx) => {
    // Nueva página para cada laboratorio (excepto si la portada tiene espacio)
    if (groupIdx > 0 || y > 100) {
      doc.addPage()
      y = 15
    }

    const labName = (group.laboratory_name || 'SIN LABORATORIO').toUpperCase()
    const totalAmt = Number(group.total_amount ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })

    // ── Encabezado del laboratorio ──
    doc.setFillColor(...COLOR_PRIMARY)
    doc.rect(margin, y, textWidth, 10, 'F')
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(10)
    doc.setTextColor(255, 255, 255)
    doc.text(`LABORATORIO / DROGUERÍA: ${labName}`, margin + 4, y + 7)
    doc.text(
      `${group.products_count} productos · ${group.total_units} unidades · $${totalAmt}`,
      pageWidth - margin - 4, y + 7,
      { align: 'right' }
    )
    y += 14

    // ── Carta formal ──────────────────────────────────────────────────────────
    const addLine = (text, opts = {}) => {
      const lines = doc.splitTextToSize(text, textWidth)
      doc.setFont(opts.font || 'helvetica', opts.style || 'normal')
      doc.setFontSize(opts.size || 9)
      doc.setTextColor(...(opts.color || COLOR_DARK))
      doc.text(lines, margin, y)
      y += lines.length * (opts.lineH || 5) + (opts.spacingAfter || 0)

      // Salto de página automático
      if (y > doc.internal.pageSize.getHeight() - 30) {
        doc.addPage()
        y = 15
      }
    }

    // Asunto
    addLine(`Asunto: Solicitud de Canje Preventivo por Vencimiento – ${pharmacyName} / ${labName}`,
      { style: 'bold', size: 9, spacingAfter: 2 })

    addLine(`${today}`, { size: 8, color: COLOR_GRAY, spacingAfter: 4 })

    addLine('Estimado Representante / Ejecutivo de Ventas,', { spacingAfter: 3 })

    addLine(
      `Junto con saludarle cordialmente, nos ponemos en contacto para hacerle llegar el reporte consolidado de inventario correspondiente a su representada con vencimiento en los próximos 90 días.`,
      { spacingAfter: 3 }
    )

    addLine(
      `En ${pharmacyName}, nuestro objetivo es mantener sus productos siempre disponibles, con excelente presentación e impulso en el punto de venta, garantizando al cliente final la máxima calidad y vigencia.`,
      { spacingAfter: 3 }
    )

    addLine(
      'Para evitar la desincorporación definitiva de estas unidades y mantener la presencia activa de su marca en nuestras estanterías, solicitamos su valioso apoyo con la gestión de canje preventivo o nota de crédito para el siguiente lote de productos:',
      { spacingAfter: 5 }
    )

    // ── Tabla de lotes ────────────────────────────────────────────────────────
    const tableHeaders = [[
      'Producto / Presentación',
      'No. Lote',
      'Fecha Vto.',
      'Cant.',
      'Proveedor',
      'Fecha Compra',
      'Monto USD',
    ]]

    const tableRows = (group.lots || []).map(lot => [
      {
        content: lot.product_name + (lot.presentation ? `\n${lot.presentation}` : ''),
        styles: { fontStyle: 'bold', fontSize: 7 }
      },
      lot.lot_number || '—',
      lot.expiration_date
        ? new Date(lot.expiration_date).toLocaleDateString('es-VE')
        : '—',
      { content: Number(lot.quantity).toLocaleString('es-VE'), styles: { halign: 'center', fontStyle: 'bold' } },
      lot.supplier_name || '—',
      lot.purchase_date
        ? new Date(lot.purchase_date).toLocaleDateString('es-VE')
        : '—',
      {
        content: `$${Number(lot.total_amount ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`,
        styles: { halign: 'right', fontStyle: 'bold', textColor: [180, 40, 40] }
      },
    ])

    autoTable(doc, {
      startY: y,
      head: tableHeaders,
      body: tableRows,
      theme: 'grid',
      styles: {
        fontSize: 7.5,
        cellPadding: 2,
        lineColor: [210, 210, 220],
        lineWidth: 0.1,
        font: 'helvetica',
      },
      headStyles: {
        fillColor: COLOR_DARK,
        textColor: 255,
        fontSize: 7,
        halign: 'center',
        fontStyle: 'bold',
      },
      alternateRowStyles: { fillColor: [248, 249, 255] },
      columnStyles: {
        0: { cellWidth: 'auto', minCellWidth: 40 },
        1: { cellWidth: 22, halign: 'center' },
        2: { cellWidth: 22, halign: 'center' },
        3: { cellWidth: 14, halign: 'center' },
        4: { cellWidth: 28 },
        5: { cellWidth: 22, halign: 'center' },
        6: { cellWidth: 22, halign: 'right' },
      },
      margin: { left: margin, right: margin },
      didDrawPage: (data) => {
        y = data.cursor.y
      },
    })

    y = doc.lastAutoTable.finalY + 8

    // ── Párrafos de cierre ────────────────────────────────────────────────────
    if (y > doc.internal.pageSize.getHeight() - 60) {
      doc.addPage()
      y = 15
    }

    addLine(
      'Agradecemos de antemano su gestión para procesar la sustitución de estos ítems por lotes de mayor vigencia, lo que nos permitirá seguir manteniendo el producto exhibido y asegurar su flujo de venta continuo en nuestra farmacia.',
      { spacingAfter: 3 }
    )

    addLine(
      'Quedamos a la espera de sus instrucciones sobre la recolección o recepción del material.',
      { spacingAfter: 6 }
    )

    addLine('Atentamente,', { style: 'bold', spacingAfter: 1 })

    addLine(buyerName, { style: 'bold', size: 9, spacingAfter: 0 })
    addLine('Gestión de Inventarios y Compras', { size: 8, color: COLOR_GRAY, spacingAfter: 0 })
    addLine(pharmacyName, { size: 8, color: COLOR_GRAY, spacingAfter: 0 })
    if (pharmacyAddress) addLine(pharmacyAddress, { size: 8, color: COLOR_GRAY, spacingAfter: 0 })
    if (pharmacyPhone)   addLine(`Tel.: ${pharmacyPhone}`, { size: 8, color: COLOR_GRAY, spacingAfter: 0 })

    y += 4

    // Línea divisoria entre laboratorios (si no es el último)
    if (groupIdx < groups.length - 1) {
      doc.setDrawColor(...COLOR_PRIMARY)
      doc.setLineWidth(0.3)
      // No hay línea — se usa salto de página al inicio del siguiente grupo
    }
  })

  // ── Pie de página numerado en todas las páginas ──────────────────────────────
  applyFooter(doc)

  const filename = `SolicitudCanje_90dias_${new Date().toISOString().slice(0, 10)}.pdf`
  doc.save(filename)
}
