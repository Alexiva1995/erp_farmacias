<script setup>
import EmployeeFormDialog from "@/components/dialogs/EmployeeFormDialog.vue";
import FireEmployeeDialog from "@/components/dialogs/FireEmployeeDialog.vue";
import ResignationFormDialog from "@/components/dialogs/ResignationFormDialog.vue";
import EmployeeFilters from "@/components/EmployeeFilters.vue";
import EmployeeTable from "@/components/EmployeeTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const showFireEmployeeDialog = ref(false);
const showDialog = ref(false);
const showResignationDialog = ref(false);
const loading = ref(false);
const search = ref("");
const currency = ref(null);

const roles = ref([]);
const employees = ref([]);
const totalEmployees = ref(0);
const selectedEmployee = ref(null);
const selectedEmployeeForResignation = ref(null);
const isEditingResignation = ref(false);
const existingResignationData = ref(null);

const showActiveEmployees = ref(true);
const page = ref(1);
const itemsPerPage = ref(10);

const fetchEmployees = async () => {
  loading.value = true;
  try {
    const params = {
      perPage: itemsPerPage.value,
      search: search.value,
      active: showActiveEmployees.value,
    };
    const { data } = await axios.get("/rrhh/employees", { params });
    employees.value = data.data;
    totalEmployees.value = data.total;
  } catch (error) {
    toast.error("No se pudo cargar la lista de empleados");
  } finally {
    loading.value = false;
  }
};

const fetchRoles = async () => {
  try {
    const { data } = await axios.get("/roles");
    roles.value = data.data;
  } catch (error) {
    toast.error("No se pudo cargar los roles");
  }
};

const handleShowDialog = () => {
  showDialog.value = true;
};

const handleEditEmployee = (employee) => {
  showDialog.value = true;
  selectedEmployee.value = employee;
};

const handleClearFilters = () => {
  search.value = "";
  showActiveEmployees.value = true;
};

const handleRefreshTable = async () => {
  fetchEmployees();
};

const handleShowFireEmployeeDialog = (employee) => {
  selectedEmployee.value = employee;
  showFireEmployeeDialog.value = true;
};

const handleDeleteEmployee = async (employee) => {
  console.log('Empleado a eliminar:', employee);
  try {
    await axios.delete(`/rrhh/employees/${employee.id}`);
    await fetchEmployees();
    toast.success("Se eliminó el empleado exitosamente");
  } catch (error) {
    console.error('Error al eliminar empleado:', error.response?.data || error.message);
    toast.error("No se pudo eliminar al empleado");
  }
};

const fetchCurrency = async () => {
  try {
    const { data } = await axios.get("finances/exchange-rates/consultOneBCV");
    currency.value = data.rate;
  } catch (error) {
    toast.error("No se pudo obtener la tasa bcv del dia");
  }
};

onMounted(() => Promise.all([fetchEmployees(), fetchRoles(), fetchCurrency()]));

const handleGenerateResignation = async (employee) => {
  try {
    const resignation = employee.resignation;

    if (resignation) {
      // Si existe, abrir en modo edición
      selectedEmployeeForResignation.value = employee;
      existingResignationData.value = {
        ...resignation,
        start_date: resignation.start_date ? resignation.start_date.split('T')[0] : null,
        effective_date: resignation.effective_date ? resignation.effective_date.split('T')[0] : null,
        request_date: resignation.request_date ? resignation.request_date.split('T')[0] : null,
      };
      isEditingResignation.value = true;
      showResignationDialog.value = true;
      return;
    }
  } catch (error) {
    console.error("Error al preparar edición de renuncia:", error);
  }

  // Si no existe, crear nueva
  selectedEmployeeForResignation.value = employee;
  existingResignationData.value = null;
  isEditingResignation.value = false;
  showResignationDialog.value = true;
};

const handleDownloadResignation = async (employee) => {
  if (!employee.resignation?.id) {
    toast.error("No se encontró una renuncia para descargar");
    return;
  }

  try {
    const downloadUrl = `/api/rrhh/resignations/${employee.resignation.id}/download-pdf`;
    
    toast.info("Descargando carta de renuncia...");
    
    const { data } = await axios.get(downloadUrl, { responseType: 'blob' });
    
    const blob = new Blob([data], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `carta-renuncia-${employee.identification}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    toast.error("Error al descargar la carta de renuncia");
    console.error(error);
  }
};

const handleResignationGenerated = (resignationData) => {
  toast.success("Carta de renuncia generada exitosamente");

  // Limpiar el estado del modal
  showResignationDialog.value = false;
  isEditingResignation.value = false;
  existingResignationData.value = null;
  selectedEmployeeForResignation.value = null;

  // Aquí se puede agregar lógica adicional como actualizar la tabla o enviar notificación
};

const handleCloseEmployeeDialog = () => {
  showDialog.value = false;
  selectedEmployee.value = null;
};

const handleReset2FA = async (id) => {
  try {
    await axios.put(`/rrhh/employees/${id}/reset-2fa`);
    toast.success("Autenticación de dos factores reiniciada exitosamente");
  } catch (error) {
    toast.error("No se pudo reiniciar la autenticación de dos factores");
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, search, showActiveEmployees],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchEmployees(), 300);
  },
  { deep: true }
);
</script>
<template>
  <div>
    <EmployeeFilters
      v-model:search="search"
      v-model:show-active-employees="showActiveEmployees"
      @clear="handleClearFilters"
      @add-employee="handleShowDialog"
    />

    <FireEmployeeDialog
      v-model="showFireEmployeeDialog"
      :selected-employee="selectedEmployee"
      :currency="currency"
      @refresh-table="handleRefreshTable"
    />

    <EmployeeFormDialog
      v-model="showDialog"
      :roles="roles"
      :selectedEmployee="selectedEmployee"
      @refresh-table="handleRefreshTable"
      @close="handleCloseEmployeeDialog"
    />

    <ResignationFormDialog
      v-model="showResignationDialog"
      :selectedEmployee="selectedEmployeeForResignation"
      :isEdit="isEditingResignation"
      :existingResignation="existingResignationData"
      @resignation-generated="handleResignationGenerated"
    />

    <EmployeeTable
      :page="page"
      :items-per-page="itemsPerPage"
      :total="totalEmployees"
      :employees="employees"
      :loading="loading"
      @fire-employee="handleShowFireEmployeeDialog"
      @edit-employee="handleEditEmployee"
      @delete-employee="handleDeleteEmployee"
      @generate-resignation="handleGenerateResignation"
      @download-resignation="handleDownloadResignation"
      @reset-2fa="handleReset2FA"
    />
  </div>
</template>
