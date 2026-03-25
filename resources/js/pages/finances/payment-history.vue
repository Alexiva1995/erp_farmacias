<template>
  <VContainer fluid class="payment-history-page pa-4">
    <!-- Header Premium -->
    <VCard class="header-main-card mb-6 overflow-hidden border shadow-sm rounded-lg">
      <div class="premium-header pa-6 d-flex align-center gap-4">
        <VAvatar size="64" color="white" variant="elevated" class="shadow-sm">
          <VIcon icon="tabler-history" color="primary" size="32" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span
            class="text-super-xs font-weight-black text-white opacity-80 uppercase mb-1"
            >Finanzas / Historial</span
          >
          <span class="text-h4 font-weight-black text-white leading-tight"
            >Historial de Pagos</span
          >
        </div>
        <VSpacer />
        <VIcon
          icon="tabler-receipt-2"
          size="100"
          class="position-absolute opacity-10"
          style="inset-block-end: -20px; inset-inline-end: -20px"
        />
      </div>
    </VCard>

    <!-- Barra de Búsqueda y Filtros Premium -->
    <VCard class="mb-6 border rounded-lg shadow-sm overflow-hidden">
      <VCardText class="pa-3">
        <!-- Fila Principal: Búsqueda y Acciones -->
        <VRow align="center" no-gutters class="gap-2">
          <!-- Buscador Principal -->
          <VCol cols="12" md="6" lg="5">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar factura, proveedor o referencia..."
              prepend-inner-icon="tabler-search"
              class="premium-input-compact"
              density="compact"
              hide-details
              clearable
              @input="handleSearch"
            />
          </VCol>

          <VSpacer />

          <div class="d-flex align-center gap-1">
            <!-- Toggle Filtros -->
            <VBtn
              icon
              variant="tonal"
              :color="isFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              @click="isFiltersVisible = !isFiltersVisible"
            >
              <VIcon :icon="isFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
              <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            </VBtn>

            <VDivider vertical class="mx-1 my-2 border-opacity-10" />

            <!-- Limpiar Filtros (Icono Circular) -->
            <VBtn icon variant="text" color="secondary" size="38" @click="clearFilters">
              <VIcon icon="tabler-eraser" size="20" />
              <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
            </VBtn>
          </div>
        </VRow>

        <!-- Panel de Filtros Avanzado -->
        <VExpandTransition>
          <div v-show="isFiltersVisible">
            <VDivider class="my-4 border-opacity-10" />
            
            <VRow>
              <VCol cols="12" sm="6" md="4">
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1"
                  >Proveedor</span
                >
                <VAutocomplete
                  v-model="selectedSupplier"
                  :items="suppliers"
                  item-title="name"
                  item-value="id"
                  placeholder="Todos los proveedores"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="premium-select-compact"
                  clearable
                />
              </VCol>

              <VCol cols="12" sm="6" md="2">
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1"
                  >Moneda</span
                >
                <VSelect
                  v-model="selectedCurrency"
                  :items="currencies"
                  item-title="label"
                  item-value="value"
                  placeholder="Moneda"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="premium-select-compact"
                  clearable
                />
              </VCol>

              <VCol cols="12" sm="6" md="3">
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1"
                  >Desde</span
                >
                <AppDateTimePicker
                  v-model="startDate"
                  placeholder="Fecha inicial"
                  class="premium-input-compact"
                  :config="{
                    altInput: true,
                    altFormat: 'Y-m-d',
                    dateFormat: 'Y-m-d',
                  }"
                />
              </VCol>

              <VCol cols="12" sm="6" md="3">
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1"
                  >Hasta</span
                >
                <AppDateTimePicker
                  v-model="endDate"
                  placeholder="Fecha final"
                  class="premium-input-compact"
                  :config="{
                    altInput: true,
                    altFormat: 'Y-m-d',
                    dateFormat: 'Y-m-d',
                  }"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- Tabla / Cards -->
    <div v-if="!$vuetify.display.smAndDown">
      <VCard class="rounded-lg border shadow-sm overflow-hidden">
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          :headers="headers"
          :items="payments"
          :items-length="totalPayments"
          :loading="loading"
          :page="page"
          class="premium-table text-no-wrap"
          @update:options="updateOptions"
        >
          <template #item.payment_date="{ item }">
            <div class="d-flex align-center gap-2">
              <VAvatar
                size="32"
                color="primary"
                variant="tonal"
                class="rounded-lg"
              >
                <VIcon icon="tabler-calendar-event" size="16" />
              </VAvatar>
              <span class="font-weight-bold">{{
                formatDate(item.payment_date)
              }}</span>
            </div>
          </template>

          <template #item.supplier="{ item }">
            <span class="font-weight-black text-high-emphasis">
              {{ item.invoices?.[0]?.supplier?.name || "N/A" }}
            </span>
          </template>

          <template #item.amount="{ item }">
            <div class="d-flex flex-column">
              <span class="font-weight-black text-base">
                {{ formatCurrency(item.amount, item.currency) }}
              </span>
              <span
                v-if="normalizeCurrencyCode(item.currency) !== 'USD'"
                class="text-super-xs text-success font-weight-bold"
              >
                REF: USD {{ formatNumber(item.amount_usd) }}
              </span>
            </div>
          </template>

          <template #item.invoice_total_usd="{ item }">
            <span class="font-weight-bold text-disabled">
              USD {{ formatNumber(item.invoice_total_usd) }}
            </span>
          </template>

          <template #item.currency="{ item }">
            <VChip
              size="small"
              :color="getCurrencyColor(item.currency)"
              variant="elevated"
              class="font-weight-black px-3 rounded-lg shadow-sm"
            >
              {{ normalizeCurrencyCode(item.currency) }}
            </VChip>
          </template>

          <template #item.reference="{ item }">
            <VChip
              v-if="item.reference"
              size="small"
              variant="tonal"
              color="primary"
              class="font-weight-bold rounded-lg"
              prepend-icon="tabler-hash"
            >
              {{ item.reference }}
            </VChip>
            <span v-else class="text-disabled italic text-xs"
              >Sin referencia</span
            >
          </template>

          <template #item.user="{ item }">
            <div class="d-flex align-center gap-2">
              <VAvatar
                size="28"
                color="secondary"
                variant="tonal"
                class="rounded-lg"
              >
                <span class="text-xs font-weight-black">{{
                  getUserInitials(item.user?.name)
                }}</span>
              </VAvatar>
              <span class="text-xs font-weight-medium">{{
                item.user?.name || "Sistema"
              }}</span>
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1 justify-center">
              <IconBtn
                size="small"
                color="primary"
                variant="tonal"
                class="rounded-lg"
                @click="viewPaymentDetails(item)"
              >
                <VIcon icon="tabler-eye" size="18" />
              </IconBtn>
              <IconBtn
                v-if="item.photo_url"
                size="small"
                color="success"
                variant="tonal"
                class="rounded-lg"
                @click="viewReceipt(item.photo_url)"
              >
                <VIcon icon="tabler-file-dollar" size="18" />
              </IconBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Vista Móvil: Cards -->
    <div v-else class="d-flex flex-column gap-4">
      <VCard
        v-for="item in payments"
        :key="item.id"
        class="rounded-lg border shadow-sm overflow-hidden"
      >
        <div class="pa-4 bg-surface-variant-light d-flex align-center gap-3">
          <VAvatar
            size="48"
            color="primary"
            variant="tonal"
            class="rounded-lg shadow-sm"
          >
            <VIcon icon="tabler-receipt" size="24" />
          </VAvatar>
          <div class="d-flex flex-column flex-grow-1">
            <span
              class="text-base font-weight-black leading-tight truncate"
              style="max-inline-size: 180px"
            >
              {{ item.invoices?.[0]?.supplier?.name || "Proveedor N/A" }}
            </span>
            <span class="text-xs text-disabled">{{
              formatDate(item.payment_date)
            }}</span>
          </div>
          <VChip
            :color="getCurrencyColor(item.currency)"
            variant="elevated"
            class="font-weight-black px-4 rounded-lg shadow-sm"
          >
            {{ normalizeCurrencyCode(item.currency) }}
          </VChip>
        </div>

        <VDivider class="opacity-10" />

        <div class="pa-4 pt-4">
          <div class="d-flex justify-space-between align-center mb-4">
            <div class="d-flex flex-column">
              <span
                class="text-super-xs text-disabled font-weight-black uppercase"
                >Monto Pagado</span
              >
              <span class="text-xl font-weight-black text-primary">
                {{ formatCurrency(item.amount, item.currency) }}
              </span>
            </div>
            <div class="text-right d-flex flex-column">
              <span
                class="text-super-xs text-disabled font-weight-black uppercase"
                >Ref. USD</span
              >
              <span class="text-base font-weight-bold text-success"
                >USD {{ formatNumber(item.amount_usd) }}</span
              >
            </div>
          </div>

          <div class="d-flex gap-2">
            <VBtn
              block
              variant="tonal"
              color="primary"
              class="rounded-lg font-weight-black flex-grow-1"
              prepend-icon="tabler-eye"
              @click="viewPaymentDetails(item)"
            >
              Detalles
            </VBtn>
            <VBtn
              v-if="item.photo_url"
              variant="tonal"
              color="success"
              class="rounded-lg px-4"
              @click="viewReceipt(item.photo_url)"
            >
              <VIcon icon="tabler-file-dollar" />
            </VBtn>
          </div>
        </div>
      </VCard>

      <!-- Paginación Móvil -->
      <VCard
        class="rounded-lg border shadow-sm pa-3 d-flex justify-center align-center bg-surface"
      >
        <VPagination
          v-model="page"
          :length="Math.ceil(totalPayments / itemsPerPage)"
          total-visible="3"
          density="comfortable"
          active-color="primary"
          @update:model-value="fetchPaymentHistory"
        />
      </VCard>
    </div>

    <!-- Modal Detalles de Pago -->
    <VDialog
      v-model="showPaymentModal"
      max-width="1000"
      persistent
      scrollable
      :fullscreen="$vuetify.display.smAndDown"
    >
      <VCard class="rounded-lg overflow-hidden">
        <VCardTitle class="pa-0">
          <div
            class="premium-dialog-header pa-5 d-flex align-center bg-primary"
          >
            <VAvatar
              size="40"
              color="rgba(255,255,255,0.2)"
              class="me-3 shadow-sm rounded-lg"
              variant="elevated"
            >
              <VIcon icon="tabler-receipt-2" color="white" size="24" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-h6 font-weight-black text-white leading-tight"
                >Detalles del Pago</span
              >
              <span
                class="text-xs text-white opacity-80 uppercase font-weight-bold"
                >Ref: {{ selectedPayment?.reference || "N/A" }} |
                {{ formatDate(selectedPayment?.payment_date) }}</span
              >
            </div>
            <VSpacer />
            <VBtn
              icon
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg"
              @click="showPaymentModal = false"
            >
              <VIcon>tabler-x</VIcon>
            </VBtn>
          </div>
        </VCardTitle>

        <VCardText v-if="selectedPayment" class="pa-6">
          <VRow>
            <!-- Resumen Financiero -->
            <VCol cols="12" md="5">
              <VCard
                class="rounded-lg border shadow-sm bg-surface-variant-light overflow-hidden mb-6"
              >
                <div class="pa-5 d-flex flex-column align-center text-center">
                  <span
                    class="text-super-xs font-weight-black text-disabled uppercase mb-2"
                    >Total Pagado</span
                  >
                  <span class="text-h3 font-weight-black text-primary mb-1">
                    {{
                      formatCurrency(
                        selectedPayment.amount,
                        selectedPayment.currency,
                      )
                    }}
                  </span>
                  <div
                    class="d-flex align-center gap-2 bg-white px-4 py-1 rounded-pill shadow-sm border"
                  >
                    <VIcon
                      icon="tabler-currency-dollar"
                      size="18"
                      color="success"
                    />
                    <span class="text-base font-weight-black text-success"
                      >USD {{ formatNumber(selectedPayment.amount_usd) }}</span
                    >
                  </div>
                </div>

                <div class="pa-5 pt-0">
                  <VDivider class="border-dashed opacity-20 mb-5" />

                  <div
                    v-if="savingsPercentage > 0"
                    class="savings-card pa-4 rounded-lg d-flex align-center"
                  >
                    <VAvatar
                      color="white"
                      size="44"
                      class="me-4 shadow-sm"
                      variant="elevated"
                    >
                      <VIcon
                        icon="tabler-trending-down"
                        color="success"
                        size="24"
                      />
                    </VAvatar>
                    <div>
                      <div class="text-h5 font-weight-black text-success">
                        {{ savingsPercentage }}%
                      </div>
                      <div
                        class="text-super-xs font-weight-black text-disabled uppercase"
                      >
                        Ahorro Detectado
                      </div>
                    </div>
                  </div>
                  <div
                    v-else
                    class="pa-4 bg-white rounded-lg border border-dashed d-flex align-center text-center justify-center min-h-60"
                  >
                    <span
                      class="text-xs font-weight-bold text-disabled uppercase"
                      >Sin descuentos registrados</span
                    >
                  </div>
                </div>
              </VCard>

              <!-- Detalles de Registro -->
              <VCard class="rounded-lg border shadow-sm bg-white pa-5">
                <div class="d-flex align-center mb-5">
                  <VIcon
                    icon="tabler-user-check"
                    size="22"
                    class="me-3 text-primary"
                  />
                  <div>
                    <span
                      class="text-super-xs font-weight-black text-disabled uppercase d-block"
                      >Registrado por</span
                    >
                    <span class="text-sm font-weight-black">{{
                      selectedPayment.user?.name || "Sistema"
                    }}</span>
                  </div>
                </div>
                <div class="d-flex align-center">
                  <VIcon
                    icon="tabler-wallet"
                    size="22"
                    class="me-3 text-primary"
                  />
                  <div>
                    <span
                      class="text-super-xs font-weight-black text-disabled uppercase d-block"
                      >Método de Pago</span
                    >
                    <span class="text-sm font-weight-black text-capitalize">{{
                      selectedPayment.method || "Transferencia"
                    }}</span>
                  </div>
                </div>
              </VCard>
            </VCol>

            <!-- Facturas -->
            <VCol cols="12" md="7">
              <div class="d-flex align-center gap-2 mb-4 ms-2">
                <VIcon icon="tabler-file-invoice" size="20" color="primary" />
                <span
                  class="font-weight-black text-uppercase text-xs text-primary"
                  >Facturas Asociadas</span
                >
              </div>

              <VCard
                class="rounded-lg border shadow-sm overflow-hidden bg-white"
              >
                <VList lines="two" class="pa-0">
                  <VListItem
                    v-for="invoice in selectedPayment.invoices"
                    :key="invoice.id"
                    class="border-b py-4"
                  >
                    <template #prepend>
                      <VAvatar
                        color="secondary"
                        variant="tonal"
                        rounded
                        size="40"
                        class="rounded-lg"
                      >
                        <VIcon icon="tabler-hash" size="22" />
                      </VAvatar>
                    </template>
                    <VListItemTitle class="font-weight-black text-base">
                      #{{ invoice.invoice_number }}
                    </VListItemTitle>
                    <VListItemSubtitle
                      class="text-xs font-weight-bold text-disabled uppercase mt-1"
                    >
                      {{ invoice.supplier?.name }}
                    </VListItemSubtitle>
                    <template #append>
                      <div class="text-right">
                        <div class="text-base font-weight-black">
                          {{
                            formatNumber(
                              invoice.total_amount,
                              normalizeCurrencyCode(invoice.currency) === "COP"
                                ? 0
                                : 2,
                            )
                          }}
                          <span class="text-super-xs font-weight-black ms-1">{{
                            normalizeCurrencyCode(invoice.currency)
                          }}</span>
                        </div>
                        <div
                          class="text-xs font-weight-black text-success uppercase"
                        >
                          USD {{ formatNumber(invoice.total_usd) }}
                        </div>
                      </div>
                    </template>
                  </VListItem>
                </VList>

                <div
                  class="bg-surface-variant-light pa-4 d-flex justify-space-between align-center border-t"
                >
                  <span
                    class="text-super-xs font-weight-black text-disabled uppercase"
                    >Total Facturado</span
                  >
                  <span class="text-h6 font-weight-black text-high-emphasis"
                    >USD
                    {{ formatNumber(selectedPayment.invoice_total_usd) }}</span
                  >
                </div>
              </VCard>

              <!-- Notas -->
              <div v-if="selectedPayment.notes" class="mt-6">
                <div class="d-flex align-center gap-2 mb-3 ms-2">
                  <VIcon icon="tabler-message-2" size="20" color="disabled" />
                  <span
                    class="font-weight-black text-uppercase text-xs text-disabled"
                    >Observaciones</span
                  >
                </div>
                <div
                  class="pa-4 bg-surface-variant-light rounded-lg border border-dashed text-sm italic text-medium-emphasis"
                >
                  "{{ selectedPayment.notes }}"
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VBtn
            block
            variant="flat"
            color="secondary"
            size="large"
            class="rounded-lg font-weight-black shadow-sm"
            @click="showPaymentModal = false"
          >
            CERRAR DETALLES
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal Comprobante Tagged -->
    <VDialog
      v-model="showReceiptModal"
      max-width="700"
      :fullscreen="$vuetify.display.smAndDown"
    >
      <VCard class="rounded-lg overflow-hidden">
        <VCardTitle class="pa-0">
          <div
            class="premium-dialog-header pa-5 d-flex align-center bg-success"
          >
            <VAvatar
              size="40"
              color="rgba(255,255,255,0.2)"
              class="me-3 rounded-lg shadow-sm"
            >
              <VIcon icon="tabler-file-dollar" color="white" size="24" />
            </VAvatar>
            <span class="text-h6 font-weight-black text-white"
              >Comprobante Digitall</span
            >
            <VSpacer />
            <VBtn
              icon
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg"
              @click="showReceiptModal = false"
            >
              <VIcon>tabler-x</VIcon>
            </VBtn>
          </div>
        </VCardTitle>
        <VCardText class="pa-6 text-center">
          <VImg
            :src="receiptUrl"
            alt="Comprobante de Pago"
            class="rounded-lg border shadow-lg mx-auto"
            max-height="600"
            cover
          >
            <template #placeholder>
              <div
                class="d-flex align-center justify-center h-100 bg-surface-variant-light"
              >
                <VProgressCircular indeterminate color="primary" />
              </div>
            </template>
          </VImg>
        </VCardText>
        <VCardActions class="pa-4 pt-0">
          <VBtn
            block
            color="success"
            variant="tonal"
            class="rounded-lg font-weight-black"
            @click="showReceiptModal = false"
          >
            ENTENDIDO
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>

<script setup>
import axios from "axios";
import { computed, onMounted, ref, watch } from "vue";

// Estado de UI
const loading = ref(false);
const isFiltersVisible = ref(false);

// Datos
const payments = ref([]);
const suppliers = ref([]);
const totalPayments = ref(0);
const page = ref(1);
const itemsPerPage = ref(15);
const sortBy = ref();
const orderBy = ref();

// Filtros
const searchQuery = ref("");
const selectedSupplier = ref(null);
const selectedCurrency = ref(null);
const startDate = ref(null);
const endDate = ref(null);

// Modales
const showPaymentModal = ref(false);
const showReceiptModal = ref(false);
const selectedPayment = ref(null);
const receiptUrl = ref("");

// Headers
const headers = [
  { title: "FECHA", key: "payment_date", sortable: true },
  { title: "PROVEEDOR", key: "supplier", sortable: false },
  {
    title: "FAC. USD",
    key: "invoice_total_usd",
    sortable: true,
    align: "center",
  },
  { title: "MONTO PAGADO", key: "amount", sortable: true },
  { title: "MONEDA", key: "currency", sortable: true, align: "center" },
  { title: "REFERENCIA", key: "reference", sortable: true },
  { title: "REGISTRO", key: "user", sortable: false },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center" },
];

const currencies = [
  { value: "VES", label: "VES - Bolívar Venezolano" },
  { value: "USD", label: "USD - Dólar Americano" },
  { value: "COP", label: "COP - Peso Colombiano" },
];

const savingsPercentage = computed(() => {
  if (!selectedPayment.value) return 0;
  const paidUSD = parseFloat(selectedPayment.value.amount_usd) || 0;
  const invoiceTotalUSD =
    parseFloat(selectedPayment.value.invoice_total_usd) || 0;
  if (invoiceTotalUSD <= 0) return 0;
  const savingsUSD = invoiceTotalUSD - paidUSD;
  const percentage = (savingsUSD / invoiceTotalUSD) * 100;
  return Math.max(0, Math.round(percentage * 100) / 100);
});

// Fetching
const fetchPaymentHistory = async () => {
  loading.value = true;
  try {
    const params = {
      search: searchQuery.value,
      supplier_id: selectedSupplier.value,
      currency: selectedCurrency.value,
      start_date: startDate.value,
      end_date: endDate.value,
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
    };

    // Filtro de nulos
    Object.keys(params).forEach(
      (key) =>
        (params[key] === null || params[key] === "") && delete params[key],
    );

    const response = await axios.get("/api/finances/payment-history", {
      params,
    });

    if (response.data.status === "success" || response.data.success) {
      payments.value = response.data.data.data || [];
      totalPayments.value = response.data.data.total || 0;
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
      "/api/finances/pending-payments/suppliers",
    );
    if (response.data.status === "success" || response.data.success) {
      suppliers.value = response.data.data || [];
    }
  } catch (error) {
    console.error("Error al cargar proveedores:", error);
  }
};

// Handlers
let debouncer;
const handleSearch = () => {
  clearTimeout(debouncer);
  debouncer = setTimeout(() => {
    page.value = 1;
    fetchPaymentHistory();
  }, 500);
};

const updateOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
  fetchPaymentHistory();
};

const clearFilters = () => {
  searchQuery.value = "";
  selectedSupplier.value = null;
  selectedCurrency.value = null;
  startDate.value = null;
  endDate.value = null;
  page.value = 1;
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

// Watchers
watch([selectedSupplier, selectedCurrency, startDate, endDate], () => {
  page.value = 1;
  fetchPaymentHistory();
});

// Formateadores
const formatDate = (date) =>
  date ? new Date(date).toLocaleDateString("es-ES") : "N/A";

const formatNumber = (num, decimals = 2) => {
  if (num === null || num === undefined) return "0.00";
  return new Intl.NumberFormat("es-ES", {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(num);
};

const formatCurrency = (amount, currency) => {
  if (!amount) return "N/A";
  const normalized = normalizeCurrencyCode(currency);
  const decimals = normalized === "COP" ? 0 : 2;
  return `${normalized} ${formatNumber(amount, decimals)}`;
};

const getUserInitials = (name) => {
  if (!name) return "S";
  return name
    .split(" ")
    .map((w) => w[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
};

const normalizeCurrencyCode = (currency) => {
  if (!currency) return "";
  const map = { BS: "VES", BS: "VES", USS: "USD" };
  const normalized = currency.toUpperCase().trim();
  return map[normalized] || normalized;
};

const getCurrencyColor = (currency) => {
  const norm = normalizeCurrencyCode(currency);
  if (norm === "VES") return "warning";
  if (norm === "USD") return "success";
  if (norm === "COP") return "info";
  return "secondary";
};

onMounted(() => {
  fetchPaymentHistory();
  fetchSuppliers();
});
</script>

<style scoped>
.payment-history-page {
  background-color: rgb(var(--v-theme-background));
  min-block-size: 100vh;
}

.premium-header {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #34495e 100%
  );
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.04);
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.h-38 {
  block-size: 38px !important;
}

.min-h-60 {
  min-block-size: 60px;
}

:deep(.premium-table) {
  .v-data-table-header th {
    background: white !important;
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05rem !important;
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
  }

  .v-data-table__td {
    padding-block: 12px !important;
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
  }
}

:deep(.premium-input-compact) {
  .v-field__input {
    font-size: 0.875rem !important;
    min-block-size: 38px !important;
    padding-block: 0 !important;
  }
}

:deep(.premium-select-compact) {
  .v-field__input {
    font-size: 0.875rem !important;
    min-block-size: 38px !important;
    padding-block: 0 !important;
  }
}

.savings-card {
  background: rgba(var(--v-theme-success), 0.08);
  border: 1px dashed rgba(var(--v-theme-success), 0.3);
}

.border-dashed {
  border-style: dashed !important;
}

.premium-dialog-header {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #2c3e50 100%
  );
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
