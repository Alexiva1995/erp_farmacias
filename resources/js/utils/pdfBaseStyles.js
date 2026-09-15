import jsPDF from "jspdf";
import { useBrandingStore } from "@/stores/useBrandingStore";

/**
 * Aplica el encabezado premium estandarizado a un documento jsPDF.
 * @param {jsPDF} doc - Instancia de jsPDF.
 * @param {string} title - Título principal del reporte.
 * @param {string} subtitle - Subtítulo opcional (ej. Periodo o Filtros).
 * @param {Object} customOptions - Opciones adicionales personalizadas.
 */
export const applyPremiumHeader = (doc, title, subtitle = "", customOptions = {}) => {
  const pageWidth = doc.internal.pageSize.getWidth();
  const margin = 15;
  
  let storeSettings = {};
  try {
    const store = useBrandingStore();
    storeSettings = store.settings || {};
  } catch (e) {
    // Contexto sin Pinia activo
  }

  const companyName = customOptions.companyName || storeSettings.app_name || "FARMACIA";
  const companyRif = customOptions.companyRif !== undefined ? customOptions.companyRif : (storeSettings.app_rif || "");
  const companyLogo = customOptions.logo || storeSettings.app_logo || "/images/logoDonative.png";

  // 1. Logo (Izquierda)
  try {
    if (companyLogo) {
      const LOGO_WIDTH = 45;
      const LOGO_HEIGHT = 18;
      doc.addImage(companyLogo, "PNG", margin, 10, LOGO_WIDTH, LOGO_HEIGHT);
    }
  } catch (error) {
    console.error("No se pudo añadir el logo al PDF", error);
  }

  // 2. Información de la Empresa (Derecha)
  doc.setFont("helvetica", "bold");
  doc.setFontSize(10);
  doc.setTextColor(0, 0, 0);
  doc.text(companyName, pageWidth - margin, 15, { align: "right" });
  
  let currentY = 20;
  if (companyRif) {
    doc.setFontSize(8);
    doc.text(`R.I.F. ${companyRif}`, pageWidth - margin, currentY, { align: "right" });
    currentY += 4;
  }
  
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
