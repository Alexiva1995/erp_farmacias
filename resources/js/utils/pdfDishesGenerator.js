import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import { applyFooter } from "./pdfBaseStyles";
import { useBrandingStore } from "@/stores/useBrandingStore";

/**
 * Generador de PDF para Carta / Menú de Platos Activos en 2 Columnas por Categoría.
 * - Prioriza "Waffles" al inicio.
 * - Formato en 2 columnas balanceadas verticalmente.
 * - Diseño limpio: ID (#id), Nombre y Precio Designado.
 * - Sin encabezados corporativos pesados ni textos redundantes.
 *
 * @param {Array} dishes - Lista de platos activos a exportar.
 * @param {Function} formatPriceFn - Función formateadora de moneda.
 * @param {String} currency - Código de la moneda activa (USD, COP, BS).
 */
export const generateActiveDishesPdf = (dishes = [], formatPriceFn, currency = "USD") => {
  const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
  const pageWidth = doc.internal.pageSize.getWidth();
  const pageHeight = doc.internal.pageSize.getHeight();
  const margin = 14;
  const colGap = 8;
  const colWidth = (pageWidth - margin * 2 - colGap) / 2;
  const footerMargin = 15;
  const maxY = pageHeight - footerMargin;

  // Datos de branding
  let storeSettings = {};
  try {
    const store = useBrandingStore();
    storeSettings = store.settings || {};
  } catch (e) {
    // Contexto sin Pinia activo
  }
  const companyName = storeSettings.app_name || "MENÚ";

  // Agrupar platos por categoría
  const groupsMap = new Map();
  (dishes || []).forEach((dish) => {
    const catName = (dish.category?.name || "Otras Delicias").trim();
    if (!groupsMap.has(catName)) {
      groupsMap.set(catName, []);
    }
    groupsMap.get(catName).push(dish);
  });

  // Ordenar categorías: "Waffles" primero siempre, luego alfabético
  const sortedCategories = Array.from(groupsMap.keys()).sort((a, b) => {
    const aIsWaffle = a.toLowerCase().includes("waffle");
    const bIsWaffle = b.toLowerCase().includes("waffle");
    if (aIsWaffle && !bIsWaffle) return -1;
    if (!aIsWaffle && bIsWaffle) return 1;
    return a.localeCompare(b);
  });

  // Dibujar encabezado minimalista superior
  const drawPageHeader = () => {
    doc.setFont("helvetica", "bold");
    doc.setFontSize(15);
    doc.setTextColor(226, 0, 116);
    doc.text(companyName.toUpperCase(), pageWidth / 2, 14, { align: "center" });

    doc.setFont("helvetica", "normal");
    doc.setFontSize(8);
    doc.setTextColor(120, 120, 120);
    doc.text(`CARTA DE PRODUCTOS (${currency})`, pageWidth / 2, 19, { align: "center" });

    doc.setDrawColor(226, 0, 116);
    doc.setLineWidth(0.3);
    doc.line(margin, 21.5, pageWidth - margin, 21.5);
  };

  drawPageHeader();
  const initialTopY = 26;

  // Estado del flujo de dos columnas
  let currentColumn = 0; // 0: Izquierda, 1: Derecha
  let currentY = initialTopY;

  const getColX = (colIndex) => margin + colIndex * (colWidth + colGap);

  sortedCategories.forEach((catName) => {
    const items = groupsMap.get(catName) || [];
    if (!items.length) return;

    // Calcular altura estimada del bloque: encabezado (~7mm) + filas (~5.5mm c/u)
    const estimatedHeight = 8 + items.length * 5.5;

    // Si no cabe en la columna actual, pasar a la siguiente columna o página
    if (currentY + estimatedHeight > maxY) {
      if (currentColumn === 0) {
        currentColumn = 1;
        currentY = initialTopY;
      } else {
        doc.addPage();
        drawPageHeader();
        currentColumn = 0;
        currentY = initialTopY;
      }
    }

    const startX = getColX(currentColumn);

    const bodyRows = items.map((dish) => {
      const formattedPrice = typeof formatPriceFn === "function"
        ? formatPriceFn(dish.designated_price, true, dish.designated_price_cop)
        : dish.designated_price;

      return [
        { content: `#${dish.id}`, styles: { halign: "center", fontStyle: "bold", textColor: [120, 120, 120], cellWidth: 12 } },
        { content: dish.name || "Sin nombre", styles: { halign: "left", fontStyle: "normal", textColor: [30, 30, 30] } },
        { content: String(formattedPrice), styles: { halign: "right", fontStyle: "bold", textColor: [226, 0, 116], cellWidth: 26 } },
      ];
    });

    autoTable(doc, {
      startY: currentY,
      margin: { left: startX, right: pageWidth - (startX + colWidth) },
      tableWidth: colWidth,
      head: [
        [
          {
            content: catName.toUpperCase(),
            colSpan: 3,
            styles: {
              halign: "left",
              fillColor: [248, 248, 248],
              textColor: [30, 30, 30],
              fontStyle: "bold",
              fontSize: 8.5,
              cellPadding: { top: 2, bottom: 2, left: 3, right: 3 },
              lineWidth: { bottom: 0.5 },
              lineColor: [226, 0, 116],
            },
          },
        ],
      ],
      body: bodyRows,
      theme: "plain",
      styles: {
        fontSize: 7.5,
        cellPadding: { top: 1.6, bottom: 1.6, left: 2, right: 2 },
        overflow: "linebreak",
      },
      columnStyles: {
        0: { cellWidth: 12, halign: "center" },
        1: { cellWidth: "auto", halign: "left" },
        2: { cellWidth: 26, halign: "right" },
      },
    });

    currentY = doc.lastAutoTable.finalY + 4;
  });

  applyFooter(doc);

  const today = new Date().toISOString().slice(0, 10);
  doc.save(`Menu_Platos_${today}.pdf`);
};

export default generateActiveDishesPdf;
