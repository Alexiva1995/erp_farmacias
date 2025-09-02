import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

const LOGO = "/images/logoDonative.png";
const LOGO_WIDTH = 90;
const LOGO_HEIGHT = 35;

export default function pdfPurchaseOrderGenerator(data) {
  const doc = new jsPDF();

  const pageWidth = doc.internal.pageSize.getWidth();
  const xPosition = (pageWidth - LOGO_WIDTH) / 2;

  try {
    doc.addImage(LOGO, "PNG", xPosition, 15, LOGO_WIDTH, LOGO_HEIGHT);
  } catch (error) {
    console.error("jsPDF no pudo añadir la imagen del logo.", error);
  }

  const dateYPosition = 60;
  doc.setFont("helvetica", "normal");
  doc.setFontSize(10);
  doc.setTextColor(0, 0, 0);
  const today = new Date();
  const dateStr = `La Fría, ${today.getDate()}/${String(today.getMonth() + 1).padStart(2, "0")}/${today.getFullYear()}`;

  /**
   * Fecha
   * Membrete
   */
  doc.text(dateStr, pageWidth - 15, dateYPosition, { align: "right" });
  doc.text("J-50540695-7 FARMACIA BARRIO SUCRE 2024, C.A.", pageWidth / 2, dateYPosition + 10, { align: "center" });
  doc.text(
    "Carretera Panamericana, esquina de Carrera 9 Barrio Sucre - La Fría - Tachira.",
    pageWidth / 2,
    dateYPosition + 15,
    { align: "center" },
  );

  /**
   * Condiciones
   */
  const headerY = 75;
  const headerX = 15;
  doc.setFontSize(11);
  doc.setFont("helvetica", "bold");
  doc.text(`Asunto: Pedido ${data.id} y condiciones de recepción`, headerX, headerY + 10);
  doc.text("Estimados,", headerX, headerY + 20);
  doc.setFont("helvetica", "normal");
  const subjectBody =
    "Adjunto nuestra lista de pedido del día. Agradecemos de antemano su atención a las siguientes condiciones, las cuales son indispensables para la recepción y el procesamiento del pago.";
  const subjectSplit = doc.splitTextToSize(subjectBody, pageWidth - 30);
  doc.text(subjectSplit, headerX, headerY + 25);
  doc.setFont("helvetica", "bold");
  doc.text("Condiciones de recepción del pedido", headerX, headerY + 40);
  doc.setFont("helvetica", "normal");
  const expirationDateBody =
    "Fecha de vencimiento: No se facturarán medicamentos con una fecha de vencimiento menor a seis (6) meses. En caso de que se envíen con un tiempo inferior, es su responsabilidad adjuntar una carta de compromiso impresa que refleje el número de factura y garantice la devolución si la mercancía no se vende.";
  const expirationSplit = doc.splitTextToSize(expirationDateBody, pageWidth - 30);
  doc.text(expirationSplit, headerX, headerY + 45);
  const returnBody =
    "Devoluciones: Se devolverá cualquier medicamento que no corresponda a la marca y presentación solicitada. Debido a nuestros procesos internos de verificación, la notificación de estas devoluciones puede tomar hasta 72 horas después de la recepción.";
  const returnSplit = doc.splitTextToSize(returnBody, pageWidth - 30);
  doc.text(returnSplit, headerX, headerY + 65);
  const discountsBody =
    "Descuentos y pagos al contado: Si la factura aplica para un descuento por pago al contado o al momento de la recepción y no nos lo informan explícitamente por este medio, procederemos a aplicar dicho descuento al procesar el pago. Nuestros procesos de pago pueden demorar hasta 72 horas, por lo que es su responsabilidad notificar esta condición al momento de facturar para evitar inconvenientes.";
  const discountsSplit = doc.splitTextToSize(discountsBody, pageWidth - 30);
  doc.text(discountsSplit, headerX, headerY + 80);
  const shippingBody =
    "Plazo de envío: Si el pedido se solicita entre lunes y miércoles, debe ser despachado esa misma semana. De lo contrario, no se recibirá el pedido.";
  const shippingSplit = doc.splitTextToSize(shippingBody, pageWidth - 30);
  doc.text(shippingSplit, headerX, headerY + 100);
  doc.text("Agradecemos su comprensión y colaboración para agilizar nuestros procedimientos.", headerX, headerY + 110);
  doc.text("¡Feliz y bendecido día!", headerX, headerY + 125);

  doc.addPage();

  const tableYPosition = 15;
  const tableColumn = ["Cod", "Nombre", "Cantidad", "Costo (Bs.)", "Costo (Usd.)"];
  const tableRows = [];
  let totalValue = 0;

  const formatBs = (amount) => {
    return (
      new Intl.NumberFormat("es-VE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(amount) + " Bs."
    );
  };
  const formatUsd = (amount) => {
    return (
      new Intl.NumberFormat("es-VE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(amount) + " $"
    );
  };

  data.details.data.forEach((detail) => {
    const { cod, product_name, quantity, unit_cost_bs, unit_cost } = detail;
    const total = unit_cost * quantity;
    totalValue += total;

    const detailData = [
      {
        content: cod,
        styles: { halign: "right" },
      },
      product_name || "N/A",
      {
        content: quantity,
        styles: { halign: "right" },
      },
      {
        content: formatBs(unit_cost_bs),
        styles: { halign: "right" },
      },
      {
        content: formatUsd(unit_cost),
        styles: { halign: "right" },
      },
    ];
    tableRows.push(detailData);
  });

  // Fila del total
  tableRows.push([
    { content: "TOTAL", colSpan: 4, styles: { halign: "right", fontStyle: "bold" } },
    { content: formatUsd(totalValue), styles: { halign: "right", fontStyle: "bold" } },
  ]);

  autoTable(doc, {
    head: [tableColumn],
    body: tableRows,
    startY: tableYPosition,
    theme: "grid",
    headStyles: { fillColor: [220, 220, 220], textColor: 0, fontStyle: "bold", halign: "center" },
    styles: { fontSize: 9, cellPadding: 2 },
    columnStyles: {
      0: { cellWidth: "auto" },
      1: { halign: "center" },
      2: { halign: "right" },
      3: { halign: "right" },
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
  const fileName = `Detalles_orden_compra_${data.id}_${today.toISOString().slice(0, 10)}.pdf`;
  doc.save(fileName);
}
