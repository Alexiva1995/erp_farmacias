<script setup>
import EmployeePaymentDialog from "@/components/dialogs/EmployeePaymentDialog.vue";
import FireEmployeeDialog from "@/components/dialogs/FireEmployeeDialog.vue";
import SocialBenefitsEmployeeFilter from "@/components/SocialBenefitsEmployeeFilter.vue";
import SocialBenefitsTable from "@/components/SocialBenefitsTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, watch } from "vue";

const search = ref(null);
const loading = ref(false);
const showPaymentDialog = ref(false);
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

const handleShowPaymentDialog = (employee) => {
  selectedEmployee.value = employee;
  showPaymentDialog.value = true;
};

const handleShowFireEmployeeDialog = (employee) => {
  selectedEmployee.value = employee;
  showFireEmployeeDialog.value = true;
};

const handlePayEmployee = async (id, type, amount) => {
  try {
    const payments = {
      vacation_voucher: "Vacaciones",
      vacation_bonus_voucher: "Bono Vacacional",
      earnings_voucher: "Utilidades",
    };

    const { data } = await axios.post(
      `/rrhh/social-benefits/employees/${id}/payment`,
      {
        payment: type,
        amount,
      }
    );

    if (data.status) {
      toast.success(`Se registró el pago de ${payments[type]}`);
      showPaymentDialog.value = false;
      selectedEmployee.value = {};
      // Refrescar la tabla para mostrar los datos actualizados
      await fetchEmployees();
    } else {
      toast.error(`No se pudo procesar el pago de ${payments[type]}`);
    }
  } catch (error) {
    if (error.response?.status === 422) {
      toast.error(
        error.response.data.message ||
          "Ya se pagó este concepto para este empleado en el año actual"
      );
    } else {
      toast.error("Hubo un error al agregar el pago del empleado");
    }
  }
};

const handleClearFilters = () => {
  search.value = null;
};

const handleCloseFireDialog = () => {
  showFireEmployeeDialog.value = false;
  selectedEmployee.value = {};
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
    <SocialBenefitsEmployeeFilter
      v-model:search="search"
      @clear="handleClearFilters"
    />

    <EmployeePaymentDialog
      v-model="showPaymentDialog"
      :selected-employee="selectedEmployee"
      :currency="currency"
      @register-payment="handlePayEmployee"
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
      @fire-employee="handleShowFireEmployeeDialog"
      @pay-employee="handleShowPaymentDialog"
    />
  </div>
</template>
