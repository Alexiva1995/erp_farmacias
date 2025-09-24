<script setup>
import ShowSalaryFormDialog from "@/components/dialogs/ShowSalaryFormDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref } from "vue";
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
    const { data } = await axios.get(`/finances/payslips/${payrollId}/data`);

    selectedPayslip.value = data.data;
  } catch (error) {
    toast.error("Hubo un error al obtener la nómina");
  } finally {
    loading.value = false;
  }
};

onMounted(() => [fetchPayslip()]);

const fullHeaders = [
  { title: "ID", key: "id", sortable: false },
  { title: "Nombre del Trabajador", key: "name", sortable: false },
  { title: "Cédula", key: "identification", sortable: false },
  { title: "Cargo", key: "role", sortable: false },
  { title: "Salario Mensual", key: "base_salary_voucher", sortable: false },
  { title: "Sueldo a Pagar", key: "salary_to_pay_voucher", sortable: false },
  { title: "Bono de alimentación", key: "food_voucher", sortable: false },
  {
    title: "Bono de Transporte",
    key: "transportation_voucher",
    sortable: false,
  },
  { title: "Bono de Rendimiento", key: "performance_voucher", sortable: false },
  { title: "Bono de Facturas", key: "invoice_voucher", sortable: false },
  { title: "Bono de Ventas", key: "sales_voucher", sortable: false },
  {
    title: "Bono de Crecimiento de Ventas",
    key: "sales_growth_voucher",
    sortable: false,
  },
  {
    title: "Bono de Productos Asignados",
    key: "assigned_products_voucher",
    sortable: false,
  },
  { title: "Utilidades", key: "earnings_voucher", sortable: false },
  {
    title: "Bono de Vacacional",
    key: "vacation_bonus_voucher",
    sortable: false,
  },
  { title: "Vacaciones", key: "vacation_voucher", sortable: false },
  {
    title: "Bono de Ayuda Familiar",
    key: "family_support_voucher",
    sortable: false,
  },
  {
    title: "Sueldo + Asignaciones",
    key: "positive_vouchers",
    sortable: false,
  },
  {
    title: "Seguro Social 4%",
    key: "social_security_voucher",
    sortable: false,
  },
  {
    title: "Prestacional de Empleo",
    key: "employment_voucher",
    sortable: false,
  },
  {
    title: "Prest. Vivienda y Habitat",
    key: "housing_property_benefits_voucher",
    sortable: false,
  },
  {
    title: "Dias NO Trabajados",
    key: "days_not_worked_voucher",
    sortable: false,
  },
  {
    title: "Prestamos",
    key: "loans_voucher",
    sortable: false,
  },
  {
    title: "Liquidación",
    key: "settlement_voucher",
    sortable: false,
  },
  {
    title: "Total Deducciones",
    key: "negative_vouchers",
    sortable: false,
  },
  { title: "NETO A PAGAR", key: "total", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const alwaysShow = [
  "id",
  "name",
  "identification",
  "role",
  "base_salary_voucher",
  "salary_to_pay_voucher",
  "food_voucher",
  "positive_vouchers",
  "social_security_voucher",
  "employment_voucher",
  "housing_property_benefits_voucher",
  "days_not_worked_voucher",
  "negative_vouchers",
  "total",
];

const headers = computed(() => {
  let list = fullHeaders;

  if (tab.value === "legal") {
    list = list.filter((h) => alwaysShow.includes(h.key));
  }

  if (selectedPayslip.value?.status === 1) {
    list = list.filter((h) => h.key !== "actions");
  } else {
    list = list.filter(
      (h) => h.key === "actions" || !h.key.startsWith("actions_")
    );
  }

  return list;
});

const formatBs = (amount) => {
  return (
    new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount) + " Bs."
  );
};

const totals = computed(() => {
  const empty = {
    salary_to_pay_voucher: 0,
    food_voucher: 0,
    transportation_voucher: 0,
    performance_voucher: 0,
    invoice_voucher: 0,
    sales_voucher: 0,
    sales_growth_voucher: 0,
    assigned_products_voucher: 0,
    earnings_voucher: 0,
    vacation_bonus_voucher: 0,
    vacation_voucher: 0,
    family_support_voucher: 0,
    positive_vouchers: 0,
    employment_voucher: 0,
    housing_property_benefits_voucher: 0,
    days_not_worked_voucher: 0,
    social_security_voucher: 0,
    loans_voucher: 0,
    settlement_voucher: 0,
    negative_vouchers: 0,
    total: 0,

    totalSalaries: 0,
    totalDeductions: 0,
    totalToPay: 0,
  };

  if (!selectedPayslip.value?.results?.length) return empty;

  const res = selectedPayslip.value.results.reduce(
    (acc, row) => {
      acc.salary_to_pay_voucher += Number(row.salary_to_pay_voucher || 0);
      acc.food_voucher += Number(row.food_voucher || 0);
      acc.transportation_voucher += Number(row.transportation_voucher || 0);
      acc.performance_voucher += Number(row.performance_voucher || 0);
      acc.invoice_voucher += Number(row.invoice_voucher || 0);
      acc.sales_voucher += Number(row.sales_voucher || 0);
      acc.sales_growth_voucher += Number(row.sales_growth_voucher || 0);
      acc.assigned_products_voucher += Number(
        row.assigned_products_voucher || 0
      );
      acc.earnings_voucher += Number(row.earnings_voucher || 0);
      acc.vacation_bonus_voucher += Number(row.vacation_bonus_voucher || 0);
      acc.vacation_voucher += Number(row.vacation_voucher || 0);
      acc.family_support_voucher += Number(row.family_support_voucher || 0);
      acc.positive_vouchers += Number(row.positive_vouchers || 0);
      acc.employment_voucher += Number(row.employment_voucher || 0);
      acc.housing_property_benefits_voucher += Number(
        row.housing_property_benefits_voucher || 0
      );
      acc.days_not_worked_voucher += Number(row.days_not_worked_voucher || 0);
      acc.social_security_voucher += Number(row.social_security_voucher || 0);
      acc.loans_voucher += Number(row.loans_voucher || 0);
      acc.settlement_voucher += Number(row.settlement_voucher || 0);
      acc.negative_vouchers += Number(row.negative_vouchers || 0);
      acc.total += Number(row.total || 0);

      return acc;
    },
    { ...empty }
  );

  res.totalSalaries = res.positive_vouchers;
  res.totalDeductions = res.negative_vouchers;
  res.totalToPay = res.totalSalaries - res.totalDeductions;

  return res;
});

const handleShowEditFormDialog = (item) => {
  showDialog.value = true;
  selectedEmployee.value = item;
};
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <VRow class="ma-1">
          <h5 class="text-h5 font-weight-bold">
            {{ selectedPayslip.name }} {{ selectedPayslip.date }}
          </h5>
        </VRow>
      </VCardText>
    </VCard>

    <ShowSalaryFormDialog
      v-model="showDialog"
      :selected-employee="selectedEmployee"
      :payslip="payrollId"
      @refresh-table="fetchPayslip"
    />

    <VCard class="mt-2">
      <VCardText>
        <VTabs v-model="tab">
          <VTab value="legal">Legal</VTab>
          <VTab value="full">Completo</VTab>
        </VTabs>

        <VTabsWindow v-model="tab">
          <VTabsWindowItem value="legal">
            <VDataTable
              :headers="headers"
              :items="selectedPayslip.results"
              :loading="loading"
              :hide-default-footer="true"
              class="mt-8"
            >
              <template #item.name="{ item }">
                <span>{{ item.name }} {{ item.last_name }}</span>
              </template>
              <template #item.base_salary_voucher="{ item }">
                <span>{{ formatBs(item.base_salary_voucher) }}</span>
              </template>
              <template #item.salary_to_pay_voucher="{ item }">
                <span>{{ formatBs(item.salary_to_pay_voucher) }}</span>
              </template>
              <template #item.food_voucher="{ item }">
                <span>{{ formatBs(item.food_voucher) }}</span>
              </template>
              <template #item.positive_vouchers="{ item }">
                <span>{{ formatBs(item.positive_vouchers) }}</span>
              </template>
              <template #item.employment_voucher="{ item }">
                <span>{{ formatBs(item.employment_voucher) }}</span>
              </template>
              <template #item.housing_property_benefits_voucher="{ item }">
                <span>{{
                  formatBs(item.housing_property_benefits_voucher)
                }}</span>
              </template>
              <template #item.days_not_worked_voucher="{ item }">
                <span>{{ formatBs(item.days_not_worked_voucher) }}</span>
              </template>
              <template #item.negative_vouchers="{ item }">
                <span>{{ formatBs(item.negative_vouchers) }}</span>
              </template>
              <template #item.total="{ item }">
                <span>{{ formatBs(item.total) }}</span>
              </template>
              <template #item.actions="{ item }">
                <VTooltip text="Editar Salario" location="top">
                  <template
                    v-if="selectedPayslip.status === 0"
                    #activator="{ props }"
                  >
                    <IconBtn
                      v-bind="props"
                      @click="handleShowEditFormDialog(item)"
                    >
                      <VIcon icon="tabler-pencil" />
                    </IconBtn>
                  </template>
                </VTooltip>
              </template>

              <template #body.append>
                <tr class="font-weight-bold">
                  <td :colspan="6" class="text-right">
                    {{ formatBs(totals.salary_to_pay_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.food_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.positive_vouchers) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.social_security_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.employment_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.housing_property_benefits_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.days_not_worked_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.negative_vouchers) }}
                  </td>
                  <td class="text-right">{{ formatBs(totals.total) }}</td>
                  <td></td>
                </tr>
                <tr class="font-weight-bold">
                  <td colspan="4" class="text-right">Total Sueldos:</td>
                  <td colspan="4" class="text-right">
                    {{ formatBs(totals.totalSalaries) }}
                  </td>

                  <td colspan="4" class="text-right">Total Deducción:</td>
                  <td class="text-right">
                    {{ formatBs(totals.totalDeductions) }}
                  </td>
                  <td class="text-right">{{ formatBs(totals.totalToPay) }}</td>
                </tr>

                <tr class="font-weight-bold">
                  <td colspan="3" class="text-right">
                    Total a Pagar en Nómina:
                  </td>
                  <td colspan="3" class="text-right">
                    {{ formatBs(totals.totalToPay) }}
                  </td>
                </tr>
              </template>
            </VDataTable>
          </VTabsWindowItem>
          <VTabsWindowItem value="full">
            <VDataTable
              :headers="headers"
              :items="selectedPayslip.results"
              :loading="loading"
              :hide-default-footer="true"
              class="mt-8"
            >
              <template #item.name="{ item }">
                <span>{{ item.name }} {{ item.last_name }}</span>
              </template>
              <template #item.base_salary_voucher="{ item }">
                <span>{{ formatBs(item.base_salary_voucher) }}</span>
              </template>
              <template #item.salary_to_pay_voucher="{ item }">
                <span>{{ formatBs(item.salary_to_pay_voucher) }}</span>
              </template>
              <template #item.food_voucher="{ item }">
                <span>{{ formatBs(item.food_voucher) }}</span>
              </template>
              <template #item.transportation_voucher="{ item }">
                <span>{{ formatBs(item.transportation_voucher) }}</span>
              </template>
              <template #item.performance_voucher="{ item }">
                <span>{{ formatBs(item.performance_voucher) }}</span>
              </template>
              <template #item.invoice_voucher="{ item }">
                <span>{{ formatBs(item.invoice_voucher) }}</span>
              </template>
              <template #item.sales_voucher="{ item }">
                <span>{{ formatBs(item.sales_voucher) }}</span>
              </template>
              <template #item.sales_growth_voucher="{ item }">
                <span>{{ formatBs(item.sales_growth_voucher) }}</span>
              </template>
              <template #item.assigned_products_voucher="{ item }">
                <span>{{ formatBs(item.assigned_products_voucher) }}</span>
              </template>
              <template #item.earnings_voucher="{ item }">
                <span>{{ formatBs(item.earnings_voucher) }}</span>
              </template>
              <template #item.vacation_bonus_voucher="{ item }">
                <span>{{ formatBs(item.vacation_bonus_voucher) }}</span>
              </template>
              <template #item.vacation_voucher="{ item }">
                <span>{{ formatBs(item.vacation_voucher) }}</span>
              </template>
              <template #item.family_support_voucher="{ item }">
                <span>{{ formatBs(item.family_support_voucher) }}</span>
              </template>
              <template #item.positive_vouchers="{ item }">
                <span>{{ formatBs(item.positive_vouchers) }}</span>
              </template>
              <template #item.employment_voucher="{ item }">
                <span>{{ formatBs(item.employment_voucher) }}</span>
              </template>
              <template #item.housing_property_benefits_voucher="{ item }">
                <span>{{
                  formatBs(item.housing_property_benefits_voucher)
                }}</span>
              </template>
              <template #item.days_not_worked_voucher="{ item }">
                <span>{{ formatBs(item.days_not_worked_voucher) }}</span>
              </template>
              <template #item.loans_voucher="{ item }">
                <span>{{ formatBs(item.loans_voucher) }}</span>
              </template>
              <template #item.settlement_voucher="{ item }">
                <span>{{ formatBs(item.settlement_voucher) }}</span>
              </template>
              <template #item.negative_vouchers="{ item }">
                <span>{{ formatBs(item.negative_vouchers) }}</span>
              </template>
              <template #item.total="{ item }">
                <span>{{ formatBs(item.total) }}</span>
              </template>
              <template #item.actions="{ item }">
                <VTooltip text="Editar Salario" location="top">
                  <template
                    v-if="selectedPayslip.status === 0"
                    #activator="{ props }"
                  >
                    <IconBtn
                      v-bind="props"
                      @click="handleShowEditFormDialog(item)"
                    >
                      <VIcon icon="tabler-pencil" />
                    </IconBtn>
                  </template>
                </VTooltip>
              </template>

              <template #body.append>
                <tr class="font-weight-bold">
                  <td :colspan="6" class="text-right">
                    {{ formatBs(totals.salary_to_pay_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.food_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.transportation_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.performance_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.invoice_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.sales_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.sales_growth_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.assigned_products_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.earnings_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.vacation_bonus_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.vacation_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.family_support_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.positive_vouchers) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.social_security_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.employment_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.housing_property_benefits_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.days_not_worked_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.loans_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.settlement_voucher) }}
                  </td>
                  <td class="text-right">
                    {{ formatBs(totals.negative_vouchers) }}
                  </td>
                  <td class="text-right">{{ formatBs(totals.total) }}</td>
                  <td></td>
                </tr>
                <tr class="font-weight-bold">
                  <td colspan="14" class="text-right">Total Sueldos:</td>
                  <td colspan="4" class="text-right">
                    {{ formatBs(totals.totalSalaries) }}
                  </td>

                  <td colspan="6" class="text-right">Total Deducción:</td>
                  <td class="text-right">
                    {{ formatBs(totals.totalDeductions) }}
                  </td>
                  <td class="text-right">{{ formatBs(totals.totalToPay) }}</td>
                </tr>

                <tr class="font-weight-bold">
                  <td colspan="3" class="text-right">
                    Total a Pagar en Nómina:
                  </td>
                  <td colspan="3" class="text-right">
                    {{ formatBs(totals.totalToPay) }}
                  </td>
                </tr>
              </template>
            </VDataTable>
          </VTabsWindowItem>
        </VTabsWindow>
      </VCardText>
    </VCard>
  </div>
</template>
