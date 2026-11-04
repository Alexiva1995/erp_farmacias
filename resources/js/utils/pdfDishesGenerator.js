import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import { applyPremiumHeader, applyFooter } from "./pdfBaseStyles";

/**
 * Generador de PDF para Platos Activos (ID, Nombre y Precio Designado).
 *
 * @param {Array} dishes - Lista de platos activos a exportar.
 * @param {Function} formatPriceFn - Función formateadora de moneda (con redondeos / tasa).
 * @param {String} currency - Código de la moneda activa (USD, COP, BS).
 */
export const generateActiveDishesPdf = (dishes = [], formatPriceFn, currency = "USD") => {
  const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });

  const startY = applyPremiumHeader(doc, "Lista de Platos Activos", `Moneda: ${currency}`);

  const headers = [
    [
      { content: "#", styles: { halign: "center", fontStyle: "bold" } },
      { content: "ID", styles: { halign: "center", fontStyle: "bold" } },
      { content: "Nombre del Plato", styles: { halign: "left", fontStyle: "bold" } },
      { content: `Precio Designado (${currency})`, styles: { halign: "right", fontStyle: "bold" } },
    ],
  ];

  const rows = (dishes || []).map((dish, index) => {
    const formattedPrice = typeof formatPriceFn === "function"
      ? formatPriceFn(dish.designated_price, true, dish.designated_price_cop)
      : dish.designated_price;

    return [
      { content: index + 1, styles: { halign: "center" } },
      { content: dish.id, styles: { halign: "center", fontStyle: "bold" } },
      { content: dish.name || "Sin nombre", styles: { halign: "left" } },
      { content: formattedPrice, styles: { halign: "right", fontStyle: "bold" } },
    ];
  });

  autoTable(doc, {
    startY,
    head: headers,
    body: rows.length ? rows : [["-", "-", "No hay platos activos registrados", "-"]],
    theme: "striped",
    headStyles: {
      fillColor: [226, 0, 116], // Color primario
      textColor: [255, 255, 255],
      fontStyle: "bold",
      fontSize: 9,
      cellPadding: 3,
    },
    alternateRowStyles: {
      fillColor: [250, 250, 250],
    },
    styles: {
      fontSize: 8.5,
      cellPadding: 2.5,
      overflow: "linebreak",
      textColor: [40, 40, 40],
    },
    columnStyles: {
      0: { cellWidth: 12, halign: "center" },
      1: { cellWidth: 20, halign: "center" },
      2: { cellWidth: "auto", halign: "left" },
      3: { cellWidth: 45, halign: "right" },
    },
    margin: { left: 15, right: 15 },
  });

  applyFooter(doc);

  const today = new Date().toISOString().slice(0, 10);
  doc.save(`Platos_Activos_${today}.pdf`);
};

export default generateActiveDishesPdf;
