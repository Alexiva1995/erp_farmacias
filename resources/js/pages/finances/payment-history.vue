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
              {{ formatWithoutCurrency(item.amount) }}
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

    <!-- Modal de Detalles del Pago -->
    <VDialog v-model="showPaymentModal" max-width="600" persistent scrollable>
      <VCard class="overflow-hidden">
        <!-- Header minimalista -->
        <VCardTitle
          class="d-flex justify-space-between align-center px-6 pt-6 pb-4"
        >
          <div class="d-flex align-center">
            <VIcon icon="tabler-receipt" size="24" class="me-3 text-primary" />
            <span class="text-h6 font-weight-medium">Detalles del Pago</span>
          </div>
          <VBtn
            icon
            variant="text"
            size="small"
            @click="showPaymentModal = false"
            class="text-medium-emphasis"
          >
            <VIcon icon="tabler-x" size="20" />
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText v-if="selectedPayment" class="pa-0">
          <!-- Información principal en tarjetas compactas -->
          <div class="pa-6">
            <div class="mb-6">
              <div class="text-body-2 text-medium-emphasis mb-3">
                Información Principal
              </div>
              <VRow>
                <VCol cols="12" md="6" class="mb-4 mb-md-0">
                  <VCard variant="flat" class="border pa-4 rounded-lg">
                    <div class="text-body-2 text-medium-emphasis mb-1">
                      Monto del Pago
                    </div>
                    <div class="text-h5 font-weight-medium text-primary mb-1">
                      {{
                        formatCurrency(
                          selectedPayment.amount,
                          selectedPayment.currency
                        )
                      }}
                    </div>
                    <div class="text-body-2 text-success">
                      USD {{ formatNumber(selectedPayment.amount_usd) }}
                    </div>
                  </VCard>
                </VCol>
                <VCol cols="12" md="6">
                  <VCard variant="flat" class="border pa-4 rounded-lg">
                    <div class="text-body-2 text-medium-emphasis mb-1">
                      Fecha de Pago
                    </div>
                    <div class="text-body-1 font-weight-medium">
                      {{ formatDate(selectedPayment.payment_date) }}
                    </div>
                    <div class="d-flex align-center mt-2">
                      <div class="text-body-2 text-medium-emphasis">
                        {{ selectedPayment.reference || "Sin referencia" }}
                      </div>
                    </div>
                  </VCard>
                </VCol>
              </VRow>
            </div>

            <!-- Detalles adicionales en grid compacto -->
            <div class="mb-6">
              <div class="text-body-2 text-medium-emphasis mb-3">
                Información Adicional
              </div>
              <VRow>
                <VCol cols="6" class="mb-3">
                  <div class="text-body-2 text-medium-emphasis mb-1">
                    Registrado por
                  </div>
                  <div class="d-flex align-center">
                    <VIcon
                      icon="tabler-user"
                      size="16"
                      class="me-2 text-medium-emphasis"
                    />
                    <span class="text-body-1">
                      {{ selectedPayment.user?.name || "Sistema" }}
                    </span>
                  </div>
                </VCol>
                <VCol cols="6" class="mb-3">
                  <div class="text-body-2 text-medium-emphasis mb-1">
                    Moneda
                  </div>
                  <div class="d-flex align-center">
                    <VChip
                      size="small"
                      :color="getCurrencyColor(selectedPayment.currency)"
                      variant="tonal"
                    >
                      {{ selectedPayment.currency }}
                    </VChip>
                  </div>
                </VCol>
              </VRow>
            </div>

            <!-- Facturas pagadas -->
            <div class="mb-6">
              <div class="d-flex justify-space-between align-center mb-3">
                <div class="text-body-2 text-medium-emphasis">
                  Facturas Pagadas ({{ selectedPayment.invoices?.length || 0 }})
                </div>
              </div>
              <VCard variant="flat" class="border rounded-lg">
                <VTable class="rounded-lg">
                  <thead>
                    <tr>
                      <th class="text-body-2 font-weight-medium">Factura</th>
                      <th class="text-body-2 font-weight-medium">Proveedor</th>
                      <th class="text-body-2 font-weight-medium">
                        Monto Original
                      </th>
                      <th class="text-body-2 font-weight-medium">Moneda</th>
                      <th class="text-body-2 font-weight-medium">Monto USD</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="invoice in selectedPayment.invoices"
                      :key="invoice.id"
                      class="text-body-2"
                    >
                      <td>
                        <div class="d-flex align-center">
                          <div class="font-weight-medium">
                            {{ invoice.invoice_number }}
                          </div>
                        </div>
                      </td>
                      <td class="text-medium-emphasis">
                        {{ invoice.supplier?.name }}
                      </td>
                      <td class="text-medium-emphasis">
                        {{ formatNumber(invoice.total_amount) }}
                      </td>
                      <td>
                        <VChip
                          size="x-small"
                          :color="getCurrencyColor(invoice.currency)"
                          variant="tonal"
                          class="ms-2"
                        >
                          {{ invoice.currency }}
                        </VChip>
                      </td>
                      <td>
                        <span class="font-weight-medium text-success">
                          {{ formatNumber(invoice.total_usd) }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </VCard>
            </div>

            <!-- Notas -->
            <template v-if="selectedPayment.notes">
              <div>
                <div class="text-body-2 text-medium-emphasis mb-3">Notas</div>
                <VCard variant="flat" class="border pa-4 rounded-lg">
                  <div class="text-body-2" style="white-space: pre-line">
                    {{ selectedPayment.notes }}
                  </div>
                </VCard>
              </div>
            </template>
          </div>
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

  // Redondear a 2 decimales
  const roundedAmount = Math.round(amount * 100) / 100;

  // Normalizar código de moneda
  const normalizedCurrency = normalizeCurrencyCode(currency);

  try {
    const formatter = new Intl.NumberFormat("es-ES", {
      style: "currency",
      currency: normalizedCurrency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

    return formatter.format(roundedAmount);
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
