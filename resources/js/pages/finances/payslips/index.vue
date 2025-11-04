<script setup>
import FinalizePayslipFormDialog from "@/components/dialogs/FinalizePayslipFormDialog.vue";
import PayslipTable from "@/components/PayslipTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfPayslipsGenerator from "@/utils/pdfPayslipGenerator";
import { onMounted, ref } from "vue";

const loading = ref(false);
const page = ref(1);
const totalPayslips = ref(0);
const itemsPerPage = ref(10);
const payslips = ref([]);

const selectedPayslip = ref(null);
const showFinalizeDialog = ref(false);

const fetchPayslips = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get("/finances/payslips");
    payslips.value = data.data.data;
    totalPayslips.value = data.data.total;
  } catch (error) {
    toast.error("No se pudo obtener el registro de nóminas");
  } finally {
    loading.value = false;
  }
};

onMounted(() => fetchPayslips());

const handleFinalizePayslip = (payslip) => {
  showFinalizeDialog.value = true;
  selectedPayslip.value = payslip;
};

const handleClosePayslip = () => {
  showFinalizeDialog.value = false;
  selectedPayslip.value = {};
};

const handleDownloadExcel = async (id) => {
  try {
    const response = await axios.get(
      `/finances/payslips/${id}/download/excel`,
      {
        responseType: "blob",
      }
    );

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = response.headers["content-disposition"];
    let fileName = `payroll.xlsx`;
    if (contentDisposition) {
      const fileNameMatch = contentDisposition.match(/filename="(.+)"/);
      if (fileNameMatch && fileNameMatch.length === 2)
        fileName = fileNameMatch[1];
    }

    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();

    link.remove();
    window.URL.revokeObjectURL(url);

    toast.success("Se ha descargado la nómina exitosamente");
  } catch (error) {
    toast.error("Hubo un error al descargar la nómina");
  }
};

const handleDownloadPdf = async (id, type) => {
  try {
    const { data } = await axios.get(`/finances/payslips/${id}/data/${type}`);

    pdfPayslipsGenerator(data.data, type);
    toast.success("Se ha descargado la nómina exitosamente");
  } catch (error) {
    toast.error("Hubo un error al descargar la nómina");
  }
};
</script>

<template>
  <div>
    <FinalizePayslipFormDialog
      v-model="showFinalizeDialog"
      :selected-payslip="selectedPayslip"
      @refresh-table="fetchPayslips"
      @close="handleClosePayslip"
    />

    <PayslipTable
      :page="page"
      :items-per-page="itemsPerPage"
      :total="totalPayslips"
      :items="payslips"
      :loading="loading"
      @finalize-payslip="handleFinalizePayslip"
      @download-excel="handleDownloadExcel"
      @download-pdf="handleDownloadPdf"
    />
  </div>
</template>
