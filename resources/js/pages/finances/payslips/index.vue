<script setup>
import FinalizePayslipFormDialog from "@/components/dialogs/FinalizePayslipFormDialog.vue";
import GeneratePayslipDialog from "@/components/dialogs/GeneratePayslipDialog.vue";
import PayslipTable from "@/components/PayslipTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const loading = ref(false);
const page = ref(1);
const totalPayslips = ref(0);
const itemsPerPage = ref(10);
const payslips = ref([]);
const searchQuery = ref("");
const startDate = ref(null);
const endDate = ref(null);
const selectedStatus = ref(null);

const selectedPayslip = ref(null);
const showFinalizeDialog = ref(false);
const showGenerateDialog = ref(false);
const isGenerating = ref(false);

const fetchPayslips = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get("/finances/payslips", {
      params: {
        page: page.value,
        itemsPerPage: itemsPerPage.value,
        search: searchQuery.value,
        startDate: startDate.value,
        endDate: endDate.value,
        status: selectedStatus.value,
      },
    });
    payslips.value = data.data.data;
    totalPayslips.value = data.data.total;
  } catch (error) {
    toast.error("No se pudo obtener el registro de nóminas");
  } finally {
    loading.value = false;
  }
};

watch(
  [page, itemsPerPage, searchQuery, startDate, endDate, selectedStatus],
  () => {
    fetchPayslips();
  },
);

const handleClearFilters = () => {
  searchQuery.value = "";
  startDate.value = null;
  endDate.value = null;
  selectedStatus.value = null;
  page.value = 1;
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
      },
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
    const response = await axios.get(`/finances/payslips/${id}/download/pdf`, {
      params: { type },
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(
      new Blob([response.data], { type: "application/pdf" }),
    );
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = response.headers["content-disposition"];
    let fileName = `nomina_${id}.pdf`;
    if (contentDisposition) {
      const match = contentDisposition.match(/filename="?([^"]+)"?/);
      if (match) fileName = match[1];
    }

    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    toast.success("PDF descargado exitosamente");
  } catch (error) {
    toast.error("Hubo un error al descargar el PDF");
  }
};

const handleDownloadBulk = async () => {
  try {
    loading.value = true;
    const response = await axios.get("/finances/payslips/download-bulk-pdf", {
      params: { year: 2025, type: "legal" },
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(
      new Blob([response.data], { type: "application/pdf" }),
    );
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", "nominas_consolidadas_2025.pdf");
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    toast.success("PDF consolidado descargado exitosamente");
  } catch (error) {
    toast.error("Hubo un error al descargar el PDF consolidado");
  } finally {
    loading.value = false;
  }
};

const handleManualPayment = async (date = null) => {
  if (!date) {
    showGenerateDialog.value = true;
    return;
  }

  isGenerating.value = true;
  try {
    const { data } = await axios.post("/finances/payslips", { date });
    if (data.status === "success") {
      toast.success(data.message || "Nómina generada exitosamente");
      showGenerateDialog.value = false;
      fetchPayslips();
    }
  } catch (error) {
    toast.error("Error al generar la nómina manual");
  } finally {
    isGenerating.value = false;
  }
};

const handleRegenerateHistory = async () => {
  const result = await toast.fire({
    title: '¿Regenerar historial?',
    text: "Se borrarán y recalcularán las nóminas del 31/03 y 15/04 con las tasas y exclusiones oficiales. Esta acción no se puede deshacer.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: 'var(--v-theme-primary)',
    cancelButtonColor: 'var(--v-theme-error)',
    confirmButtonText: 'Sí, regenerar',
    cancelButtonText: 'Cancelar'
  });

  if (result.isConfirmed) {
    loading.value = true;
    try {
      const { data } = await axios.post("/finances/payslips/regenerate-history");
      if (data.status === "success") {
        toast.success(data.message || "Historial regenerado con éxito");
        fetchPayslips();
      }
    } catch (error) {
      toast.error("Error al regenerar el historial de nóminas");
    } finally {
      loading.value = false;
    }
  }
};
</script>

<template>
  <div class="payslips-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <FinalizePayslipFormDialog
        v-model="showFinalizeDialog"
        :selected-payslip="selectedPayslip"
        @refresh-table="fetchPayslips"
        @close="handleClosePayslip"
      />

      <GeneratePayslipDialog
        v-model="showGenerateDialog"
        :loading="isGenerating"
        @generate="handleManualPayment"
      />

      <!-- Filtros Premium Colapsables -->
      <PayslipFilters
        v-model:search-query="searchQuery"
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        v-model:selected-status="selectedStatus"
        :loading="loading"
        @clear="handleClearFilters"
        @generated="handleManualPayment"
        @regenerate-history="handleRegenerateHistory"
        @download-bulk="handleDownloadBulk"
        @refresh="fetchPayslips"
      />

      <!-- Tabla y Cards Premium -->
      <PayslipTable
        :page="page"
        :items-per-page="itemsPerPage"
        :total="totalPayslips"
        :items="payslips"
        :loading="loading"
        @update:options="
          (options) => {
            page = options.page;
            itemsPerPage = options.itemsPerPage;
            fetchPayslips();
          }
        "
        @finalize-payslip="handleFinalizePayslip"
        @download-excel="handleDownloadExcel"
        @download-pdf="handleDownloadPdf"
        class="ma-0"
      />
    </div>
  </div>
</template>

<style scoped>
.leading-none {
  line-height: 1;
}
</style>
