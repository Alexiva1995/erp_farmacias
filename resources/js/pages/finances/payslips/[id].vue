<script setup>
import ShowSalaryFormDialog from "@/components/dialogs/ShowSalaryFormDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";

const route = useRoute();
const tab = ref("legal");
const loading = ref(false);
const showDialog = ref(false);
const payrollId = route.params.id;

const selectedPayslip = ref({});
const selectedEmployee = ref(null);

const fetchPayslip = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(
      `/finances/payslips/${payrollId}/data/${tab.value}`
    );
    selectedPayslip.value = data.data;
  } catch {
    toast.error("Hubo un error al obtener la nómina");
  } finally {
    loading.value = false;
  }
};
onMounted(fetchPayslip);

const fullHeaders = computed(() => {
  const rateMention = selectedPayslip.value?.exchange_rate ? `(${selectedPayslip.value.exchange_rate} Bs.)` : '';
  return [
    { title: "Trabajador", key: "employee_full_name", sortable: true, align: 'start' },
    { title: "Identificación", key: "identification", sortable: true },
    // Conceptos Salariales
    { title: `Salario Mensual ${rateMention}`, key: "base_salary_voucher", sortable: true, align: 'end', group: 'salarial' },
    { title: "Sueldo Base (Pago)", key: "salary_to_pay_voucher", sortable: true, align: 'end', group: 'salarial' },
    // Conceptos No Salariales (LOTTT)
    { title: "Cesta Ticket", key: "food_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    { title: "Asist. Salud", key: "health_support_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    { title: "Rendim. Extra", key: "performance_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    { title: "Transp.", key: "transportation_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    { title: "Facturas", key: "invoice_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    { title: "Ventas", key: "sales_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    { title: "Crecim.", key: "sales_growth_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    { title: "Prods.", key: "assigned_products_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    { title: "Utilidades", key: "earnings_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    { title: "B. Vac.", key: "vacation_bonus_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    { title: "Vac.", key: "vacation_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    { title: "Ayuda Fam.", key: "family_support_voucher", sortable: true, align: 'end', group: 'no_salarial', class: 'header-no-salarial' },
    // Totales y Deducciones
    { title: "Total Asignaciones", key: "positive_vouchers", sortable: true, align: 'end', class: 'font-weight-bold text-success' },
    { title: "IVSS (4%)", key: "social_security_voucher", sortable: true, align: 'end' },
    { title: "RPE (0.5%)", key: "employment_voucher", sortable: true, align: 'end' },
    { title: "FAOV (1%)", key: "housing_property_benefits_voucher", sortable: true, align: 'end' },
    { title: "Inasist.", key: "days_not_worked_voucher", sortable: true, align: 'end' },
    { title: "Préstamos", key: "loans_voucher", sortable: true, align: 'end' },
    { title: "Liq.", key: "settlement_voucher", sortable: true, align: 'end' },
    { title: "Total Deducciones", key: "negative_vouchers", sortable: true, align: 'end', class: 'font-weight-bold text-error' },
    { title: "NETO A COBRAR", key: "total", sortable: true, align: 'end', class: 'font-weight-black text-primary' },
  ];
});

const alwaysShow = [
  "employee_full_name",
  "identification",
  "base_salary_voucher",
  "salary_to_pay_voucher",
  "food_voucher",
  "health_support_voucher",
  "performance_voucher",
  "positive_vouchers",
  "social_security_voucher",
  "employment_voucher",
  "housing_property_benefits_voucher",
  "negative_vouchers",
  "total",
];

const headers = computed(() => {
  let list = fullHeaders.value;
  if (tab.value === "legal")
    list = list.filter((h) => alwaysShow.includes(h.key));
  
  return list;
});

const formatCurrency = (amount) => {
  const newAmount = Number(amount) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(newAmount) + (tab.value === "legal" ? " Bs." : " $");
};

const formatIdentification = (id) => {
  if (!id) return '-';
  const num = String(id).replace(/\D/g, '');
  const formatted = num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return `V-${formatted}`;
};

const employeesWithVouchers = computed(() => {
  const rows = selectedPayslip.value?.results || [];
  return rows.map(r => ({
    ...r,
    employee_full_name: `${r.name || ''} ${r.last_name || ''}`.trim() || 'Desconocido',
    positive_vouchers: (Number(r.salary_to_pay_voucher) || 0) + 
                       (Number(r.food_voucher) || 0) + 
                       (Number(r.health_support_voucher) || 0) + 
                       (Number(r.performance_voucher) || 0) + 
                       (Number(r.transportation_voucher) || 0) + 
                       (Number(r.invoice_voucher) || 0) + 
                       (Number(r.sales_voucher) || 0) + 
                       (Number(r.sales_growth_voucher) || 0) + 
                       (Number(r.assigned_products_voucher) || 0) + 
                       (Number(r.earnings_voucher) || 0) + 
                       (Number(r.vacation_bonus_voucher) || 0) + 
                       (Number(r.vacation_voucher) || 0) + 
                       (Number(r.family_support_voucher) || 0),
    negative_vouchers: (Number(r.social_security_voucher) || 0) + 
                       (Number(r.employment_voucher) || 0) + 
                       (Number(r.housing_property_benefits_voucher) || 0) + 
                       (Number(r.days_not_worked_voucher) || 0) + 
                       (Number(r.loans_voucher) || 0) + 
                       (Number(r.settlement_voucher) || 0),
    total: ((Number(r.salary_to_pay_voucher) || 0) + 
            (Number(r.food_voucher) || 0) + 
            (Number(r.health_support_voucher) || 0) + 
            (Number(r.performance_voucher) || 0) + 
            (Number(r.transportation_voucher) || 0) + 
            (Number(r.invoice_voucher) || 0) + 
            (Number(r.sales_voucher) || 0) + 
            (Number(r.sales_growth_voucher) || 0) + 
            (Number(r.assigned_products_voucher) || 0) + 
            (Number(r.earnings_voucher) || 0) + 
            (Number(r.vacation_bonus_voucher) || 0) + 
            (Number(r.vacation_voucher) || 0) + 
            (Number(r.family_support_voucher) || 0)) +
           ((Number(r.social_security_voucher) || 0) + 
            (Number(r.employment_voucher) || 0) + 
            (Number(r.housing_property_benefits_voucher) || 0) + 
            (Number(r.days_not_worked_voucher) || 0) + 
            (Number(r.loans_voucher) || 0) + 
            (Number(r.settlement_voucher) || 0))
  }));
});

const totals = computed(() => {
  const keys = [
    'salary_to_pay_voucher', 'food_voucher', 'health_support_voucher',
    'transportation_voucher', 'performance_voucher', 'invoice_voucher', 'sales_voucher', 
    'sales_growth_voucher', 'assigned_products_voucher', 'earnings_voucher', 
    'vacation_bonus_voucher', 'vacation_voucher', 'family_support_voucher', 
    'positive_vouchers', 'social_security_voucher', 'employment_voucher', 
    'housing_property_benefits_voucher', 'days_not_worked_voucher', 
    'loans_voucher', 'settlement_voucher', 'negative_vouchers', 'total'
  ];

  const res = {};
  keys.forEach(k => res[k] = 0);

  employeesWithVouchers.value.forEach(row => {
    keys.forEach(k => res[k] += Number(row[k]) || 0);
  });

  return res;
});

const handleShowEditFormDialog = (item) => {
  showDialog.value = true;
  selectedEmployee.value = item;
};

watch(tab, () => fetchPayslip());
</script>

<template>
  <div class="payroll-details-page">
    <!-- Header Section -->
    <div class="header-glass mb-6 pa-6 d-flex align-center justify-space-between">
      <div class="d-flex align-center">
        <VAvatar color="primary" variant="tonal" size="64" class="me-4 rounded-lg">
          <VIcon icon="tabler-file-spreadsheet" size="36" />
        </VAvatar>
        <div>
          <h2 class="text-h2 font-weight-black text-high-emphasis mb-1">
            {{ selectedPayslip?.name || 'Cargando...' }}
          </h2>
          <div class="d-flex align-center text-subtitle-1 text-medium-emphasis">
            <span class="d-flex align-center me-4">
              <VIcon icon="tabler-calendar-event" size="20" class="me-2 text-primary" />
              {{ selectedPayslip?.period }}
            </span>
            <VChip
              v-if="selectedPayslip?.status !== undefined"
              :color="selectedPayslip?.status === 1 ? 'success' : 'warning'"
              variant="flat"
              size="small"
              class="font-weight-bold px-4"
              rounded="pill"
            >
              {{ selectedPayslip?.status === 1 ? 'FINALIZADA' : 'PENDIENTE' }}
            </VChip>
          </div>
        </div>
      </div>
      
      <div class="text-right d-none d-md-block">
        <p class="text-caption text-uppercase font-weight-bold text-disabled mb-1">Tasa de Cambio</p>
        <div class="d-flex align-center justify-end">
          <span class="text-h4 font-weight-black text-primary me-2">1.00 $</span>
          <VIcon icon="tabler-arrows-right-left" size="20" class="text-disabled me-2" />
          <span class="text-h4 font-weight-black text-success">{{ selectedPayslip?.exchange_rate }} Bs.</span>
        </div>
        <p class="text-caption text-disabled mt-1">* Tasa utilizada para esta nómina</p>
      </div>
    </div>

    <!-- Stats Cards -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard class="stats-card glass-morphism overflow-hidden">
          <VCardText class="d-flex align-center">
            <VAvatar color="primary" variant="tonal" size="48" class="me-4">
              <VIcon icon="tabler-cash" />
            </VAvatar>
            <div>
              <p class="text-caption text-disabled mb-0 font-weight-medium">Total Asignaciones</p>
              <h5 class="text-h5 font-weight-bold text-success">{{ formatCurrency(totals.positive_vouchers) }}</h5>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="stats-card glass-morphism overflow-hidden">
          <VCardText class="d-flex align-center">
            <VAvatar color="error" variant="tonal" size="48" class="me-4">
              <VIcon icon="tabler-minus" />
            </VAvatar>
            <div>
              <p class="text-caption text-disabled mb-0 font-weight-medium">Total Deducciones</p>
              <h5 class="text-h5 font-weight-bold text-error">{{ formatCurrency(totals.negative_vouchers) }}</h5>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="stats-card glass-morphism overflow-hidden highlight-card">
          <VCardText class="d-flex align-center">
            <VAvatar color="success" variant="tonal" size="48" class="me-4">
              <VIcon icon="tabler-wallet" />
            </VAvatar>
            <div>
              <p class="text-caption text-disabled mb-0 font-weight-medium">Neto a Pagar</p>
              <h5 class="text-h5 font-weight-black text-primary">{{ formatCurrency(totals.total) }}</h5>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="stats-card glass-morphism overflow-hidden">
          <VCardText class="d-flex align-center">
            <VAvatar color="info" variant="tonal" size="48" class="me-4">
              <VIcon icon="tabler-chart-arrows" />
            </VAvatar>
            <div>
              <p class="text-caption text-disabled mb-0 font-weight-medium">Tasa Ref.</p>
              <h5 class="text-h5 font-weight-bold">1 $ = {{ selectedPayslip?.exchange_rate }} Bs.</h5>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <ShowSalaryFormDialog
      v-model="showDialog"
      :selected-employee="selectedEmployee"
      :payslip="payrollId"
      @refresh-table="fetchPayslip"
    />

    <VCard class="main-table-card shadow-sm border-0 glass-morphism">
      <VTabs v-model="tab" color="primary" align-tabs="start" class="px-4 pt-2">
        <VTab value="legal">
          <VIcon icon="tabler-building-bank" class="me-2" size="18" />
          Nómina Legal (Bs.)
        </VTab>
        <VTab value="full">
          <VIcon icon="tabler-file-analytics" class="me-2" size="18" />
          Nómina Completa (USD)
        </VTab>
      </VTabs>
      <VDivider />


      <VCardText class="pa-0">
        <VDataTable
          :headers="headers"
          :items="employeesWithVouchers"
          :loading="loading"
          :hide-default-footer="true"
          class="payroll-table mt-4"
        >
          <template #item.employee_full_name="{ item }">
            <span class="font-weight-bold text-high-emphasis">
              {{ item.employee_full_name }}
            </span>
          </template>

          <template #item.identification="{ value }">
            <span class="font-weight-medium text-high-emphasis">
              {{ formatIdentification(value) }}
            </span>
          </template>

          <template v-for="header in headers.filter(h => !['employee_full_name', 'identification'].includes(h.key))" :key="header.key" v-slot:[`item.${header.key}`]="{ value }">
            {{ formatCurrency(value) }}
          </template>

          <template #body.append>
            <tr class="footer-totals font-weight-black">
              <td colspan="2" class="text-right">TOTALES GENERALES</td>
              <template v-for="header in headers" :key="header.key">
                <td v-if="!['employee_full_name', 'identification'].includes(header.key)" class="text-right py-4">
                  {{ formatCurrency(totals[header.key]) }}
                </td>
              </template>
            </tr>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.payroll-details-page {
  padding: 1.5rem;
}

.gradient-text {
  background: linear-gradient(135deg, #7367f0 0%, #ce93d8 100%);
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.glass-morphism {
  border: 1px solid rgba(var(--v-border-color), 0.1) !important;
  backdrop-filter: blur(10px);
  background: rgba(var(--v-theme-surface), 0.7) !important;
}

.stats-card {
  border-radius: 12px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stats-card:hover {
  box-shadow: 0 8px 16px rgba(0, 0, 0, 10%);
  transform: translateY(-4px);
}

.highlight-card {
  border-inline-start: 4px solid rgb(var(--v-theme-primary)) !important;
}

.main-table-card {
  overflow: hidden;
  border-radius: 16px;
}

.payroll-table :deep(th) {
  background: rgba(var(--v-theme-surface), 0.5) !important;
  color: rgba(var(--v-theme-on-surface), 0.7) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.payroll-table :deep(tr:hover) {
  background: rgba(var(--v-theme-primary), 0.03) !important;
}

.footer-totals {
  background: rgba(var(--v-theme-surface), 1) !important;
  border-block-start: 2px solid rgb(var(--v-theme-primary)) !important;
}

.footer-totals td {
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 0.9rem !important;
}

/* Estilos para grupos de columnas */
:deep(.header-salarial) {
  border-block-end: 2px solid rgba(var(--v-theme-info), 0.5) !important;
}

:deep(.header-no-salarial) {
  border-block-end: 2px solid rgba(var(--v-theme-warning), 0.5) !important;
}
</style>
