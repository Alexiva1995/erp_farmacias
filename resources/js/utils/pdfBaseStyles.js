import jsPDF from "jspdf";

/**
 * Aplica el encabezado premium estandarizado (estilo Nómina) a un documento jsPDF.
 * @param {jsPDF} doc - Instancia de jsPDF.
 * @param {string} title - Título principal del reporte.
 * @param {string} subtitle - Subtítulo opcional (ej. Periodo o Filtros).
 */
export const applyPremiumHeader = (doc, title, subtitle = "") => {
  const pageWidth = doc.internal.pageSize.getWidth();
  const margin = 15;
  
  // 1. Logo (Izquierda)
  try {
    const LOGO = "/images/logoDonative.png";
    const LOGO_WIDTH = 45;
    const LOGO_HEIGHT = 18;
    doc.addImage(LOGO, "PNG", margin, 10, LOGO_WIDTH, LOGO_HEIGHT);
  } catch (error) {
    console.error("No se pudo añadir el logo al PDF", error);
  }

  // 2. Información de la Empresa (Derecha)
  doc.setFont("helvetica", "bold");
  doc.setFontSize(10);
  doc.setTextColor(0, 0, 0);
  doc.text("FARMACIA BARRIO SUCRE 2024, C.A.", pageWidth - margin, 15, { align: "right" });
  
  doc.setFontSize(8);
  doc.text("R.I.F. J-50540695-7", pageWidth - margin, 20, { align: "right" });
  
  doc.setFont("helvetica", "normal");
  doc.setTextColor(100, 100, 100);
  doc.text("Calle Principal Local 05 (L3) Sector Barrio Sucre", pageWidth - margin, 24, { align: "right" });
  doc.text("La Fría, Táchira", pageWidth - margin, 28, { align: "right" });

  // 3. Título del Reporte (Central)
  doc.setDrawColor(0, 0, 0);
  doc.setLineWidth(0.5);
  doc.line(margin, 35, pageWidth - margin, 35); // Línea superior titulo
  
  doc.setFont("helvetica", "bold");
  doc.setFontSize(14);
  doc.setTextColor(0, 0, 0);
  doc.text(title.toUpperCase(), pageWidth / 2, 43, { align: "center" });
  
  doc.line(margin, 48, pageWidth - margin, 48); // Línea inferior titulo

  // 4. Subtítulo o Meta (Fecha/Filtros)
  doc.setFont("helvetica", "normal");
  doc.setFontSize(9);
  const today = new Date().toLocaleDateString("es-VE");
  doc.text(`Fecha de Emisión: ${today}`, margin, 55);
  
  if (subtitle) {
    doc.text(subtitle, pageWidth - margin, 55, { align: "right" });
  }

  return 65; // Retorna la posición Y recomendada para iniciar el contenido
};

/**
 * Aplica el pie de página estándar con numeración.
 * @param {jsPDF} doc 
 */
export const applyFooter = (doc) => {
  const pageCount = doc.internal.getNumberOfPages();
  const pageWidth = doc.internal.pageSize.getWidth();
  const pageHeight = doc.internal.pageSize.getHeight();
  
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i);
    doc.setFont("helvetica", "italic");
    doc.setFontSize(8);
    doc.setTextColor(150, 150, 150);
    doc.text(
      `Página ${i} de ${pageCount} | Generado por ERP Farmacia Barrio Sucre`,
      pageWidth / 2,
      pageHeight - 10,
      { align: "center" }
    );
  }
};
