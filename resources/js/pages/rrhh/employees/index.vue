<script setup>
import EmployeeFormDialog from "@/components/dialogs/EmployeeFormDialog.vue";
import ResignationFormDialog from "@/components/dialogs/ResignationFormDialog.vue";
import EmployeeFilters from "@/components/EmployeeFilters.vue";
import EmployeeTable from "@/components/EmployeeTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, watch } from "vue";

const showDialog = ref(false);
const showResignationDialog = ref(false);
const loading = ref(false);
const search = ref("");

const roles = ref([]);
const employees = ref([]);
const totalEmployees = ref(0);
const selectedEmployee = ref(null);
const selectedEmployeeForResignation = ref(null);

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

const handleFireEmployee = async (id) => {
  try {
    const { data } = await axios.put(`/rrhh/employees/${id}/fire`);

    if (data.data.status) {
      toast.success("El empleado ha sido despedido exitosamente");

      handleRefreshTable();
    } else {
      toast.success("No se pudo despedir al empleado");
    }
  } catch (error) {
    toast.error("Hubo un error al despedir al empleado");
  }
};

const handleDeleteEmployee = async (employee) => {
  try {
    const form = new FormData();
    form.append("_method", "DELETE");
    await axios.post(`/rrhh/employees/${employee.id}`, form);

    toast.success("Se eliminó el empleado exitosamente");
  } catch (error) {
    toast.error("No se pudo eliminar al empleado");
  }
};

const handleGenerateResignation = (employee) => {
  selectedEmployeeForResignation.value = employee;
  showResignationDialog.value = true;
};

const handleResignationGenerated = (resignationData) => {
  console.log("Renuncia generada:", resignationData);
  toast.success("Carta de renuncia generada exitosamente");
  // Aquí se puede agregar lógica adicional como actualizar la tabla o enviar notificación
};

onMounted(() => Promise.all([fetchEmployees(), fetchRoles()]));

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

    <EmployeeFormDialog
      v-model="showDialog"
      :roles="roles"
      :selectedEmployee="selectedEmployee"
      @refresh-table="handleRefreshTable"
    />

    <ResignationFormDialog
      v-model="showResignationDialog"
      :selectedEmployee="selectedEmployeeForResignation"
      @resignation-generated="handleResignationGenerated"
    />

    <EmployeeTable
      :page="page"
      :items-per-page="itemsPerPage"
      :total="totalEmployees"
      :employees="employees"
      @fire-employee="handleFireEmployee"
      @edit-employee="handleEditEmployee"
      @delete-employee="handleDeleteEmployee"
      @generate-resignation="handleGenerateResignation"
    />
  </div>
</template>
