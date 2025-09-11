<script setup>
import PendingPaymentModal from "@/components/dialogs/PendingPaymentModal.vue";
import ProcessPaymentModal from "@/components/dialogs/ProcessPaymentModal.vue";
import PendingPaymentsFilters from "@/components/PendingPaymentsFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";

// Estado reactivo
const loading = ref(false);
const pendingPayments = ref([]);
const totalGroups = ref(0);
const totalAmount = ref(0);
const statistics = ref({});
const exchangeRates = ref({});

// Estado para filtros
const suppliers = ref([]);
const isLoadingFilters = ref(false);

// Filtros
const searchQuery = ref("");
const selectedSupplier = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const showOverdueOnly = ref(false);

// Paginación
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("payment_date");
const orderBy = ref("asc");

// Modales
const showPaymentModal = ref(false);
const showProcessModal = ref(false);
const selectedPaymentGroup = ref(null);
const selectedInvoices = ref([]);
const selectedTableInvoices = ref([]);

// Headers de la tabla
const headers = [
  { title: "Seleccionar", key: "select", sortable: false, width: "50px" },
  { title: "N° Factura", key: "invoice_number", sortable: true },
  { title: "Proveedor", key: "supplier_name", sortable: true },
  { title: "Fecha de Pago", key: "payment_date", sortable: true },
  { title: "Monto", key: "total_amount", sortable: true },
  { title: "Moneda", key: "currency", sortable: false },
  { title: "Fecha Vencimiento", key: "exp_date", sortable: true },
  { title: "Estado", key: "status", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

// Cargar proveedores para filtros
const fetchSuppliers = async () => {
  isLoadingFilters.value = true;
  try {
    const response = await axios.get("/finances/pending-payments/suppliers");

    if (response.data.success || response.data.status === "success") {
      suppliers.value = response.data.data;
    } else {
      console.error("Error al cargar proveedores:", response.data.message);
      toast.error(
        response.data.message || "No se pudieron cargar los proveedores."
      );
    }
  } catch (error) {
    console.error("Error al cargar proveedores:", error);
    toast.error("No se pudieron cargar los proveedores.");
  } finally {
    isLoadingFilters.value = false;
  }
};

// Cargar datos
const fetchPendingPayments = async () => {
  loading.value = true;
  try {
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
      q: searchQuery.value,
      supplier_id: selectedSupplier.value,
      start_date: startDate.value,
      end_date: endDate.value,
    };

    // Limpiar parámetros vacíos
    Object.keys(params).forEach((key) => {
      if (params[key] === null || params[key] === "") {
        delete params[key];
      }
    });

    const response = await axios.get("/finances/pending-payments", {
      params,
    });

    if (response.data.status === "success" || response.data.success) {
      // Aplanar las facturas agrupadas para mostrar cada factura individualmente
      const allInvoices = [];
      response.data.data.pending_payments.forEach((group) => {
        group.invoices.forEach((invoice) => {
          allInvoices.push({
            ...invoice,
            supplier_name: group.supplier_name,
            payment_date: group.payment_date,
            group_id: `${group.supplier_id}_${group.payment_date}`,
          });
        });
      });

      pendingPayments.value = allInvoices;
      totalGroups.value = allInvoices.length; // Total de facturas individuales
      // totalAmount se calcula ahora con totalAmountUSD (computed)
    } else {
      console.error("Error al cargar pagos pendientes:", response.data.message);
      toast.error(
        response.data.message || "Error al cargar los pagos pendientes"
      );
    }
  } catch (error) {
    console.error("Error al cargar pagos pendientes:", error);
    toast.error("Error al cargar los pagos pendientes");
  } finally {
    loading.value = false;
  }
};

// Cargar tasas de cambio
const fetchExchangeRates = async () => {
  try {
    const response = await axios.get("/api/public/exchange-rates");
    if (Array.isArray(response.data)) {
      exchangeRates.value = response.data.reduce((acc, rate) => {
        acc[rate.currency_code] = parseFloat(rate.rate);
        return acc;
      }, {});
      console.log(
        "Tasas de cambio cargadas en pending-payments:",
        exchangeRates.value
      );
    }
    return true;
  } catch (error) {
    console.error("Error al cargar tasas de cambio:", error);
    console.error("Error response:", error.response);
    return false;
  }
};

// Convertir monto a USD
const convertToUSD = (amount, currency) => {
  console.log("Convirtiendo a USD:", {
    amount,
    currency,
    exchangeRates: exchangeRates.value,
  });

  if (currency === "USD") return parseFloat(amount);

  // Mapear moneda para buscar la tasa de cambio
  const currencyKey = currency === "Bs" ? "BS" : currency;

  if (!exchangeRates.value[currencyKey]) {
    console.log("No hay tasa de cambio para:", currencyKey);
    return 0;
  }

  const result =
    Math.round((parseFloat(amount) / exchangeRates.value[currencyKey]) * 100) /
    100;
  console.log("Resultado conversión:", result);
  return result;
};

// Calcular total en USD
const totalAmountUSD = computed(() => {
  return pendingPayments.value.reduce((sum, invoice) => {
    // Usar directamente total_usd del backend
    return sum + (parseFloat(invoice.total_amount_usd) || 0);
  }, 0);
});

// Calcular desglose por moneda
const currencyBreakdown = computed(() => {
  const breakdown = {};
  pendingPayments.value.forEach((invoice) => {
    const currency = invoice.currency;
    if (!breakdown[currency]) {
      breakdown[currency] = {
        count: 0,
        total: 0,
        totalUSD: 0,
      };
    }
    breakdown[currency].count++;
    breakdown[currency].total += parseFloat(invoice.total_amount);

    // Usar directamente total_usd del backend en lugar de convertir
    const usdAmount = parseFloat(invoice.total_amount_usd) || 0;

    breakdown[currency].totalUSD += usdAmount;
  });

  return breakdown;
});

// Cargar estadísticas
const fetchStatistics = async () => {
  try {
    const response = await axios.get("/finances/pending-payments/statistics");

    if (response.data.status === "success" || response.data.success) {
      statistics.value = response.data.data;
    } else {
      console.error("Error al cargar estadísticas:", response.data.message);
    }
  } catch (error) {
    console.error("Error al cargar estadísticas:", error);
  }
};

// Ver factura individual
const viewInvoice = (invoice) => {
  selectedPaymentGroup.value = {
    supplier_name: invoice.supplier_name,
    payment_date: invoice.payment_date,
    currency: invoice.currency,
    total_amount: invoice.total_amount,
    invoice_count: 1,
  };
  selectedInvoices.value = [invoice];
  showPaymentModal.value = true;
};

// Procesar pago de factura individual
const processPayment = (invoice) => {
  selectedPaymentGroup.value = {
    supplier_name: invoice.supplier_name,
    payment_date: invoice.payment_date,
    currency: invoice.currency,
    total_amount: invoice.total_amount,
    invoice_count: 1,
  };
  selectedInvoices.value = [invoice];
  showProcessModal.value = true;
};

// Seleccionar/deseleccionar factura de la tabla
const toggleTableInvoiceSelection = (invoice) => {
  const index = selectedTableInvoices.value.findIndex(
    (inv) => inv.id === invoice.id
  );
  if (index > -1) {
    selectedTableInvoices.value.splice(index, 1);
  } else {
    selectedTableInvoices.value.push(invoice);
  }
};

// Verificar si una factura está seleccionada en la tabla
const isTableInvoiceSelected = (invoice) => {
  return selectedTableInvoices.value.some((inv) => inv.id === invoice.id);
};

// Seleccionar todas las facturas
const selectAllInvoices = () => {
  selectedTableInvoices.value = [...pendingPayments.value];
};

// Deseleccionar todas las facturas
const deselectAllInvoices = () => {
  selectedTableInvoices.value = [];
};

// Procesar pago de múltiples facturas seleccionadas
const processMultiplePayments = () => {
  if (selectedTableInvoices.value.length === 0) {
    toast.error("Debe seleccionar al menos una factura");
    return;
  }

  selectedInvoices.value = [...selectedTableInvoices.value];

  // Agrupar por proveedor y fecha
  const groupedInvoices = selectedTableInvoices.value.reduce(
    (groups, invoice) => {
      const key = `${invoice.supplier_name}_${invoice.payment_date}`;
      if (!groups[key]) {
        groups[key] = {
          supplier_name: invoice.supplier_name,
          payment_date: invoice.payment_date,
          currency: invoice.currency,
          invoices: [],
        };
      }
      groups[key].invoices.push(invoice);
      return groups;
    },
    {}
  );

  // Si hay múltiples grupos, mostrar modal con todas las facturas
  if (Object.keys(groupedInvoices).length > 1) {
    selectedPaymentGroup.value = {
      supplier_name: "Múltiples Proveedores",
      payment_date: "Varias Fechas",
      currency: "Múltiples Monedas",
      total_amount: selectedTableInvoices.value.reduce(
        (sum, inv) => sum + parseFloat(inv.total_amount),
        0
      ),
      invoice_count: selectedTableInvoices.value.length,
    };
  } else {
    const group = Object.values(groupedInvoices)[0];
    selectedPaymentGroup.value = {
      supplier_name: group.supplier_name,
      payment_date: group.payment_date,
      currency: group.currency,
      total_amount: group.invoices.reduce(
        (sum, inv) => sum + parseFloat(inv.total_amount),
        0
      ),
      invoice_count: group.invoices.length,
    };
  }

  showProcessModal.value = true;
};

// Cerrar modales
const closePaymentModal = () => {
  showPaymentModal.value = false;
  selectedPaymentGroup.value = null;
  selectedInvoices.value = [];
};

const closeProcessModal = () => {
  showProcessModal.value = false;
  selectedPaymentGroup.value = null;
  selectedInvoices.value = [];
};

// Manejar pago procesado
const handlePaymentProcessed = () => {
  closeProcessModal();
  fetchPendingPayments();
  fetchStatistics();
  toast.success("Pago procesado exitosamente");
};

// Limpiar filtros
const clearFilters = () => {
  searchQuery.value = "";
  selectedSupplier.value = null;
  startDate.value = null;
  endDate.value = null;
  showOverdueOnly.value = false;
};

// Formatear fecha
const formatDate = (date) => {
  return new Date(date).toLocaleDateString("es-VE");
};

// Formatear moneda
const formatCurrency = (amount, currency) => {
  if (!amount) return "N/A";

  // Redondear a 2 decimales
  const roundedAmount = Math.round(amount * 100) / 100;

  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: currency === "Bs" ? "VES" : currency === "COP" ? "COP" : "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(roundedAmount);
};

// Obtener color del estado
const getStatusColor = (status) => {
  switch (status) {
    case "loaded":
      return "info";
    case "to_order":
      return "warning";
    default:
      return "success";
  }
};

// Obtener texto del estado
const getStatusText = (status) => {
  switch (status) {
    case "loaded":
      return "Cargada";
    case "to_order":
      return "Por Ordenar";
    default:
      return "Pendiente";
  }
};

// Watchers para recargar datos
watch([page, itemsPerPage, sortBy, orderBy], () => {
  fetchPendingPayments();
});

watch(
  [searchQuery, selectedSupplier, startDate, endDate, showOverdueOnly],
  () => {
    page.value = 1;
    fetchPendingPayments();
  },
  { deep: true }
);

// Cargar datos al montar el componente
onMounted(async () => {
  await fetchExchangeRates();
  await fetchSuppliers();
  await fetchPendingPayments();
  await fetchStatistics();
});
</script>

<template>
  <div>
    <!-- Header con estadísticas -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-credit-card" class="me-2" />
        Pagos Pendientes
      </VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <VCard variant="tonal" color="primary">
              <VCardText class="text-center">
                <div class="text-h4 font-weight-bold">{{ totalGroups }}</div>
                <div class="text-caption">Grupos Pendientes</div>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="12" md="3">
            <VCard variant="tonal" color="warning">
              <VCardText class="text-center">
                <div class="text-h4 font-weight-bold">
                  {{ statistics.overdue_invoices || 0 }}
                </div>
                <div class="text-caption">Vencidos</div>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="12" md="3">
            <VCard variant="tonal" color="success">
              <VCardText class="text-center">
                <div class="text-h4 font-weight-bold">
                  {{ statistics.total_pending_invoices || 0 }}
                </div>
                <div class="text-caption">Total Facturas</div>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="12" md="3">
            <VCard variant="tonal" color="info">
              <VCardText class="text-center">
                <div class="text-h4 font-weight-bold">
                  {{ formatCurrency(totalAmountUSD, "USD") }}
                </div>
                <div class="text-caption">Monto Total (USD)</div>
                <div class="text-caption text-medium-emphasis mt-1">
                  <div
                    v-for="(data, currency) in currencyBreakdown"
                    :key="currency"
                    class="text-xs"
                  >
                    {{ currency }}: {{ formatCurrency(data.totalUSD, "USD") }}
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Filtros -->
    <PendingPaymentsFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedSupplier="selectedSupplier"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      v-model:showOverdueOnly="showOverdueOnly"
      :suppliers="suppliers"
      :loading="isLoadingFilters"
      @clear="clearFilters"
    />

    <!-- Tabla de pagos pendientes -->
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>Pagos Pendientes</span>
        <div class="d-flex align-center gap-2">
          <VBtn
            size="small"
            variant="outlined"
            color="primary"
            @click="selectAllInvoices"
            :disabled="pendingPayments.length === 0"
          >
            <VIcon icon="tabler-check-all" size="small" />
            Seleccionar Todas
          </VBtn>
          <VBtn
            size="small"
            variant="outlined"
            color="secondary"
            @click="deselectAllInvoices"
            :disabled="selectedTableInvoices.length === 0"
          >
            <VIcon icon="tabler-x" size="small" />
            Deseleccionar
          </VBtn>
          <VBtn
            size="small"
            :variant="selectedTableInvoices.length > 0 ? 'flat' : 'outlined'"
            color="success"
            @click="processMultiplePayments"
            :disabled="selectedTableInvoices.length === 0"
          >
            <VIcon icon="tabler-credit-card" size="small" />
            Pagar Seleccionadas ({{ selectedTableInvoices.length }})
          </VBtn>
        </div>
      </VCardTitle>
      <VCardText>
        <VDataTable
          :headers="headers"
          :items="pendingPayments"
          :loading="loading"
          :items-per-page="itemsPerPage"
          :page="page"
          :sort-by="[{ key: sortBy, order: orderBy }]"
          @update:options="
            (options) => {
              page = options.page;
              itemsPerPage = options.itemsPerPage;
              sortBy = options.sortBy[0]?.key || 'payment_date';
              orderBy = options.sortBy[0]?.order || 'asc';
            }
          "
        >
          <!-- Columna de selección -->
          <template #item.select="{ item }">
            <VCheckbox
              :model-value="isTableInvoiceSelected(item)"
              @change="toggleTableInvoiceSelection(item)"
              color="primary"
            />
          </template>

          <!-- Columna de fecha de pago -->
          <template #item.payment_date="{ item }">
            <div>{{ formatDate(item.payment_date) }}</div>
          </template>

          <!-- Columna de fecha de vencimiento -->
          <template #item.exp_date="{ item }">
            <div>{{ formatDate(item.exp_date) }}</div>
          </template>

          <!-- Columna de total -->
          <template #item.total_amount="{ item }">
            <div class="font-weight-bold">
              {{ formatCurrency(item.total_amount, item.currency) }}
            </div>
          </template>

          <!-- Columna de estado -->
          <template #item.status="{ item }">
            <VChip
              :color="getStatusColor(item.status)"
              size="small"
              variant="tonal"
            >
              {{ getStatusText(item.status) }}
            </VChip>
          </template>

          <!-- Columna de acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex gap-2">
              <VBtn
                size="small"
                variant="outlined"
                color="primary"
                @click="viewInvoice(item)"
              >
                <VIcon icon="tabler-eye" size="small" />
                Ver
              </VBtn>
              <VBtn size="small" color="success" @click="processPayment(item)">
                <VIcon icon="tabler-credit-card" size="small" />
                Pagar
              </VBtn>
            </div>
          </template>

          <!-- Estado vacío -->
          <template #no-data>
            <div class="text-center py-8">
              <VIcon
                icon="tabler-receipt-off"
                size="48"
                class="text-disabled mb-4"
              />
              <div class="text-h6 text-disabled">No hay pagos pendientes</div>
              <div class="text-caption text-disabled">
                No se encontraron facturas pendientes de pago
              </div>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Modal para ver facturas -->
    <PendingPaymentModal
      v-model="showPaymentModal"
      :payment-group="selectedPaymentGroup"
      :invoices="selectedInvoices"
      @close="closePaymentModal"
    />

    <!-- Modal para procesar pago -->
    <ProcessPaymentModal
      v-model="showProcessModal"
      :payment-group="selectedPaymentGroup"
      :invoices="selectedInvoices"
      @close="closeProcessModal"
      @payment-processed="handlePaymentProcessed"
    />
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
