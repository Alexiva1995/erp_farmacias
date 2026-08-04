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
  const cashData = ref(null);
  const orderDataHistory = ref(null);
  const isDownloadCashDataSellers = ref(false);
  const monthlyCashDataSellers = ref(null);

  const downloadcash = async (cash) => {
    try {
      toast.info("Obteniendo detalles del cierre de caja...");
      const detailsResponse = await axios.get(`/finances/cash-closure/${cash.id}`);
      const cashDetailed = detailsResponse.data.data;

      orderDataHistory.value = cashDetailed.orders;
      cashData.value = cashDetailed;
      isDownload.value = true;
      await nextTick();

      const printContents = document.getElementById("HistoryDownload");
      if (!printContents) {
        toast.error("Hubo un error al generar el PDF. Contenido no disponible.");
        return;
      }
      const htmlContent = printContents.innerHTML;

      const response = await axios.post(
        "/finances/cash-closure/generate-pdf",
        {
          html: `<style>${ticketStyles}</style>${htmlContent}`,
          filename: `historico-${cashDetailed.id}.pdf`,
        },
        { responseType: "blob" }
      );
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", `Cierre-Caja-${cashDetailed.id}.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      toast.success("PDF generado y descargado con éxito.");
    } catch (error) {
      console.error("Error al descargar el PDF:", error);
      toast.error("Hubo un error al generar y descargar el PDF.");
    } finally {
      isDownload.value = false;
      orderDataHistory.value = null;
      cashData.value = null;
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
      printWindow.document.write("<html><head><title>Farmacia Barrio Sucre</title>");
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
      const htmlContent = printContents.innerHTML;

      const params = {
        html_content: `<style>${ticketStyles}</style>${htmlContent}`,
        filename: "Cierre de caja",
      };

      const response = await axios.post("/finances/cash-closure/downloadReport", params, { responseType: "blob" });
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", "CierreCaja.pdf");
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
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
    cashData,
    orderDataHistory,
    isDownloadCashDataSellers,
    monthlyCashDataSellers,
    downloadcash,
    printCash,
    closingCashAllSellers,
  };
}
