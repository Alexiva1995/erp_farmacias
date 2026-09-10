import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import { applyPremiumHeader, applyFooter } from "./pdfBaseStyles";

export function pdfPurchaseOrderGenerator(data) {
  const doc = new jsPDF();
  const today = new Date();

  // 1. Encabezado estandarizado premium
  const startY = applyPremiumHeader(
    doc,
    `ORDEN DE COMPRA #${data.id}`,
    data.supplier ? `Proveedor: ${data.supplier}` : ""
  );

  // 2. Definición de columnas y filas
  const tableColumn = ["Cod", "Nombre", "Cantidad", "Costo (Usd.)", "Subtotal (Usd.)"];
  const tableRows = [];
  let totalValue = 0;

  const formatUsd = (amount) => {
    return (
      new Intl.NumberFormat("es-VE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(amount) + " $"
    );
  };

  const detailsList = data.details?.data || (Array.isArray(data.details) ? data.details : []);

  detailsList.forEach((detail) => {
    const { cod, product_name, quantity, unit_cost } = detail;
    const total = (unit_cost || 0) * (quantity || 0);
    totalValue += total;

    const detailData = [
      {
        content: cod || "N/A",
        styles: { halign: "center" },
      },
      product_name || "N/A",
      {
        content: quantity,
        styles: { halign: "center" },
      },
      {
        content: formatUsd(unit_cost || 0),
        styles: { halign: "right" },
      },
      {
        content: formatUsd(total),
        styles: { halign: "right" },
      },
    ];
    tableRows.push(detailData);
  });

  // Fila del total acumulado
  tableRows.push([
    { content: "TOTAL", colSpan: 3, styles: { halign: "right", fontStyle: "bold" } },
    { content: "TOTAL:", styles: { halign: "right", fontStyle: "bold" } },
    { content: formatUsd(totalValue), styles: { halign: "right", fontStyle: "bold" } },
  ]);

  // 3. Renderizado de la tabla en la página principal
  autoTable(doc, {
    head: [tableColumn],
    body: tableRows,
    startY: startY || 65,
    theme: "grid",
    headStyles: { 
      fillColor: [105, 108, 255], 
      textColor: [255, 255, 255], 
      fontStyle: "bold", 
      halign: "center",
      fontSize: 8.5
    },
    styles: { 
      fontSize: 8, 
      cellPadding: 1.5,
      valign: 'middle'
    },
    columnStyles: {
      0: { cellWidth: 25, halign: 'center' },
      1: { cellWidth: 'auto' },
      2: { cellWidth: 25, halign: 'center' },
      3: { cellWidth: 30, halign: 'right' },
      4: { cellWidth: 30, halign: 'right' },
    },
  });

  // 4. Pie de página con numeración
  applyFooter(doc);

  // 5. Guardar PDF
  const fileName = `Orden_compra_${data.id}_${today.toISOString().slice(0, 10)}.pdf`;
  doc.save(fileName);
}
