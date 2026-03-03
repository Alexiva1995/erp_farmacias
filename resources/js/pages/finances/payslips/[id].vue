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

const fullHeaders = [
  { title: "Trabajador", key: "name", sortable: true, align: 'start' },
  { title: "Identificación", key: "identification", sortable: true },
  { title: "Salario (Ref)", key: "base_salary_voucher", sortable: true, align: 'end' },
  { title: "Sueldo Base", key: "salary_to_pay_voucher", sortable: true, align: 'end' },
  { title: "Bono Alim.", key: "food_voucher", sortable: true, align: 'end' },
  { title: "Transp.", key: "transportation_voucher", sortable: true, align: 'end' },
  { title: "Rendim.", key: "performance_voucher", sortable: true, align: 'end' },
  { title: "Facturas", key: "invoice_voucher", sortable: true, align: 'end' },
  { title: "Ventas", key: "sales_voucher", sortable: true, align: 'end' },
  { title: "Crecim.", key: "sales_growth_voucher", sortable: true, align: 'end' },
  { title: "Prods.", key: "assigned_products_voucher", sortable: true, align: 'end' },
  { title: "Utilidades", key: "earnings_voucher", sortable: true, align: 'end' },
  { title: "B. Vac.", key: "vacation_bonus_voucher", sortable: true, align: 'end' },
  { title: "Vac.", key: "vacation_voucher", sortable: true, align: 'end' },
  { title: "Ayuda Fam.", key: "family_support_voucher", sortable: true, align: 'end' },
  { title: "Asignaciones", key: "positive_vouchers", sortable: true, align: 'end', class: 'font-weight-bold text-success' },
  { title: "IVSS/Deduc.", key: "social_security_voucher", sortable: true, align: 'end' },
  { title: "RPE", key: "employment_voucher", sortable: true, align: 'end' },
  { title: "FAOV", key: "housing_property_benefits_voucher", sortable: true, align: 'end' },
  { title: "Inasist.", key: "days_not_worked_voucher", sortable: true, align: 'end' },
  { title: "Préstamos", key: "loans_voucher", sortable: true, align: 'end' },
  { title: "Liq.", key: "settlement_voucher", sortable: true, align: 'end' },
  { title: "Deducciones", key: "negative_vouchers", sortable: true, align: 'end', class: 'font-weight-bold text-error' },
  { title: "NETO", key: "total", sortable: true, align: 'end', class: 'font-weight-black text-primary' },
  { title: "Acciones", key: "actions", sortable: false, align: 'center' },
];

const alwaysShow = [
  "name",
  "identification",
  "base_salary_voucher",
  "salary_to_pay_voucher",
  "food_voucher",
  "positive_vouchers",
  "social_security_voucher",
  "employment_voucher",
  "housing_property_benefits_voucher",
  "negative_vouchers",
  "total",
  "actions",
];

const headers = computed(() => {
  let list = fullHeaders;
  if (tab.value === "legal")
    list = list.filter((h) => alwaysShow.includes(h.key));
  if (selectedPayslip.value?.status === 1)
    list = list.filter((h) => h.key !== "actions");

  return list;
});

const formatCurrency = (amount) => {
  const newAmount = Number(amount) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(newAmount) + (tab.value === "legal" ? " Bs." : " $");
};

const employeesWithVouchers = computed(() => {
  const rows = selectedPayslip.value?.results || [];
  return rows.map(r => ({
    ...r,
    positive_vouchers: (Number(r.salary_to_pay_voucher) || 0) + 
                       (Number(r.food_voucher) || 0) + 
                       (Number(r.transportation_voucher) || 0) + 
                       (Number(r.performance_voucher) || 0) + 
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
            (Number(r.transportation_voucher) || 0) + 
            (Number(r.performance_voucher) || 0) + 
            (Number(r.invoice_voucher) || 0) + 
            (Number(r.sales_voucher) || 0) + 
            (Number(r.sales_growth_voucher) || 0) + 
            (Number(r.assigned_products_voucher) || 0) + 
            (Number(r.earnings_voucher) || 0) + 
            (Number(r.vacation_bonus_voucher) || 0) + 
            (Number(r.vacation_voucher) || 0) + 
            (Number(r.family_support_voucher) || 0)) - 
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
    'salary_to_pay_voucher', 'food_voucher', 'transportation_voucher', 
    'performance_voucher', 'invoice_voucher', 'sales_voucher', 
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
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h3 class="text-h3 font-weight-bold gradient-text mb-1">
          {{ selectedPayslip?.name || 'Cargando Nómina...' }}
        </h3>
        <p class="text-subtitle-1 text-disabled d-flex align-center">
          <VIcon icon="tabler-calendar" size="18" class="me-2" />
          Periodo: {{ selectedPayslip?.period }}
        </p>
      </div>
      <VChip
        :color="selectedPayslip?.status === 1 ? 'success' : 'warning'"
        variant="elevated"
        class="text-uppercase font-weight-black"
      >
        {{ selectedPayslip?.status === 1 ? 'Finalizada' : 'Pendiente' }}
      </VChip>
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
          class="payroll-table"
        >
          <template #item.name="{ item }">
            <div class="d-flex align-center py-2">
              <VAvatar size="32" color="primary" variant="tonal" class="me-3 font-weight-bold text-uppercase" style="font-size: 0.7rem;">
                {{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}
              </VAvatar>
              <div>
                <p class="mb-0 font-weight-bold text-high-emphasis">{{ item.name }} {{ item.last_name }}</p>
                <p class="mb-0 text-caption text-disabled">{{ item.role }}</p>
              </div>
            </div>
          </template>

          <template v-for="header in headers" :key="header.key" v-slot:[`item.${header.key}`]="{ value }">
            <span v-if="!['name', 'identification', 'actions'].includes(header.key)">
              {{ formatCurrency(value) }}
            </span>
            <span v-else-if="header.key === 'identification'">{{ value }}</span>
          </template>

          <template #item.actions="{ item }">
            <div v-if="selectedPayslip.status === 0">
              <VTooltip text="Editar Salario" location="top">
                <template #activator="{ props }">
                  <IconBtn v-bind="props" size="small" color="primary" @click="handleShowEditFormDialog(item)">
                    <VIcon icon="tabler-pencil" size="20" />
                  </IconBtn>
                </template>
              </VTooltip>
            </div>
          </template>

          <template #body.append>
            <tr class="footer-totals font-weight-black">
              <td colspan="2" class="text-right">TOTALES GENERALES</td>
              <template v-for="header in headers" :key="header.key">
                <td v-if="!['name', 'identification', 'actions'].includes(header.key)" class="text-right py-4">
                  {{ formatCurrency(totals[header.key]) }}
                </td>
              </template>
              <td v-if="selectedPayslip.status === 0"></td>
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
</style>
