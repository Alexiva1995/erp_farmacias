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
  created_invoice_date: null,
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

const selectedSupplier = computed(() => {
  return (
    suppliers.value.find((s) => s.id === formData.value.supplier_id) || null
  );
});

const translatePaymentMethodType = (type) => {
  const translations = {
    due_date: "Fecha de vencimiento",
    early_payment: "Pronto Pago",
    creation_date: "Fecha de creación",
    credit_days: "Días de crédito",
  };
  return translations[type] || type;
};

const calculatePaymentDate = () => {
  if (
    props.isEditMode ||
    !selectedSupplier.value ||
    !selectedSupplier.value.payment_date
  ) {
    return;
  }

  const paymentMethod = selectedSupplier.value.payment_date.type;
  const paymentDays = selectedSupplier.value.payment_date.days;
  const supplierPaymentRules = selectedSupplier.value.payment_rules || [];

  switch (paymentMethod) {
    case "due_date":
      if (formData.value.exp_date) {
        const expDate = new Date(formData.value.exp_date);
        expDate.setDate(expDate.getDate() - 1);
        formData.value.payment_date = expDate.toISOString().split("T")[0];
      }
      break;

    case "early_payment":
      if (formData.value.received_date && supplierPaymentRules.length > 0) {
        const minDaysRule = supplierPaymentRules.reduce((min, rule) =>
          rule.days < min.days ? rule : min
        );
        const receivedDate = new Date(formData.value.received_date);
        const daysToAdd = Math.max(0, minDaysRule.days - 1);
        receivedDate.setDate(receivedDate.getDate() + daysToAdd);
        formData.value.payment_date = receivedDate.toISOString().split("T")[0];
      }
      break;

    case "creation_date":
      if (
        formData.value.created_invoice_date &&
        supplierPaymentRules.length > 0
      ) {
        const minDaysRule = supplierPaymentRules.reduce((min, rule) =>
          rule.days < min.days ? rule : min
        );
        const createdDate = new Date(formData.value.created_invoice_date);
        const daysToAdd = Math.max(0, minDaysRule.days - 1);
        createdDate.setDate(createdDate.getDate() + daysToAdd);
        formData.value.payment_date = createdDate.toISOString().split("T")[0];
      }
      break;

    case "credit_days":
      if (formData.value.received_date && paymentDays) {
        const receivedDate = new Date(formData.value.received_date);
        const daysToAdd = Math.max(0, paymentDays - 1);
        receivedDate.setDate(receivedDate.getDate() + daysToAdd);
        formData.value.payment_date = receivedDate.toISOString().split("T")[0];
      }
      break;

    default:
      formData.value.payment_date = null;
  }
};

const shouldShowExchangeRate = computed(() => {
  return formData.value.currency === "Bs" || formData.value.currency === "COP";
});

const calculatedTaxAmount = computed(() => {
  const base = Number(formData.value.taxable_base) || 0;
  return (base * 0.16).toFixed(2);
});

const calculatedTotalAmount = computed(() => {
  const excento = Number(formData.value.exempt_amount) || 0;
  const base = Number(formData.value.taxable_base) || 0;
  const impuesto = Number(formData.value.tax_amount) || 0;
  return (excento + base + impuesto).toFixed(2);
});

const calculatedTotalUsd = computed(() => {
  const totalAmount = Number(formData.value.total_amount) || 0;
  const currency = formData.value.currency;
  const exchangeRate = Number(formData.value.exchange_rate) || 0;

  if (currency === "USD") {
    return totalAmount.toFixed(2);
  }

  if (exchangeRate > 0) {
    return (totalAmount / exchangeRate).toFixed(2);
  }

  return "0.00";
});

const getCurrencySymbol = computed(() => {
  const symbolMap = {
    Bs: "Bs.",
    USD: "$",
    COP: "COP$",
  };
  return symbolMap[formData.value.currency] || "Bs.";
});

// Función para limpiar el formulario manteniendo el proveedor
const resetFormFields = () => {
  const currentSupplierId = formData.value.supplier_id;

  formData.value = {
    supplier_id: currentSupplierId,
    invoice_number: "",
    control_number: "",
    exp_date: null,
    payment_date: null,
    received_date: null,
    created_invoice_date: null,
    currency: "Bs",
    discount_rule_id: null,
    exempt_amount: 0,
    taxable_base: 0,
    tax_amount: 0,
    exchange_rate: 0,
    total_amount: 0,
    total_usd: 0,
  };
};

watch(
  () => formData.value.supplier_id,
  (newSupplierId) => {
    formData.value.discount_rule_id = null;
    discountRules.value = [];
    if (newSupplierId) {
      fetchDiscountRules(newSupplierId);
      if (!props.isEditMode) {
        calculatePaymentDate();
      }
    }
  }
);

watch(
  () => formData.value.exp_date,
  () => {
    if (!props.isEditMode) calculatePaymentDate();
  }
);
watch(
  () => formData.value.received_date,
  () => {
    if (!props.isEditMode) calculatePaymentDate();
  }
);
watch(
  () => formData.value.created_invoice_date,
  () => {
    if (!props.isEditMode) calculatePaymentDate();
  }
);

watch(
  () => formData.value.currency,
  (newCurrency) => {
    if (newCurrency === "USD") {
      formData.value.exchange_rate = 0;
    }
  }
);

watch(
  () => formData.value.taxable_base,
  () => {
    formData.value.tax_amount = calculatedTaxAmount.value;
  }
);

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

watch(
  () => [
    formData.value.total_amount,
    formData.value.currency,
    formData.value.exchange_rate,
  ],
  () => {
    formData.value.total_usd = calculatedTotalUsd.value;
  },
  { deep: true }
);

onMounted(async () => {
  await fetchSuppliers();
  if (props.isEditMode && props.invoiceId) {
    await fetchInvoiceData();
  }
});

const fetchInvoiceData = async () => {
  loadingInvoice.value = true;
  try {
    const response = await axios.get(`/invoices/${props.invoiceId}`);
    const invoice = response.data.data;

    formData.value = {
      supplier_id: invoice.supplier_id,
      invoice_number: invoice.invoice_number,
      control_number: invoice.control_number,
      exp_date: invoice.exp_date,
      payment_date: invoice.payment_date,
      received_date: invoice.received_date,
      created_invoice_date: invoice.created_invoice_date,
      currency: invoice.currency,
      discount_rule_id: invoice.discount_rule_id,
      exempt_amount: invoice.exempt_amount,
      taxable_base: invoice.taxable_base,
      tax_amount: invoice.tax_amount,
      total_amount: invoice.total_amount,
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
    const response = await axios.get(
      "/suppliers?include=payment_date,payment_rules"
    );
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

  const payload = {
    ...formData.value,
  };

  try {
    if (props.isEditMode) {
      await axios.put(`/invoices/${props.invoiceId}/data`, payload);
      toast.success("Factura actualizada con éxito.");
      emit("invoice-saved");
      emit("back-to-list");
    } else {
      await axios.post("/invoices", payload);
      toast.success("Factura registrada con éxito.");
      emit("invoice-saved");
      // Limpiar campos después de registrar, manteniendo el proveedor
      resetFormFields();
    }
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
  if (props.isEditMode) {
    emit("back-to-list");
  } else {
    console.log("Operación cancelada");
  }
};
</script>

<template>
  <div>
    <div v-if="loadingInvoice" class="text-center pa-10">
      <VProgressCircular indeterminate color="primary" size="64" />
      <p class="mt-4 text-h6">Cargando datos de la factura...</p>
    </div>

    <VCard v-else :title="isEditMode ? 'Editar Factura' : 'Registrar Factura'">
      <template v-if="isEditMode" #prepend>
        <VBtn icon="tabler-arrow-left" variant="text" @click="handleCancel" />
      </template>

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
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.exp_date"
                label="Fecha de Vencimiento"
                type="date"
                placeholder="YYYY-MM-DD"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.received_date"
                label="F. de Recibo"
                type="date"
                placeholder="YYYY-MM-DD"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.created_invoice_date"
                label="F. Creación Factura"
                type="date"
                placeholder="YYYY-MM-DD"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.payment_date"
                label="Fecha de Pago"
                type="date"
                placeholder="YYYY-MM-DD"
                hint="Se calcula automáticamente pero puede editarse"
                persistent-hint
              />
            </VCol>
          </VRow>
          <VRow v-if="!isEditMode && selectedSupplier">
            <VCol cols="12">
              <VAlert type="info" variant="outlined" density="compact">
                <div class="text-caption">
                  Proveedor seleccionado: {{ selectedSupplier.name }} | Método
                  de pago:
                  {{
                    translatePaymentMethodType(
                      selectedSupplier.payment_date?.type
                    ) || "No definido"
                  }}
                  | Días: {{ selectedSupplier.payment_date?.days || "N/A" }}
                </div>
              </VAlert>
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
                :prefix="getCurrencySymbol"
              />
            </VCol>
            <VCol cols="12" md="2">
              <VTextField
                v-model.number="formData.taxable_base"
                label="Base Imponible 16%"
                type="number"
                :prefix="getCurrencySymbol"
              />
            </VCol>
            <VCol cols="12" md="2">
              <VTextField
                v-model.number="formData.tax_amount"
                label="Impuesto 16%"
                type="number"
                :prefix="getCurrencySymbol"
                readonly
              />
            </VCol>
            <VCol cols="12" md="2">
              <VTextField
                v-model.number="formData.total_amount"
                label="Total Factura"
                type="number"
                :prefix="getCurrencySymbol"
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
              {{ isEditMode ? "Actualizar" : "Registrar" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </div>
</template>
