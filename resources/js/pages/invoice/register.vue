<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, nextTick, onMounted, ref, watch } from "vue";
import InvoiceBasicInfoForm from "./components/InvoiceBasicInfoForm.vue";
import InvoiceFinancialForm from "./components/InvoiceFinancialForm.vue";
import InvoiceSummaryCard from "./components/InvoiceSummaryCard.vue";

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

  formData.value.payment_date = calculatedDate;
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
    discountRules.value = [];
  } finally {
    loadingRules.value = false;
  }
};

const handleCancel = () => {
  emit("back-to-list");
};

const syncingBot = ref(false);

const handleSyncBot = async (bot) => {
  if (!formData.value.supplier_id) {
    toast.error("Seleccione un proveedor primero.");
    return;
  }

  syncingBot.value = true;
  try {
    const payload = {
      supplier_id: formData.value.supplier_id,
      invoice_number: formData.value.invoice_number || null,
    };

    const response = await axios.post(bot.endpoint, payload);
    const msg = response.data?.message || `Sincronización con ${bot.label} completada.`;
    toast.success(msg);

    // Si estamos en modo edición, recargar los datos actualizados
    if (props.isEditMode && props.invoiceId) {
      await fetchInvoiceData();
    } else {
      // Si estamos en modo creación y tenemos el número de factura, buscar los datos actualizados por el bot
      const searchNumber = formData.value.invoice_number;
      if (searchNumber) {
        try {
          const invRes = await axios.get('/invoices', {
            params: {
              supplierId: formData.value.supplier_id,
              q: searchNumber,
              itemsPerPage: 1
            }
          });
          const found = (invRes.data?.data || [])[0];
          if (found) {
            formData.value.control_number = found.control_number || formData.value.control_number;
            formData.value.created_invoice_date = found.created_invoice_date || formData.value.created_invoice_date;
            formData.value.exp_date = found.exp_date || formData.value.exp_date;
            formData.value.received_date = found.received_date || formData.value.received_date;
            formData.value.exempt_amount = Number(found.exempt_amount || 0);
            formData.value.taxable_base = Number(found.taxable_base || 0);
            formData.value.tax_amount = Number(found.tax_amount || 0);
            formData.value.total_amount = Number(found.total_amount || 0);
            formData.value.total_usd = Number(found.total_usd || 0);
            formData.value.exchange_rate = Number(found.exchange_rate || 0);
            formData.value.currency = found.currency || formData.value.currency;
            await nextTick();
            calculatePaymentDate();
          }
        } catch (err) {
          // Si no se encuentra puntual, continuar
        }
      }
    }
  } catch (error) {
    const errorMsg = error.response?.data?.message || `Error al sincronizar con el bot de ${bot.label}.`;
    toast.error(errorMsg);
  } finally {
    syncingBot.value = false;
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
    <VCard elevation="1" class="rounded-lg">
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="42"
            class="me-3"
          >
            <VIcon :icon="isEditMode ? 'tabler-edit' : 'tabler-file-plus'" size="24" />
          </VAvatar>
        </template>
        
        <template v-if="isEditMode" #append>
          <VBtn
            icon="tabler-arrow-left"
            variant="tonal"
            color="secondary"
            size="small"
            @click="handleCancel"
          />
        </template>

        <VCardTitle class="text-h6 font-weight-bold">
          {{ isEditMode ? 'Editar Factura de Compra' : 'Registrar Factura de Compra' }}
        </VCardTitle>
        <VCardSubtitle class="text-caption">
          {{ isEditMode ? 'Modifique los datos fiscales y financieros del documento' : 'Ingrese los datos fiscales, fechas y valores para el control de cuentas por pagar' }}
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pt-5">
        <div v-if="loadingInvoice" class="py-6">
          <VSkeletonLoader type="card, paragraph, actions" />
        </div>

        <VForm v-else @submit.prevent="handleSubmit">
          <VRow>
            <!-- COLUMNA IZQUIERDA: Entrada de Datos Completa (Datos Proveedor, Fechas y Montos) -->
            <VCol cols="12" md="7" lg="8">
              <InvoiceBasicInfoForm
                :form-data="formData"
                :suppliers="suppliers"
                :loading-suppliers="loadingSuppliers"
                :validation-errors="validationErrors"
                :exp-date-error="expDateError"
                :selected-supplier="selectedSupplier"
                :is-informal-supplier="isInformalSupplier"
                :is-edit-mode="isEditMode"
                :syncing-bot="syncingBot"
                @sync-bot="handleSyncBot"
              />

              <VDivider class="my-5" />

              <InvoiceFinancialForm
                :form-data="formData"
                :currency-options="currencyOptions"
                :should-show-exchange-rate="shouldShowExchangeRate"
                :get-currency-symbol="getCurrencySymbol"
                :computed-tax-amount="computedTaxAmount"
                :validation-errors="validationErrors"
              />
            </VCol>

            <!-- COLUMNA DERECHA: Resumen Financiero Fijo (Sticky) y Acciones CTA -->
            <VCol cols="12" md="5" lg="4">
              <InvoiceSummaryCard
                :form-data="formData"
                :get-currency-symbol="getCurrencySymbol"
                :computed-tax-amount="computedTaxAmount"
                :computed-total-amount="computedTotalAmount"
                :computed-total-usd="computedTotalUsd"
                :loading="loading"
                :is-edit-mode="isEditMode"
                @submit="handleSubmit"
                @cancel="handleCancel"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>
