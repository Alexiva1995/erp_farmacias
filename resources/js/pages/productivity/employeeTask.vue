<script setup>
import EmployeeCleaningFilters from "@/components/EmployeeCleaningFilters.vue";
import EmployeeCleaningTable from "@/components/EmployeeCleaningTable.vue";
import EmployeeAssignmentsTable from "@/components/EmployeeAssignmentsTable.vue";
import EmployeeCleaningDialog from "@/components/dialogs/EmployeeCleaningDialog.vue";
import EmployeeCleaningViewDialog from "@/components/dialogs/EmployeeCleaningViewDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

// Estado de navegación por pestañas
const activeTab = ref('employees');

// Datos para la tabla de empleados
const employeeCleanings = ref([]);
const totalRecords = ref(0);
const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

// Datos para la tabla de asignaciones (lista plana)
const assignments = ref([]);
const totalAssignments = ref(0);
const loadingAssignments = ref(false);
const assignmentPage = ref(1);
const assignmentItemsPerPage = ref(10);
const hideDaily = ref(false);

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
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/employee-cleaning-activities", { params });
    employeeCleanings.value = response.data.data.data;
    totalRecords.value = response.data.data.total;
  } catch (error) {
    console.error("Error al obtener las asignaciones:", error);
    toast.error("Error al obtener las asignaciones de actividades.");
  } finally {
    loading.value = false;
  }
};

// Función para obtener la lista plana de asignaciones
const fetchAssignments = async () => {
  loadingAssignments.value = true;
  const params = {
    q: searchQuery.value,
    hide_daily: hideDaily.value,
    page: assignmentPage.value,
    itemsPerPage: assignmentItemsPerPage.value,
  };

  try {
    const response = await axios.get("/employee-cleaning-activities/assignments", { params });
    assignments.value = response.data.data.data;
    totalAssignments.value = response.data.data.total;
  } catch (error) {
    console.error("Error al obtener lista de asignaciones:", error);
  } finally {
    loadingAssignments.value = false;
  }
};

// Función para obtener las actividades de limpieza
const fetchCleaningActivities = async () => {
  try {
    const response = await axios.get("/cleaning-activities", {
      params: { itemsPerPage: -1 },
    });

    cleaningActivities.value = response.data.data.map((activity) => ({
      title: activity.activity,
      value: activity.id,
      frequency: activity.frequency,
    }));
  } catch (error) {
    console.error("Error al obtener actividades:", error);
  }
};

// Función para obtener los empleados
const fetchEmployees = async () => {
  try {
    const response = await axios.get("/rrhh/employees", {
      params: { itemsPerPage: 500, active: true },
    });
    employees.value = response.data.data
      .filter((emp) => emp.role_id === 3)
      .map((emp) => ({
        title: `${emp.name} ${emp.last_name}`,
        value: emp.id,
      }));
  } catch (error) {
    console.error("Error al obtener empleados:", error);
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, selectedStatus],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      if (activeTab.value === 'employees') {
        fetchEmployeeCleanings();
      } else {
        fetchAssignments();
      }
    }, 300);
  },
  { deep: true },
);

watch(
  [assignmentPage, assignmentItemsPerPage, hideDaily],
  () => {
    if (activeTab.value === 'assignments') {
      fetchAssignments();
    }
  }
);

watch([searchQuery, selectedStatus], () => {
  page.value = 1;
  assignmentPage.value = 1;
});

onMounted(async () => {
  loading.value = true;
  await Promise.all([
    fetchCleaningActivities(),
    fetchEmployees(),
    fetchEmployeeCleanings(),
    fetchAssignments()
  ]);
  loading.value = false;
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const updateAssignmentOptions = (options) => {
  assignmentPage.value = options.page;
  assignmentItemsPerPage.value = options.itemsPerPage;
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
    customClass: {
      confirmButton: 'v-btn v-btn--variant-flat bg-primary rounded-lg px-6',
      cancelButton: 'v-btn v-btn--variant-tonal bg-secondary rounded-lg px-6'
    }
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/employee-cleaning-activities/${employeeId}/${activityId}`);
      toast.success("Asignación eliminada con éxito.");
      fetchEmployeeCleanings();
      fetchAssignments();
    } catch (error) {
      console.error("Error al eliminar la asignación:", error);
      toast.error("No se pudo eliminar la asignación.");
    }
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedStatus.value = null;
  hideDaily.value = false;
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const handleViewActivities = (employee) => {
  currentEmployeeView.value = { ...employee };
  isViewDialogVisible.value = true;
};

const handleAddAssignment = async () => {
  if (employees.value.length === 0) {
    loading.value = true;
    await fetchEmployees();
    loading.value = false;
  }
  currentEmployee.value = {};
  dialogErrors.value = {};
  isDialogVisible.value = true;
};

const handleEditAssignment = async (employee) => {
  if (employees.value.length === 0) {
    loading.value = true;
    await fetchEmployees();
    loading.value = false;
  }
  currentEmployee.value = { ...employee };
  dialogErrors.value = {};
  isDialogVisible.value = true;
};

const handleSaveAssignment = async (assignmentData) => {
  try {
    await axios.post("/employee-cleaning-activities", assignmentData);
    const isEditMode = !!currentEmployee.value.employee_id;
    toast.success(`Actividades ${isEditMode ? "actualizadas" : "asignadas"} con éxito`);
    isDialogVisible.value = false;
    await fetchEmployeeCleanings();
    await fetchAssignments();
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
  <div class="productivity-employee-task-page pb-12">
    <div class="d-flex flex-column gap-3">
      <EmployeeCleaningFilters
        v-model:searchQuery="searchQuery"
        v-model:selectedStatus="selectedStatus"
        :loading="loading"
        @clear="handleClearFilters"
        @add-assignment="handleAddAssignment"
        @sort="handleSort"
      />

      <!-- Pestañas de Navegación Estándar -->
      <VTabs v-model="activeTab" class="mb-2" density="comfortable">
        <VTab value="employees">
          <VIcon start icon="tabler-users" />
          Por Empleado
        </VTab>
        <VTab value="assignments">
          <VIcon start icon="tabler-list-check" />
          Resumen de Asignaciones
        </VTab>
      </VTabs>

      <VWindow v-model="activeTab" class="disable-tab-transition">
        <VWindowItem value="employees">
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
        </VWindowItem>

        <VWindowItem value="assignments">
          <EmployeeAssignmentsTable
            v-model:hideDaily="hideDaily"
            :assignments="assignments"
            :loading="loadingAssignments"
            :total-records="totalAssignments"
            :items-per-page="assignmentItemsPerPage"
            :page="assignmentPage"
            @update:options="updateAssignmentOptions"
            @delete-assignment="handleDeleteAssignment"
          />
        </VWindowItem>
      </VWindow>

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
  </div>
</template>

<style scoped>
:deep(.v-btn.bg-primary) {
  --v-theme-overlay: 255, 255, 255;
}
</style>
