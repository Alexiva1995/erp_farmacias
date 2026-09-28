import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { applyPremiumHeader, applyFooter } from '@/utils/pdfBaseStyles';

/**
 * Generador de Reporte Ejecutivo en PDF para Analítica de Clientes (BI)
 * Aplica estilos corporativos de ERP Farmacias (#E20074 / Magenta primario).
 *
 * @param {Object} payload Datos completos del dashboard de clientes
 * @param {Object} filters Filtros temporales aplicados (start_date, end_date)
 */
export function generateBiCustomerExecutivePdf(payload = {}, filters = {}) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
  const subtitle = `Periodo: ${filters.start_date || 'N/A'} al ${filters.end_date || 'N/A'}`;
  let currentY = applyPremiumHeader(doc, 'Reporte Ejecutivo de Analítica de Clientes', subtitle);

  const kpis = payload.kpis || {};

  // 1. Resumen de Indicadores Clave (KPIs)
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(11);
  doc.setTextColor(226, 0, 116);
  doc.text('1. RESUMEN DE INDICADORES CLAVE (KPIS)', 15, currentY);
  currentY += 4;

  const kpiRows = [
    [
      'Tasa de Retención (CRR)', `${(kpis.crr || 0).toFixed(1)}%`,
      'Tasa de Recompra', `${(kpis.repurchase_rate || 0).toFixed(1)}%`,
    ],
    [
      'Tasa de Abandono (Churn)', `${(kpis.churn_rate || 0).toFixed(1)}%`,
      'LTV Promedio', `$${(kpis.avg_ltv || 0).toFixed(2)}`,
    ],
    [
      'Ticket Promedio (AOV)', `$${(kpis.aov || 0).toFixed(2)}`,
      'Ingresos Totales', `$${(kpis.total_revenue || 0).toFixed(2)} (${kpis.total_orders || 0} órdenes)`,
    ],
  ];

  autoTable(doc, {
    body: kpiRows,
    startY: currentY,
    theme: 'grid',
    styles: { fontSize: 9, cellPadding: 2.5, font: 'helvetica' },
    columnStyles: {
      0: { fontStyle: 'bold', fillColor: [248, 249, 250], cellWidth: 50 },
      1: { cellWidth: 40 },
      2: { fontStyle: 'bold', fillColor: [248, 249, 250], cellWidth: 50 },
      3: { cellWidth: 40 },
    },
  });

  currentY = doc.lastAutoTable.finalY + 8;

  // 2. Pirámide de Valor y Segmentación
  const seg = payload.segmentation || {};
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(11);
  doc.setTextColor(226, 0, 116);
  doc.text('2. SEGMENTACIÓN POR VALOR DEL CLIENTE', 15, currentY);
  currentY += 4;

  const segRows = [
    ['Platino (Top 5%)', `${seg.platinum?.count || 0}`, `$${(seg.platinum?.revenue || 0).toFixed(2)}`, `$${(seg.platinum?.avg_per_client || 0).toFixed(2)}`],
    ['Oro (Top 15%)', `${seg.gold?.count || 0}`, `$${(seg.gold?.revenue || 0).toFixed(2)}`, `$${(seg.gold?.avg_per_client || 0).toFixed(2)}`],
    ['Plata (Top 30%)', `${seg.silver?.count || 0}`, `$${(seg.silver?.revenue || 0).toFixed(2)}`, `$${(seg.silver?.avg_per_client || 0).toFixed(2)}`],
    ['Bronce (Resto 50%)', `${seg.bronze?.count || 0}`, `$${(seg.bronze?.revenue || 0).toFixed(2)}`, `$${(seg.bronze?.avg_per_client || 0).toFixed(2)}`],
  ];

  autoTable(doc, {
    head: [['Segmento', 'N° Clientes', 'Facturación Total', 'Gasto Promedio']],
    body: segRows,
    startY: currentY,
    theme: 'grid',
    headStyles: { fillColor: [226, 0, 116], textColor: [255, 255, 255], fontStyle: 'bold' },
    styles: { fontSize: 8.5, cellPadding: 2 },
  });

  currentY = doc.lastAutoTable.finalY + 8;

  // 3. Clientes Críticos en Riesgo (RFM)
  const atRisk = payload.at_risk || [];
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(11);
  doc.setTextColor(226, 0, 116);
  doc.text('3. TOP CLIENTES CRÍTICOS EN RIESGO DE ABANDONO', 15, currentY);
  currentY += 4;

  if (atRisk.length > 0) {
    const riskRows = atRisk.map((c) => [
      `${c.name || ''} ${c.last_name || ''}`,
      c.phone || 'N/A',
      c.last_order_date || 'N/A',
      `${c.recency_days || 0} días`,
      `$${Number(c.monetary || 0).toFixed(2)}`,
    ]);

    autoTable(doc, {
      head: [['Cliente', 'Teléfono', 'Última Compra', 'Inactividad', 'Gasto Total']],
      body: riskRows,
      startY: currentY,
      theme: 'striped',
      headStyles: { fillColor: [255, 76, 81], textColor: [255, 255, 255], fontStyle: 'bold' },
      styles: { fontSize: 8, cellPadding: 2 },
    });
  }

  applyFooter(doc);
  doc.save(`analitica_clientes_${filters.start_date || 'inicio'}_al_${filters.end_date || 'fin'}.pdf`);
}
