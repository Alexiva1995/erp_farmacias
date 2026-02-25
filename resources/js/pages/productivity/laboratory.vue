<script setup>
import EmployeeLaboratoriesFilters from "@/components/EmployeeLaboratoriesFilters.vue";
import EmployeeLaboratoriesTable from "@/components/EmployeeLaboratoriesTable.vue";
import EmployeeLaboratoryDialog from "@/components/dialogs/EmployeeLaboratoryDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const employeeLaboratories = ref([]);
const totalRecords = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const searchQuery = ref("");
const selectedLaboratory = ref(null);
const laboratories = ref([]);
const employees = ref([]);

const isDialogVisible = ref(false);
const currentEmployee = ref({});
const dialogErrors = ref({});

// Función para obtener los empleados con sus laboratorios
const fetchEmployeeLaboratories = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    laboratory_id: selectedLaboratory.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/employee-laboratories", { params });
    employeeLaboratories.value = response.data.data.data;
    totalRecords.value = response.data.data.total;
  } catch (error) {
    console.error("Error al obtener las asignaciones:", error);
    toast.error("Error al obtener las asignaciones de laboratorios.");
  } finally {
    loading.value = false;
  }
};

// Función para obtener los laboratorios
const fetchLaboratories = async () => {
  try {
    const response = await axios.get("/laboratories");
    laboratories.value = response.data.map((lab) => ({
      title: lab.name,
      value: lab.id,
    }));
  } catch (error) {
    console.error("Error al obtener laboratorios:", error);
    toast.error("Error al cargar los laboratorios.");
  }
};

// Función para obtener los empleados
const fetchEmployees = async () => {
  try {
    const response = await axios.get("/rrhh/employees", {
      params: { itemsPerPage: 1000, active: true },
    });

    employees.value = response.data.data
      .filter((emp) => emp.role_id === 3)
      .map((emp) => ({
        title: `${emp.name} ${emp.last_name}`,
        value: emp.id,
      }));
  } catch (error) {
    console.error("Error al obtener empleados:", error);
    toast.error("Error al cargar los empleados.");
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, selectedLaboratory],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchEmployeeLaboratories(), 300);
  },
  { deep: true },
);

watch([searchQuery, selectedLaboratory], () => {
  page.value = 1;
});

onMounted(() => {
  fetchLaboratories();
  fetchEmployees();
  fetchEmployeeLaboratories();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleDeleteAssignment = async (employeeId, laboratoryId) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "Se eliminará la asignación de este laboratorio al empleado",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(
        `/employee-laboratories/${employeeId}/${laboratoryId}`,
      );
      toast.success("Asignación eliminada con éxito.");
      fetchEmployeeLaboratories();
    } catch (error) {
      console.error("Error al eliminar la asignación:", error);
      toast.error("No se pudo eliminar la asignación.");
    }
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
};

const handleSort = (sortOptions) => {
  if (sortOptions.key === undefined && sortOptions.order === undefined) {
    sortBy.value = undefined;
    orderBy.value = undefined;
  } else {
    sortBy.value = sortOptions.key;
    orderBy.value = sortOptions.order;
  }
};

const handleAddAssignment = () => {
  currentEmployee.value = {};
  dialogErrors.value = {};
  isDialogVisible.value = true;
};

const handleEditAssignment = (employee) => {
  currentEmployee.value = { ...employee };
  dialogErrors.value = {};
  isDialogVisible.value = true;
};

const handleSaveAssignment = async (assignmentData) => {
  try {
    await axios.post("/employee-laboratories", assignmentData);

    const isEditMode = !!currentEmployee.value.employee_id;
    toast.success(
      `Laboratorios ${isEditMode ? "actualizados" : "asignados"} con éxito`,
    );

    isDialogVisible.value = false;
    await fetchEmployeeLaboratories();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      dialogErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar la asignación:", error);
      toast.error("Hubo un error al guardar la asignación.");
    }
  }
};

const clearDialogErrors = () => {
  dialogErrors.value = {};
};
</script>

<template>
  <div>
    <EmployeeLaboratoriesFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      :laboratories="laboratories"
      :loading="loading"
      @clear="handleClearFilters"
      @add-assignment="handleAddAssignment"
      @sort="handleSort"
    />

    <EmployeeLaboratoriesTable
      :employee-laboratories="employeeLaboratories"
      :loading="loading"
      :total-records="totalRecords"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @edit-assignment="handleEditAssignment"
      @delete-assignment="handleDeleteAssignment"
    />

    <EmployeeLaboratoryDialog
      v-model="isDialogVisible"
      :employee="currentEmployee"
      :employees="employees"
      :laboratories="laboratories"
      :errors="dialogErrors"
      @save="handleSaveAssignment"
      @clear-errors="clearDialogErrors"
    />
  </div>
</template>
