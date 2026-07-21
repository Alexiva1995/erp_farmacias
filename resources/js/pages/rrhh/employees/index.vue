<script setup>
import EmployeeFormDialog from "@/components/dialogs/EmployeeFormDialog.vue";
import FireEmployeeDialog from "@/components/dialogs/FireEmployeeDialog.vue";
import ResignationFormDialog from "@/components/dialogs/ResignationFormDialog.vue";
import EmployeeFilters from "@/components/EmployeeFilters.vue";
import EmployeeTable from "@/components/EmployeeTable.vue";
import axios from "@/plugins/axios";
import { toast, Swal } from "@/plugins/sweetalert";
import { onMounted, onUnmounted, ref, watch } from "vue";

const showFireEmployeeDialog = ref(false);
const showDialog = ref(false);
const showResignationDialog = ref(false);
const loading = ref(false);
const search = ref("");

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
      page: page.value,
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
  selectedEmployee.value = null;
  showDialog.value = true;
};

const handleEditEmployee = (employee) => {
  selectedEmployee.value = employee;
  showDialog.value = true;
};

const handleClearFilters = () => {
  search.value = "";
  showActiveEmployees.value = true;
  page.value = 1;
};

const handleRefreshTable = async () => {
  await fetchEmployees();
};

const handleShowFireEmployeeDialog = (employee) => {
  selectedEmployee.value = employee;
  showFireEmployeeDialog.value = true;
};

const handleDeleteEmployee = async (employee) => {
  const result = await Swal.fire({
    title: '¿Eliminar empleado?',
    text: `¿Está seguro de que desea eliminar a ${employee.name || ''} ${employee.last_name || ''}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
  });

  if (!result.isConfirmed) return;

  try {
    await axios.delete(`/rrhh/employees/${employee.id}`);
    await fetchEmployees();
    toast.success("Se eliminó el empleado exitosamente");
  } catch (error) {
    toast.error("No se pudo eliminar al empleado");
  }
};

onMounted(() => {
  Promise.all([fetchEmployees(), fetchRoles()]);
});

const handleGenerateResignation = async (employee) => {
  try {
    const resignation = employee.resignation;

    if (resignation) {
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

  let url = null;
  let link = null;
  try {
    const downloadUrl = `/api/rrhh/resignations/${employee.resignation.id}/download-pdf`;
    
    toast.info("Descargando carta de renuncia...");
    
    const { data } = await axios.get(downloadUrl, { responseType: 'blob' });
    
    const blob = new Blob([data], { type: 'application/pdf' });
    url = window.URL.createObjectURL(blob);
    link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `carta-renuncia-${employee.identification}.pdf`);
    document.body.appendChild(link);
    link.click();
  } catch (error) {
    toast.error("Error al descargar la carta de renuncia");
  } finally {
    if (link && link.parentNode) {
      link.remove();
    }
    if (url) {
      window.URL.revokeObjectURL(url);
    }
  }
};

const handleResignationGenerated = () => {
  toast.success("Carta de renuncia generada exitosamente");

  showResignationDialog.value = false;
  isEditingResignation.value = false;
  existingResignationData.value = null;
  selectedEmployeeForResignation.value = null;

  fetchEmployees();
};

const handleCloseEmployeeDialog = () => {
  showDialog.value = false;
  selectedEmployee.value = null;
};

const handleReset2FA = async (id) => {
  const result = await Swal.fire({
    title: '¿Reiniciar 2FA?',
    text: '¿Desea reiniciar la autenticación de dos factores para este empleado?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, reiniciar',
    cancelButtonText: 'Cancelar',
  });

  if (!result.isConfirmed) return;

  try {
    await axios.put(`/rrhh/employees/${id}/reset-2fa`);
    toast.success("Autenticación de dos factores reiniciada exitosamente");
  } catch (error) {
    toast.error("No se pudo reiniciar la autenticación de dos factores");
  }
};

// Reiniciar la página a 1 si cambia la búsqueda o el filtro activo
watch([search, showActiveEmployees], () => {
  page.value = 1;
});

let debounceTimer;
watch(
  [page, itemsPerPage, search, showActiveEmployees],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchEmployees(), 300);
  },
  { deep: true }
);

onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
  }
});
</script>

<template>
  <div class="rrhh-employees-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <EmployeeFilters
        v-model:search="search"
        v-model:show-active-employees="showActiveEmployees"
        @clear="handleClearFilters"
        @add-employee="handleShowDialog"
      />

      <FireEmployeeDialog
        v-model="showFireEmployeeDialog"
        :selected-employee="selectedEmployee"
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
  </div>
</template>
