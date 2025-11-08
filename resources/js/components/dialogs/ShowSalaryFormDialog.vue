<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  selectedEmployee: { type: Object, default: {} },
  payslip: { type: String, default: "" },
  vouchers: { type: Array, default: [] },
});

const emit = defineEmits(["update:modelValue", "refresh-table"]);

const rows = ref({});
const rowsErrors = ref({});
const currency = ref(0);
const vouchers = ref([]);

const closeDialog = () => {
  emit("update:modelValue", false);
};

const format = (amount, currency) => {
  if (amount == null) return 0 + ` ${currency}.`;
  return (
    new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(Number(amount)) + ` ${currency}.`
  );
};

const headers = [
  { title: "Nombre", key: "name", sortable: false },
  {
    title: "Tipo",
    key: "type",
    sortable: false,
    value: (item) => (item.type === "salary" ? "Bono" : "Deducción"),
  },
  {
    title: "Frecuencia",
    key: "frequency",
    sortable: false,
    value: (item) =>
      item.type === "annual"
        ? "Anual"
        : item.type === "monthly"
        ? "Mensual"
        : "Quicenal",
  },
  {
    title: "Monto (USD)",
    key: "amount_usd",
    sortable: false,
  },
  {
    title: "Monto (BS)",
    key: "amount_bs",
    sortable: false,
    value: (item) => format(item.amount_bs, "Bs"),
  },
];

const fetchEmployeeVouchers = async (id) => {
  try {
    const { data } = await axios.get(
      `/finances/payslips/${props.payslip}/employees/${id}/vouchers`
    );

    vouchers.value = data.data.results;
    currency.value = data.data.currency;
    rows.value = {};
    vouchers.value.forEach((v) => (rows.value[v.id] = Number(v.amount_usd)));
  } catch (error) {
    toast.error(
      "Hubo un error al obtener los datos del empleado, intente de nuevo"
    );
  }
};

const handleSubmitForm = async () => {
  try {
    const vouchers = Object.entries(rows.value).map(([id, amount]) => ({
      id: Number(id),
      amount_usd: Number(amount),
    }));
    const { data } = await axios.put(
      `/finances/payslips/${props.payslip}/vouchers`,
      { vouchers }
    );

    if (data.status) {
      toast.success(
        "Se actualizaron los bonos o deducciones del empleado satisfactoriamente"
      );
      closeDialog();
      emit("refresh-table");
    } else {
      toast.error("No se pudo actualizar los bonos o deducciones del empleado");
    }
  } catch (error) {
    toast.error("Hubo un error al actualizar el salario");
  }
};

watch(
  () => props.selectedEmployee?.employee_id,
  (employeeId) => {
    if (employeeId) {
      fetchEmployeeVouchers(employeeId);
    }
  }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">
          Editar Salario
          <span class="font-weight-bold">
            {{
              `${props.selectedEmployee.name} ${props.selectedEmployee.last_name} V-${props.selectedEmployee.identification}`
            }}
          </span>
        </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VContainer>
        <VDataTable
          :headers="headers"
          :items="vouchers"
          :hide-default-footer="true"
        >
          <template #item.amount_usd="{ item }">
            <VNumberInput
              v-model.number="rows[item.id]"
              label="Monto"
              :min="1"
              :step="0.01"
              variant="outlined"
              control-variant="hidden"
              density="comfortable"
              hide-details="auto"
              style="width: 200px"
              :error="!!rowsErrors[item.id]"
              :error-messages="rowsErrors[item.id]"
            />
          </template>

          <template #item.amount_bs="{ item }">
            <span>
              {{ format(rows[item.id] * currency, "Bs") }}
            </span>
          </template>

          <template #body.append>
            <tr class="font-weight-bold">
              <td :colspan="headers.length - 1" class="text-right">Total</td>
              <td class="text-right">
                {{
                  format(
                    Object.values(rows).reduce(
                      (total, acc) => total + acc * currency,
                      0
                    ),
                    "Bs"
                  )
                }}
              </td>
            </tr>
          </template>
        </VDataTable>
      </VContainer>
      <VDivider />
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleSubmitForm"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
