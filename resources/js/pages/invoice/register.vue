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

  // Permitir vencimiento anterior a hoy, pero no anterior a la fecha de emisión (created_invoice_date)
  if (formData.value.created_invoice_date) {
    const emissionDate = new Date(formData.value.created_invoice_date);
    emissionDate.setHours(0, 0, 0, 0);

    const expDate = new Date(date);
    expDate.setHours(0, 0, 0, 0);

    if (expDate < emissionDate) {
      expDateError.value = "La fecha de vencimiento no puede ser anterior a la fecha de emisión";
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
    return false;
  }

  expDateError.value = "";
  return true;
};

const calculatePaymentDate = () => {
  if (!selectedSupplier.value) return;

  // Verificar configuración
  // Nota: payment_due_type viene directo del proveedor, no de una relación
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
      // Validar según la referencia de fecha de factura
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
        // Se toma la fecha tal cual, sin sumar ni restar dias
        calculatedDate = baseDate;
      }
      break;

    case "early_payment":
      // Determina la fecha base
      if (paymentRef === "receipt_date") {
        baseDate = formData.value.received_date;
      } else if (paymentRef === "issue_date") {
        baseDate = formData.value.created_invoice_date;
      }

      // Buscar la regla con menor cantidad de días
      // La glosa "early_payment" usualmente implica reglas de pago
      if (baseDate && supplierPaymentRules.length > 0) {
        const minDaysRule = supplierPaymentRules.reduce((min, rule) =>
          rule.days < min.days ? rule : min,
        );
        const dateObj = new Date(baseDate);
        // Se suman los días de la regla DIRECTAMENTE (sin restar 1)
        dateObj.setDate(dateObj.getDate() + Number(minDaysRule.days));
        calculatedDate = dateObj.toISOString().split("T")[0];
      }
      break;

    case "custom":
      // Determina la fecha base
      if (paymentRef === "receipt_date") {
        baseDate = formData.value.received_date;
      } else if (paymentRef === "issue_date") {
        baseDate = formData.value.created_invoice_date;
      }

      if (baseDate) {
        const dateObj = new Date(baseDate);
        // Se suman los custom_due_days DIRECTAMENTE (sin restar 1)
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
    Bs: "Bs",
    USD: "$",
    COP: "COP$",
  };
  return symbolMap[formData.value.currency] || "Bs";
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
  async (newSupplierId) => {
    formData.value.discount_rule_id = null;
    discountRules.value = [];
    if (newSupplierId) {
      await fetchDiscountRules(newSupplierId);
      // Esperar a que selectedSupplier se actualice
      await nextTick();
      calculatePaymentDate();

      // Si es proveedor informal, auto-generamos secuencia correlativa y llenamos campos
      if (isInformalSupplier.value) {
        try {
          const res = await axios.get("/invoices/next-sequence", {
            params: { supplier_id: newSupplierId }
          });
          const seq = res.data.next_sequence;
          formData.value.invoice_number = seq;
          formData.value.control_number = seq;
        } catch (e) {
          console.error("Error al obtener la secuencia correlativa informal", e);
        }
      }
    } else {
      // Limpiar fecha de pago si no hay proveedor
      formData.value.payment_date = null;
    }
  },
);

// Watcher adicional para cuando selectedSupplier cambie
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
  () => formData.value.created_invoice_date,
  async (newDate) => {
    if (formData.value.exp_date) {
      validateExpDate(formData.value.exp_date);
    }
    await nextTick();
    calculatePaymentDate();
  },
);

watch(
  () => formData.value.exp_date,
  async (newDate) => {
    validateExpDate(newDate);
    await nextTick();
    calculatePaymentDate();
  },
);
watch(
  () => formData.value.received_date,
  async () => {
    await nextTick();
    calculatePaymentDate();
  },
);
watch(
  () => formData.value.created_invoice_date,
  async () => {
    await nextTick();
    calculatePaymentDate();
  },
);

watch(
  () => formData.value.currency,
  (newCurrency) => {
    if (newCurrency === "USD") {
      formData.value.exchange_rate = 0;
    }
  },
);

watch(
  () => formData.value.taxable_base,
  () => {
    formData.value.tax_amount = calculatedTaxAmount.value;
  },
);

watch(
  () => [
    formData.value.exempt_amount,
    formData.value.taxable_base,
    formData.value.tax_amount,
  ],
  () => {
    formData.value.total_amount = calculatedTotalAmount.value;
  },
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
  { deep: true },
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
    // InvoiceResource devuelve los datos directamente en response.data
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

    // Forzar el cálculo de la fecha de pago una vez que todo está cargado
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
        itemsPerPage: -1, // Obtener todos los proveedores sin paginación
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
    // Verificar si existe la propiedad discount_rules o si es response.data directamente
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
  // Validar fecha de vencimiento antes de enviar
  if (!validateExpDate(formData.value.exp_date)) {
    toast.error(expDateError.value);
    return;
  }

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
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model="formData.invoice_number"
                label="N° de factura"
                :disabled="isInformalSupplier"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model="formData.control_number"
                label="N° de Control"
                :disabled="isInformalSupplier"
              />
            </VCol>
          </VRow>

          <VRow index="1" density="compact" class="mb-2">
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.created_invoice_date"
                label="F. de Emisión"
                type="date"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.received_date"
                label="F. de Recibo"
                type="date"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.exp_date"
                label="Vencimiento"
                type="date"
                :error="!!expDateError"
                :error-messages="expDateError"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="formData.payment_date"
                label="Fecha de Pago"
                type="date"
                hint="Auto-calculado"
                persistent-hint
              />
            </VCol>
          </VRow>
          <VRow v-if="!isEditMode && selectedSupplier" class="mb-4">
            <VCol cols="12">
              <div class="d-flex align-center flex-wrap gap-2 pa-3 rounded bg-light-primary" style="background: rgba(var(--v-theme-primary), 0.05);">
                <VIcon icon="tabler-info-circle" color="primary" class="mr-2" />
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
              />
            </VCol>
            <VCol cols="12" md="2" :md="shouldShowExchangeRate ? 2 : 3">
              <VTextField
                v-model.number="formData.taxable_base"
                label="Base (16%)"
                type="number"
                :prefix="getCurrencySymbol"
                variant="underlined"
              />
            </VCol>
            <VCol cols="12" md="2" :md="shouldShowExchangeRate ? 2 : 3">
              <VTextField
                v-model.number="formData.tax_amount"
                label="IVA"
                type="number"
                :prefix="getCurrencySymbol"
                readonly
                variant="underlined"
              />
            </VCol>
          </VRow>

          <!-- Totales Minimalistas -->
          <VRow index="3" density="compact" class="mt-2">
            <VCol cols="12" md="6">
              <div class="pa-2 rounded d-flex justify-space-between align-center" style=" border: 1px solid rgba(var(--v-theme-primary), 0.1);background: rgba(var(--v-theme-primary), 0.05);">
                <span class="text-caption text-medium-emphasis">Total Factura ({{ formData.currency }})</span>
                <span class="text-h6 font-weight-bold text-primary">{{ getCurrencySymbol }} {{ formData.total_amount }}</span>
              </div>
            </VCol>
            <VCol cols="12" md="6">
              <div class="pa-2 rounded d-flex justify-space-between align-center" style=" border: 1px solid rgba(var(--v-theme-success), 0.1);background: rgba(var(--v-theme-success), 0.05);">
                <span class="text-caption text-medium-emphasis">Referencia Total (USD)</span>
                <span class="text-h6 font-weight-bold text-success">$ {{ formData.total_usd }}</span>
              </div>
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
