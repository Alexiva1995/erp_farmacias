<template>
  <div>
    <!-- Filtros -->
    <VCard class="mb-6">
      <VCardTitle class="pb-2">
        <VIcon icon="tabler-filter" class="me-2" />
        Filtros de Historial
      </VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="2">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar por factura, proveedor o referencia..."
              prepend-inner-icon="tabler-search"
              clearable
              @input="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="2">
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
          <VCol cols="12" md="2">
            <VBtn variant="outlined" color="secondary" @click="clearFilters">
              <VIcon icon="tabler-x" class="me-2" />
              Limpiar Filtros
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
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
            <div class="d-flex align-center">
              <VAvatar size="32" class="me-3" color="primary" variant="tonal">
                <span class="text-sm font-weight-medium">
                  {{ getSupplierInitials(item.invoices?.[0]?.supplier?.name) }}
                </span>
              </VAvatar>
              <div>
                <div class="text-body-2 font-weight-medium">
                  {{ item.invoices?.[0]?.supplier?.name || "N/A" }}
                </div>
              </div>
            </div>
          </template>

          <!-- Monto -->
          <template #item.amount="{ item }">
            <div class="text-body-2 font-weight-medium">
              {{ formatCurrency(item.amount, item.currency, true) }}
            </div>
            <div
              v-if="item.currency === 'BS'"
              class="text-caption text-medium-emphasis"
            >
              USD {{ formatNumber(item.total_paid_usd || 0) }}
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

          <!-- Comprobante -->
          <template #item.receipt_url="{ item }">
            <VBtn
              v-if="item.receipt_url"
              size="small"
              variant="outlined"
              color="primary"
              @click="viewReceipt(item.receipt_url)"
            >
              <VIcon icon="tabler-eye" size="16" class="me-1" />
              Ver
            </VBtn>
            <span v-else class="text-body-2 text-disabled">
              Sin comprobante
            </span>
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
            <IconBtn @click="viewPaymentDetails(item)">
              <VIcon icon="tabler-eye" />
            </IconBtn>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Modal de Detalles del Pago -->
    <VDialog v-model="showPaymentModal" max-width="600" persistent>
      <VCard class="pa-0" :elevation="0">
        <!-- Header -->
        <VCardItem class="px-6 pt-6 pb-0">
          <VCardTitle class="text-h6 font-weight-medium"
            >Detalles del Pago</VCardTitle
          >
          <template #append>
            <VBtn
              icon
              size="small"
              variant="text"
              @click="showPaymentModal = false"
            >
              <VIcon icon="tabler-x" size="20" />
            </VBtn>
          </template>
        </VCardItem>

        <VCardText v-if="selectedPayment" class="pt-4 px-6 pb-0">
          <!-- Key Payment Info - Grid Layout -->
          <VRow dense class="mb-4">
            <VCol cols="6">
              <div class="text-caption text-medium-emphasis mb-1">Fecha</div>
              <div class="text-body-1">
                {{ formatDate(selectedPayment.payment_date) }}
              </div>
            </VCol>

            <VCol cols="6">
              <div class="text-caption text-medium-emphasis mb-1">Monto</div>
              <div class="text-body-1 font-weight-medium">
                {{
                  formatCurrency(
                    selectedPayment.amount,
                    selectedPayment.currency
                  )
                }}
              </div>
            </VCol>

            <VCol cols="6">
              <div class="text-caption text-medium-emphasis mb-1">
                Equivalente USD
              </div>
              <div class="text-body-1 text-success">
                USD {{ formatNumber(selectedPayment.amount_usd) }}
              </div>
            </VCol>

            <VCol cols="6">
              <div class="text-caption text-medium-emphasis mb-1">Tipo</div>
              <VChip
                :color="
                  selectedPayment.payment_type === 'full'
                    ? 'success'
                    : 'warning'
                "
                size="small"
                variant="flat"
                density="comfortable"
                class="font-weight-medium"
              >
                {{
                  selectedPayment.payment_type === "full"
                    ? "Completo"
                    : "Parcial"
                }}
              </VChip>
            </VCol>

            <VCol cols="12">
              <VDivider class="my-4" />
            </VCol>

            <VCol cols="6">
              <div class="text-caption text-medium-emphasis mb-1">
                Registrado por
              </div>
              <div class="text-body-1">
                {{ selectedPayment.user?.name || "Sistema" }}
              </div>
            </VCol>

            <VCol cols="6">
              <div class="text-caption text-medium-emphasis mb-1">
                Referencia
              </div>
              <div
                class="text-body-1"
                :class="{ 'text-disabled': !selectedPayment.reference }"
              >
                {{ selectedPayment.reference || "Sin referencia" }}
              </div>
            </VCol>
          </VRow>

          <!-- Partial Payment Info - Only when needed -->
          <VExpandTransition>
            <div v-if="selectedPayment.payment_type === 'partial'" class="mb-6">
              <div class="text-caption text-medium-emphasis mb-3">
                Detalles del pago parcial
              </div>
              <VRow dense>
                <VCol cols="4">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Total factura
                  </div>
                  <div class="text-body-2">
                    USD {{ formatNumber(selectedPayment.invoice_total_usd) }}
                  </div>
                </VCol>
                <VCol cols="4">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Pagado
                  </div>
                  <div class="text-body-2 text-success">
                    USD {{ formatNumber(selectedPayment.total_paid_usd || 0) }}
                  </div>
                </VCol>
                <VCol cols="4">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Restante
                  </div>
                  <div
                    class="text-body-2"
                    :class="
                      selectedPayment.remaining_amount_usd > 0
                        ? 'text-error'
                        : 'text-success'
                    "
                  >
                    USD
                    {{
                      formatNumber(selectedPayment.remaining_amount_usd || 0)
                    }}
                  </div>
                </VCol>
                <VCol cols="12" class="mt-3">
                  <VProgressLinear
                    :model-value="selectedPayment.payment_percentage || 0"
                    height="6"
                    :color="
                      selectedPayment.payment_percentage === 100
                        ? 'success'
                        : 'warning'
                    "
                    rounded
                  />
                  <div
                    class="text-caption text-medium-emphasis text-center mt-1"
                  >
                    {{ formatNumber(selectedPayment.payment_percentage || 0) }}%
                    completado
                  </div>
                </VCol>
              </VRow>
            </div>
          </VExpandTransition>

          <!-- Payment Notes - Only when exists -->
          <VExpandTransition>
            <div v-if="selectedPayment.notes" class="mb-6">
              <div class="text-caption text-medium-emphasis mb-2">Notas</div>
              <div class="text-body-2 pa-3 bg-grey-lighten-4 rounded-lg">
                {{ selectedPayment.notes }}
              </div>
            </div>
          </VExpandTransition>

          <!-- Invoices Table -->
          <div>
            <div class="text-caption text-medium-emphasis mb-3">
              Facturas pagadas
            </div>
            <div class="rounded-lg overflow-hidden">
              <VTable density="compact" class="mb-0">
                <thead class="bg-grey-lighten-4">
                  <tr>
                    <th class="text-caption font-weight-medium">N° Factura</th>
                    <th class="text-caption font-weight-medium">Proveedor</th>
                    <th class="text-caption font-weight-medium">Monto USD</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="invoice in selectedPayment.invoices"
                    :key="invoice.id"
                    class="border-b"
                  >
                    <td class="text-body-2 py-3">
                      {{ invoice.invoice_number }}
                    </td>
                    <td class="text-body-2">{{ invoice.supplier?.name }}</td>
                    <td class="text-body-2">
                      <div class="d-flex align-center">
                        <span class="text-success font-weight-medium mr-2"
                          >USD {{ formatNumber(invoice.total_usd) }}</span
                        >
                      </div>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </div>

          <!-- Footer Actions -->
          <VCardActions class="px-0 pb-6 pt-4">
            <VSpacer />
            <VBtn
              variant="tonal"
              @click="showPaymentModal = false"
              size="small"
            >
              Cerrar
            </VBtn>
          </VCardActions>
        </VCardText>
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
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";

// Composables
const router = useRouter();

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

// Headers de la tabla
const headers = [
  { title: "Fecha de Pago", key: "payment_date", sortable: true },
  { title: "Proveedor", key: "supplier", sortable: false },
  { title: "Monto USD", key: "invoice_total_usd", sortable: true },
  { title: "Monto Pagado", key: "amount", sortable: true },
  { title: "Moneda", key: "currency", sortable: true },
  { title: "Equivalente USD", key: "amount_usd", sortable: true },
  { title: "Referencia", key: "reference", sortable: false },
  { title: "Comprobante", key: "receipt_url", sortable: false },
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

const applyFilters = () => {
  fetchPaymentHistory();
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

const formatCurrency = (amount, currency, hiddenCurrency) => {
  if (!amount) return "N/A";

  // Redondear a 2 decimales
  const roundedAmount = Math.round(amount * 100) / 100;

  // Normalizar código de moneda
  const normalizedCurrency = normalizeCurrencyCode(currency);

  try {
    if (hiddenCurrency) {
      return new Intl.NumberFormat("es-ES", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(roundedAmount);
    } else {
      return new Intl.NumberFormat("es-ES", {
        style: "currency",
        currency: normalizedCurrency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(roundedAmount);
    }
  } catch (error) {
    // Si falla el formateo, devolver formato simple con moneda normalizada
    return `${roundedAmount} ${normalizedCurrency}`;
  }
};

const formatNumber = (number) => {
  if (!number) return "0.00";
  return new Intl.NumberFormat("es-ES", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
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
