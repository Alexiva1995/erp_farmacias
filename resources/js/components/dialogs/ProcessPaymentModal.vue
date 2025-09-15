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

// Estado del formulario
const form = ref({
  payment_type: "full", // Nuevo campo para tipo de pago
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

// Validaciones de monto
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

  // Validar que no exceda el monto sugerido
  const suggestedAmount = suggestedAmountInLocalCurrency.value;
  if (numValue > suggestedAmount) {
    errors.push(
      `❌ El monto no puede exceder ${formatCurrency(
        suggestedAmount,
        form.value.payment_currency
      )}`
    );
    return errors;
  }

  // Validar formato decimal (máximo 2 decimales)
  const decimalPlaces = (value.toString().split(".")[1] || "").length;
  if (decimalPlaces > 2) {
    errors.push("❌ Máximo 2 decimales permitidos");
    return errors;
  }

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
    console.log("🔵 Estado: default (sin valor)");
    return "default"; // Sin valor
  }

  // Validar el monto
  const amountErrors = validatePaymentAmount(form.value.payment_amount);

  console.log("🔍 Validando monto:", {
    amount: form.value.payment_amount,
    errors: amountErrors,
    suggestedAmount: suggestedAmountInLocalCurrency.value,
  });

  // Si hay errores, estado de error
  if (amountErrors.length > 0) {
    console.log("❌ Estado: error");
    return "error"; // Con errores
  }

  // Si no hay errores, estado de éxito
  console.log("✅ Estado: success - Mostrando mensaje de éxito");
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

// Opciones de tipo de pago
const paymentTypes = [
  { value: "full", label: "Pago Completo" },
  { value: "partial", label: "Pago Parcial" },
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
    return sum + parseFloat(invoice.total_amount_usd || 0);
  }, 0);
});

const suggestedAmountInLocalCurrency = computed(() => {
  const paymentCurrency = form.value.payment_currency;

  console.log("🔍 Calculando monto sugerido:");
  console.log("- Moneda de pago:", paymentCurrency);
  console.log("- Moneda de factura:", invoiceCurrency.value);
  console.log("- Monto original:", totalInOriginalCurrency.value);
  console.log("- Total en USD (de BD):", totalInUSD.value);
  console.log("- Monto restante:", paymentInfo.value.remaining_amount);
  console.log("- Hay pagos previos:", paymentInfo.value.has_previous_payments);

  // Si hay pagos previos, usar el monto restante
  if (paymentInfo.value.has_previous_payments) {
    const remainingAmount = paymentInfo.value.remaining_amount;

    if (paymentCurrency === "USD") {
      const roundedAmount = roundToTwoDecimals(remainingAmount);
      console.log("✅ Usando monto restante en USD:", roundedAmount);
      return roundedAmount;
    }

    // Convertir monto restante a la moneda de pago
    const currencyKey = paymentCurrency === "VES" ? "BS" : paymentCurrency;
    const rate = exchangeRates.value[currencyKey];

    if (!rate) {
      console.log("❌ No hay tasa de cambio para:", currencyKey);
      return 0;
    }

    const calculated = roundToTwoDecimals(remainingAmount * rate);
    console.log(
      "✅ Conversión de monto restante:",
      remainingAmount,
      "×",
      rate,
      "=",
      calculated
    );
    return calculated;
  }

  // Si es USD, usar el total en USD de la base de datos
  if (paymentCurrency === "USD") {
    console.log("✅ Usando total en USD de BD:", totalInUSD.value);
    return totalInUSD.value;
  }

  // Si la moneda de pago es la misma que la factura, usar el monto original
  if (
    selectedInvoices.value.length > 0 &&
    paymentCurrency === selectedInvoices.value[0].currency
  ) {
    console.log(
      "✅ Usando monto original (misma moneda):",
      totalInOriginalCurrency.value
    );
    return totalInOriginalCurrency.value;
  }

  // Para conversiones a otras monedas, usar la tasa de cambio
  const currencyKey = paymentCurrency === "VES" ? "BS" : paymentCurrency;
  const rate = exchangeRates.value[currencyKey];

  if (!rate) {
    console.log("❌ No hay tasa de cambio para:", currencyKey);
    return 0;
  }

  const calculated = roundToTwoDecimals(totalInUSD.value * rate);
  console.log(
    "✅ Conversión calculada:",
    totalInUSD.value,
    "×",
    rate,
    "=",
    calculated
  );
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
    return roundToTwoDecimals(totalInUSD.value - amountInUSD.value);
  }
  return 0;
});

// Porcentaje pagado
const paymentPercentage = computed(() => {
  if (totalInUSD.value === 0) return 0;
  return roundToTwoDecimals((amountInUSD.value / totalInUSD.value) * 100);
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

  form.value.payment_amount = suggestedAmountInLocalCurrency.value;
};

// Establecer monto por defecto cuando cambia la moneda
const setDefaultAmount = () => {
  form.value.payment_amount = suggestedAmountInLocalCurrency.value;
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
        form.value.payment_amount = paymentInfo.value.remaining_amount;
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
      payment_type: form.value.payment_type, // Nuevo campo
      payment_currency: form.value.payment_currency,
      payment_amount: form.value.payment_amount,
      payment_date: form.value.payment_date,
      reference: form.value.reference || null,
      photo_url: form.value.payment_receipt
        ? form.value.payment_receipt.name
        : null,
      invoice_ids: selectedInvoices.value.map((inv) => inv.id),
    };

    console.log("Enviando datos de pago:", paymentData);

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
    };
  }
  return null;
});

// Watchers
watch(
  () => props.modelValue,
  async (newValue) => {
    if (newValue) {
      selectedInvoices.value = [...props.invoices];
      await fetchExchangeRates();
      await fetchPaymentInfo(); // Obtener información de pagos previos
      form.value.payment_amount = suggestedAmountInLocalCurrency.value;
    }
  }
);

watch(
  () => form.value.payment_currency,
  () => {
    form.value.payment_amount = suggestedAmountInLocalCurrency.value;
  }
);

// Watcher para cuando cambien las facturas seleccionadas
watch(
  () => selectedInvoices.value,
  async () => {
    await fetchPaymentInfo();
    form.value.payment_amount = suggestedAmountInLocalCurrency.value;
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
        <!-- Información del proveedor -->
        <div v-if="supplierInfo" class="mb-6">
          <VCard variant="tonal" color="primary">
            <VCardText>
              <VRow>
                <VCol cols="12" md="6">
                  <div class="text-h6 font-weight-bold">
                    {{ supplierInfo.name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Fecha de Pago: {{ formatDate(supplierInfo.paymentDate) }}
                  </div>
                </VCol>
                <VCol cols="12" md="6">
                  <div class="text-caption text-medium-emphasis">
                    Total a Pagar
                  </div>
                  <div class="text-h6 font-weight-bold text-success">
                    {{ formatCurrency(totalInUSD, "USD") }}
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </div>

        <!-- Selección de facturas -->
        <VRow>
          <VCol cols="12">
            <VCard variant="outlined">
              <VCardTitle class="text-h6">
                Seleccionar Facturas a Pagar
              </VCardTitle>
              <VCardText>
                <div class="text-caption text-medium-emphasis mb-3">
                  {{ selectedInvoices.length }} de
                  {{ invoices.length }} factura(s) seleccionada(s)
                </div>

                <!-- Tabla de facturas con checkboxes -->
                <VTable density="compact">
                  <thead>
                    <tr>
                      <th width="50">Seleccionar</th>
                      <th>N° Factura</th>
                      <th>Monto</th>
                      <th>Moneda</th>
                      <th>Fecha Vencimiento</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="invoice in invoices"
                      :key="invoice.id"
                      :class="{
                        'bg-primary-lighten-5': isInvoiceSelected(invoice),
                      }"
                    >
                      <td>
                        <VCheckbox
                          :model-value="isInvoiceSelected(invoice)"
                          @change="toggleInvoiceSelection(invoice)"
                        />
                      </td>
                      <td>{{ invoice.invoice_number }}</td>
                      <td>
                        {{
                          formatCurrency(invoice.total_amount, invoice.currency)
                        }}
                      </td>
                      <td>{{ invoice.currency }}</td>
                      <td>{{ formatDate(invoice.due_date) }}</td>
                    </tr>
                  </tbody>
                </VTable>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <VDivider class="my-6" />

        <!-- Formulario de pago -->
        <VForm @submit.prevent="processPayment">
          <VRow>
            <VCol cols="12" md="6">
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
            <VCol cols="12" md="6">
              <VSelect
                v-model="form.payment_type"
                :items="paymentTypes"
                item-title="label"
                item-value="value"
                label="Tipo de Pago"
                :error-messages="errors.payment_type"
                required
                :return-object="false"
              />
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12" md="6">
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
                :hint="
                  amountFieldState === 'success'
                    ? '✅ Monto correcto - Listo para procesar'
                    : `Monto sugerido: ${formatCurrency(
                        suggestedAmountInLocalCurrency.value,
                        form.payment_currency
                      )}`
                "
                persistent-hint
                @input="validateAmountRealtime"
                @blur="validateAmountRealtime"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
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

          <!-- Información y detalles del pago -->
          <VRow>
            <VCol cols="12">
              <VAlert type="info" variant="tonal" class="mb-4">
                <template #title> Información y detalles del pago </template>

                <!-- Información de pagos previos -->
                <div
                  v-if="paymentInfo.has_previous_payments"
                  class="mb-3 pa-3 bg-info-lighten-4 rounded"
                >
                  <div class="text-subtitle-2 mb-2">
                    💰 Pagos Previos Realizados
                  </div>
                  <div>
                    <strong>Total de la factura:</strong>
                    {{ formatCurrency(paymentInfo.total_invoice_usd, "USD") }}
                  </div>
                  <div>
                    <strong>Ya pagado:</strong>
                    {{ formatCurrency(paymentInfo.total_paid_usd, "USD") }}
                  </div>
                  <div>
                    <strong>Monto restante:</strong>
                    {{ formatCurrency(paymentInfo.remaining_amount, "USD") }}
                  </div>
                  <div>
                    <strong>Progreso:</strong>
                    {{ paymentInfo.payment_percentage }}%
                  </div>
                  <div class="mt-2">
                    <VProgressLinear
                      :model-value="paymentInfo.payment_percentage"
                      color="info"
                      height="6"
                      rounded
                    />
                  </div>
                </div>

                <!-- Para USD -->
                <div v-if="form.payment_currency === 'USD'">
                  <div>
                    <strong
                      >Monto sugerido en {{ form.payment_currency }}:</strong
                    >
                    {{
                      formatCurrency(
                        suggestedAmountInLocalCurrency,
                        form.payment_currency
                      )
                    }}
                  </div>
                  <div>
                    <strong
                      >Monto ingresado en {{ form.payment_currency }}:</strong
                    >
                    {{
                      formatCurrency(form.payment_amount, form.payment_currency)
                    }}
                  </div>
                  <div>
                    <strong>Total a Pagar (USD):</strong>
                    {{ formatCurrency(totalInUSD, "USD") }}
                  </div>

                  <!-- Información adicional para pagos parciales -->
                  <div
                    v-if="form.payment_type === 'partial'"
                    class="mt-3 pa-3 bg-warning-lighten-4 rounded"
                  >
                    <div class="text-subtitle-2 mb-2">
                      📊 Información del Pago Parcial
                    </div>
                    <div>
                      <strong>Progreso del pago:</strong>
                      {{ paymentPercentage }}%
                    </div>
                    <div>
                      <strong>Monto restante:</strong>
                      {{ formatCurrency(remainingAmount, "USD") }}
                    </div>
                    <div class="mt-2">
                      <VProgressLinear
                        :model-value="paymentPercentage"
                        color="warning"
                        height="8"
                        rounded
                      />
                    </div>
                  </div>
                </div>

                <!-- Para VES/COP -->
                <div v-else>
                  <div>
                    <strong>Tasa de cambio:</strong>
                    1 USD = {{ currentExchangeRate }}
                    {{ form.payment_currency }}
                  </div>
                  <div>
                    <strong
                      >Monto sugerido en {{ form.payment_currency }}:</strong
                    >
                    {{
                      formatCurrency(
                        suggestedAmountInLocalCurrency,
                        form.payment_currency
                      )
                    }}
                  </div>
                  <div>
                    <strong
                      >Monto ingresado en {{ form.payment_currency }}:</strong
                    >
                    {{
                      formatCurrency(form.payment_amount, form.payment_currency)
                    }}
                  </div>
                  <div>
                    <strong>Equivalente en USD:</strong>
                    {{ formatCurrency(amountInUSD, "USD") }}
                  </div>

                  <!-- Información adicional para pagos parciales -->
                  <div
                    v-if="form.payment_type === 'partial'"
                    class="mt-3 pa-3 bg-warning-lighten-4 rounded"
                  >
                    <div class="text-subtitle-2 mb-2">
                      📊 Información del Pago Parcial
                    </div>
                    <div>
                      <strong>Progreso del pago:</strong>
                      {{ paymentPercentage }}%
                    </div>
                    <div>
                      <strong>Monto restante (USD):</strong>
                      {{ formatCurrency(remainingAmount, "USD") }}
                    </div>
                    <div class="mt-2">
                      <VProgressLinear
                        :model-value="paymentPercentage"
                        color="warning"
                        height="8"
                        rounded
                      />
                    </div>
                  </div>

                  <!-- Información de la factura original -->
                  <div
                    v-if="invoiceCurrency"
                    class="mt-3 pa-3 bg-grey-lighten-4 rounded"
                  >
                    <div>
                      <strong>Monto original de la factura:</strong>
                      {{
                        formatCurrency(totalInOriginalCurrency, invoiceCurrency)
                      }}
                    </div>
                    <div>
                      <strong>Total a Pagar (USD):</strong>
                      {{ formatCurrency(totalInUSD, "USD") }}
                    </div>
                  </div>
                </div>
              </VAlert>
            </VCol>
          </VRow>

          <!-- Resumen de pago -->
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="closeModal"
          :disabled="loading"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          @click="processPayment"
          :loading="loading"
          :disabled="selectedInvoices.length === 0 || !isFormValid"
        >
          Procesar Pago
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
