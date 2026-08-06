<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from '@/plugins/axios';

import EmployeePerformanceHeader from './components/EmployeePerformanceHeader.vue';
import EmployeePerformanceHallOfFame from './components/EmployeePerformanceHallOfFame.vue';
import EmployeePerformanceRanking from './components/EmployeePerformanceRanking.vue';
import EmployeePerformanceDetail from './components/EmployeePerformanceDetail.vue';
import EmployeePerformanceFaceOff from './components/EmployeePerformanceFaceOff.vue';

// --- ESTADO ---
const loading = ref(false);
const detailLoading = ref(false);
const compareLoading = ref(false);
const errorAlert = ref({ show: false, text: '' });

const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10));
const endDate = ref(new Date().toISOString().substr(0, 10));
const dashboardData = ref(null);
const selectedEmployee = ref(null);
const employeeDetail = ref(null);

// Face-Off
const compareMode = ref(false);
const employeeA = ref(null);
const employeeB = ref(null);
const comparisonData = ref(null);

// --- MOSTRAR ERROR ---
const showError = (msg) => {
  errorAlert.value = { show: true, text: msg };
  setTimeout(() => { errorAlert.value.show = false; }, 4000);
};

// --- CARGA DE DATOS ---
const fetchDashboard = async () => {
  loading.value = true;
  try {
    const params = { start_date: startDate.value, end_date: endDate.value };
    const { data } = await axios.get('/bi/employees/dashboard', { params });
    dashboardData.value = data;
  } catch (error) {
    showError("No se pudo cargar el Balanced Scorecard. Intente nuevamente.");
    console.error("Error al cargar Balanced Scorecard:", error);
  } finally {
    loading.value = false;
  }
};

const fetchDetail = async (id) => {
  detailLoading.value = true;
  selectedEmployee.value = id;
  try {
    const params = { start_date: startDate.value, end_date: endDate.value };
    const { data } = await axios.get(`/bi/employees/${id}/detail`, { params });
    employeeDetail.value = data;
  } catch (error) {
    showError("No se pudo cargar el detalle del vendedor.");
    console.error("Error al cargar detalle de empleado:", error);
  } finally {
    detailLoading.value = false;
  }
};

const fetchComparison = async () => {
  if (!employeeA.value || !employeeB.value) return;
  compareLoading.value = true;
  try {
    const params = { 
      start_date: startDate.value, 
      end_date: endDate.value,
      employee_a: employeeA.value,
      employee_b: employeeB.value
    };
    const { data } = await axios.get('/bi/employees/compare', { params });
    comparisonData.value = data;
  } catch (error) {
    showError("Error al generar la comparación.");
    console.error("Error al cargar comparativa:", error);
  } finally {
    compareLoading.value = false;
  }
};

onMounted(fetchDashboard);

watch([startDate, endDate], () => {
  fetchDashboard();
  if (selectedEmployee.value) fetchDetail(selectedEmployee.value);
  if (employeeA.value && employeeB.value && compareMode.value) fetchComparison();
});
</script>

<template>
  <VContainer fluid class="employee-performance pa-0">
    <!-- Header & Filtros -->
    <EmployeePerformanceHeader
      v-model:compare-mode="compareMode"
      v-model:start-date="startDate"
      v-model:end-date="endDate"
      :loading="loading"
      @refresh="fetchDashboard"
    />

    <!-- Alerta de Errores de la API -->
    <VAlert
      v-if="errorAlert.show"
      type="error"
      variant="tonal"
      closable
      class="mb-4 mx-1"
      @click:close="errorAlert.show = false"
    >
      {{ errorAlert.text }}
    </VAlert>

    <!-- State Loader Principal -->
    <div v-if="loading && !dashboardData" class="d-flex flex-column justify-center align-center h-[60vh] gap-3">
      <VProgressCircular indeterminate color="primary" size="40" />
      <span class="text-disabled text-xs">Cargando Balanced Scorecard...</span>
    </div>

    <!-- VISTA COMPARATIVA (FACE-OFF) -->
    <EmployeePerformanceFaceOff
      v-else-if="compareMode"
      :employees="dashboardData?.employees || []"
      v-model:employee-a="employeeA"
      v-model:employee-b="employeeB"
      :comparison-data="comparisonData"
      :compare-loading="compareLoading"
      @compare="fetchComparison"
    />

    <!-- VISTA PRINCIPAL (RANKING & DRILL-DOWN) -->
    <div v-else-if="dashboardData" class="px-1">
      <EmployeePerformanceHallOfFame :hall-of-fame="dashboardData.hall_of_fame" />

      <VRow dense>
        <!-- Ranking List -->
        <VCol cols="12" md="5">
          <EmployeePerformanceRanking
            :employees="dashboardData.employees"
            :selected-employee="selectedEmployee"
            @select="fetchDetail"
          />
        </VCol>

        <!-- Drill-Down Detail -->
        <VCol cols="12" md="7">
          <EmployeePerformanceDetail
            :employee-detail="employeeDetail"
            :detail-loading="detailLoading"
          />
        </VCol>
      </VRow>
    </div>
  </VContainer>
</template>

<style scoped>
.employee-performance { background-color: transparent; }
.gap-3 { gap: 12px; }
</style>
