<script setup>
import EmployeeProductsFilters from "@/components/EmployeeProductsFilters.vue";
import EmployeeProductsTable from "@/components/EmployeeProductsTable.vue";
import EmployeeProductDialog from "@/components/dialogs/EmployeeProductDialog.vue";
import EmployeeProductsViewDialog from "@/components/dialogs/EmployeeProductsViewDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const employeeProducts = ref([]);
const totalRecords = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const searchQuery = ref("");
const selectedProduct = ref(null);
const products = ref([]);
const employees = ref([]);

const isViewDialogVisible = ref(false);
const currentEmployeeView = ref({});

const isDialogVisible = ref(false);
const currentEmployee = ref({});
const dialogErrors = ref({});

// Función para obtener los empleados con sus productos
const fetchEmployeeProducts = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    product_id: selectedProduct.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/employee-products", { params });
    employeeProducts.value = response.data.data.data;
    totalRecords.value = response.data.data.total;
  } catch (error) {
    console.error("Error al obtener las asignaciones:", error);
    toast.error("Error al obtener las asignaciones de productos.");
  } finally {
    loading.value = false;
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
  [page, itemsPerPage, sortBy, orderBy, searchQuery, selectedProduct],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchEmployeeProducts(), 300);
  },
  { deep: true },
);

watch([searchQuery, selectedProduct], () => {
  page.value = 1;
});

onMounted(() => {
  fetchEmployees();
  fetchEmployeeProducts();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleDeleteAssignment = async (employeeId, productId) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "Se eliminará la asignación de este producto al empleado",
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
      await axios.delete(`/employee-products/${employeeId}/${productId}`);
      toast.success("Asignación eliminada con éxito.");
      fetchEmployeeProducts();
    } catch (error) {
      console.error("Error al eliminar la asignación:", error);
      toast.error("No se pudo eliminar la asignación.");
    }
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedProduct.value = null;
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

const handleViewProducts = (employee) => {
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
    console.log("Datos que se enviarán al backend:", assignmentData);

    await axios.post("/employee-products", assignmentData);

    const isEditMode = !!currentEmployee.value.employee_id;
    toast.success(
      `Productos ${isEditMode ? "actualizados" : "asignados"} con éxito`,
    );

    isDialogVisible.value = false;
    await fetchEmployeeProducts();
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
    <EmployeeProductsFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedProduct="selectedProduct"
      :products="products"
      :loading="loading"
      @clear="handleClearFilters"
      @add-assignment="handleAddAssignment"
      @sort="handleSort"
    />

    <EmployeeProductsTable
      :employee-products="employeeProducts"
      :loading="loading"
      :total-records="totalRecords"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @view-products="handleViewProducts"
      @edit-assignment="handleEditAssignment"
      @delete-assignment="handleDeleteAssignment"
    />

    <EmployeeProductsViewDialog
      v-model="isViewDialogVisible"
      :employee="currentEmployeeView"
    />

    <EmployeeProductDialog
      v-model="isDialogVisible"
      :employee="currentEmployee"
      :employees="employees"
      :products="products"
      :errors="dialogErrors"
      @save="handleSaveAssignment"
      @clear-errors="clearDialogErrors"
    />
  </div>
</template>
