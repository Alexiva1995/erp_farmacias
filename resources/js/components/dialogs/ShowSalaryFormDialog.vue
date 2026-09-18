<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

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
    max-width="850px"
    persistent
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="closeDialog"
    :scrollable="true"
  >
    <VCard class="rounded-xl border-0 shadow-xl bg-surface overflow-hidden d-flex flex-column">
      <!-- Header Premium con Degradado -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 shadow-sm rounded-lg elevation-1"
          >
            <VIcon icon="tabler-file-dollar" size="22" color="primary" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h3 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Editar Salario y Asignaciones
            </h3>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-90 uppercase font-weight-bold"
                style="font-size: 0.65rem; letter-spacing: 0.05em;"
              >
                Trabajador: {{ props.selectedEmployee.name }} {{ props.selectedEmployee.last_name }}
              </span>
            </div>
          </div>
          <VSpacer />
          <IconBtn
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeDialog"
          >
            <VIcon icon="tabler-x" size="20" />
            <VTooltip activator="parent" location="top">Cerrar</VTooltip>
          </IconBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <VCard variant="flat" class="rounded-lg border bg-surface overflow-hidden">
          <VDataTable
            :headers="headers"
            :items="vouchers"
            :hide-default-footer="true"
            class="text-no-wrap"
          >
            <template #item.name="{ item }">
              <span class="font-weight-bold text-high-emphasis">{{ item.name }}</span>
            </template>

            <template #item.type="{ item }">
              <VChip
                :color="item.type === 'salary' ? 'success' : 'error'"
                size="x-small"
                variant="tonal"
                class="font-weight-black"
              >
                {{ item.type === "salary" ? "Asignación / Bono" : "Deducción" }}
              </VChip>
            </template>

            <template #item.amount_usd="{ item }">
              <VNumberInput
                v-model.number="rows[item.id]"
                placeholder="0.00"
                :min="0"
                :step="0.01"
                variant="outlined"
                control-variant="hidden"
                density="comfortable"
                hide-details="auto"
                style="inline-size: 160px;"
                :error="!!rowsErrors[item.id]"
                :error-messages="rowsErrors[item.id]"
                class="premium-input"
              />
            </template>

            <template #item.amount_bs="{ item }">
              <span class="font-weight-bold text-high-emphasis">
                {{ format(rows[item.id] * currency, "Bs") }}
              </span>
            </template>

            <template #body.append>
              <tr class="font-weight-black bg-surface-variant-subtle">
                <td :colspan="headers.length - 1" class="text-end text-uppercase text-caption font-weight-black">Total en Bolívares:</td>
                <td class="text-end font-weight-black text-primary text-subtitle-2">
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
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light">
        <VSpacer />
        <VBtn
          color="secondary"
          variant="outlined"
          class="rounded-lg px-6 font-weight-bold"
          @click="closeDialog"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          class="rounded-lg px-8 shadow-primary font-weight-black"
          @click="handleSubmitForm"
        >
          <VIcon start icon="tabler-check" size="18" class="me-1" />
          Guardar Cambios
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.shadow-xl {
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.leading-none {
  line-height: 1 !important;
}
</style>
