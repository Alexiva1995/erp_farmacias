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
  payment_currency: "USD",
  payment_amount: 0,
  payment_date: new Date().toISOString().split("T")[0],
  payment_receipt: null,
  notes: "",
});

// Estado de carga
const loading = ref(false);
const uploading = ref(false);
const exchangeRates = ref({});

// Errores de validación
const errors = ref({});

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

  const calculated = Math.round(totalInUSD.value * rate * 100) / 100;
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

  return Math.round((paymentAmount / rate) * 100) / 100;
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
    notes: "",
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

// Procesar pago
const processPayment = async () => {
  loading.value = true;
  errors.value = {};

  try {
    const paymentData = {
      payment_currency: form.value.payment_currency,
      payment_amount: form.value.payment_amount,
      payment_date: form.value.payment_date,
      notes: form.value.notes,
      photo_url: form.value.payment_receipt
        ? form.value.payment_receipt.name
        : null,
      invoice_ids: selectedInvoices.value.map((inv) => inv.id),
    };

    // console.log("Enviando datos de pago:", paymentData);

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
              <VTextField
                v-model.number="form.payment_amount"
                label="Monto a Pagar"
                type="number"
                step="0.01"
                min="0"
                :error-messages="errors.payment_amount"
                required
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

          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="form.notes"
                label="Notas (Opcional)"
                placeholder="Observaciones del pago"
              />
            </VCol>
          </VRow>

          <!-- Información y detalles del pago -->
          <VRow>
            <VCol cols="12">
              <VAlert type="info" variant="tonal" class="mb-4">
                <template #title> Información y detalles del pago </template>

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
          :disabled="selectedInvoices.length === 0"
        >
          Procesar Pago
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
