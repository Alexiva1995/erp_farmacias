<script setup>
import EmployeeCleaningFilters from "@/components/EmployeeCleaningFilters.vue";
import EmployeeCleaningTable from "@/components/EmployeeCleaningTable.vue";
import EmployeeCleaningDialog from "@/components/dialogs/EmployeeCleaningDialog.vue";
import EmployeeCleaningViewDialog from "@/components/dialogs/EmployeeCleaningViewDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const employeeCleanings = ref([]);
const totalRecords = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const searchQuery = ref("");
const selectedStatus = ref(null);
const cleaningActivities = ref([]);
const employees = ref([]);

const isViewDialogVisible = ref(false);
const currentEmployeeView = ref({});

const isDialogVisible = ref(false);
const currentEmployee = ref({});
const dialogErrors = ref({});

// Función para obtener los empleados con sus actividades de limpieza
const fetchEmployeeCleanings = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    status: selectedStatus.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/employee-cleaning-activities", {
      params,
    });
    console.log(response.data.data);

    employeeCleanings.value = response.data.data.data;
    totalRecords.value = response.data.data.total;
  } catch (error) {
    console.error("Error al obtener las asignaciones:", error);
    toast.error("Error al obtener las asignaciones de actividades.");
  } finally {
    loading.value = false;
  }
};

// Función para obtener las actividades de limpieza
const fetchCleaningActivities = async () => {
  try {
    const response = await axios.get("/cleaning-activities", {
      params: { itemsPerPage: -1 }, // Obtener todas las actividades
    });

    cleaningActivities.value = response.data.data.map((activity) => ({
      title: activity.activity,
      value: activity.id,
    }));
  } catch (error) {
    console.error("Error al obtener actividades:", error);
    toast.error("Error al cargar las actividades de limpieza.");
  }
};

// Función para obtener los empleados
const fetchEmployees = async () => {
  try {
    const response = await axios.get("/rrhh/employees", {
      params: { itemsPerPage: 1000, active: true },
    });
    employees.value = response.data.data.map((emp) => ({
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
  [page, itemsPerPage, sortBy, orderBy, searchQuery, selectedStatus],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchEmployeeCleanings(), 300);
  },
  { deep: true }
);

watch([searchQuery, selectedStatus], () => {
  page.value = 1;
});

onMounted(() => {
  fetchCleaningActivities();
  fetchEmployees();
  fetchEmployeeCleanings();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleDeleteAssignment = async (employeeId, activityId) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "Se eliminará la asignación de esta actividad al empleado",
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
        `/employee-cleaning-activities/${employeeId}/${activityId}`
      );
      toast.success("Asignación eliminada con éxito.");
      fetchEmployeeCleanings();
    } catch (error) {
      console.error("Error al eliminar la asignación:", error);
      toast.error("No se pudo eliminar la asignación.");
    }
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedStatus.value = null;
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

const handleViewActivities = (employee) => {
  currentEmployeeView.value = { ...employee };
  isViewDialogVisible.value = true;
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
    await axios.post("/employee-cleaning-activities", assignmentData);

    const isEditMode = !!currentEmployee.value.employee_id;
    toast.success(
      `Actividades ${isEditMode ? "actualizadas" : "asignadas"} con éxito`
    );

    isDialogVisible.value = false;
    await fetchEmployeeCleanings();
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
    <EmployeeCleaningFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedStatus="selectedStatus"
      :loading="loading"
      @clear="handleClearFilters"
      @add-assignment="handleAddAssignment"
      @sort="handleSort"
    />

    <EmployeeCleaningTable
      :employee-cleanings="employeeCleanings"
      :loading="loading"
      :total-records="totalRecords"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @view-activities="handleViewActivities"
      @edit-assignment="handleEditAssignment"
      @delete-assignment="handleDeleteAssignment"
    />

    <EmployeeCleaningViewDialog
      v-model="isViewDialogVisible"
      :employee="currentEmployeeView"
    />

    <EmployeeCleaningDialog
      v-model="isDialogVisible"
      :employee="currentEmployee"
      :employees="employees"
      :cleaning-activities="cleaningActivities"
      :errors="dialogErrors"
      @save="handleSaveAssignment"
      @clear-errors="clearDialogErrors"
    />
  </div>
</template>
