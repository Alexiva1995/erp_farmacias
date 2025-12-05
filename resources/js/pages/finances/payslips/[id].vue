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
  { title: "Sueldo + Asignaciones", key: "positive_vouchers", sortable: false },
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
  { title: "Prestamos", key: "loans_voucher", sortable: false },
  { title: "Liquidación", key: "settlement_voucher", sortable: false },
  { title: "Total Deducciones", key: "negative_vouchers", sortable: false },
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

const formatBs = (amount) => {
  const newAmount = toNum(amount);

  return (
    new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(newAmount) + (tab.value === "legal" ? " Bs." : " $")
  );
};

const toNum = (v) => {
  const n = v >> 0;
  return n === n ? n : Number(v) || 0;
};

const calcVouchers = (
  food,
  transport,
  perf,
  inv,
  sales,
  salesGr,
  assProd,
  earn,
  vacBonus,
  vac,
  fam,
  salary,
  social,
  employ,
  housing,
  daysOff,
  loans,
  settlement
) => {
  const pos =
    toNum(food) +
    toNum(transport) +
    toNum(perf) +
    toNum(inv) +
    toNum(sales) +
    toNum(salesGr) +
    toNum(assProd) +
    toNum(earn) +
    toNum(vacBonus) +
    toNum(vac) +
    toNum(fam) +
    toNum(salary);
  const neg =
    toNum(social) +
    toNum(employ) +
    toNum(housing) +
    toNum(daysOff) +
    toNum(loans) +
    toNum(settlement);

  return {
    positive: Math.round(pos * 100) / 100,
    negative: Math.round(neg * 100) / 100,
  };
};

const employeesWithVouchers = computed(() => {
  const rows = selectedPayslip.value?.results;
  if (!rows) return [];

  const isDecember = new Date().getMonth() === 11;
  const out = [];

  rows.forEach((r) => {
    let salary = 0,
      food = 0,
      transport = 0,
      perf = 0,
      inv = 0,
      sales = 0,
      salesGr = 0,
      assProd = 0,
      earn = 0,
      vacBonus = 0,
      vac = 0,
      fam = 0,
      positive = 0,
      social = 0,
      employ = 0,
      housing = 0,
      daysOff = 0,
      loans = 0,
      settlement = 0,
      negative = 0,
      final = 0;

    if (isDecember) earn += toNum(r.earnings_voucher);
    if (r.active_years >= 2) {
      vacBonus += toNum(r.vacation_bonus_voucher);
      vac += toNum(r.vacation_voucher);
    }

    const { positive: pos, negative: neg } = calcVouchers(
      r.food_voucher,
      tab.value === "legal" ? 0 : r.transportation_voucher,
      tab.value === "legal" ? 0 : r.performance_voucher,
      tab.value === "legal" ? 0 : r.invoice_voucher,
      tab.value === "legal" ? 0 : r.sales_voucher,
      tab.value === "legal" ? 0 : r.sales_growth_voucher,
      tab.value === "legal" ? 0 : r.assigned_products_voucher,
      tab.value !== "legal" && isDecember ? r.earnings_voucher : 0,
      tab.value !== "legal" && r.active_years >= 2
        ? r.vacation_bonus_voucher
        : 0,
      tab.value !== "legal" && r.active_years >= 2 ? r.vacation_voucher : 0,
      tab.value === "legal" ? 0 : r.family_support_voucher,
      r.salary_to_pay_voucher,
      r.social_security_voucher,
      r.employment_voucher,
      r.housing_property_benefits_voucher,
      r.days_not_worked_voucher,
      tab.value === "legal" ? 0 : r.loans_voucher,
      tab.value === "legal" ? 0 : r.settlement_voucher
    );

    salary += toNum(r.salary_to_pay_voucher);
    food += toNum(r.food_voucher);
    transport += toNum(r.transportation_voucher);
    perf += toNum(r.performance_voucher);
    inv += toNum(r.invoice_voucher);
    sales += toNum(r.sales_voucher);
    salesGr += toNum(r.sales_growth_voucher);
    assProd += toNum(r.assigned_products_voucher);
    fam += toNum(r.family_support_voucher);
    positive += pos;
    social += toNum(r.social_security_voucher);
    employ += toNum(r.employment_voucher);
    housing += toNum(r.housing_property_benefits_voucher);
    daysOff += toNum(r.days_not_worked_voucher);
    loans += toNum(r.loans_voucher);
    settlement += toNum(r.settlement_voucher);
    negative += neg;
    final += toNum(pos - neg);

    const employee = {
      salary_to_pay_voucher: salary,
      food_voucher: food,
      transportation_voucher: transport,
      performance_voucher: perf,
      invoice_voucher: inv,
      sales_voucher: sales,
      sales_growth_voucher: salesGr,
      assigned_products_voucher: assProd,
      earnings_voucher: earn,
      vacation_bonus_voucher: vacBonus,
      vacation_voucher: vac,
      family_support_voucher: fam,
      positive_vouchers: positive,
      employment_voucher: employ,
      housing_property_benefits_voucher: housing,
      days_not_worked_voucher: daysOff,
      social_security_voucher: social,
      loans_voucher: loans,
      settlement_voucher: settlement,
      negative_vouchers: negative,
      total: final,
      name: r.name,
      last_name: r.last_name,
      identification: r.identification,
      role: r.role,
      employee_id: r.employee_id,
      base_salary_voucher: r.base_salary_voucher,
    };

    out.push(employee);
  });

  return out;
});

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
  };

  return employeesWithVouchers.value.reduce(
    (acc, row) => {
      Object.keys(acc).forEach((k) => {
        acc[k] += row[k] || 0;
      });
      return acc;
    },
    { ...empty }
  );
});

const handleShowEditFormDialog = (item) => {
  showDialog.value = true;
  selectedEmployee.value = item;
};

watch(tab, () => fetchPayslip());
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <VRow class="ma-1">
          <h5 class="text-h5 font-weight-bold">
            {{ selectedPayslip?.name }} {{ selectedPayslip?.date }}
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
              :items="employeesWithVouchers"
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
                    {{ formatBs(totals.positive_vouchers) }}
                  </td>

                  <td colspan="4" class="text-right">Total Deducción:</td>
                  <td class="text-right">
                    {{ formatBs(totals.negative_vouchers) }}
                  </td>
                  <td class="text-right">{{ formatBs(totals.total) }}</td>
                </tr>

                <tr class="font-weight-bold">
                  <td colspan="3" class="text-right">
                    Total a Pagar en Nómina:
                  </td>
                  <td colspan="3" class="text-right">
                    {{ formatBs(totals.total) }}
                  </td>
                </tr>
              </template>
            </VDataTable>
          </VTabsWindowItem>
          <VTabsWindowItem value="full">
            <VDataTable
              :headers="headers"
              :items="employeesWithVouchers"
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
                    {{ formatBs(totals.positive_vouchers) }}
                  </td>

                  <td colspan="6" class="text-right">Total Deducción:</td>
                  <td class="text-right">
                    {{ formatBs(totals.negative_vouchers) }}
                  </td>
                  <td class="text-right">{{ formatBs(totals.total) }}</td>
                </tr>

                <tr class="font-weight-bold">
                  <td colspan="3" class="text-right">
                    Total a Pagar en Nómina:
                  </td>
                  <td colspan="3" class="text-right">
                    {{ formatBs(totals.total) }}
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
