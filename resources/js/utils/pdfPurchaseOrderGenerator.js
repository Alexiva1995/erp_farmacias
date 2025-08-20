import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

export default function pdfPurchaseOrderGenerator(data) {
  const doc = new jsPDF();
  const pageWidth = doc.internal.pageSize.getWidth();

  // 2. Encabezado
  const headerY = 10; // Ajusta según necesidad
  doc.setFontSize(18);
  doc.setFont("helvetica", "bold");
  doc.text("ORDEN DE COMPRA", pageWidth / 2, headerY, { align: "center" });
  doc.text(data.supplier, pageWidth / 2, headerY + 5, { align: "center" });

  // 4. Fecha y detalles
  const today = new Date();
  const dateStr = `Generado el: ${today.getDate()}/${String(today.getMonth() + 1).padStart(2, "0")}/${today.getFullYear()}`;
  doc.text(dateStr, pageWidth - 15, headerY + 15, { align: "right" });

  // 5. Configuración de la tabla
  const headers = [
    [
      { content: "#", styles: { halign: "center", fontStyle: "bold" } },
      { content: "Nombre", styles: { fontStyle: "bold" } },
      { content: "Cantidad" },
      { content: "Coste" },
      { content: "Subtotal" },
    ],
  ];

  const rows = data.details.map((detail, index) => [
    { content: index + 1, styles: { halign: "center" } },
    detail.product_name,
    detail.quantity,
    detail.unit_cost,
    (detail.quantity * detail.unit_cost).toFixed(2),
  ]);
  const rowsWithFooter = [...rows, ["", "Total", data.total_quantity, "", data.total_cost]];

  // 6. Generar tabla
  autoTable(doc, {
    startY: headerY + 25,
    head: headers,
    body: rowsWithFooter,
    theme: "grid",
    headStyles: {
      fillColor: [41, 128, 185],
      textColor: 255,
      halign: "center",
    },
    alternateRowStyles: {
      fillColor: [245, 245, 245],
    },
    styles: {
      fontSize: 9,
      cellPadding: 3,
      overflow: "linebreak",
    },
    columnStyles: {
      0: { cellWidth: 20, halign: "center" },
      1: { cellWidth: 70 },
      2: { cellWidth: 20 },
      3: { cellWidth: 40 },
      4: { cellWidth: 40, halign: "center" },
    },
    margin: { left: 15, right: 15 },
  });

  // 7. Pie de página
  const pageCount = doc.internal.getNumberOfPages();
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i);
    doc.setFontSize(10);
    doc.text(`Página ${i} de ${pageCount}`, pageWidth / 2, doc.internal.pageSize.height - 10, { align: "center" });
  }

  // 8. Guardar PDF
  const fileName = `Detalles_orden_compra_${today.toISOString().slice(0, 10)}.pdf`;
  doc.save(fileName);
}
