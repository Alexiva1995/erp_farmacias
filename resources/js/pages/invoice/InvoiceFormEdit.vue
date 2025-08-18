<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  invoiceId: { type: [Number, String], default: null },
  isEditMode: { type: Boolean, default: false },
  exchangeRates: { type: Array, default: () => [] },
});

const emit = defineEmits(["back-to-list", "invoice-saved"]);

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
const discountRules = ref([]);
const loading = ref(false);
const loadingSuppliers = ref(false);
const loadingRules = ref(false);
const loadingInvoice = ref(false);

const getExchangeRate = (currency) => {
  if (!currency || currency === "USD") return 1;

  const currencyMapping = { Bs: "BS", COP: "COP" };
  const mappedCurrency = currencyMapping[currency] || currency;
  const rate = props.exchangeRates.find(
    (r) => r.currency_code === mappedCurrency
  );
  return rate ? parseFloat(rate.rate) : 1;
};

const convertAmount = (usdAmount, targetCurrency) => {
  if (!targetCurrency || targetCurrency === "USD" || !usdAmount) {
    return usdAmount;
  }
  const rate = getExchangeRate(targetCurrency);
  return parseFloat((usdAmount * rate).toFixed(2));
};

const convertAmountToUSD = (localAmount, fromCurrency) => {
  if (!fromCurrency || fromCurrency === "USD" || !localAmount) {
    return localAmount;
  }
  const rate = getExchangeRate(fromCurrency);
  if (rate === 0) return 0;
  return parseFloat((localAmount / rate).toFixed(2));
};

const getCurrencySymbol = computed(() => {
  const symbolMap = {
    Bs: "Bs.",
    USD: "$",
    COP: "COP$",
  };
  return symbolMap[formData.value.currency] || "Bs.";
});

onMounted(async () => {
  await fetchSuppliers();
  if (props.isEditMode && props.invoiceId) {
    await fetchInvoiceData();
  }
});

watch(
  () => formData.value.supplier_id,
  (newSupplierId) => {
    formData.value.discount_rule_id = null;
    discountRules.value = [];
    if (newSupplierId) {
      fetchDiscountRules(newSupplierId);
    }
  }
);

const calculatedTotalAmount = computed(() => {
  const excento = Number(formData.value.exempt_amount) || 0;
  const base = Number(formData.value.taxable_base) || 0;
  const impuesto = Number(formData.value.tax_amount) || 0;
  return parseFloat((excento + base + impuesto).toFixed(2));
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
  const totalLocal = Number(formData.value.total_amount) || 0;
  return convertAmountToUSD(totalLocal, formData.value.currency);
});

watch(
  () => [
    formData.value.total_amount,
    formData.value.currency,
    props.exchangeRates,
  ],
  () => {
    formData.value.total_usd = calculatedTotalUsd.value;
  },
  { deep: true }
);

const fetchInvoiceData = async () => {
  loadingInvoice.value = true;
  try {
    const response = await axios.get(`/invoices/${props.invoiceId}`);
    const invoice = response.data.data;

    const targetCurrency = invoice.currency;
    formData.value = {
      supplier_id: invoice.supplier_id,
      invoice_number: invoice.invoice_number,
      control_number: invoice.control_number,
      exp_date: invoice.exp_date,
      payment_date: invoice.payment_date,
      received_date: invoice.received_date,
      currency: invoice.currency,
      discount_rule_id: invoice.discount_rule_id,
      exempt_amount: convertAmount(invoice.exempt_amount, targetCurrency),
      taxable_base: convertAmount(invoice.taxable_base, targetCurrency),
      tax_amount: convertAmount(invoice.tax_amount, targetCurrency),
      total_amount: convertAmount(invoice.total_amount, targetCurrency),
      exchange_rate: invoice.exchange_rate,
      total_usd: invoice.total_usd,
    };

    if (invoice.supplier_id) {
      await fetchDiscountRules(invoice.supplier_id);
    }
  } catch (error) {
    console.error("Error al cargar la factura:", error);
    toast.error("No se pudo cargar la información de la factura.");
    emit("back-to-list");
  } finally {
    loadingInvoice.value = false;
  }
};

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

const fetchDiscountRules = async (supplierId) => {
  loadingRules.value = true;
  try {
    const response = await axios.get(`/suppliers/${supplierId}/discount-rules`);
    discountRules.value = response.data.map((rule) => ({
      ...rule,
      description: `${rule.days} días con un descuento de ${rule.descPorcentaje}%`,
    }));
  } catch (error) {
    console.error("Error al obtener las reglas de descuento:", error);
  } finally {
    loadingRules.value = false;
  }
};

const handleSubmit = async () => {
  loading.value = true;

  const currency = formData.value.currency;
  const payload = {
    ...formData.value,
    exempt_amount: convertAmountToUSD(formData.value.exempt_amount, currency),
    taxable_base: convertAmountToUSD(formData.value.taxable_base, currency),
    tax_amount: convertAmountToUSD(formData.value.tax_amount, currency),
    total_amount: convertAmountToUSD(formData.value.total_amount, currency),
  };

  try {
    if (props.isEditMode) {
      await axios.put(`/invoices/${props.invoiceId}/data`, payload);
      toast.success("Factura actualizada con éxito.");
    } else {
      await axios.post("/invoices", payload);
      toast.success("Factura registrada con éxito.");
    }
    emit("invoice-saved");
    emit("back-to-list");
  } catch (error) {
    console.error("Error al procesar la factura:", error);
    if (error.response && error.response.status === 422) {
      const errors = Object.values(error.response.data.errors).flat();
      toast.error(errors.join("\n"));
    } else {
      toast.error("Hubo un problema al procesar la factura.");
    }
  } finally {
    loading.value = false;
  }
};

const handleCancel = () => {
  emit("back-to-list");
};
</script>

<template>
  <div>
    <div v-if="loadingInvoice" class="text-center pa-10">
      <VProgressCircular indeterminate color="primary" size="64" />
      <p class="mt-4 text-h6">Cargando datos de la factura...</p>
    </div>

    <VCard v-else :title="isEditMode ? 'Editar Factura' : 'Registrar Factura'">
      <template #prepend>
        <VBtn icon="tabler-arrow-left" variant="text" @click="handleCancel" />
      </template>

      <VCardText>
        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <VCol cols="12" md="6">
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
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.invoice_number"
                label="N° de factura"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.control_number"
                label="N° de Control"
              />
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12" md="6">
              <VSelect
                v-model="formData.discount_rule_id"
                :items="discountRules"
                :loading="loadingRules"
                :disabled="!formData.supplier_id"
                item-title="description"
                item-value="id"
                label="Regla de Descuento (Pronto Pago)"
                placeholder="Seleccione una regla"
                clearable
              />
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <VRow>
            <VCol cols="12" md="3">
              <VSelect
                v-model="formData.currency"
                :items="currencyOptions"
                label="Moneda de la Factura"
                item-title="title"
                item-value="value"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model.number="formData.exchange_rate"
                label="Tasa de Cambio"
                type="number"
              />
            </VCol>
            <VCol cols="12" md="2">
              <VTextField
                v-model="formData.exp_date"
                label="Fecha de Vencimiento"
                type="date"
                placeholder="YYYY-MM-DD"
              />
            </VCol>
            <VCol cols="12" md="2">
              <VTextField
                v-model="formData.payment_date"
                label="F. Límite de Pago"
                type="date"
                placeholder="YYYY-MM-DD"
              />
            </VCol>
            <VCol cols="12" md="2">
              <VTextField
                v-model="formData.received_date"
                label="F. de Recibo"
                type="date"
                placeholder="YYYY-MM-DD"
              />
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12" md="3">
              <VTextField
                v-model.number="formData.exempt_amount"
                label="Monto Excento IVA"
                type="number"
                :prefix="getCurrencySymbol"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model.number="formData.taxable_base"
                label="Base Imponible 16%"
                type="number"
                :prefix="getCurrencySymbol"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model.number="formData.tax_amount"
                label="Impuesto 16%"
                type="number"
                :prefix="getCurrencySymbol"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model.number="formData.total_amount"
                label="Total Factura"
                type="number"
                :prefix="getCurrencySymbol"
              />
            </VCol>
            <VCol cols="12" md="3" offset-md="9">
              <VTextField
                v-model.number="formData.total_usd"
                label="Total de Referencia (USD)"
                type="number"
                prefix="$"
                readonly
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="pa-4 px-6">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="handleCancel">
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          :loading="loading"
          @click="handleSubmit"
        >
          {{ isEditMode ? "Actualizar" : "Registrar" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>
