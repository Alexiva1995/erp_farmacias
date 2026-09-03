import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import { applyFooter } from './pdfBaseStyles'

/**
 * Genera el PDF de Solicitud de Canje Preventivo agrupado por laboratorio en flujo continuo.
 * Los laboratorios se imprimen uno tras otro de forma secuencial sin saltos de página forzados,
 * optimizando el uso del papel y quebrando de hoja solo cuando sea físicamente necesario.
 *
 * @param {Object} reportData  - Respuesta del endpoint /bi/supplier-returns
 * @param {Object} options     - Opciones del PDF (nombre del encargado, etc.)
 */
export default function pdfSupplierReturnsGenerator(reportData, options = {}) {
  const { groups = [], metadata = {} } = reportData

  const buyerName = options.buyerName || metadata.buyer_name || 'Encargada de Compras'
  const pharmacyName = metadata.pharmacy_name || 'FARMACIA BARRIO SUCRE 2024, C.A.'
  const pharmacyRif = metadata.pharmacy_rif || 'R.I.F. J-50540695-7'
  const pharmacyAddress = metadata.pharmacy_address || 'Calle Principal Local 05 (L3) Sector Barrio Sucre · La Fría, Táchira'
  const pharmacyPhone = metadata.pharmacy_phone || ''
  const today = metadata.generated_at || new Date().toLocaleDateString('es-VE')
  const cutoffDate = metadata.cutoff_date || ''

  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const pageWidth = doc.internal.pageSize.getWidth()
  const pageHeight = doc.internal.pageSize.getHeight()
  const margin = 15
  const textWidth = pageWidth - margin * 2

  // Paleta corporativa
  const COLOR_PRIMARY  = [41, 121, 255]
  const COLOR_DARK     = [30, 30, 30]
  const COLOR_GRAY     = [100, 100, 100]

  /**
   * Dibuja el membrete corporativo centrado en la página inicial.
   */
  const drawCenteredHeader = () => {
    // 1. Membrete Centrado
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(11)
    doc.setTextColor(0, 0, 0)
    doc.text(pharmacyName, pageWidth / 2, 13, { align: 'center' })

    doc.setFontSize(8.5)
    doc.text(pharmacyRif, pageWidth / 2, 17.5, { align: 'center' })

    doc.setFont('helvetica', 'normal')
    doc.setFontSize(7.5)
    doc.setTextColor(...COLOR_GRAY)
    doc.text(pharmacyAddress, pageWidth / 2, 21.5, { align: 'center' })

    // 2. Línea divisoria superior
    doc.setDrawColor(0, 0, 0)
    doc.setLineWidth(0.4)
    doc.line(margin, 25, pageWidth - margin, 25)

    // 3. Título del Reporte Centrado
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(12)
    doc.setTextColor(0, 0, 0)
    doc.text('REPORTE DE CANJE PREVENTIVO — 90 DÍAS', pageWidth / 2, 31, { align: 'center' })

    // 4. Línea divisoria inferior
    doc.line(margin, 34, pageWidth - margin, 34)

    // 5. Fechas
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(8)
    doc.setTextColor(...COLOR_GRAY)
    doc.text(`Fecha de Emisión: ${today}`, margin, 39)
    if (cutoffDate) {
      doc.text(`Fecha de corte: ${cutoffDate}`, pageWidth - margin, 39, { align: 'right' })
    }

    return 44
  }

  /**
   * Normaliza el nombre del proveedor para ocultar proveedores informales.
   */
  const formatSupplierName = (name) => {
    if (!name) return 'Desconocido'
    const clean = String(name).trim()
    const upper = clean.toUpperCase()
    if (
      upper === 'INFORMAL' ||
      upper === 'PROVEEDOR INFORMAL' ||
      upper === 'PROVEEDORES INFORMALES' ||
      upper === 'INFORMA' ||
      upper.includes('INFORMAL') ||
      upper === 'S/P' ||
      upper === 'SIN PROVEEDOR'
    ) {
      return 'Desconocido'
    }
    return clean
  }

  // Encabezado principal en la primera página
  let y = drawCenteredHeader()

  /**
   * Imprime un párrafo de texto justificado gestionando el salto de página natural.
   */
  const addParagraph = (text, opts = {}) => {
    doc.setFont(opts.font || 'helvetica', opts.style || 'normal')
    const fontSize = opts.size || 8.5
    doc.setFontSize(fontSize)
    doc.setTextColor(...(opts.color || COLOR_DARK))

    const align = opts.align || 'justify'
    const startX = align === 'center' ? pageWidth / 2 : (align === 'right' ? pageWidth - margin : margin)
    
    const lines = doc.splitTextToSize(text, textWidth)
    const lineHeight = fontSize * 0.3527 * 1.35 + 0.8
    const blockHeight = lines.length * lineHeight

    // Salto de página solo si el bloque no cabe
    if (y + blockHeight > pageHeight - 20) {
      doc.addPage()
      y = 15
    }

    doc.text(lines, startX, y, {
      align: align,
      maxWidth: textWidth,
    })

    y += blockHeight + (opts.spacingAfter ?? 2.5)
  }

  // ─── Renderizado Secuencial y Continuo por cada laboratorio ──────────────────
  groups.forEach((group, groupIdx) => {
    const labName = (group.laboratory_name || 'SIN LABORATORIO').toUpperCase()
    const totalAmt = Number(group.total_amount ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })

    // Si no es el primer grupo, verificar si cabe en la página actual
    if (groupIdx > 0) {
      // Si queda menos de 50mm, saltar de página para no dejar un encabezado huérfano
      if (y > pageHeight - 50) {
        doc.addPage()
        y = 15
      } else {
        // Separador sutil continuo
        y += 4
        doc.setDrawColor(210, 215, 230)
        doc.setLineWidth(0.3)
        doc.line(margin, y, pageWidth - margin, y)
        y += 6
      }
    }

    // ── Banner del Laboratorio ──
    doc.setFillColor(...COLOR_PRIMARY)
    doc.rect(margin, y, textWidth, 8, 'F')
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(9)
    doc.setTextColor(255, 255, 255)
    doc.text(`LABORATORIO / DROGUERÍA: ${labName}`, margin + 4, y + 5.5)
    doc.text(
      `${group.products_count} productos · ${group.total_units} unidades · $${totalAmt}`,
      pageWidth - margin - 4, y + 5.5,
      { align: 'right' }
    )
    y += 11

    // ── Carta Formal con Texto Justificado ──
    addParagraph(`Asunto: Solicitud de Canje Preventivo por Vencimiento – ${pharmacyName} / ${labName}`,
      { style: 'bold', size: 8.5, spacingAfter: 1.5, align: 'left' })

    addParagraph('Estimado Representante / Ejecutivo de Ventas,', { spacingAfter: 2, align: 'left' })

    addParagraph(
      'Junto con saludarle cordialmente, nos ponemos en contacto para hacerle llegar el reporte consolidado de inventario correspondiente a su representada con vencimiento en los próximos 90 días.',
      { spacingAfter: 2 }
    )

    addParagraph(
      `En ${pharmacyName}, nuestro objetivo es mantener sus productos siempre disponibles, con excelente presentación e impulso en el punto de venta, garantizando al cliente final la máxima calidad y vigencia.`,
      { spacingAfter: 2 }
    )

    addParagraph(
      'Para evitar la desincorporación definitiva de estas unidades y mantener la presencia activa de su marca en nuestras estanterías, solicitamos su valioso apoyo con la gestión de canje preventivo o nota de crédito para el siguiente lote de productos:',
      { spacingAfter: 3.5 }
    )

    // ── Tabla de lotes con Totales ──
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
      formatSupplierName(lot.supplier_name),
      lot.purchase_date
        ? new Date(lot.purchase_date).toLocaleDateString('es-VE')
        : '—',
      {
        content: `$${Number(lot.total_amount ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`,
        styles: { halign: 'right', fontStyle: 'bold', textColor: [180, 40, 40] }
      },
    ])

    // Fila de Totales
    const tableFoot = [[
      { content: 'TOTAL GENERAL:', colSpan: 3, styles: { halign: 'right', fontStyle: 'bold', fontSize: 7.5 } },
      { content: Number(group.total_units || 0).toLocaleString('es-VE'), styles: { halign: 'center', fontStyle: 'bold', fontSize: 7.5 } },
      { content: '', styles: {} },
      { content: '', styles: {} },
      { content: `$${totalAmt}`, styles: { halign: 'right', fontStyle: 'bold', fontSize: 7.5, textColor: [180, 40, 40] } }
    ]]

    autoTable(doc, {
      startY: y,
      head: tableHeaders,
      body: tableRows,
      foot: tableFoot,
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
      footStyles: {
        fillColor: [240, 243, 255],
        textColor: [0, 0, 0],
        fontSize: 7.5,
        fontStyle: 'bold',
        lineWidth: 0.2,
        lineColor: [180, 190, 215],
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

    y = doc.lastAutoTable.finalY + 5

    // ── Párrafos de Cierre con Texto Justificado ──
    const totalDoubleAmt = Number((group.total_amount ?? 0) * 2).toLocaleString('es-VE', { minimumFractionDigits: 2 })

    addParagraph('Compromiso Comercial de Recompra:', { style: 'bold', size: 8, spacingAfter: 1, align: 'left' })

    addParagraph(
      `Con el firme propósito de fortalecer nuestra alianza comercial y dinamizar la rotación de sus líneas en nuestros anaqueles, nos comprometemos formalmente a emitir una nueva orden de compra por el doble del valor de la mercancía canjeada (aprox. $${totalDoubleAmt} USD) al ser aprobada y procesada la presente solicitud de canje.`,
      { size: 7.5, spacingAfter: 2 }
    )

    addParagraph(
      'Agradecemos de antemano su valiosa gestión para coordinar la sustitución de estos ítems por lotes de mayor vigencia o la nota de crédito correspondiente, asegurando así la presencia continua de su marca y el crecimiento comercial conjunto.',
      { size: 7.5, spacingAfter: 2 }
    )

    addParagraph(
      'Quedamos a su disposición para coordinar los detalles operativos y la respectiva orden de reposición.',
      { size: 7.5, spacingAfter: 3 }
    )

    addParagraph('Atentamente,', { style: 'bold', size: 8, spacingAfter: 1, align: 'left' })
    addParagraph(buyerName, { style: 'bold', size: 8.5, spacingAfter: 0.5, align: 'left' })
    addParagraph('Gestión de Inventarios y Compras', { size: 7.5, color: COLOR_GRAY, spacingAfter: 0.5, align: 'left' })
    addParagraph(pharmacyName, { size: 7.5, color: COLOR_GRAY, spacingAfter: 0.5, align: 'left' })
    if (pharmacyAddress) addParagraph(pharmacyAddress, { size: 7.5, color: COLOR_GRAY, spacingAfter: 0.5, align: 'left' })
    if (pharmacyPhone)   addParagraph(`Tel.: ${pharmacyPhone}`, { size: 7.5, color: COLOR_GRAY, spacingAfter: 0.5, align: 'left' })
  })

  // ── Pie de página numerado en todas las páginas ──
  applyFooter(doc)

  const filename = `SolicitudCanje_90dias_${new Date().toISOString().slice(0, 10)}.pdf`
  doc.save(filename)
}
