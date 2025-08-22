<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";

const formData = ref({
  supplier_id: null,
  invoice_number: "",
  control_number: "",
  exp_date: null,
  payment_date: null,
  received_date: null,
  currency: "Bs",
  discount_rule_id: null,
  exempt_amount: 0,
  taxable_base: 0,
  tax_amount: 0,
  exchange_rate: 0,
  total_amount: 0,
  total_usd: 0,
});

const currencyOptions = [
  { title: "Bolívares (Bs)", value: "Bs" },
  { title: "Dólares (USD)", value: "USD" },
  { title: "Pesos Colombianos (COP)", value: "COP" },
];

const suppliers = ref([]);
const loading = ref(false);
const loadingSuppliers = ref(false);

onMounted(() => {
  fetchSuppliers();
});

const shouldShowExchangeRate = computed(() => {
  return formData.value.currency === "Bs" || formData.value.currency === "COP";
});

watch(
  () => formData.value.currency,
  (newCurrency) => {
    if (newCurrency === "USD") {
      formData.value.exchange_rate = 0;
    }
  }
);

const calculatedTaxAmount = computed(() => {
  const base = Number(formData.value.taxable_base) || 0;
  return (base * 0.16).toFixed(2);
});

watch(
  () => formData.value.taxable_base,
  () => {
    formData.value.tax_amount = calculatedTaxAmount.value;
  }
);

const calculatedTotalAmount = computed(() => {
  const excento = Number(formData.value.exempt_amount) || 0;
  const base = Number(formData.value.taxable_base) || 0;
  const impuesto = Number(formData.value.tax_amount) || 0;
  return (excento + base + impuesto).toFixed(2);
});

watch(
  () => [
    formData.value.exempt_amount,
    formData.value.taxable_base,
    formData.value.tax_amount,
  ],
  () => {
    formData.value.total_amount = calculatedTotalAmount.value;
  }
);

const calculatedTotalUsd = computed(() => {
  const totalBs = Number(formData.value.total_amount) || 0;
  const tasa = Number(formData.value.exchange_rate) || 0;
  if (tasa > 0) {
    return (totalBs / tasa).toFixed(2);
  }
  return 0;
});

watch(
  () => [formData.value.total_amount, formData.value.exchange_rate],
  () => {
    formData.value.total_usd = calculatedTotalUsd.value;
  },
  { deep: true }
);

const fetchSuppliers = async () => {
  loadingSuppliers.value = true;
  try {
    const response = await axios.get("/suppliers");
    suppliers.value = response.data.data ?? response.data;
  } catch (error) {
    console.error("Error al obtener los proveedores:", error);
    toast.error("No se pudieron cargar los proveedores.");
  } finally {
    loadingSuppliers.value = false;
  }
};

const handleSubmit = async () => {
  loading.value = true;
  try {
    await axios.post("/invoices", formData.value);
    toast.success("Factura registrada con éxito.");
  } catch (error) {
    console.error("Error al registrar la factura:", error);
    if (error.response && error.response.status === 422) {
      const errors = Object.values(error.response.data.errors).flat();
      toast.error(errors.join("\n"));
    } else {
      toast.error("Hubo un problema al registrar la factura.");
    }
  } finally {
    loading.value = false;
  }
};

const handleCancel = () => {
  console.log("Operación cancelada");
};
</script>

<template>
  <VCard title="Registrar Factura">
    <VCardText>
      <VForm @submit.prevent="handleSubmit">
        <VRow>
          <VCol cols="12" md="4">
            <VAutocomplete
              v-model="formData.supplier_id"
              :items="suppliers"
              :loading="loadingSuppliers"
              item-title="name"
              item-value="id"
              label="Proveedor"
              placeholder="Busque un proveedor"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model="formData.invoice_number"
              label="N° de factura"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model="formData.control_number"
              label="N° de Control"
            />
          </VCol>
        </VRow>

        <VRow>
          <VCol cols="12" md="4">
            <VTextField
              v-model="formData.exp_date"
              label="Fecha de Vencimiento"
              type="date"
              placeholder="YYYY-MM-DD"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model="formData.payment_date"
              label="F. Límite de Pago"
              type="date"
              placeholder="YYYY-MM-DD"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model="formData.received_date"
              label="F. de Recibo"
              type="date"
              placeholder="YYYY-MM-DD"
            />
          </VCol>
        </VRow>

        <VDivider class="my-4" />

        <VRow>
          <VCol cols="12" md="4">
            <VSelect
              v-model="formData.currency"
              :items="currencyOptions"
              label="Moneda de la Factura"
              item-title="title"
              item-value="value"
            />
          </VCol>
        </VRow>

        <VRow>
          <VCol cols="12" md="2">
            <VTextField
              v-model.number="formData.exempt_amount"
              label="Monto Excento IVA"
              type="number"
              :prefix="formData.currency"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model.number="formData.taxable_base"
              label="Base Imponible 16%"
              type="number"
              :prefix="formData.currency"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model.number="formData.tax_amount"
              label="Impuesto 16%"
              type="number"
              :prefix="formData.currency"
              readonly
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model.number="formData.total_amount"
              label="Total Factura"
              type="number"
              :prefix="formData.currency"
              readonly
            />
          </VCol>
          <VCol v-if="shouldShowExchangeRate" cols="12" md="2">
            <VTextField
              v-model.number="formData.exchange_rate"
              label="Tasa de Cambio"
              type="number"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model.number="formData.total_usd"
              label="Total Referencia (USD)"
              type="number"
              prefix="$"
              readonly
            />
          </VCol>
        </VRow>
      </VForm>
    </VCardText>

    <VCardActions class="pa-4 px-6">
      <VRow>
        <VCol cols="6">
          <VBtn
            color="secondary"
            variant="outlined"
            @click="handleCancel"
            block
          >
            Cancelar
          </VBtn>
        </VCol>
        <VCol cols="6">
          <VBtn
            color="primary"
            variant="flat"
            :loading="loading"
            @click="handleSubmit"
            block
          >
            Registrar
          </VBtn>
        </VCol>
      </VRow>
    </VCardActions>
  </VCard>
</template>

<style scoped>
.v-row .date-fix {
  margin-top: -22px;
}
</style>
