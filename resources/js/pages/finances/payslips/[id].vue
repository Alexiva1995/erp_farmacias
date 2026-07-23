<script setup>
import ShowSalaryFormDialog from "@/components/dialogs/ShowSalaryFormDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();
const route = useRoute();
const initialTab = route.query.tab === 'eye' ? 'full' : (route.query.tab || "legal");
const tab = ref(initialTab);
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
    { title: `Salario Mensual ${rateMention}`, key: "base_salary_voucher", sortable: true, align: 'end' },
    { title: "Sueldo Base (Pago)", key: "salary_to_pay_voucher", sortable: true, align: 'end' },
    { title: "Cesta Ticket", key: "food_voucher", sortable: true, align: 'end' },
    { title: "Asist. Salud", key: "health_support_voucher", sortable: true, align: 'end' },
    { title: "Rendim. Extra", key: "performance_voucher", sortable: true, align: 'end' },
    { title: "Total Asignaciones", key: "positive_vouchers", sortable: true, align: 'end' },
    { title: "IVSS (4%)", key: "social_security_voucher", sortable: true, align: 'end' },
    { title: "RPE (0.5%)", key: "employment_voucher", sortable: true, align: 'end' },
    { title: "FAOV (1%)", key: "housing_property_benefits_voucher", sortable: true, align: 'end' },
    { title: "Total Deducciones", key: "negative_vouchers", sortable: true, align: 'end' },
    { title: "NETO A COBRAR", key: "total", sortable: true, align: 'end' },
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
  if (tab.value === "legal") {
    list = list.filter((h) => alwaysShow.includes(h.key));
  } else if (tab.value === "full") {
    const fullModeKeys = [
      "employee_full_name",
      "identification",
      "salary_to_pay_voucher",
      "food_voucher",
      "total",
    ];
    list = list.filter((h) => fullModeKeys.includes(h.key)).map(h => {
      if (h.key === 'salary_to_pay_voucher') return { ...h, title: 'Salario Base (Interno)' };
      return h;
    });
  }
  return list;
});

const formatCurrency = (amount) => {
  const newAmount = Number(amount) || 0;
  const isFullMode = tab.value === 'full';
  const currencyCode = selectedPayslip.value?.currency_code;
  const isCop = isFullMode || currencyCode === 'COP';
  const symbol = isCop ? 'COP' : (currencyCode || (tab.value === 'legal' ? 'Bs.' : 'COP'));

  if (isCop) {
    return Math.round(newAmount)
      .toString()
      .replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " COP";
  }

  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(newAmount) + " " + symbol;
};

const formatRate = (rate) => {
  return Math.round(Number(rate) || 0)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ".");
};

const formatIdentification = (id) => {
  if (!id) return '-';
  const num = String(id).replace(/\D/g, '');
  const formatted = num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return `V-${formatted}`;
};

const employeesWithVouchers = computed(() => {
  const rows = selectedPayslip.value?.results || [];
  const payslipDate = selectedPayslip.value?.date;
  const day = payslipDate ? Number(payslipDate.split('-')[2]) : 15;
  const isSecondNomina = day > 15;

  return rows.map(r => {
    const isFull = tab.value === 'full';
    const rate = Number(selectedPayslip.value.exchange_rate) || 1;
    const totalPackageUsd = Number(r.total_package_usd) || 0;
    
    const foodVoucher = isFull 
      ? (isSecondNomina ? (40 * rate) : 0) 
      : (Number(r.food_voucher) || 0);

    const calculatedSalary = isFull 
      ? Math.round(((totalPackageUsd - 40) / 2) * rate * 100) / 100
      : Number(r.salary_to_pay_voucher) || 0;

    const data = {
      ...r,
      employee_full_name: `${r.name || ''} ${r.last_name || ''}`.trim() || 'Desconocido',
      salary_to_pay_voucher: calculatedSalary,
      food_voucher: foodVoucher,
    };

    if (isFull) {
      data.positive_vouchers = calculatedSalary + foodVoucher;
      data.negative_vouchers = 0;
      data.total = data.positive_vouchers;
    } else {
      data.positive_vouchers = (Number(r.salary_to_pay_voucher) || 0) + 
                         foodVoucher + 
                         (Number(r.health_support_voucher) || 0) + 
                         (Number(r.performance_voucher) || 0);
      
      data.negative_vouchers = (Number(r.social_security_voucher) || 0) + 
                         (Number(r.employment_voucher) || 0) + 
                         (Number(r.housing_property_benefits_voucher) || 0);
      
      data.total = data.positive_vouchers - data.negative_vouchers;
    }

    return data;
  });
});

const totals = computed(() => {
  const keys = ['positive_vouchers', 'negative_vouchers', 'total'];
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

const changeTab = (newTab) => {
  tab.value = newTab;
  fetchPayslip();
};
</script>

<template>
  <div class="payroll-details-page pa-0">
    <!-- Header Premium -->
    <div class="header-premium mb-6 overflow-hidden position-relative rounded-lg" :class="mobile ? 'rounded-0' : ''">
      <div class="header-overlay pa-6">
        <div class="d-flex align-center flex-wrap gap-4">
          <VAvatar color="white" variant="flat" size="64" class="rounded-lg shadow-lg">
            <VIcon icon="tabler-file-spreadsheet" size="32" color="primary" />
          </VAvatar>
          <div class="flex-grow-1">
            <div class="d-flex align-center gap-2 mb-1">
              <h1 class="text-h4 font-weight-black text-white leading-tight">
                {{ selectedPayslip?.name || 'Cargando Detalles...' }}
              </h1>
              <VChip
                v-if="selectedPayslip?.status !== undefined"
                :color="selectedPayslip?.status === 1 ? 'success' : 'warning'"
                variant="flat"
                size="x-small"
                class="font-weight-black rounded px-3"
              >
                {{ selectedPayslip?.status === 1 ? 'FINALIZADA' : 'PENDIENTE' }}
              </VChip>
            </div>
            <div class="d-flex align-center flex-wrap gap-4 text-white opacity-80">
              <span class="d-flex align-center text-xs font-weight-bold">
                <VIcon icon="tabler-calendar" size="14" class="me-1" />
                {{ selectedPayslip?.period }}
              </span>
              <span class="d-flex align-center text-xs font-weight-bold">
                <VIcon icon="tabler-currency-dollar" size="14" class="me-1" />
                Ref: 1 USD = {{ formatRate(selectedPayslip?.exchange_rate) }} {{ selectedPayslip?.currency_code }}
              </span>
            </div>
          </div>
          
          <!-- Selector de Pestañas Premium (Píldora) -->
          <div class="tab-pill-container bg-white-opacity-20 pa-1 rounded-pill d-flex gap-1">
            <VBtn
              size="small"
              :variant="tab === 'legal' ? 'flat' : 'text'"
              :color="tab === 'legal' ? 'white' : 'white'"
              class="rounded-pill font-weight-black px-6"
              :class="tab === 'legal' ? 'text-primary' : 'text-white'"
              @click="changeTab('legal')"
            >
              LEGAL (Bs)
            </VBtn>
            <VBtn
              size="small"
              :variant="tab === 'full' ? 'flat' : 'text'"
              :color="tab === 'full' ? 'white' : 'white'"
              class="rounded-pill font-weight-black px-6"
              :class="tab === 'full' ? 'text-primary' : 'text-white'"
              @click="changeTab('full')"
            >
              COMPLETA (COP)
            </VBtn>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Cards Refinadas -->
    <VRow class="mb-6">
      <VCol cols="12" sm="4">
        <VCard class="rounded-lg border-0 shadow-sm stats-card overflow-hidden">
          <VCardText class="d-flex align-center pa-4">
            <VAvatar color="success" variant="tonal" size="48" class="rounded-lg me-4">
              <VIcon icon="tabler-circle-plus" size="24" />
            </VAvatar>
            <div>
              <p class="text-super-xs font-weight-black text-disabled uppercase mb-0">Total Asignaciones</p>
              <h5 class="text-h5 font-weight-black text-success">{{ formatCurrency(totals.positive_vouchers) }}</h5>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard class="rounded-lg border-0 shadow-sm stats-card overflow-hidden">
          <VCardText class="d-flex align-center pa-4">
            <VAvatar color="error" variant="tonal" size="48" class="rounded-lg me-4">
              <VIcon icon="tabler-circle-minus" size="24" />
            </VAvatar>
            <div>
              <p class="text-super-xs font-weight-black text-disabled uppercase mb-0">Total Deducciones</p>
              <h5 class="text-h5 font-weight-black text-error">{{ formatCurrency(totals.negative_vouchers) }}</h5>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard class="rounded-lg border-0 shadow-sm stats-card overflow-hidden bg-primary-gradient shadow-lg">
          <VCardText class="d-flex align-center pa-4">
            <VAvatar color="white" variant="flat" size="48" class="rounded-lg me-4 opacity-20">
              <VIcon icon="tabler-wallet" size="24" color="white" />
            </VAvatar>
            <div>
              <p class="text-super-xs font-weight-black text-white-opacity-60 uppercase mb-0">Neto Consolidado</p>
              <h5 class="text-h5 font-weight-black text-white">{{ formatCurrency(totals.total) }}</h5>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla / Cards de Trabajadores -->
    <div>
      <VCard 
        class="rounded-lg border-0 shadow-sm overflow-hidden bg-surface mb-6"
        :style="loading ? 'opacity: 0.6; pointer-events: none; transition: opacity 0.15s ease;' : 'transition: opacity 0.15s ease;'"
      >
        <VCardTitle class="pa-4 flex align-center bg-surface-variant-opacity-2">
          <VIcon icon="tabler-users" size="20" class="me-2 text-primary" />
          <span class="text-sm font-weight-black uppercase">Listado de Trabajadores</span>
          <VSpacer />
          <VChip size="x-small" variant="tonal" color="primary" class="font-weight-black rounded">
            {{ employeesWithVouchers.length }} PERSONAL
          </VChip>
        </VCardTitle>

        <!-- Vista Escritorio -->
        <VDataTable
          v-if="!mobile"
          :headers="headers"
          :items="employeesWithVouchers"
          :loading="loading"
          class="payroll-table premium-table"
        >
          <template #item.employee_full_name="{ item }">
            <span class="text-xs font-weight-black text-high-emphasis leading-tight">{{ item.employee_full_name }}</span>
          </template>

          <template #item.identification="{ value }">
            <span class="text-xs font-weight-medium">{{ formatIdentification(value) }}</span>
          </template>

          <template v-for="header in headers.filter(h => !['employee_full_name', 'identification'].includes(h.key))" :key="header.key" v-slot:[`item.${header.key}`]="{ value }">
            <span class="text-xs font-weight-bold" :class="header.key === 'total' ? 'text-primary' : ''">
              {{ formatCurrency(value) }}
            </span>
          </template>

          <template #body.append>
            <tr class="footer-totals font-weight-black bg-surface-variant-opacity-2">
              <td colspan="2" class="text-right py-4 text-xs">TOTALES GENERALES</td>
              <template v-for="header in headers" :key="header.key">
                <td v-if="!['employee_full_name', 'identification'].includes(header.key)" class="text-right py-4 text-xs">
                   <span :class="header.key === 'total' ? 'text-primary' : ''">
                     {{ formatCurrency(employeesWithVouchers.reduce((s, i) => s + (Number(i[header.key]) || 0), 0)) }}
                   </span>
                </td>
              </template>
            </tr>
          </template>
        </VDataTable>

        <!-- Vista Móvil Cards -->
        <div v-else class="pa-4 bg-surface-variant-opacity-2">
           <div class="d-flex flex-column gap-4">
             <VCard 
               v-for="item in employeesWithVouchers" 
               :key="item.id"
               class="rounded-lg border-0 shadow-sm overflow-hidden"
             >
               <VCardText class="pa-4">
                 <div class="d-flex align-center justify-space-between mb-4">
                   <div class="d-flex align-center gap-3">
                     <VAvatar color="primary" variant="tonal" size="40" class="rounded-lg">
                       <span class="text-sm font-weight-black">{{ item.name?.[0] }}{{ item.last_name?.[0] }}</span>
                     </VAvatar>
                     <div class="d-flex flex-column">
                       <span class="text-sm font-weight-black leading-tight">{{ item.employee_full_name }}</span>
                       <span class="text-super-xs text-disabled font-weight-bold mt-1">{{ formatIdentification(item.identification) }}</span>
                     </div>
                   </div>
                   <VBtn
                     icon="tabler-eye"
                     variant="tonal"
                     color="primary"
                     size="32"
                     class="rounded-lg"
                     @click="handleShowEditFormDialog(item)"
                   />
                 </div>

                 <VDivider class="mb-4 opacity-10" />

                 <div class="d-flex flex-column gap-2 mb-4">
                   <div class="d-flex justify-space-between align-center">
                     <span class="text-xs text-disabled font-weight-bold uppercase">Asignaciones</span>
                     <span class="text-xs font-weight-black text-success">{{ formatCurrency(item.positive_vouchers) }}</span>
                   </div>
                   <div class="d-flex justify-space-between align-center">
                     <span class="text-xs text-disabled font-weight-bold uppercase">Deducciones</span>
                     <span class="text-xs font-weight-black text-error">{{ formatCurrency(item.negative_vouchers) }}</span>
                   </div>
                 </div>

                 <div class="bg-primary-gradient pa-3 rounded-lg d-flex justify-space-between align-center shadow-sm">
                   <span class="text-xs font-weight-black text-white uppercase">Neto a Cobrar</span>
                   <span class="text-sm font-weight-black text-white">{{ formatCurrency(item.total) }}</span>
                 </div>
               </VCardText>
             </VCard>
           </div>
        </div>
      </VCard>
    </div>

    <ShowSalaryFormDialog
      v-model="showDialog"
      :selected-employee="selectedEmployee"
      :payslip="payrollId"
      @refresh-table="fetchPayslip"
    />
  </div>
</template>

<style scoped>
.header-premium {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #ce93d8 100%);
  min-height: 140px;
}

.header-overlay {
  background: rgba(0, 0, 0, 0.1);
  height: 100%;
}

.bg-primary-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #9575cd 100%);
}

.bg-white-opacity-20 {
  background-color: rgba(255, 255, 255, 0.2);
}

.text-white-opacity-60 {
  color: rgba(255, 255, 255, 0.6);
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.leading-tight {
  line-height: 1.25;
}

.shadow-lg {
  box-shadow: 0 10px 30px -10px rgba(var(--v-theme-primary), 0.5) !important;
}

.stats-card {
  transition: transform 0.2s ease;
}

.stats-card:hover {
  transform: translateY(-4px);
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.premium-table :deep(th) {
  font-size: 0.65rem !important;
  font-weight: 900 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05em !important;
  color: rgba(var(--v-theme-on-surface), 0.5) !important;
}

.footer-totals td {
  border-top: 2px solid rgb(var(--v-theme-primary)) !important;
}
</style>
