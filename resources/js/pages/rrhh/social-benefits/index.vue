<script setup>
import FireEmployeeDialog from "@/components/dialogs/FireEmployeeDialog.vue";
import SocialBenefitsEmployeeFilter from "@/components/SocialBenefitsEmployeeFilter.vue";
import SocialBenefitsTable from "@/components/SocialBenefitsTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, watch } from "vue";

const search = ref(null);
const loading = ref(false);
const showFireEmployeeDialog = ref(false);
const selectedEmployee = ref({});
const currency = ref(null);

const employees = ref([]);
const totalEmployees = ref(0);

const page = ref(1);
const itemsPerPage = ref(10);

const fetchCurrency = async () => {
  try {
    const { data } = await axios.get("finances/exchange-rates/consultOneBCV");
    currency.value = data.rate;
  } catch (error) {
    toast.error("No se pudo obtener la tasa bcv del dia");
  }
};

const fetchEmployees = async () => {
  loading.value = true;
  try {
    const params = {
      perPage: itemsPerPage.value,
      search: search.value,
    };
    const { data } = await axios.get("/rrhh/social-benefits/employees", {
      params,
    });
    employees.value = data.data.data;
    totalEmployees.value = data.data.total;
  } catch (error) {
    toast.error("No se pudo cargar la lista de empleados");
  } finally {
    loading.value = false;
  }
};

const handleRefreshTable = async () => {
  fetchEmployees();
};

const handleShowFireEmployeeDialog = (employee) => {
  selectedEmployee.value = employee;
  showFireEmployeeDialog.value = true;
};

const handleClearFilters = () => {
  search.value = null;
};

const handleCloseFireDialog = () => {
  showFireEmployeeDialog.value = false;
  selectedEmployee.value = {};
};

const handleDownloadSettlement = async (employee) => {
  try {
    const response = await axios.get(
      `/rrhh/social-benefits/employees/${employee.id}/download-settlement`,
      { responseType: "blob" }
    );
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `liquidacion-${employee.identification}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success("Liquidación descargada con éxito");
  } catch (error) {
    toast.error("No se pudo descargar la liquidación");
  }
};

const selectedEmployeeForUpload = ref(null);
const fileInput = ref(null);

const handleUploadSignedSettlement = (employee) => {
  selectedEmployeeForUpload.value = employee;
  if (fileInput.value) {
    fileInput.value.click();
  }
};

const onFileSelected = async (event) => {
  const file = event.target.files[0];
  if (!file || !selectedEmployeeForUpload.value) return;

  const formData = new FormData();
  formData.append('file', file);

  try {
    loading.value = true;
    await axios.post(`/rrhh/social-benefits/employees/${selectedEmployeeForUpload.value.id}/upload-signed-settlement`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    toast.success('Documento firmado subido con éxito');
    fetchEmployees();
  } catch (error) {
    toast.error('Error al subir el documento firmado');
  } finally {
    loading.value = false;
    // Limpiar input para permitir subir el mismo archivo si es necesario
    if (fileInput.value) fileInput.value.value = '';
    selectedEmployeeForUpload.value = null;
  }
};

const handleDownloadSignedSettlement = async (employee) => {
  try {
    const response = await axios.get(
      `/rrhh/social-benefits/employees/${employee.id}/download-signed-settlement`,
      { responseType: "blob" }
    );
    
    // Obtener extensión del Content-Type o del path si fuera posible, por defecto pdf
    const contentType = response.headers['content-type'];
    const extension = contentType?.includes('image') ? (contentType.includes('png') ? 'png' : 'jpg') : 'pdf';

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `liquidacion-firmada-${employee.identification}.${extension}`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success("Documento firmado descargado con éxito");
  } catch (error) {
    toast.error("No se pudo descargar el documento firmado");
  }
};

onMounted(() => Promise.all([fetchCurrency(), fetchEmployees()]));

let debounceTimer;
watch(
  [page, itemsPerPage, search],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchEmployees(), 300);
  },
  { deep: true }
);
</script>
<template>
  <div>
    <input
      type="file"
      ref="fileInput"
      class="d-none"
      accept="application/pdf,image/*"
      @change="onFileSelected"
    />
    
    <SocialBenefitsEmployeeFilter
      v-model:search="search"
      @clear="handleClearFilters"
    />

    <FireEmployeeDialog
      v-model="showFireEmployeeDialog"
      :selected-employee="selectedEmployee"
      :currency="currency"
      @refresh-table="handleRefreshTable"
      @close="handleCloseFireDialog"
    />

    <SocialBenefitsTable
      :page="page"
      :items-per-page="itemsPerPage"
      :total="totalEmployees"
      :employees="employees"
      :loading="loading"
      @update:options="(options) => { page = options.page; itemsPerPage = options.itemsPerPage; fetchEmployees(); }"
      @fire-employee="handleShowFireEmployeeDialog"
      @download-settlement="handleDownloadSettlement"
      @upload-signed="handleUploadSignedSettlement"
      @download-signed="handleDownloadSignedSettlement"
    />
  </div>
</template>
