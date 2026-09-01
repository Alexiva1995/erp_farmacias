import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { nextTick, ref } from "vue";

export const ticketStyles = `
/* CSS Adaptado para Ticket Térmico POS */
@page {
  margin: 0;
  size: 80mm auto;
}
body {
  margin: 0;
  padding: 5px;
  background-color: #fff;
  font-family: 'Courier New', Courier, monospace;
  font-size: 13px !important;
  color: #000 !important;
  line-height: 1.2;
}
* {
  box-sizing: border-box;
}
.pa-2 { padding: 4px; }
.pa-4 { padding: 8px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-left { text-align: left; }
.mb-2 { margin-bottom: 6px; }
.tbody-bordered { border: none; }
.center-block { margin-left: auto; margin-right: auto; }
.w-75, .w-100 { width: 100% !important; }
.mx-auto { margin-left: auto !important; margin-right: auto !important; }
table { width: 100% !important; border-collapse: collapse; }
td, th { padding: 1px 0; }
hr { border: none; border-top: 1px dashed #000; margin: 5px 0; }
.pdf-row-2col { width: 100%; display: block; }
.pdf-col-multi {
  float: left;
  width: 48%; 
  padding: 0 2px; 
  margin-right: 2%;
}
.pdf-row-multi:after {
  content: "";
  display: table; 
  clear: both;
}
.ticket-bold { font-weight: bold; }
.v-card--variant-outlined { border: none !important; }
.v-card {
   box-shadow: none !important;
   border: none !important;
   background: transparent !important;
}
`;

export function useCashClosurePrint() {
  const isDownload = ref(false);
  const isDownloadingPdf = ref(false);
  const isPrinting = ref(false);
  const downloadingCashId = ref(null);
  const cashData = ref(null);
  const orderDataHistory = ref(null);
  const isDownloadCashDataSellers = ref(false);
  const monthlyCashDataSellers = ref(null);

  /**
   * Sanitiza el HTML capturado del DOM para compatibilidad con DomPDF.
   * DomPDF usa motor CSS 2.1 y no soporta propiedades lógicas ni valores lógicos.
   */
  const sanitizeHtmlForDompdf = (html) => {
    return html
      // Propiedades de dimensión lógicas
      .replace(/inline-size/g, 'width')
      .replace(/block-size/g, 'height')
      .replace(/min-inline-size/g, 'min-width')
      .replace(/max-inline-size/g, 'max-width')
      .replace(/min-block-size/g, 'min-height')
      .replace(/max-block-size/g, 'max-height')
      // Márgenes lógicos (orden importa: más específicos primero)
      .replace(/margin-block-start/g, 'margin-top')
      .replace(/margin-block-end/g, 'margin-bottom')
      .replace(/margin-block/g, 'margin-top')
      .replace(/margin-inline-start/g, 'margin-left')
      .replace(/margin-inline-end/g, 'margin-right')
      .replace(/margin-inline/g, 'margin-left')
      // Padding lógico
      .replace(/padding-block-start/g, 'padding-top')
      .replace(/padding-block-end/g, 'padding-bottom')
      .replace(/padding-block/g, 'padding-top')
      .replace(/padding-inline-start/g, 'padding-left')
      .replace(/padding-inline-end/g, 'padding-right')
      .replace(/padding-inline/g, 'padding-left')
      // Bordes lógicos
      .replace(/border-block-start/g, 'border-top')
      .replace(/border-block-end/g, 'border-bottom')
      .replace(/border-block/g, 'border-top')
      .replace(/border-inline-start/g, 'border-left')
      .replace(/border-inline-end/g, 'border-right')
      .replace(/border-inline/g, 'border-left')
      // Valores lógicos de text-align (DomPDF no soporta start/end)
      .replace(/text-align:\s*start/g, 'text-align: left')
      .replace(/text-align:\s*end/g, 'text-align: right');
  };

  /** Estilos específicos para reportes de cierre de caja (tamaño A4) */
  const reportStyles = `
    @page { margin: 10mm; size: A4; }
    body { margin: 0; padding: 0; background: #fff; font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #333; }
    * { box-sizing: border-box; }
    table { width: 100% !important; border-collapse: collapse; }
    .v-card--variant-outlined { border: none !important; }
    .v-card { box-shadow: none !important; border: none !important; background: transparent !important; display: block !important; }
    div, span, p, h1, h2, h3, h4, td, th { display: revert; }
  `;

  const downloadcash = async (cash) => {
    try {
      downloadingCashId.value = cash.id;
      toast.info("Generando reporte de cierre...");

      const response = await axios.get(
        `/finances/cash-closure/download-pdf/${cash.id}`,
        { responseType: "blob" }
      );

      const blob = new Blob([response.data], { type: "application/pdf" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", `Cierre-Caja-${cash.id}.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(() => {
        window.URL.revokeObjectURL(url);
      }, 1000);
      toast.success("PDF descargado con éxito.");
    } catch (error) {
      console.error("Error al descargar el PDF:", error);
      toast.error("Hubo un error al generar y descargar el PDF.");
    } finally {
      downloadingCashId.value = null;
    }
  };

  const printCash = async (cash) => {
    try {
      toast.info("Obteniendo detalles del cierre de caja...");
      const detailsResponse = await axios.get(`/finances/cash-closure/${cash.id}`);
      const cashDetailed = detailsResponse.data.data;

      isDownloadingPdf.value = false;
      cashData.value = cashDetailed;
      isPrinting.value = true;
      await nextTick();

      const printContents = document.getElementById("CashClosurePrint");
      if (!printContents) {
        window.print();
        return;
      }

      const printWindow = window.open("", "", "height=600,width=800");
      printWindow.document.write("<html><head><title>Cierre de Caja</title>");
      const styleSheets = document.styleSheets;
      for (let i = 0; i < styleSheets.length; i++) {
        const sheet = styleSheets[i];
        try {
          if (sheet.cssRules) {
            let cssText = "";
            for (let j = 0; j < sheet.cssRules.length; j++) {
              cssText += sheet.cssRules[j].cssText;
            }
            printWindow.document.write(`<style>${cssText}</style>`);
          } else if (sheet.href) {
            printWindow.document.write(`<link rel="stylesheet" href="${sheet.href}">`);
          }
        } catch (e) {
          console.warn("No se pudo acceder a la hoja de estilo:", sheet.href || sheet, e);
        }
      }
      printWindow.document.write("</head><body>");
      printWindow.document.write(printContents.innerHTML);
      printWindow.document.write("</body></html>");
      printWindow.document.close();
      printWindow.focus();
      printWindow.print();
      printWindow.close();
    } catch (error) {
      toast.error("No se pudo cargar los detalles del cierre de caja.");
      isPrinting.value = false;
      cashData.value = null;
      isDownloadingPdf.value = false;
    } finally {
      setTimeout(() => {
        isPrinting.value = false;
        cashData.value = null;
        isDownloadingPdf.value = false;
      }, 500);
    }
  };

  const closingCashAllSellers = async (cash) => {
    try {
      const paramsData = { closingMonthlyIds: cash.daily_closure_ids };
      const responseData = await axios.get("/finances/cash-closure/monthlyCashclosingAllSellers", { params: paramsData });
      monthlyCashDataSellers.value = responseData.data.data;

      isDownloadCashDataSellers.value = true;
      await nextTick();

      const printContents = document.getElementById("cashClosingSellersDownload");
      if (!printContents) {
        toast.error("Hubo un error al generar el PDF. Contenido no disponible.");
        return;
      }
      const rawHtml = printContents.innerHTML;
      const htmlContent = sanitizeHtmlForDompdf(rawHtml);

      const params = {
        html_content: `<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><style>${reportStyles}</style></head><body>${htmlContent}</body></html>`,
        filename: "Cierre de caja",
      };

      const response = await axios.post("/finances/cash-closure/downloadReport", params, { responseType: "blob" });
      const blob = new Blob([response.data], { type: "application/pdf" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", "CierreCaja.pdf");
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(() => {
        window.URL.revokeObjectURL(url);
      }, 1000);
      toast.success("PDF generado y descargado con éxito.");
    } catch (error) {
      console.error("Error al obtener los detalles del cierre por vendedor:", error);
      toast.error("Error al obtener los detalles del cierre por vendedor.");
    } finally {
      isDownloadCashDataSellers.value = false;
      monthlyCashDataSellers.value = null;
    }
  };

  return {
    isDownload,
    isDownloadingPdf,
    isPrinting,
    downloadingCashId,
    cashData,
    orderDataHistory,
    isDownloadCashDataSellers,
    monthlyCashDataSellers,
    downloadcash,
    printCash,
    closingCashAllSellers,
  };
}
