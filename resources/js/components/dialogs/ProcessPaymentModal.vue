<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  paymentGroup: {
    type: Object,
    default: null,
  },
  invoices: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue", "close", "payment-processed"]);

const form = ref({
  payment_type: "full",
  payment_currency: "USD",
  payment_amount: 0,
  payment_date: new Date().toISOString().split("T")[0],
  payment_receipt: null,
  reference: "",
});

// Estado de carga
const loading = ref(false);
const uploading = ref(false);
const exchangeRates = ref({});

// Estado de pagos previos
const paymentInfo = ref({
  total_invoice_usd: 0,
  total_paid_usd: 0,
  remaining_amount: 0,
  has_previous_payments: false,
  payment_status: "unpaid",
  payment_percentage: 0,
});

// Errores de validación
const errors = ref({});

// Función utilitaria para redondear correctamente a 2 decimales
const roundToTwoDecimals = (value) => {
  return parseFloat(parseFloat(value).toFixed(2));
};

// Validaciones de monto - CORRECCIÓN ISSUE #2: Flexibilidad total
const validatePaymentAmount = (value) => {
  const errors = [];

  // Validar que no esté vacío
  if (!value || value === "") {
    errors.push("❌ El monto es requerido");
    return errors;
  }

  // Convertir a número
  const numValue = parseFloat(value);

  // Validar que sea un número válido
  if (isNaN(numValue)) {
    errors.push("❌ Debe ser un número válido");
    return errors;
  }

  // Validar que sea mayor a 0
  if (numValue <= 0) {
    errors.push("❌ El monto debe ser mayor a 0");
    return errors;
  }

  // Validar formato decimal (máximo 2 decimales)
  const decimalPlaces = (value.toString().split(".")[1] || "").length;
  if (decimalPlaces > 2) {
    errors.push("❌ Máximo 2 decimales permitidos");
    return errors;
  }

  // CORRECCIÓN ISSUE #2: Eliminar validaciones restrictivas
  // El usuario puede pagar cualquier monto (más o menos que el sugerido)
  // Solo validamos que sea un número positivo válido

  return errors;
};

// Validar monto en tiempo real
const validateAmountRealtime = () => {
  // Solo validar si hay valor en el campo
  if (!form.value.payment_amount || form.value.payment_amount === "") {
    errors.value.payment_amount = [];
    return true;
  }

  const amountErrors = validatePaymentAmount(form.value.payment_amount);
  errors.value.payment_amount = amountErrors;

  return amountErrors.length === 0;
};

// Estado de validación del campo de monto
const amountFieldState = computed(() => {
  // Si no hay valor o está vacío, estado neutro
  if (
    !form.value.payment_amount ||
    form.value.payment_amount === "" ||
    form.value.payment_amount === null
  ) {
    return "default"; // Sin valor
  }

  // Validar el monto
  const amountErrors = validatePaymentAmount(form.value.payment_amount);

  // Si hay errores, estado de error
  if (amountErrors.length > 0) {
    return "error"; // Con errores
  }

  // Si no hay errores, estado de éxito
  return "success"; // Válido
});

// Validación general del formulario
const isFormValid = computed(() => {
  // Verificar que haya facturas seleccionadas
  if (selectedInvoices.value.length === 0) return false;

  // Verificar que el monto sea válido
  const amountErrors = validatePaymentAmount(form.value.payment_amount);
  if (amountErrors.length > 0) return false;

  // Verificar que la fecha esté presente
  if (!form.value.payment_date) return false;

  return true;
});

// Facturas seleccionadas
const selectedInvoices = ref([]);

// Monedas disponibles
const currencies = [
  { value: "USD", label: "USD - Dólar Americano" },
  { value: "VES", label: "VES - Bolívar Venezolano" },
  { value: "COP", label: "COP - Peso Colombiano" },
];

// Computed properties para cálculos
const totalInOriginalCurrency = computed(() => {
  if (!selectedInvoices.value || selectedInvoices.value.length === 0) return 0;
  return selectedInvoices.value.reduce((sum, invoice) => {
    return sum + parseFloat(invoice.total_amount || 0);
  }, 0);
});

const totalInUSD = computed(() => {
  if (!selectedInvoices.value || selectedInvoices.value.length === 0) return 0;
  return selectedInvoices.value.reduce((sum, invoice) => {
    // Siempre usar total_usd de la base de datos para el "Total a Pagar"
    return sum + parseFloat(invoice.total_usd || 0);
  }, 0);
});

const totalInBS = computed(() => {
  if (!selectedInvoices.value || selectedInvoices.value.length === 0) return 0;
  return selectedInvoices.value.reduce((sum, invoice) => {
    return sum + parseFloat(invoice.remaining_amount || 0);
  }, 0);
});

const suggestedAmountInLocalCurrency = computed(() => {
  const paymentCurrency = form.value.payment_currency;
  const supplierCurrency = supplierInfo.value?.currency || "USD";

  // Si hay pagos previos, usar el monto restante
  if (paymentInfo.value.has_previous_payments) {
    const remainingAmount = paymentInfo.value.remaining_amount;

    if (paymentCurrency === "USD") {
      const roundedAmount = roundToTwoDecimals(remainingAmount);
      return roundedAmount;
    }

    // Convertir monto restante a la moneda de pago
    const currencyKey = paymentCurrency === "VES" ? "BS" : paymentCurrency;
    const rate = exchangeRates.value[currencyKey];

    if (!rate) {
      return 0;
    }

    const calculated = roundToTwoDecimals(remainingAmount * rate);
    return calculated;
  }

  // Si la moneda de pago es la misma que la moneda del proveedor, usar el total del proveedor
  if (
    paymentCurrency === supplierCurrency ||
    (paymentCurrency === "VES" && supplierCurrency === "Bs")
  ) {
    return totalInSupplierCurrency.value;
  }

  // Si la moneda de pago es la misma que la factura, usar el monto original
  if (
    selectedInvoices.value.length > 0 &&
    (paymentCurrency === selectedInvoices.value[0].currency ||
      (paymentCurrency === "VES" &&
        selectedInvoices.value[0].currency === "Bs") ||
      (paymentCurrency === "COP" &&
        selectedInvoices.value[0].currency === "COP"))
  ) {
    return totalInOriginalCurrency.value;
  }

  // Si es USD, usar el total en USD de la base de datos
  if (paymentCurrency === "USD") {
    return totalInUSD.value;
  }

  // Para conversiones a otras monedas, usar la tasa de cambio
  const currencyKey = paymentCurrency === "VES" ? "BS" : paymentCurrency;
  const rate = exchangeRates.value[currencyKey];

  if (!rate) {
    return 0;
  }

  const calculated = roundToTwoDecimals(totalInUSD.value * rate);
  return calculated;
});

const amountInUSD = computed(() => {
  const paymentAmount = form.value.payment_amount;
  const paymentCurrency = form.value.payment_currency;

  if (!paymentAmount || paymentAmount === 0) return 0;
  if (paymentCurrency === "USD") return paymentAmount;

  const currencyKey = paymentCurrency === "VES" ? "BS" : paymentCurrency;
  const rate = exchangeRates.value[currencyKey];

  if (!rate) return 0;

  return roundToTwoDecimals(paymentAmount / rate);
});

// Monto restante para pagos parciales
const remainingAmount = computed(() => {
  if (form.value.payment_type === "partial") {
    // CORRECCIÓN: Mostrar monto restante ANTES del pago actual
    const totalPaidUSD = paidAmountUSD.value;
    const currentPaymentUSD = amountInUSD.value;
    const remainingBeforeCurrentPayment = totalPaidUSD - currentPaymentUSD;

    return roundToTwoDecimals(totalInUSD.value - remainingBeforeCurrentPayment);
  }
  return 0;
});

// Porcentaje pagado
const paymentPercentage = computed(() => {
  if (totalInUSD.value === 0) return 0;

  // CORRECCIÓN: Calcular porcentaje considerando pagos anteriores + pago actual
  const totalPaidUSD = paidAmountUSD.value;
  return roundToTwoDecimals((totalPaidUSD / totalInUSD.value) * 100);
});

const currentExchangeRate = computed(() => {
  const paymentCurrency = form.value.payment_currency;

  if (paymentCurrency === "USD") return 1.0;

  const currencyKey = paymentCurrency === "VES" ? "BS" : paymentCurrency;
  return exchangeRates.value[currencyKey] || 0;
});

// Obtener la moneda de la factura seleccionada
const invoiceCurrency = computed(() => {
  if (selectedInvoices.value.length > 0) {
    return selectedInvoices.value[0].currency;
  }
  return null;
});

// Cargar tasas de cambio desde el backend
const fetchExchangeRates = async () => {
  try {
    const response = await axios.get("/public/exchange-rates");

    if (Array.isArray(response.data)) {
      const rates = {};
      response.data.forEach((rate) => {
        rates[rate.currency_code] = parseFloat(rate.rate);
      });
      exchangeRates.value = rates;
    }
  } catch (error) {
    console.error("Error al cargar tasas de cambio:", error);
    toast.error("Error al cargar las tasas de cambio");
  }
};

// Cerrar modal
const closeModal = () => {
  emit("update:modelValue", false);
  emit("close");
  resetForm();
};

// Resetear formulario
const resetForm = () => {
  form.value = {
    payment_currency: "USD",
    payment_amount: 0,
    payment_date: new Date().toISOString().split("T")[0],
    payment_receipt: null,
    reference: "",
  };
  errors.value = {};
  selectedInvoices.value = [];
};

// Seleccionar/deseleccionar factura
const toggleInvoiceSelection = (invoice) => {
  const index = selectedInvoices.value.findIndex(
    (inv) => inv.id === invoice.id
  );
  if (index > -1) {
    selectedInvoices.value.splice(index, 1);
  } else {
    selectedInvoices.value.push(invoice);
  }
  updatePaymentAmount();
};

// Verificar si una factura está seleccionada
const isInvoiceSelected = (invoice) => {
  return selectedInvoices.value.some((inv) => inv.id === invoice.id);
};

// Actualizar monto de pago basado en facturas seleccionadas
const updatePaymentAmount = () => {
  if (selectedInvoices.value.length === 0) {
    form.value.payment_amount = 0;
    return;
  }

  // CORRECCIÓN: No establecer monto sugerido automáticamente
  // El usuario debe ingresar el monto manualmente
  // form.value.payment_amount = suggestedAmountInLocalCurrency.value;
};

// Establecer monto por defecto cuando cambia la moneda
const setDefaultAmount = () => {
  // CORRECCIÓN: No establecer monto sugerido automáticamente
  // El usuario debe ingresar el monto manualmente
  // form.value.payment_amount = suggestedAmountInLocalCurrency.value;
};

// Función helper para obtener tasa de cambio
const getExchangeRate = (currency) => {
  if (currency === "USD") return 1;
  const currencyKey = currency === "VES" ? "BS" : currency;
  return exchangeRates.value[currencyKey] || 1;
};

// Obtener información de pagos previos
const fetchPaymentInfo = async () => {
  if (selectedInvoices.value.length === 0) {
    paymentInfo.value = {
      total_invoice_usd: 0,
      total_paid_usd: 0,
      remaining_amount: 0,
      has_previous_payments: false,
      payment_status: "unpaid",
      payment_percentage: 0,
    };
    return;
  }

  try {
    const response = await axios.post(
      "/finances/pending-payments/get-paid-amount",
      {
        invoice_ids: selectedInvoices.value.map((inv) => inv.id),
      }
    );

    if (response.data.status === "success") {
      paymentInfo.value = response.data.data;

      // Si hay pagos previos, ajustar el monto sugerido
      if (paymentInfo.value.has_previous_payments) {
        form.value.payment_type = "partial"; // Cambiar automáticamente a parcial
        // CORRECCIÓN: No establecer monto sugerido automáticamente
        // form.value.payment_amount = paymentInfo.value.remaining_amount;
      }
    }
  } catch (error) {
    console.error("Error al obtener información de pagos:", error);
  }
};

// Procesar pago
const processPayment = async () => {
  loading.value = true;
  errors.value = {};

  // Validar monto antes de procesar
  if (!validateAmountRealtime()) {
    loading.value = false;
    return;
  }

  try {
    const paymentData = {
      payment_type: "full",
      payment_currency: form.value.payment_currency,
      payment_amount: form.value.payment_amount,
      payment_date: form.value.payment_date,
      reference: form.value.reference || null,
      photo_url: form.value.payment_receipt
        ? form.value.payment_receipt.name
        : null,
      invoice_ids: selectedInvoices.value.map((inv) => inv.id),
    };

    const response = await axios.post(
      "/finances/pending-payments/process-payment",
      paymentData
    );

    if (response.data.status === "success") {
      toast.success("Pago procesado exitosamente");
      emit("payment-processed", response.data.data);
      closeModal();
    } else {
      toast.error(response.data.message || "Error al procesar el pago");
    }
  } catch (error) {
    console.error("Error al procesar pago:", error);
    console.error("Error response:", error.response?.data);

    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
      toast.error(
        "Error de validación: " + JSON.stringify(error.response.data.errors)
      );
    } else if (error.response?.data?.message) {
      toast.error("Error: " + error.response.data.message);
    } else {
      toast.error("Error al procesar el pago");
    }
  } finally {
    loading.value = false;
  }
};

// Manejar subida de archivo
const handleFileUpload = (file) => {
  if (file) {
    form.value.payment_receipt = file;
  }
};

// Formatear moneda
const formatCurrency = (amount, currency) => {
  if (!amount) return "0.00";

  // Mapear códigos de moneda a códigos válidos para Intl.NumberFormat
  const currencyMap = {
    Bs: "VES", // Bolívar Venezolano
    VES: "VES", // Bolívar Venezolano
    COP: "COP", // Peso Colombiano
    USD: "USD", // Dólar Americano
  };

  const validCurrency = currencyMap[currency] || currency;

  const formatter = new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: validCurrency,
  });

  return formatter.format(amount);
};

const formatWithoutCurrency = (amount) => {
  if (!amount) return "0.00";

  const formatter = new Intl.NumberFormat("es-VE", {
    style: "decimal",
  });

  return formatter.format(amount);
};

// Formatear fecha
const formatDate = (date) => {
  if (!date) return "";
  return new Date(date).toLocaleDateString("es-VE");
};

// Información del proveedor
const supplierInfo = computed(() => {
  if (props.paymentGroup) {
    return {
      name: props.paymentGroup.supplier_name,
      paymentDate: props.paymentGroup.payment_date,
      currency: getSupplierCurrency(props.paymentGroup.supplier_name),
    };
  }
  return null;
});

// Función para determinar la moneda del proveedor
const getSupplierCurrency = (supplierName) => {
  if (!supplierName) return "USD";

  // Cristalmedicals siempre es USD
  if (supplierName.toLowerCase().includes("cristalmedicals")) {
    return "USD";
  }

  // Para otros proveedores, usar la moneda de la factura
  if (selectedInvoices.value.length > 0) {
    return selectedInvoices.value[0].currency;
  }

  return "USD"; // Default
};

// Total a pagar en la moneda del proveedor
const totalInSupplierCurrency = computed(() => {
  if (!supplierInfo.value) return 0;

  const supplierCurrency = supplierInfo.value.currency;

  // Si la moneda del proveedor es la misma que la factura, usar el monto original
  if (
    selectedInvoices.value.length > 0 &&
    supplierCurrency === selectedInvoices.value[0].currency
  ) {
    return totalInOriginalCurrency.value;
  }

  // Si es USD, usar el total en USD
  if (supplierCurrency === "USD") {
    return totalInUSD.value;
  }

  // Para otras monedas, convertir desde USD
  const currencyKey = supplierCurrency === "VES" ? "BS" : supplierCurrency;
  const rate = exchangeRates.value[currencyKey];

  if (!rate) return 0;

  return roundToTwoDecimals(totalInUSD.value * rate);
});

// Monto original de la factura en USD (para el % de ahorro)
const originalAmountUSD = computed(() => {
  return totalInUSD.value;
});

// Monto pagado en USD (convertido desde la moneda de pago)
const paidAmountUSD = computed(() => {
  const paymentAmount = form.value.payment_amount || 0;
  const paymentCurrency = form.value.payment_currency;

  let currentPaymentUSD = 0;
  if (paymentCurrency === "USD") {
    currentPaymentUSD = paymentAmount;
  } else {
    // Convertir a USD
    const currencyKey = paymentCurrency === "VES" ? "BS" : paymentCurrency;
    const rate = exchangeRates.value[currencyKey];
    if (rate) {
      currentPaymentUSD = roundToTwoDecimals(paymentAmount / rate);
    }
  }

  // CORRECCIÓN: Incluir pagos anteriores + pago actual
  const previousPaymentsUSD = paymentInfo.value.total_paid_usd || 0;
  return previousPaymentsUSD + currentPaymentUSD;
});

// Porcentaje de ahorro
const savingsPercentage = computed(() => {
  const original = originalAmountUSD.value;
  const paid = paidAmountUSD.value;

  if (original <= 0 || paid <= 0) return 0;

  const savings = original - paid;
  const percentage = (savings / original) * 100;

  return roundToTwoDecimals(percentage);
});

// Función para obtener la moneda original de la factura
const getOriginalCurrency = () => {
  if (selectedInvoices.value.length > 0) {
    return selectedInvoices.value[0].currency;
  }
  return "USD";
};

// Función para calcular el monto restante en la moneda original
const getRemainingAmountInOriginalCurrency = () => {
  if (form.value.payment_type === "partial" && remainingAmount.value > 0) {
    const originalCurrency = getOriginalCurrency();

    if (originalCurrency === "USD") {
      return remainingAmount.value;
    }

    // CORRECCIÓN: Mapear correctamente las monedas a las claves de exchangeRates
    let currencyKey;
    if (originalCurrency === "Bs" || originalCurrency === "VES") {
      currencyKey = "BS";
    } else if (originalCurrency === "COP") {
      currencyKey = "COP";
    } else {
      currencyKey = originalCurrency;
    }

    const rate = exchangeRates.value[currencyKey];

    if (rate) {
      return roundToTwoDecimals(remainingAmount.value * rate);
    }
  }

  return 0;
};

// Watchers
watch(
  () => props.modelValue,
  async (newValue) => {
    if (newValue) {
      selectedInvoices.value = [...props.invoices];
      await fetchExchangeRates();
      await fetchPaymentInfo(); // Obtener información de pagos previos
      // CORRECCIÓN: No establecer monto sugerido automáticamente
      // form.value.payment_amount = suggestedAmountInLocalCurrency.value;
    }
  }
);

watch(
  () => form.value.payment_currency,
  () => {
    // CORRECCIÓN: No establecer monto sugerido automáticamente
    // form.value.payment_amount = suggestedAmountInLocalCurrency.value;
  }
);

// Watcher para cuando cambien las facturas seleccionadas
watch(
  () => selectedInvoices.value,
  async () => {
    await fetchPaymentInfo();
    // CORRECCIÓN: No establecer monto sugerido automáticamente
    // form.value.payment_amount = suggestedAmountInLocalCurrency.value;
  },
  { deep: true }
);

// Watcher para validación en tiempo real del monto
watch(
  () => form.value.payment_amount,
  () => {
    validateAmountRealtime();
  }
);

// Watcher para cuando cambie el tipo de pago
watch(
  () => form.value.payment_type,
  () => {
    // CORRECCIÓN: No establecer monto sugerido automáticamente
    // El usuario debe ingresar el monto manualmente
    // if (form.value.payment_type === "full") {
    //   form.value.payment_amount = suggestedAmountInLocalCurrency.value;
    // }
    // Si es pago parcial, permitir que el usuario ingrese un monto menor
    validateAmountRealtime();
  }
);

// Lifecycle
onMounted(() => {
  fetchExchangeRates();
});
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="1200"
    persistent
    @update:model-value="closeModal"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>Procesar Pago</span>
        <VBtn icon="tabler-x" variant="text" size="small" @click="closeModal" />
      </VCardTitle>

      <VDivider />

      <VCardText>
        <!-- Información de facturas seleccionadas -->
        <VRow>
          <VCol cols="12">
            <VCard>
              <VAlert type="info" variant="tonal" class="mb-4">
                <div class="d-flex align-center">
                  <VIcon icon="tabler-receipt" class="me-2" />
                  Facturas a Pagar
                </div>
                <div class="text-caption text-medium-emphasis mb-2">
                  {{ invoices.length }} factura(s) seleccionada(s) para pago
                </div>
                <VRow>
                  <VCol
                    v-for="invoice in invoices"
                    :key="invoice.id"
                    cols="4"
                    class="my-0 py-0 pb-2 pt-1"
                  >
                    <strong>#{{ invoice.invoice_number }}</strong> -
                    {{ formatWithoutCurrency(invoice.total_amount) }}
                    {{ invoice.currency }}
                  </VCol>
                </VRow>
              </VAlert>
            </VCard>
          </VCol>
        </VRow>

        <!-- Formulario de pago -->
        <VForm @submit.prevent="processPayment">
          <!-- CORRECCIÓN ISSUE #2: Mostrar monto de referencia en USD -->
          <VRow>
            <VCol cols="12">
              <VAlert type="info" variant="outlined" class="mb-4">
                <template #title>
                  <div class="d-flex align-center">
                    <VIcon icon="tabler-currency-dollar" class="me-2" />
                    Monto de Referencia de la Factura
                  </div>
                </template>
                <div class="text-center">
                  <div class="text-h4 font-weight-bold text-primary mb-2">
                    {{ formatCurrency(totalInUSD, "USD") }} |
                    {{ formatWithoutCurrency(totalInBS) }} Bs
                  </div>
                  <div class="text-body-2 text-medium-emphasis">
                    Este es el monto de la factura en USD. Puede pagar más o
                    menos según su conveniencia.
                  </div>
                </div>
              </VAlert>
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12" md="4">
              <VSelect
                v-model="form.payment_currency"
                :items="currencies"
                item-title="label"
                item-value="value"
                label="Moneda de Pago"
                :error-messages="errors.payment_currency"
                @update:model-value="setDefaultAmount"
                required
                :return-object="false"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model.number="form.payment_amount"
                label="Monto a Pagar"
                type="number"
                step="0.01"
                min="0"
                :error-messages="
                  amountFieldState === 'error' ? errors.payment_amount : []
                "
                :success-messages="[]"
                :color="
                  amountFieldState === 'success'
                    ? 'success'
                    : amountFieldState === 'error'
                    ? 'error'
                    : 'primary'
                "
                :prepend-inner-icon="amountFieldState === 'error' ? '❌' : '💲'"
                persistent-hint
                @input="validateAmountRealtime"
                @blur="validateAmountRealtime"
                required
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model="form.reference"
                label="Referencia de Pago"
                placeholder="Número de referencia bancaria/transferencia"
                :error-messages="errors.reference"
                prepend-inner-icon="tabler-receipt"
                hint="Opcional: Número de referencia del pago bancario"
                persistent-hint
              />
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.payment_date"
                label="Fecha de Pago"
                type="date"
                :error-messages="errors.payment_date"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VFileInput
                v-model="form.payment_receipt"
                label="Comprobante de Pago"
                accept="image/*"
                :loading="uploading"
                @change="handleFileUpload"
                prepend-icon="tabler-upload"
              />
            </VCol>
          </VRow>

          <!-- Información simplificada del pago -->
          <VRow>
            <VCol cols="12">
              <VAlert type="info" variant="tonal" class="mb-4">
                <template #title>
                  <div class="d-flex align-center">
                    <VIcon icon="tabler-info-circle" class="me-2" />
                    Información del Pago
                  </div>
                </template>

                <VRow>
                  <VCol cols="12" md="4">
                    <div class="text-center pa-3 bg-primary-lighten-5 rounded">
                      <div class="text-h6 font-weight-bold text-primary">
                        {{ formatCurrency(originalAmountUSD, "USD") }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        Monto Original de la Factura
                      </div>
                    </div>
                  </VCol>

                  <VCol cols="12" md="4">
                    <div class="text-center pa-3 bg-success-lighten-5 rounded">
                      <div class="text-h6 font-weight-bold text-success">
                        {{ formatCurrency(paidAmountUSD, "USD") }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        Total Pagado (Incluye este pago)
                      </div>
                      <div
                        v-if="paymentInfo.has_previous_payments"
                        class="text-caption text-info mt-1"
                      >
                        Anterior:
                        {{
                          formatCurrency(paymentInfo.total_paid_usd || 0, "USD")
                        }}
                      </div>
                    </div>
                  </VCol>

                  <VCol cols="12" md="4">
                    <div class="text-center pa-3 bg-warning-lighten-5 rounded">
                      <div class="text-h6 font-weight-bold text-warning">
                        {{ savingsPercentage }}%
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        % de Ahorro
                      </div>
                    </div>
                  </VCol>
                </VRow>
              </VAlert>
            </VCol>
          </VRow>

          <!-- Resumen de pago -->
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          variant="outlined"
          color="secondary"
          @click="closeModal"
          :disabled="loading"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          @click="processPayment"
          :loading="loading"
          :disabled="selectedInvoices.length === 0 || !isFormValid"
          class="flex-grow-1 w-0"
        >
          Procesar Pago
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
