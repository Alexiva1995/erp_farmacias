<script setup>
import PendingPaymentModal from "@/components/dialogs/PendingPaymentModal.vue";
import ProcessPaymentModal from "@/components/dialogs/ProcessPaymentModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";

// Estado reactivo
const loading = ref(false);
const pendingPayments = ref([]);
const totalGroups = ref(0);
const totalSuppliers = ref(0);
const totalAmount = ref(0);
const statistics = ref({});
const exchangeRates = ref({});

// Totales por moneda
const totalsByCurrency = ref({
  bs: { amount: 0, count: 0, total_usd: 0 },
  usd: { amount: 0, count: 0, total_usd: 0 },
  cop: { amount: 0, count: 0, total_usd: 0 },
  usd_converted: 0,
});

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
  { title: "", key: "select", sortable: false, width: "50px" },
  { title: "FAC", key: "invoice_number", sortable: false },
  { title: "Proveedor", key: "supplier_name", sortable: false },
  { title: "Fecha de Pago", key: "payment_date", sortable: false },
  { title: "Moneda", key: "currency", sortable: false },
  { title: "Monto USD", key: "original_amount", sortable: false },
  { title: "Monto BS", key: "remaining_amount", sortable: false },
  { title: "Indexada", key: "is_indexed", sortable: false, width: "80px" },
  { title: "Total Proveedor", key: "total_supplier_currency", sortable: false }, // ISSUE #4: Nueva columna
  { title: "Estado", key: "status", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const selectedAll = computed(() => {
  if (pendingPayments.value.length === 0) return false;
  return selectedTableInvoices.value.length === pendingPayments.value.length;
});

const indeterminate = computed(() => {
  return (
    selectedTableInvoices.value.length > 0 &&
    selectedTableInvoices.value.length < pendingPayments.value.length
  );
});

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

  // 🔍 LOG DEBUG: Inicio de fetchPendingPayments
  console.log("🔍 [DEBUG] fetchPendingPayments - INICIO", {
    timestamp: new Date().toISOString(),
    params_antes_limpiar: {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      q: searchQuery.value,
      supplier_id: selectedSupplier.value,
      start_date: startDate.value,
      end_date: endDate.value,
      show_overdue_only: showOverdueOnly.value,
    },
  });

  try {
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      q: searchQuery.value,
      supplier_id: selectedSupplier.value,
      start_date: startDate.value,
      end_date: endDate.value,
      show_overdue_only: showOverdueOnly.value,
    };

    // Limpiar parámetros vacíos (mantener show_overdue_only siempre)
    Object.keys(params).forEach((key) => {
      if (params[key] === null || params[key] === "") {
        delete params[key];
      }
    });

    // 🔍 LOG DEBUG: Parámetros finales
    console.log("🔍 [DEBUG] Parámetros finales enviados", {
      params_finales: params,
      url: "/finances/pending-payments",
    });

    const response = await axios.get("/finances/pending-payments", {
      params,
    });

    // 🔍 LOG DEBUG: Respuesta recibida
    console.log("🔍 [DEBUG] Respuesta recibida del servidor", {
      status: response.status,
      statusText: response.statusText,
      response_data: response.data,
      response_structure: {
        has_status: "status" in response.data,
        has_success: "success" in response.data,
        has_data: "data" in response.data,
        data_keys: response.data.data
          ? Object.keys(response.data.data)
          : "no_data",
        pending_payments_count:
          response.data.data?.pending_payments?.length || 0,
      },
    });

    if (response.data.status === "success" || response.data.success) {
      // 🔍 LOG DEBUG: Procesando datos exitosos
      console.log("🔍 [DEBUG] Procesando datos exitosos", {
        pending_payments_raw: response.data.data.pending_payments,
        total_groups_raw: response.data.data.total_groups,
        total_suppliers_raw: response.data.data.total_suppliers,
        totals_by_currency_raw: response.data.data.totals_by_currency,
      });

      // Aplanar las facturas agrupadas para mostrar cada factura individualmente
      const allInvoices = [];
      response.data.data.pending_payments.forEach((group, groupIndex) => {
        console.log(`🔍 [DEBUG] Procesando grupo ${groupIndex}`, {
          group_supplier_name: group.supplier_name,
          group_payment_date: group.payment_date,
          group_invoices_count: group.invoices?.length || 0,
          group_invoices: group.invoices,
        });

        group.invoices.forEach((invoice, invoiceIndex) => {
          const flattenedInvoice = {
            ...invoice,
            supplier_name: group.supplier_name,
            payment_date: group.payment_date,
            group_id: `${group.supplier_id}_${group.payment_date}`,
            // ISSUE #4: Agregar campos del grupo para cada factura
            total_in_supplier_currency: group.total_in_supplier_currency,
            supplier_preferred_currency: group.supplier_preferred_currency,
          };

          console.log(
            `🔍 [DEBUG] Factura ${invoiceIndex} del grupo ${groupIndex}`,
            {
              invoice_id: flattenedInvoice.id,
              invoice_number: flattenedInvoice.invoice_number,
              supplier_name: flattenedInvoice.supplier_name,
              currency: flattenedInvoice.currency,
              total_amount: flattenedInvoice.total_amount,
            }
          );

          allInvoices.push(flattenedInvoice);
        });
      });

      // 🔍 LOG DEBUG: Datos procesados
      console.log("🔍 [DEBUG] Datos procesados completamente", {
        allInvoices_count: allInvoices.length,
        allInvoices_sample: allInvoices.slice(0, 3), // Primeras 3 facturas
        pendingPayments_antes_asignar: pendingPayments.value.length,
      });

      pendingPayments.value = allInvoices;
      totalGroups.value = allInvoices.length; // Total de facturas individuales
      totalSuppliers.value = response.data.data.total_suppliers || 0; // Total de proveedores únicos
      totalsByCurrency.value = response.data.data.totals_by_currency || {
        bs: { amount: 0, count: 0, total_usd: 0 },
        usd: { amount: 0, count: 0, total_usd: 0 },
        cop: { amount: 0, count: 0, total_usd: 0 },
        usd_converted: 0,
      };

      // 🔍 LOG DEBUG: Variables reactivas asignadas
      console.log("🔍 [DEBUG] Variables reactivas asignadas", {
        pendingPayments_length: pendingPayments.value.length,
        totalGroups_value: totalGroups.value,
        totalSuppliers_value: totalSuppliers.value,
        totalsByCurrency_value: totalsByCurrency.value,
        loading_value: loading.value,
      });

      // totalAmount se calcula ahora con totalAmountUSD (computed)
    } else {
      console.error(
        "🔍 [DEBUG] Error en respuesta del servidor:",
        response.data.message
      );
      toast.error(
        response.data.message || "Error al cargar los pagos pendientes"
      );
    }
  } catch (error) {
    console.error("🔍 [DEBUG] Error en fetchPendingPayments:", error);
    console.error("🔍 [DEBUG] Error details:", {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status,
    });
    toast.error("Error al cargar los pagos pendientes");
  } finally {
    loading.value = false;

    // 🔍 LOG DEBUG: Final de fetchPendingPayments
    console.log("🔍 [DEBUG] fetchPendingPayments - FINAL", {
      loading_final: loading.value,
      pendingPayments_final: pendingPayments.value.length,
      timestamp: new Date().toISOString(),
    });
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
  if (currency === "USD") return parseFloat(amount);

  // Mapear moneda para buscar la tasa de cambio
  const currencyKey = currency === "Bs" ? "BS" : currency;

  if (!exchangeRates.value[currencyKey]) {
    return 0;
  }

  const result =
    Math.round((parseFloat(amount) / exchangeRates.value[currencyKey]) * 100) /
    100;
  return result;
};

// Calcular total en USD
const totalAmountUSD = computed(() => {
  const result = pendingPayments.value.reduce((sum, invoice) => {
    // Usar directamente total_usd del backend
    return sum + (parseFloat(invoice.total_amount_usd) || 0);
  }, 0);

  // 🔍 LOG DEBUG: Computed totalAmountUSD
  console.log("🔍 [DEBUG] Computed totalAmountUSD", {
    pendingPayments_length: pendingPayments.value.length,
    result: result,
    sample_invoices: pendingPayments.value.slice(0, 2).map((inv) => ({
      id: inv.id,
      total_amount_usd: inv.total_amount_usd,
      parsed: parseFloat(inv.total_amount_usd) || 0,
    })),
  });

  return result;
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

  // 🔍 LOG DEBUG: Computed currencyBreakdown
  console.log("🔍 [DEBUG] Computed currencyBreakdown", {
    pendingPayments_length: pendingPayments.value.length,
    breakdown_result: breakdown,
    currencies_found: Object.keys(breakdown),
  });

  return breakdown;
});

// Cargar estadísticas
const fetchStatistics = async () => {
  try {
    const response = await axios.get("/finances/pending-payments/statistics");

    if (response.data.status === "success" || response.data.success) {
      statistics.value = response.data.data;
      // Actualizar también totalsByCurrency con los datos de estadísticas
      totalsByCurrency.value = response.data.data.totals_by_currency || {
        bs: { amount: 0, count: 0, total_usd: 0 },
        usd: { amount: 0, count: 0, total_usd: 0 },
        cop: { amount: 0, count: 0, total_usd: 0 },
        usd_converted: 0,
      };
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

const handleHeaderCheckboxChange = (value) => {
  if (value) {
    selectAllInvoices();
  } else {
    deselectAllInvoices();
  }
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
const formatCurrency = (amount, currency, omitCurrency) => {
  if (!amount || amount === 0) return "N/A";

  // Redondear a 2 decimales
  const roundedAmount = Math.round(amount * 100) / 100;

  // Formatear número con separadores de miles
  const formatter = new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  const formattedNumber = formatter.format(roundedAmount);

  if (omitCurrency) {
    return formattedNumber;
  }

  // Agregar símbolo de moneda según la moneda
  switch (currency) {
    case "Bs":
      return `Bs ${formattedNumber}`;
    case "COP":
      return `COP ${formattedNumber}`;
    case "USD":
      return `USD ${formattedNumber}`;
    default:
      return `${currency} ${formattedNumber}`;
  }
};

// Obtener clase CSS para monto restante
const getRemainingAmountClass = (item) => {
  const remainingAmount = item.remaining_amount || item.total_amount;
  const originalAmount = item.original_amount || item.total_amount;

  // Si el monto restante es menor al original, significa que hay pagos parciales
  if (remainingAmount < originalAmount) {
    return "text-warning"; // Color naranja para pagos parciales
  }

  return "text-success"; // Color verde para facturas sin pagos
};

// ISSUE #3: Función para obtener monto a mostrar (considerando indexación)
const getDisplayAmount = (item) => {
  // CORRECCIÓN: Para facturas indexadas, calcular el monto restante indexado
  if (item.is_indexed && item.indexed_data && item.indexed_data.is_indexed) {
    // Calcular el porcentaje pagado
    const originalAmount = parseFloat(item.indexed_data.original_amount_usd);
    const remainingAmountUSD = parseFloat(item.remaining_amount_usd);
    const paidAmountUSD = originalAmount - remainingAmountUSD;
    const paidPercentage = paidAmountUSD / originalAmount;

    // Aplicar el mismo porcentaje al monto indexado
    const indexedAmount = parseFloat(item.indexed_data.indexed_amount);
    const remainingIndexedAmount = indexedAmount * (1 - paidPercentage);

    return Math.round(remainingIndexedAmount);
  }

  // Si no está indexada, usar el monto restante normal
  return item.remaining_amount || item.total_amount;
};

// ISSUE #3: Función para obtener monto USD a mostrar (considerando indexación)
const getDisplayAmountUSD = (item) => {
  // CORRECCIÓN: Para facturas indexadas, usar el monto restante USD real
  if (item.is_indexed && item.indexed_data && item.indexed_data.is_indexed) {
    // Para facturas indexadas, el monto USD restante ya está calculado correctamente
    return item.remaining_amount_usd || item.total_usd;
  }

  // Si no está indexada, usar el USD restante normal
  return item.remaining_amount_usd || item.total_usd;
};

// ISSUE #3: Función para cambiar estado de factura indexada
const toggleIndexedStatus = async (item) => {
  try {
    const response = await axios.put(
      `/finances/invoices/${item.id}/toggle-indexed`,
      {
        is_indexed: item.is_indexed,
      }
    );

    if (response.data.status === "success") {
      toast.success(
        `Factura ${item.invoice_number} ${
          item.is_indexed ? "indexada" : "desindexada"
        } correctamente`
      );

      // CORRECCIÓN: Recargar datos para obtener los nuevos cálculos indexados
      await fetchPendingPayments();
    } else {
      // Revertir el cambio si falla
      item.is_indexed = !item.is_indexed;
      toast.error(
        response.data.message || "Error al actualizar el estado de indexación"
      );
    }
  } catch (error) {
    // Revertir el cambio si falla
    item.is_indexed = !item.is_indexed;
    console.error("Error al cambiar estado de indexación:", error);
    toast.error("Error al actualizar el estado de indexación");
  }
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

// CORRECCIÓN ISSUE #1: Función para formatear fecha de vencimiento (payment_date - 1 día)
const formatDueDate = (paymentDate) => {
  if (!paymentDate) return "N/A";

  const dueDate = new Date(paymentDate);
  dueDate.setDate(dueDate.getDate() - 1);

  return dueDate.toLocaleDateString("es-VE", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
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
watch([page, itemsPerPage], () => {
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
        <!-- Tarjetas de Monedas -->
        <VRow>
          <VCol cols="12" sm="6" md="4">
            <VCard flat class="h-100 pa-4">
              <VCardText class="text-center pa-0">
                <div class="text-h5 font-weight-medium text-error">
                  {{ formatCurrency(totalsByCurrency.bs.amount, "Bs") }}
                </div>
                <div class="text-caption text-medium-emphasis mt-2">
                  Deuda Bs
                </div>
                <VDivider class="my-2" />
                <div class="text-caption text-medium-emphasis">
                  {{ totalsByCurrency.bs.count }} factura{{
                    totalsByCurrency.bs.count !== 1 ? "s" : ""
                  }}
                  • ≈
                  {{
                    formatCurrency(totalsByCurrency.bs.total_usd || 0, "USD")
                  }}
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <VCard flat class="h-100 pa-4">
              <VCardText class="text-center pa-0">
                <div class="text-h5 font-weight-medium text-info">
                  {{ formatCurrency(totalsByCurrency.usd.amount, "USD") }}
                </div>
                <div class="text-caption text-medium-emphasis mt-2">
                  Deuda USD
                </div>
                <VDivider class="my-2" />
                <div class="text-caption text-medium-emphasis">
                  {{ totalsByCurrency.usd.count }} factura{{
                    totalsByCurrency.usd.count !== 1 ? "s" : ""
                  }}
                  • Total:
                  {{ formatCurrency(totalsByCurrency.usd_converted, "USD") }}
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <VCard flat class="h-100 pa-4">
              <VCardText class="text-center pa-0">
                <div class="text-h5 font-weight-medium text-secondary">
                  {{ formatCurrency(totalsByCurrency.cop.amount, "COP") }}
                </div>
                <div class="text-caption text-medium-emphasis mt-2">
                  Deuda COP
                </div>
                <VDivider class="my-2" />
                <div class="text-caption text-medium-emphasis">
                  {{ totalsByCurrency.cop.count }} factura{{
                    totalsByCurrency.cop.count !== 1 ? "s" : ""
                  }}
                  • ≈
                  {{
                    formatCurrency(totalsByCurrency.cop.total_usd || 0, "USD")
                  }}
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <!-- Filtros integrados -->
        <VDivider class="my-4 mb-8" />
        <VRow>
          <VCol cols="12" sm="6" md="2">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar por factura..."
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="2">
            <VAutocomplete
              v-model="selectedSupplier"
              :items="suppliers"
              :loading="isLoadingFilters"
              label="Proveedor"
              item-title="name"
              item-value="id"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="2">
            <AppDateTimePicker
              v-model="startDate"
              placeholder="Desde"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="2">
            <AppDateTimePicker
              v-model="endDate"
              placeholder="Hasta"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="2">
            <VCheckbox v-model="showOverdueOnly" label="Pagos vencidos" />
          </VCol>
          <VCol cols="12" sm="6" md="2" class="text-end">
            <VBtn color="secondary" variant="outlined" @click="clearFilters">
              Limpiar Filtros
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Tabla de pagos pendientes -->
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>Pagos Pendientes</span>
        <div class="d-flex align-center gap-2">
          <VBtn
            variant="outlined"
            color="secondary"
            @click="deselectAllInvoices"
            :disabled="selectedTableInvoices.length === 0"
          >
            <VIcon icon="tabler-x" class="mr-2" />
            {{ selectedTableInvoices.length }}
          </VBtn>
          <VBtn
            :variant="selectedTableInvoices.length > 0 ? 'flat' : 'outlined'"
            color="success"
            @click="processMultiplePayments"
            :disabled="selectedTableInvoices.length === 0"
          >
            <VIcon icon="tabler-credit-card" class="mr-2" />
            {{ selectedTableInvoices.length }}
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
          :sort-by="[]"
          @update:options="
            (options) => {
              page = options.page;
              itemsPerPage = options.itemsPerPage;
              // NO aplicar ordenamiento del frontend - el backend ya envía los datos ordenados
            }
          "
        >
          <template #header.select>
            <VCheckbox
              :model-value="selectedAll"
              :indeterminate="indeterminate"
              @update:model-value="handleHeaderCheckboxChange"
              :disabled="pendingPayments.length === 0"
              density="compact"
            />
          </template>

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

          <!-- Columna de monto original -->
          <template #item.original_amount="{ item }">
            <div class="font-weight-bold">
              {{ formatCurrency(item.original_amount_usd, "USD", true) }}
            </div>
          </template>

          <!-- Columna de monto restante -->
          <template #item.remaining_amount="{ item }">
            <div
              class="font-weight-bold"
              :class="getRemainingAmountClass(item)"
            >
              {{ formatCurrency(getDisplayAmount(item), item.currency, true) }}
            </div>
          </template>

          <!-- Columna de factura indexada -->
          <template #item.is_indexed="{ item }">
            <VSwitch
              v-model="item.is_indexed"
              color="primary"
              @change="toggleIndexedStatus(item)"
              :disabled="loading"
            />
          </template>

          <!-- ISSUE #4: Columna de total en moneda del proveedor -->
          <template #item.total_supplier_currency="{ item }">
            <div class="font-weight-bold text-primary">
              {{
                formatCurrency(item.total_amount || 0, item.currency || "USD")
              }}
            </div>
            <div
              v-if="item.currency === 'Bs'"
              class="text-caption text-medium-emphasis"
            >
              {{ formatCurrency(item.remaining_amount_usd || 0, "USD") }}
            </div>
          </template>

          <!-- Columna de estado -->
          <template #item.status="{ item }">
            <VChip :color="getStatusColor(item.status)" variant="tonal">
              {{ getStatusText(item.status) }}
            </VChip>
          </template>

          <!-- Columna de acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex gap-2">
              <IconBtn @click="viewInvoice(item)">
                <VIcon icon="tabler-eye" />
              </IconBtn>
              <IconBtn @click="processPayment(item)">
                <VIcon icon="tabler-credit-card" />
              </IconBtn>
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
