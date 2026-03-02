<template>
  <div>
    <!-- Filtros -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar por factura, proveedor o ref..."
              clearable
              @input="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VAutocomplete
              v-model="selectedSupplier"
              :items="suppliers"
              item-title="name"
              item-value="id"
              placeholder="Proveedor"
              clearable
              @update:model-value="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="selectedCurrency"
              :items="currencies"
              item-title="label"
              item-value="value"
              placeholder="Moneda"
              clearable
              @update:model-value="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="2">
            <AppDateTimePicker
              v-model="startDate"
              placeholder="Fecha desde"
              @update:model-value="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="2">
            <AppDateTimePicker
              v-model="endDate"
              placeholder="Fecha hasta"
              @update:model-value="applyFilters"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
        <VBtn variant="outlined" color="secondary" @click="clearFilters">
          Limpiar Filtros
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Tabla de Historial -->
    <VCard>
      <VCardTitle class="pb-2">
        <VIcon icon="tabler-history" class="me-2" />
        Historial de Pagos
      </VCardTitle>
      <VCardText>
        <VDataTable
          :headers="headers"
          :items="payments"
          :loading="loading"
          :items-per-page="15"
          class="text-no-wrap"
        >
          <!-- Fecha de Pago -->
          <template #item.payment_date="{ item }">
            <span class="text-body-2">
              {{ formatDate(item.payment_date) }}
            </span>
          </template>

          <!-- Proveedor -->
          <template #item.supplier="{ item }">
            <div class="text-body-2 font-weight-medium">
              {{ item.invoices?.[0]?.supplier?.name || "N/A" }}
            </div>
          </template>

          <!-- Monto -->
          <template #item.amount="{ item }">
            <div class="text-body-2 font-weight-medium">
              {{ formatNumber(item.amount, normalizeCurrencyCode(item.currency) === 'COP' ? 0 : 2) }}
            </div>
            <div
              v-if="item.currency === 'BS'"
              class="text-caption text-success text-medium-emphasis"
            >
              {{ formatWithoutCurrency(item.amount_usd || 0) }}
            </div>
          </template>

          <!-- Moneda -->
          <template #item.currency="{ item }">
            <VChip
              size="small"
              :color="getCurrencyColor(item.currency)"
              variant="tonal"
            >
              {{ normalizeCurrencyCode(item.currency) }}
            </VChip>
          </template>

          <!-- Equivalente USD -->
          <template #item.amount_usd="{ item }">
            <div class="text-body-2 font-weight-medium text-success">
              USD {{ formatNumber(item.amount_usd) }}
            </div>
          </template>

          <!-- Monto Factura USD -->
          <template #item.invoice_total_usd="{ item }">
            <div class="text-body-2 font-weight-medium">
              USD {{ formatNumber(item.invoice_total_usd) }}
            </div>
          </template>

          <!-- Referencia -->
          <template #item.reference="{ item }">
            <div class="text-body-2">
              <span v-if="item.reference" class="text-primary">
                {{ item.reference }}
              </span>
              <span v-else class="text-disabled"> Sin referencia </span>
            </div>
          </template>

          <!-- Usuario -->
          <template #item.user="{ item }">
            <div class="d-flex align-center">
              <VAvatar size="24" class="me-2" color="secondary" variant="tonal">
                <span class="text-xs">
                  {{ getUserInitials(item.user?.name) }}
                </span>
              </VAvatar>
              <span class="text-body-2">
                {{ item.user?.name || "Sistema" }}
              </span>
            </div>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex gap-2">
              <IconBtn @click="viewPaymentDetails(item)">
                <VIcon icon="tabler-eye" />
              </IconBtn>
              <IconBtn
                v-if="item.photo_url"
                @click="viewReceipt(item.photo_url)"
              >
                <VIcon icon="tabler-file-dollar" />
              </IconBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <VDialog v-model="showPaymentModal" max-width="1000" persistent scrollable>
      <VCard class="pa-2">
        <VCardTitle class="d-flex align-center justify-space-between pb-4">
          <div class="d-flex align-center">
            <VAvatar color="primary" variant="tonal" rounded size="40" class="me-3">
              <VIcon icon="tabler-receipt" size="24" />
            </VAvatar>
            <div>
              <div class="text-h6 font-weight-bold">Detalles del Pago</div>
              <div class="text-caption text-medium-emphasis">
                Referencia: {{ selectedPayment?.reference || 'N/A' }} | {{ formatDate(selectedPayment?.payment_date) }}
              </div>
            </div>
          </div>
          <VBtn icon="tabler-x" variant="text" color="secondary" @click="showPaymentModal = false" />
        </VCardTitle>

        <VDivider />

        <VCardText v-if="selectedPayment" class="pt-6 px-4">
          <VRow>
            <!-- Panel Izquierdo: Resumen Financiero -->
            <VCol cols="12" md="5">
              <VCard variant="tonal" color="primary" class="border-none mb-4 rounded-lg overflow-hidden">
                <VCardText class="pa-5">
                  <div class="text-overline mb-1 opacity-70">Monto del Pago</div>
                  <div class="text-h4 font-weight-black mb-1">
                    {{ formatCurrency(selectedPayment.amount, selectedPayment.currency) }}
                  </div>
                  <div class="text-subtitle-2 opacity-90 d-flex align-center">
                    <VIcon icon="tabler-currency-dollar" size="16" class="me-1" />
                    USD {{ formatNumber(selectedPayment.amount_usd) }}
                  </div>
                </VCardText>
              </VCard>

              <VCard v-if="savingsPercentage > 0" variant="tonal" color="success" class="border-none rounded-lg overflow-hidden">
                <VCardText class="pa-5 d-flex align-center">
                  <VAvatar color="white" size="40" class="me-4 elevation-1">
                    <VIcon icon="tabler-trending-down" color="success" size="22" />
                  </VAvatar>
                  <div>
                    <div class="text-h5 font-weight-bold">{{ savingsPercentage }}%</div>
                    <div class="text-caption font-weight-medium">Porcentaje de Ahorro Detectado</div>
                  </div>
                </VCardText>
              </VCard>
              <VCard v-else variant="tonal" color="info" class="border-none rounded-lg overflow-hidden">
                <VCardText class="pa-5 d-flex align-center">
                  <VAvatar color="white" size="40" class="me-4 elevation-1">
                    <VIcon icon="tabler-check" color="info" size="22" />
                  </VAvatar>
                  <div class="text-caption font-weight-medium">Pago completo sin descuentos adicionales</div>
                </VCardText>
              </VCard>

              <!-- Información de Registro -->
              <div class="mt-6 px-2">
                <div class="d-flex align-center mb-3">
                  <VIcon icon="tabler-user" size="18" class="me-3 text-medium-emphasis" />
                  <div>
                    <div class="text-caption text-medium-emphasis">Registrado por</div>
                    <div class="text-body-2 font-weight-medium">{{ selectedPayment.user?.name || "Sistema" }}</div>
                  </div>
                </div>
                <div class="d-flex align-center mb-3">
                  <VIcon icon="tabler-wallet" size="18" class="me-3 text-medium-emphasis" />
                  <div>
                    <div class="text-caption text-medium-emphasis">Método de Pago</div>
                    <div class="text-body-2 font-weight-medium text-capitalize">{{ selectedPayment.method || 'Transferencia / Otro' }}</div>
                  </div>
                </div>
              </div>
            </VCol>

            <!-- Panel Derecho: Listado de Facturas -->
            <VCol cols="12" md="7">
              <div class="text-body-2 font-weight-bold text-uppercase letter-spacing-1 mb-3 text-medium-emphasis px-1">
                Facturas Asociadas
              </div>
              <VCard variant="outlined" class="border-opacity-25 rounded-lg overflow-hidden">
                <VList lines="two" class="pa-0">
                  <VListItem
                    v-for="invoice in selectedPayment.invoices"
                    :key="invoice.id"
                    class="border-b last:border-0 border-opacity-10 py-3"
                  >
                    <template #prepend>
                      <VAvatar color="secondary" variant="tonal" rounded size="36">
                        <VIcon icon="tabler-hash" size="20" />
                      </VAvatar>
                    </template>
                    <VListItemTitle class="font-weight-bold text-body-1">
                      #{{ invoice.invoice_number }}
                    </VListItemTitle>
                    <VListItemSubtitle class="text-caption mt-1">
                      {{ invoice.supplier?.name }}
                    </VListItemSubtitle>
                    <template #append>
                      <div class="text-end">
                        <div class="text-body-1 font-weight-bold">
                          {{ formatNumber(invoice.total_amount, normalizeCurrencyCode(invoice.currency) === 'COP' ? 0 : 2) }} <span class="text-caption text-disabled">{{ normalizeCurrencyCode(invoice.currency) }}</span>
                        </div>
                        <div class="text-caption text-success font-weight-medium">
                          USD {{ formatNumber(invoice.total_usd) }}
                        </div>
                      </div>
                    </template>
                  </VListItem>
                </VList>
                
                <VCardText class="bg-var-theme-background-soft px-4 py-3 d-flex justify-space-between align-center">
                  <span class="text-caption font-weight-bold">TOTAL FACTURADO (REF. USD)</span>
                  <span class="text-body-1 font-weight-black">USD {{ formatNumber(selectedPayment.invoice_total_usd) }}</span>
                </VCardText>
              </VCard>

              <!-- Notas -->
              <div v-if="selectedPayment.notes" class="mt-6">
                <div class="text-body-2 font-weight-bold text-uppercase letter-spacing-1 mb-2 text-medium-emphasis px-1">Notas</div>
                <div class="pa-4 bg-var-theme-background rounded-lg border text-body-2 italic text-medium-emphasis">
                  "{{ selectedPayment.notes }}"
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>

        <VDivider class="mt-4" />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn 
            block 
            variant="tonal" 
            color="secondary" 
            class="px-8 rounded-lg font-weight-bold" 
            @click="showPaymentModal = false"
          >
            Cerrar Ventana
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal de Comprobante -->
    <VDialog v-model="showReceiptModal" max-width="600">
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center">
          <span>Comprobante de Pago</span>
          <VBtn icon variant="text" @click="showReceiptModal = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VCardText>
          <VImg
            :src="receiptUrl"
            alt="Comprobante de Pago"
            class="rounded"
            max-height="400"
          />
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import axios from "axios";
import { computed, onMounted, ref } from "vue";

// Estado reactivo
const loading = ref(false);
const payments = ref([]);
const suppliers = ref([]);
const searchQuery = ref("");
const selectedSupplier = ref(null);
const selectedCurrency = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const showPaymentModal = ref(false);
const showReceiptModal = ref(false);
const selectedPayment = ref(null);
const receiptUrl = ref("");

// Cálculo de ahorro para el pago seleccionado
const savingsPercentage = computed(() => {
  if (!selectedPayment.value) return 0;
  
  const paidUSD = parseFloat(selectedPayment.value.amount_usd) || 0;
  const invoiceTotalUSD = parseFloat(selectedPayment.value.invoice_total_usd) || 0;
  
  if (invoiceTotalUSD <= 0) return 0;
  
  const savingsUSD = invoiceTotalUSD - paidUSD;
  const percentage = (savingsUSD / invoiceTotalUSD) * 100;
  
  // Limitar a 0 si pagó de más, redondear a 2 decimales
  return Math.max(0, Math.round(percentage * 100) / 100);
});

// Headers de la tabla
const headers = [
  { title: "Fecha de Pago", key: "payment_date", sortable: true },
  { title: "Proveedor", key: "supplier", sortable: false },
  { title: "Monto Fac. USD", key: "invoice_total_usd", sortable: true },
  { title: "Monto Pagado", key: "amount", sortable: true },
  { title: "Moneda", key: "currency", sortable: true },
  { title: "Equivalente USD", key: "amount_usd", sortable: true },
  { title: "Referencia", key: "reference", sortable: true },
  { title: "Usuario", key: "user", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

// Opciones de monedas
const currencies = [
  { value: "VES", label: "VES - Bolívar Venezolano" },
  { value: "USD", label: "USD - Dólar Americano" },
  { value: "COP", label: "COP - Peso Colombiano" },
];

// Métodos
const fetchPaymentHistory = async () => {
  loading.value = true;
  try {
    const params = new URLSearchParams();

    if (searchQuery.value) params.append("search", searchQuery.value);
    if (selectedSupplier.value)
      params.append("supplier_id", selectedSupplier.value);
    if (selectedCurrency.value)
      params.append("currency", selectedCurrency.value);
    if (startDate.value) params.append("start_date", startDate.value);
    if (endDate.value) params.append("end_date", endDate.value);

    const response = await axios.get(
      `/api/finances/payment-history?${params.toString()}`
    );

    console.log("Respuesta del historial:", response.data);

    if (response.data.status === "success" || response.data.success) {
      payments.value = response.data.data.data || [];
      console.log("Pagos cargados:", payments.value);
    } else {
      console.error("Error al cargar historial:", response.data.message);
    }
  } catch (error) {
    console.error("Error al cargar historial de pagos:", error);
  } finally {
    loading.value = false;
  }
};

const fetchSuppliers = async () => {
  try {
    const response = await axios.get(
      "/api/finances/pending-payments/suppliers"
    );

    console.log("Respuesta de proveedores:", response.data);

    if (response.data.status === "success" || response.data.success) {
      suppliers.value = response.data.data || [];
      console.log("Proveedores cargados:", suppliers.value);
    }
  } catch (error) {
    console.error("Error al cargar proveedores:", error);
  }
};

let debouncer;
const applyFilters = () => {
  clearTimeout(debouncer);
  debouncer = setTimeout(() => {
    fetchPaymentHistory();
  }, 500);
};

const clearFilters = () => {
  searchQuery.value = "";
  selectedSupplier.value = null;
  selectedCurrency.value = null;
  startDate.value = null;
  endDate.value = null;
  fetchPaymentHistory();
};

const viewPaymentDetails = (payment) => {
  selectedPayment.value = payment;
  showPaymentModal.value = true;
};

const viewReceipt = (url) => {
  receiptUrl.value = url;
  showReceiptModal.value = true;
};

// Utilidades
const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString("es-ES");
};

const formatWithoutCurrency = (amount) => {
  if (!amount) return "N/A";

  // Redondear a 2 decimales
  const roundedAmount = Math.round(amount * 100) / 100;

  try {
    const formatter = new Intl.NumberFormat("es-ES", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

    return formatter.format(roundedAmount);
  } catch (error) {
    // Si falla el formateo, devolver formato simple con moneda normalizada
    return roundedAmount;
  }
};

const formatCurrency = (amount, currency) => {
  if (!amount) return "N/A";

  // Normalizar código de moneda
  const normalizedCurrency = normalizeCurrencyCode(currency);
  
  // Redondear según la moneda (COP no usa decimales)
  let roundedAmount;
  if (normalizedCurrency === "COP") {
    roundedAmount = Math.round(amount);
  } else {
    roundedAmount = Math.round(amount * 100) / 100;
  }

  try {
    const formatter = new Intl.NumberFormat("es-ES", {
      style: "currency",
      currency: normalizedCurrency,
      minimumFractionDigits: normalizedCurrency === "COP" ? 0 : 2,
      maximumFractionDigits: normalizedCurrency === "COP" ? 0 : 2,
    });

    let formatted = formatter.format(roundedAmount);
    
    // Forzar USD en lugar de US$ o similar
    if (normalizedCurrency === "USD") {
      formatted = "USD " + formatNumber(roundedAmount);
    }
    
    return formatted;
  } catch (error) {
    return `${formatNumber(roundedAmount, normalizedCurrency === "COP" ? 0 : 2)} ${normalizedCurrency}`;
  }
};

const formatNumber = (number, decimals = 2) => {
  if (number === null || number === undefined) return "0.00";
  return new Intl.NumberFormat("es-ES", {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(number);
};

const getSupplierInitials = (name) => {
  if (!name) return "N/A";
  return name
    .split(" ")
    .map((word) => word[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
};

const getUserInitials = (name) => {
  if (!name) return "S";
  return name
    .split(" ")
    .map((word) => word[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
};

const getCurrencyColor = (currency) => {
  // Normalizar código de moneda para consistencia
  const normalizedCurrency = normalizeCurrencyCode(currency);

  const colors = {
    VES: "warning", // Bolívares venezolanos (naranja)
    USD: "success", // Dólares americanos (verde)
    COP: "info", // Pesos colombianos (azul)
  };
  return colors[normalizedCurrency] || "secondary";
};

// Función para normalizar códigos de moneda
const normalizeCurrencyCode = (currency) => {
  if (!currency) return currency;

  const normalized = currency.toUpperCase().trim();

  // Mapeo de códigos inconsistentes a códigos estándar
  const currencyMap = {
    BS: "VES", // Bolívares venezolanos
    Bs: "VES", // Bolívares venezolanos (minúscula)
    VES: "VES", // Ya está correcto
    USD: "USD", // Dólares americanos
    USS: "USD", // Corrección de error común
    COP: "COP", // Pesos colombianos
  };

  return currencyMap[normalized] || normalized;
};

// Lifecycle
onMounted(() => {
  fetchPaymentHistory();
  fetchSuppliers();
});
</script>
