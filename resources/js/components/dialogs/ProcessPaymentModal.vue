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

  // Calcular total en la moneda original del grupo
  const total = selectedInvoices.value.reduce((sum, invoice) => {
    return sum + parseFloat(invoice.total_amount);
  }, 0);

  form.value.payment_amount = total;
};

// Cargar tasas de cambio
const fetchExchangeRates = async () => {
  try {
    const response = await axios.get("/exchange-rates");

    // El endpoint devuelve un array directo, no un objeto con success/data
    if (Array.isArray(response.data)) {
      exchangeRates.value = response.data.reduce((acc, rate) => {
        acc[rate.currency_code] = parseFloat(rate.rate);
        return acc;
      }, {});
    }
  } catch (error) {
    console.error("Error al cargar tasas de cambio:", error);
    toast.error("Error al cargar las tasas de cambio");
  }
};

// Actualizar monto cuando cambie la moneda
const updateAmountForCurrency = () => {
  if (form.value.payment_currency === "USD") {
    // Si es USD, usar el total original
    form.value.payment_amount = totalInOriginalCurrency.value;
  } else {
    // Si es otra moneda, calcular el monto sugerido
    const rate = exchangeRates.value[form.value.payment_currency];
    if (rate) {
      // Redondear a 2 decimales
      form.value.payment_amount =
        Math.round(totalInOriginalCurrency.value * rate * 100) / 100;
    }
  }
};

// Calcular monto sugerido en la moneda local
const suggestedAmountInLocalCurrency = computed(() => {
  if (!exchangeRates.value[form.value.payment_currency]) {
    return 0;
  }

  const rate = exchangeRates.value[form.value.payment_currency];

  // Si la moneda es USD, el monto ya está en USD
  if (form.value.payment_currency === "USD") {
    return totalInOriginalCurrency.value;
  }

  // Para otras monedas, multiplicar por la tasa (1 USD = X moneda)
  // Redondear a 2 decimales
  return Math.round(totalInOriginalCurrency.value * rate * 100) / 100;
});

// Calcular monto en USD
const amountInUSD = computed(() => {
  if (
    !form.value.payment_amount ||
    !exchangeRates.value[form.value.payment_currency]
  ) {
    return 0;
  }

  const rate = exchangeRates.value[form.value.payment_currency];

  // Si la moneda es USD, el monto ya está en USD
  if (form.value.payment_currency === "USD") {
    return form.value.payment_amount;
  }

  // Para otras monedas, dividir por la tasa (1 USD = X moneda)
  // Redondear a 2 decimales
  return Math.round((form.value.payment_amount / rate) * 100) / 100;
});

// Verificar si es pago parcial
const isPartialPayment = computed(() => {
  return (
    form.value.payment_amount > 0 &&
    form.value.payment_amount < totalInOriginalCurrency.value
  );
});

// Monto restante
const remainingAmount = computed(() => {
  return totalInOriginalCurrency.value - form.value.payment_amount;
});

// Total de facturas seleccionadas en la moneda original
const totalInOriginalCurrency = computed(() => {
  if (selectedInvoices.value.length === 0) return 0;
  return selectedInvoices.value.reduce((sum, invoice) => {
    return sum + parseFloat(invoice.total_amount);
  }, 0);
});

// Moneda original de las facturas
const originalCurrency = computed(() => {
  return props.paymentGroup?.currency || "USD";
});

// Información del proveedor
const supplierInfo = computed(() => {
  if (!props.paymentGroup) return null;
  return {
    name: props.paymentGroup.supplier_name,
    paymentDate: props.paymentGroup.payment_date,
    currency: props.paymentGroup.currency,
    invoiceCount: props.paymentGroup.invoice_count,
  };
});

// Formatear fecha
const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString("es-VE");
};

// Formatear moneda
const formatCurrency = (amount, currency) => {
  if (!amount) return "N/A";

  // Redondear a 2 decimales
  const roundedAmount = Math.round(amount * 100) / 100;

  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: currency === "VES" ? "VES" : currency === "COP" ? "COP" : "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(roundedAmount);
};

// Validar formulario
const validateForm = () => {
  errors.value = {};

  if (!form.value.payment_currency) {
    errors.value.payment_currency = "La moneda es requerida";
  }

  if (!form.value.payment_amount || form.value.payment_amount <= 0) {
    errors.value.payment_amount = "El monto debe ser mayor a 0";
  }

  if (!form.value.payment_date) {
    errors.value.payment_date = "La fecha de pago es requerida";
  }

  return Object.keys(errors.value).length === 0;
};

// Upload de comprobante
const handleFileUpload = async (file) => {
  if (!file) return;

  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append("file", file);

    const response = await axios.post(
      "/finances/pending-payments/upload-receipt",
      formData,
      {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      }
    );

    if (response.data.success) {
      form.value.photo_url = response.data.data.url;
      toast.success("Comprobante subido exitosamente");
    }
  } catch (error) {
    console.error("Error al subir comprobante:", error);
    toast.error("Error al subir el comprobante");
  } finally {
    uploading.value = false;
  }
};

// Procesar pago
const processPayment = async () => {
  if (selectedInvoices.value.length === 0) {
    toast.error("Debe seleccionar al menos una factura para procesar el pago");
    return;
  }

  if (!validateForm()) {
    toast.error("Por favor, corrija los errores en el formulario");
    return;
  }

  loading.value = true;
  try {
    const paymentData = {
      invoice_ids: selectedInvoices.value.map((invoice) => invoice.id),
      payment_currency: form.value.payment_currency,
      payment_amount: parseFloat(form.value.payment_amount),
      payment_date: form.value.payment_date,
      photo_url: form.value.photo_url,
      notes: form.value.notes,
    };

    const response = await axios.post(
      "/finances/pending-payments/process-payment",
      paymentData
    );

    if (response.data.status === "success" || response.data.success) {
      toast.success("Pago procesado exitosamente");
      emit("payment-processed");
      closeModal();
    } else {
      toast.error(response.data.message || "Error al procesar el pago");
    }
  } catch (error) {
    console.error("Error al procesar pago:", error);
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    }
    toast.error(error.response?.data?.message || "Error al procesar el pago");
  } finally {
    loading.value = false;
  }
};

// Establecer monto por defecto
const setDefaultAmount = () => {
  if (form.value.payment_currency === originalCurrency.value) {
    form.value.payment_amount = totalInOriginalCurrency.value;
  }
};

// Watchers
watch(
  () => form.value.payment_currency,
  () => {
    setDefaultAmount();
  }
);

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      // Inicializar con todas las facturas seleccionadas por defecto
      selectedInvoices.value = [...props.invoices];
      setDefaultAmount();
      fetchExchangeRates();
    }
  }
);

// Cargar datos al montar
onMounted(() => {
  fetchExchangeRates();
});
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="600px"
    persistent
    @update:model-value="closeModal"
    scrollable
  >
    <VCard class="d-flex flex-column">
      <!-- Header -->
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-credit-card" class="me-2" />
        <span class="text-h5 font-weight-bold">Procesar Pago</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VDivider />

      <!-- Contenido -->
      <VCardText class="flex-grow-1" style="overflow-y: auto">
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
                    {{
                      formatCurrency(totalInOriginalCurrency, originalCurrency)
                    }}
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </div>

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
                @update:model-value="updateAmountForCurrency"
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

          <!-- Indicador de pago parcial -->
          <VRow v-if="isPartialPayment">
            <VCol cols="12">
              <VAlert type="warning" variant="tonal" class="mb-4">
                <template #title> Pago Parcial </template>
                <div>
                  <strong>Monto restante:</strong>
                  {{ formatCurrency(remainingAmount, originalCurrency) }}
                </div>
                <div>
                  <strong>Estado:</strong> Pago parcial -
                  {{
                    formatCurrency(form.payment_amount, form.payment_currency)
                  }}
                  de
                  {{
                    formatCurrency(totalInOriginalCurrency, originalCurrency)
                  }}
                </div>
              </VAlert>
            </VCol>
          </VRow>

          <!-- Conversión a USD -->
          <VRow v-if="form.payment_currency !== 'USD'">
            <VCol cols="12">
              <VAlert type="info" variant="tonal" class="mb-4">
                <template #title> Conversión a USD </template>
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
                <div v-if="exchangeRates[form.payment_currency]">
                  <strong>Tasa de cambio:</strong>
                  1 USD = {{ exchangeRates[form.payment_currency] }}
                  {{ form.payment_currency }}
                </div>
              </VAlert>
            </VCol>
          </VRow>

          <!-- Selección de facturas -->
          <VRow>
            <VCol cols="12">
              <VCard variant="outlined">
                <VCardTitle class="text-h6"
                  >Seleccionar Facturas a Pagar</VCardTitle
                >
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
                      <tr v-for="invoice in invoices" :key="invoice.id">
                        <td>
                          <VCheckbox
                            :model-value="isInvoiceSelected(invoice)"
                            @change="toggleInvoiceSelection(invoice)"
                            color="primary"
                          />
                        </td>
                        <td>{{ invoice.invoice_number }}</td>
                        <td>
                          {{
                            formatCurrency(
                              invoice.total_amount,
                              invoice.currency
                            )
                          }}
                        </td>
                        <td>{{ invoice.currency }}</td>
                        <td>{{ formatDate(invoice.exp_date) }}</td>
                      </tr>
                    </tbody>
                  </VTable>

                  <!-- Total de facturas seleccionadas -->
                  <div
                    v-if="selectedInvoices.length > 0"
                    class="mt-3 pa-3 bg-primary-lighten-5 rounded"
                  >
                    <div class="text-h6 text-primary">
                      Total a Pagar:
                      {{
                        formatCurrency(
                          totalInOriginalCurrency,
                          originalCurrency
                        )
                      }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ selectedInvoices.length }} factura(s) seleccionada(s)
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <!-- Footer -->
      <VDivider />
      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn variant="outlined" @click="closeModal" :disabled="loading">
          Cancelar
        </VBtn>
        <VBtn color="success" @click="processPayment" :loading="loading">
          <VIcon icon="tabler-credit-card" class="me-2" />
          Procesar Pago
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-medium-emphasis {
  opacity: 0.7;
}

.gap-2 {
  gap: 8px;
}
</style>
