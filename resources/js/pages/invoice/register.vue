<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, nextTick, onMounted, ref, watch } from "vue";

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

const validationErrors = ref({});

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
const expDateError = ref("");

const selectedSupplier = computed(() => {
  return (
    suppliers.value.find((s) => s.id === formData.value.supplier_id) || null
  );
});

const translatePaymentMethodType = (type) => {
  const translations = {
    invoice_date: "Fecha de factura",
    early_payment: "Pronto Pago",
    custom: "Personalizado",
  };
  return translations[type] || type;
};

const isInformalSupplier = computed(() => {
  if (!selectedSupplier.value) return false;
  return selectedSupplier.value.name.toLowerCase().includes("informal");
});

const validateExpDate = (date) => {
  if (!date) {
    expDateError.value = "";
    return true;
  }

  if (formData.value.created_invoice_date) {
    const emissionDate = new Date(formData.value.created_invoice_date);
    emissionDate.setHours(0, 0, 0, 0);

    const expDate = new Date(date);
    expDate.setHours(0, 0, 0, 0);

    if (expDate < emissionDate) {
      expDateError.value = "La fecha de vencimiento no puede ser anterior a la fecha de emisión";
      validationErrors.value.exp_date = expDateError.value;
      return false;
    }
  }

  const sixMonthsFromNow = new Date();
  sixMonthsFromNow.setMonth(sixMonthsFromNow.getMonth() + 6);
  sixMonthsFromNow.setHours(0, 0, 0, 0);

  const expDate = new Date(date);
  expDate.setHours(0, 0, 0, 0);

  if (expDate > sixMonthsFromNow) {
    expDateError.value =
      "La fecha de vencimiento no puede ser más de 6 meses en el futuro";
    validationErrors.value.exp_date = expDateError.value;
    return false;
  }

  expDateError.value = "";
  if (validationErrors.value.exp_date) {
    delete validationErrors.value.exp_date;
  }
  return true;
};

const calculatePaymentDate = () => {
  if (!selectedSupplier.value) return;

  if (!selectedSupplier.value.payment_due_type) {
    console.warn(
      "El proveedor no tiene configuración de fecha de pago (payment_due_type)",
    );
    return;
  }

  const paymentMethod = selectedSupplier.value.payment_due_type;
  const customDays = Number(selectedSupplier.value.custom_due_days) || 0;
  const paymentRef = selectedSupplier.value.payment_due_reference;
  const invoiceDateRef = selectedSupplier.value.invoice_date_reference;
  const supplierPaymentRules = selectedSupplier.value.payment_rules || [];

  let calculatedDate = null;
  let baseDate = null;

  switch (paymentMethod) {
    case "invoice_date":
      if (invoiceDateRef === "expiration_date" && formData.value.exp_date) {
        baseDate = formData.value.exp_date;
      } else if (
        invoiceDateRef === "receipt_date" &&
        formData.value.received_date
      ) {
        baseDate = formData.value.received_date;
      } else if (
        invoiceDateRef === "issue_date" &&
        formData.value.created_invoice_date
      ) {
        baseDate = formData.value.created_invoice_date;
      }

      if (baseDate) {
        calculatedDate = baseDate;
      }
      break;

    case "early_payment":
      if (paymentRef === "receipt_date") {
        baseDate = formData.value.received_date;
      } else if (paymentRef === "issue_date") {
        baseDate = formData.value.created_invoice_date;
      }

      if (baseDate && supplierPaymentRules.length > 0) {
        const minDaysRule = supplierPaymentRules.reduce((min, rule) =>
          rule.days < min.days ? rule : min,
        );
        const dateObj = new Date(baseDate);
        dateObj.setDate(dateObj.getDate() + Number(minDaysRule.days));
        calculatedDate = dateObj.toISOString().split("T")[0];
      }
      break;

    case "custom":
      if (paymentRef === "receipt_date") {
        baseDate = formData.value.received_date;
      } else if (paymentRef === "issue_date") {
        baseDate = formData.value.created_invoice_date;
      }

      if (baseDate) {
        const dateObj = new Date(baseDate);
        dateObj.setDate(dateObj.getDate() + customDays);
        calculatedDate = dateObj.toISOString().split("T")[0];
      }
      break;

    default:
      calculatedDate = null;
  }

  if (calculatedDate) {
    formData.value.payment_date = calculatedDate;
  } else {
    formData.value.payment_date = null;
  }
};

const shouldShowExchangeRate = computed(() => {
  return formData.value.currency === "Bs" || formData.value.currency === "COP";
});

const computedTaxAmount = computed(() => {
  const base = Number(formData.value.taxable_base) || 0;
  return Number((base * 0.16).toFixed(2));
});

const computedTotalAmount = computed(() => {
  const exempt = Number(formData.value.exempt_amount) || 0;
  const base = Number(formData.value.taxable_base) || 0;
  return Number((exempt + base + computedTaxAmount.value).toFixed(2));
});

const computedTotalUsd = computed(() => {
  const totalAmount = computedTotalAmount.value;
  const currency = formData.value.currency;
  const exchangeRate = Number(formData.value.exchange_rate) || 0;

  if (currency === "USD") {
    return Number(totalAmount.toFixed(2));
  }

  if (exchangeRate > 0) {
    return Number((totalAmount / exchangeRate).toFixed(2));
  }

  return 0;
});

const getCurrencySymbol = computed(() => {
  const symbolMap = {
    Bs: "Bs",
    USD: "$",
    COP: "COP$",
  };
  return symbolMap[formData.value.currency] || "Bs";
});

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
  validationErrors.value = {};
};

watch(
  () => formData.value.supplier_id,
  async (newSupplierId) => {
    formData.value.discount_rule_id = null;
    discountRules.value = [];
    if (newSupplierId) {
      await fetchDiscountRules(newSupplierId);
      await nextTick();
      calculatePaymentDate();

      if (isInformalSupplier.value) {
        try {
          const res = await axios.get("/invoices/next-sequence", {
            params: { supplier_id: newSupplierId }
          });
          const seq = res.data.next_sequence;
          formData.value.invoice_number = seq;
          formData.value.control_number = seq;
        } catch (e) {
          console.error("Error al obtener la secuencia correlativa informal, autogenerando en frontend", e);
          const nowObj = new Date();
          const yyyy = nowObj.getFullYear();
          const mm = String(nowObj.getMonth() + 1).padStart(2, '0');
          const dd = String(nowObj.getDate()).padStart(2, '0');
          const hh = String(nowObj.getHours()).padStart(2, '0');
          const min = String(nowObj.getMinutes()).padStart(2, '0');
          const ss = String(nowObj.getSeconds()).padStart(2, '0');
          const seq = `INF-${yyyy}${mm}${dd}-${hh}${min}${ss}`;
          formData.value.invoice_number = seq;
          formData.value.control_number = seq;
        }
      }
    } else {
      formData.value.payment_date = null;
    }
  },
);

watch(
  () => selectedSupplier.value,
  async (newSupplier) => {
    if (newSupplier) {
      await nextTick();
      calculatePaymentDate();
    }
  },
  { deep: true },
);

watch(
  () => [
    formData.value.created_invoice_date,
    formData.value.exp_date,
    formData.value.received_date,
  ],
  async (newVals, oldVals) => {
    const [newCreated, newExp] = newVals;
    const [oldCreated, oldExp] = oldVals || [];

    if (newExp !== oldExp) {
      validateExpDate(newExp);
    } else if (newCreated !== oldCreated && formData.value.exp_date) {
      validateExpDate(formData.value.exp_date);
    }

    await nextTick();
    calculatePaymentDate();

    if (newCreated !== oldCreated && isInformalSupplier.value && newCreated) {
      const formattedDate = newCreated.replace(/-/g, "");
      const nowObj = new Date();
      const hh = String(nowObj.getHours()).padStart(2, '0');
      const min = String(nowObj.getMinutes()).padStart(2, '0');
      const ss = String(nowObj.getSeconds()).padStart(2, '0');
      const seq = `INF-${formattedDate}-${hh}${min}${ss}`;
      formData.value.invoice_number = seq;
      formData.value.control_number = seq;
    }
  }
);

watch(
  () => formData.value.currency,
  (newCurrency) => {
    if (newCurrency === "USD") {
      formData.value.exchange_rate = 0;
    }
  },
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
    const invoice = response.data.data ?? response.data;

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

    await nextTick();
    calculatePaymentDate();
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
    const response = await axios.get("/suppliers", {
      params: {
        include: "payment_date,payment_rules",
        itemsPerPage: -1,
      },
    });
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
    const response = await axios.get(
      `/supplier-laboratories/${supplierId}/discount-rules`,
    );
    const rulesData = response.data.discount_rules || [];

    discountRules.value = rulesData.map((rule) => ({
      ...rule,
      description: `${rule.days} días con un descuento de ${rule.descPorcentaje}%`,
    }));
  } catch (error) {
    console.error("Error al obtener las reglas de descuento:", error);
    discountRules.value = [];
  } finally {
    loadingRules.value = false;
  }
};

const handleSubmit = async () => {
  if (!validateExpDate(formData.value.exp_date)) {
    toast.error(expDateError.value);
    return;
  }

  loading.value = true;
  validationErrors.value = {};

  const payload = {
    ...formData.value,
    tax_amount: computedTaxAmount.value,
    total_amount: computedTotalAmount.value,
    total_usd: computedTotalUsd.value,
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
      resetFormFields();
    }
  } catch (error) {
    console.error("Error al procesar la factura:", error);
    if (error.response && error.response.status === 422) {
      validationErrors.value = error.response.data.errors || {};
      const errors = Object.values(error.response.data.errors).flat();
      toast.error(errors.join("\n"));
    } else {
      toast.error("Hubo un problema al procesar la factura.");
    }
  } finally {
    loading.value = false;
  }
};

</script>

<template>
  <div>
    <VCard :title="isEditMode ? 'Editar Factura' : 'Registrar Factura'">
      <template v-if="isEditMode" #prepend>
        <VBtn icon="tabler-arrow-left" variant="text" @click="handleCancel" />
      </template>

      <VCardText>
        <div v-if="loadingInvoice" class="py-6">
          <VSkeletonLoader type="card, paragraph, actions" />
        </div>

        <VForm v-else @submit.prevent="handleSubmit">
          <VRow index="0" density="compact" class="mb-2">
            <VCol cols="12" md="4">
              <VAutocomplete
                v-model="formData.supplier_id"
                :items="suppliers"
                :loading="loadingSuppliers"
                item-title="name"
                item-value="id"
                label="Proveedor"
                placeholder="Busque un proveedor"
                :error-messages="validationErrors.supplier_id"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model="formData.invoice_number"
                label="N° de factura"
                :disabled="isInformalSupplier"
                :error-messages="validationErrors.invoice_number"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model="formData.control_number"
                label="N° de Control"
                :disabled="isInformalSupplier"
                :error-messages="validationErrors.control_number"
              />
            </VCol>
          </VRow>

          <VRow index="1" density="compact" class="mb-2">
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.created_invoice_date"
                label="F. de Emisión"
                type="date"
                :error-messages="validationErrors.created_invoice_date"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.received_date"
                label="F. de Recibo"
                type="date"
                :error-messages="validationErrors.received_date"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.exp_date"
                label="Vencimiento"
                type="date"
                :error="!!expDateError"
                :error-messages="validationErrors.exp_date || expDateError"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.payment_date"
                label="Fecha de Pago"
                type="date"
                hint="Auto-calculado"
                persistent-hint
                readonly
                variant="filled"
                :error-messages="validationErrors.payment_date"
              />
            </VCol>
          </VRow>

          <VRow v-if="!isEditMode && selectedSupplier" class="mb-4">
            <VCol cols="12">
              <VAlert
                color="primary"
                variant="tonal"
                icon="tabler-info-circle"
                class="rounded pa-3"
              >
                <div class="d-flex align-center flex-wrap gap-2">
                  <span class="text-body-2 font-weight-medium">Configuración de Pago:</span>
                  <VChip color="primary" size="small" variant="flat" class="ml-2">
                    {{ translatePaymentMethodType(selectedSupplier.payment_due_type) || "No definido" }}
                  </VChip>
                  <VChip v-if="selectedSupplier.custom_due_days" color="secondary" size="small" variant="tonal">
                    {{ selectedSupplier.custom_due_days }} días
                  </VChip>
                  <VSpacer />
                  <span class="text-caption text-medium-emphasis italic">
                     La fecha de pago se recalcula automáticamente basado en estas reglas.
                  </span>
                </div>
              </VAlert>
            </VCol>
          </VRow>

          <VDivider class="my-4" />
          
          <!-- Bloque Financiero -->
          <VRow index="2" density="compact" class="align-center mb-2">
            <VCol cols="12" md="2">
              <VSelect
                v-model="formData.currency"
                :items="currencyOptions"
                label="Moneda"
                item-title="title"
                item-value="value"
                variant="solo-filled"
                flat
                :error-messages="validationErrors.currency"
              >
                <template #prepend-inner>
                   <VIcon icon="tabler-coin" color="primary" size="20" />
                </template>
              </VSelect>
            </VCol>
            <VCol v-if="shouldShowExchangeRate" cols="12" md="2">
              <VExpandTransition>
                <VTextField
                  v-model.number="formData.exchange_rate"
                  label="Tasa"
                  type="number"
                  variant="outlined"
                  color="primary"
                  :error-messages="validationErrors.exchange_rate"
                >
                   <template #prepend-inner>
                    <VIcon icon="tabler-trending-up" size="20" />
                  </template>
                </VTextField>
              </VExpandTransition>
            </VCol>
            <VCol cols="12" md="2">
              <VTextField
                v-model.number="formData.exempt_amount"
                label="Exento"
                type="number"
                :prefix="getCurrencySymbol"
                variant="underlined"
                :error-messages="validationErrors.exempt_amount"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model.number="formData.taxable_base"
                label="Base (16%)"
                type="number"
                :prefix="getCurrencySymbol"
                variant="underlined"
                :error-messages="validationErrors.taxable_base"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                :model-value="computedTaxAmount"
                label="IVA (Auto)"
                type="number"
                :prefix="getCurrencySymbol"
                readonly
                variant="underlined"
                :error-messages="validationErrors.tax_amount"
              />
            </VCol>
          </VRow>

          <!-- Totales Minimalistas -->
          <VRow index="3" density="compact" class="mt-4">
            <VCol cols="12" md="6">
              <VAlert
                color="primary"
                variant="outlined"
                class="pa-4 d-flex justify-space-between align-center"
              >
                <span class="text-subtitle-2 text-medium-emphasis">Total Factura ({{ formData.currency }})</span>
                <span class="text-h5 font-weight-bold text-primary">{{ getCurrencySymbol }} {{ computedTotalAmount }}</span>
              </VAlert>
            </VCol>
            <VCol cols="12" md="6">
              <VAlert
                color="success"
                variant="outlined"
                class="pa-4 d-flex justify-space-between align-center"
              >
                <span class="text-subtitle-2 text-medium-emphasis">Referencia Total (USD)</span>
                <span class="text-h5 font-weight-bold text-success">$ {{ computedTotalUsd }}</span>
              </VAlert>
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
              :disabled="loading"
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
              {{ isEditMode ? "Actualizar Factura" : "Registrar Factura" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </div>
</template>
